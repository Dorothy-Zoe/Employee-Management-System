<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['employee_user'])) {
    die("Error: Employee ID not found in session.");
}

$EmployeeID = $_SESSION['employee_user'];

// Fetch employee details
$sql = "SELECT FName, LName, ProfilePicture FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Validate fetched employee data
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . $employee['ProfilePicture'] : "../uploads/default.jpg";

//EDIT PROFILE PROCESS
$successMsg = "";
$errorMsg = "";

// Fetch current employee details from the database
$sql = "SELECT MobileNumber, emergency_contact_name, emergency_contact_number, MaritalStatus, Email, Address, City, ZipCode 
        FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $EmployeeID);
$stmt->execute();
$result = $stmt->get_result();
$currentData = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $updatedFields = [];
    $params = [];
    $paramTypes = "";

    // Define the fields to check for changes
    $fieldsToUpdate = [
        "MobileNumber",
        "emergency_contact_name",
        "emergency_contact_number",
        "MaritalStatus",
        "Email",
        "Address",
        "City",
        "ZipCode"
    ];

    // Compare submitted values with current database values
    foreach ($fieldsToUpdate as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== $currentData[$field]) {
            $updatedFields[] = "$field = ?";
            $params[] = $_POST[$field];
            $paramTypes .= "s"; // Assuming all fields are strings
        }
    }

    // If there are fields to update, execute the query
    if (!empty($updatedFields)) {
        $sql = "UPDATE EmployeeDetail SET " . implode(", ", $updatedFields) . " WHERE EmployeeID = ?";
        $params[] = $EmployeeID;
        $paramTypes .= "s";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($paramTypes, ...$params);

        if ($stmt->execute()) {
            $successMsg = "Profile updated successfully!";
        } else {
            $errorMsg = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $successMsg = "No changes made.";
    }
}

// Fetch updated employee details
$sql = "SELECT * FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $EmployeeID);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

// Fetch employee details from the database
$sql = "SELECT FName, MName, LName, MobileNumber, Gender, DateOfBirth, 
        MaritalStatus, Email, Nationality, Address, City, ZipCode, EmployeeID, EmployeeStatus, 
        Department, JoiningDate, emergency_contact_name, emergency_contact_number, 
        PrimaryLevel, PrimaryYearStarted, PrimaryYearGraduated, 
        SecondaryLevel, SecondaryYearStarted, SecondaryYearGraduated, 
        TertiaryLevel, TertiaryYearStarted, TertiaryYearGraduated, Degree, Course
        FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Assign values safely to prevent undefined variables
$FName = $employee['FName'] ?? '';
$MName = $employee['MName'] ?? '';
$LName = $employee['LName'] ?? '';
$MobileNumber = $employee['MobileNumber'] ?? '';
$Gender = $employee['Gender'] ?? '';
$DateOfBirth = $employee['DateOfBirth'] ?? '';
$MaritalStatus = $employee['MaritalStatus'] ?? '';
$Email = $employee['Email'] ?? '';
$Nationality = $employee['Nationality'] ?? '';
$Address = $employee['Address'] ?? '';
$City = $employee['City'] ?? '';
$ZipCode = $employee['ZipCode'] ?? '';
$EmployeeStatus = $employee['EmployeeStatus'] ?? '';
$Department = $employee['Department'] ?? '';
$JoiningDate = $employee['JoiningDate'] ?? '';
$emergency_contact_name = $employee['emergency_contact_name'] ?? '';
$emergency_contact_number = $employee['emergency_contact_number'] ?? '';
$PrimaryLevel = $employee['PrimaryLevel'] ?? '';
$PrimaryYearStarted = $employee['PrimaryYearStarted'] ?? '';
$PrimaryYearGraduated = $employee['PrimaryYearGraduated'] ?? '';
$SecondaryLevel = $employee['SecondaryLevel'] ?? '';
$SecondaryYearStarted = $employee['SecondaryYearStarted'] ?? '';
$SecondaryYearGraduated = $employee['SecondaryYearGraduated'] ?? '';
$TertiaryLevel = $employee['TertiaryLevel'] ?? '';
$TertiaryYearStarted = $employee['TertiaryYearStarted'] ?? '';
$TertiaryYearGraduated = $employee['TertiaryYearGraduated'] ?? '';
$Degree = $employee['Degree'] ?? '';
$Course = $employee['Course'] ?? '';

// Notification Process
$sql = "SELECT l.ID, l.Status, l.AppliedDate, ns.IsRead 
    FROM Leavetable l
    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
    WHERE l.EmployeeID = ? 
    ORDER BY l.AppliedDate DESC 
    LIMIT 5";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Mark notifications as read when the dropdown is opened
// PHP code to handle the mark all as read request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markAllRead'])) {
    if (!isset($_SESSION['employee_user'])) {
        die("Error: Employee ID not found in session.");
    }
    $EmployeeID = $_SESSION['employee_user']; // Get logged-in employee ID
    
    // Update notifications status to 'read' (IsRead = 1)
    $updateSql = "UPDATE leave_notification_status SET IsRead = 1 WHERE LeaveID IN (SELECT ID FROM leavetable WHERE EmployeeID = ?)";
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt) {
        $updateStmt->bind_param("s", $EmployeeID);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        die("Error in SQL query: {$conn->error}");
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="../src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCU EMS</title>
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/adminDashboard.css">
    <link rel="stylesheet" href="../includes/addEmp.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <script src="../includes/adminDashboard.js"></script>
    <script src="../includes/addEmp.js"></script>
    <!--For notification-->
    <link rel="stylesheet" href="../includes/InstructorSchedule.css">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>



<!-- Sidebar -->
<div class="sidebar">
    <div class="logo-container">
        <img src="../src/tcu-logo.png" alt="TCU Logo" class="logo">
        <span class="university-name">Taguig City University</span>
    </div>
    <div class="profile-section">
        <img src="<?= $profilePicture; ?>" alt="Profile Picture" class="profile-image">
        <div class="profile-details">
            <span class="profile-name"><?= htmlspecialchars($fullName); ?></span>
            <span class="profile-role">Instructor</span>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="instructorDashboard.php" class="menu-item">
            <span class="icon icon-dashboard"></span> Dashboard
        </a>
        <a href="schedule.php" class="menu-item">
            <span class="icon icon-schedule"></span> Schedule
        </a>
        <a href="leaveManagement.php" class="menu-item">
            <span class="icon icon-leave"></span> Leave Management
          
        </a>
        <a href="settings.php" class="menu-item  active">
            <span class="icon icon-settings"></span> Settings
        </a>
    </div>
    <a href="../index.html" class="logout">
        <span class="icon icon-logout"></span> Log out
    </a>
</div>
    <!-- End of the Sidebar -->
<div class="main-content">
 <!-- Header -->
<div class="header">
        <h1 class="header-title">Settings - Edit Profile</h1>
        <div class="header-actions">
 <!-- Search Container
 <div class="search-container">
    <span class="icon icon-search"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Search anything here" onkeyup="searchTable()">
</div>  -->

<!-- Settings 
<a href="settings.php"><span class="icon icon-settings-header header-icon"></span></a> -->


<!-- Notification -->
<div class="notif-dropdown">
    <span class="icon icon-notification header-icon" id="notificationDropdown"></span>
    <span id="unreadCount" class="unread-count"><?= $result->num_rows; ?></span> <!-- Unread count -->
    <div class="dropdown-content notification-dropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <span class="mark-all-read" id="markAllRead">Mark all as read</span>
        </div>
        <?php 
        $hasValidNotifications = false;
        if ($result->num_rows > 0): 
        ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php if ($row['Status'] !== 'Pending'): ?> <!-- Exclude pending notifications -->
                    <?php $hasValidNotifications = true; ?>
                    <div class="notification-item <?= ($row['IsRead'] == 0) ? 'unread' : 'read'; ?>" id="notif-<?= $row['ID']; ?>">
                        <div class="notification-icon <?= ($row['Status'] === 'Approved') ? 'notification-icon-leave' : 'notification-icon-rejected'; ?>">
                            <!-- Font Awesome icons for approved and rejected status -->
                            <?php if ($row['Status'] === 'Approved'): ?>
                                <i class="fa fa-check"></i> <!-- Green checkmark for approved -->
                            <?php else: ?>
                                <i class="fa fa-times"></i> <!-- Red cross for rejected -->
                            <?php endif; ?>
                        </div>
                        <div class="notification-content">
                            <p>
                                <?= ($row['Status'] === 'Approved') 
                                    ? 'Your leave request has been approved.' 
                                    : 'Your leave request has been rejected.'; ?>
                            </p>
                            <!-- Only the applied date is shown now -->
                            <span class="notification-time">
                                <?= 'Applied on: ' . date('F j, Y', strtotime($row['AppliedDate'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php endif; ?>
        <?php if (!$hasValidNotifications): ?>
            <div class="notification-item">
                <div class="notification-content">
                    <p>No notifications available</p>
                </div>
            </div>
        <?php endif; ?>
        <a href="#" class="view-all-notifications" id="viewAllNotifications">View all notifications</a>
    </div>
</div>

<!-- Modal for all notifications -->
<div id="allNotificationsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeModal">&times;</span>
        <h2>All Notifications</h2>

        <div class="all-notifications-list">
            <?php 
            $allNotificationsSql = "SELECT l.ID, l.Status, l.AppliedDate, ns.IsRead 
                                    FROM Leavetable l
                                    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
                                    WHERE l.EmployeeID = ? AND l.Status IN ('Approved', 'Rejected')
                                    ORDER BY l.AppliedDate DESC";
            $allStmt = $conn->prepare($allNotificationsSql);
            if ($allStmt) {
                $allStmt->bind_param("s", $EmployeeID);
                $allStmt->execute();
                $allResult = $allStmt->get_result();
                if ($allResult->num_rows > 0) {
                    while ($row = $allResult->fetch_assoc()): ?>
                        <div class="notification-item ">
                            <div class="notification-icon <?= ($row['Status'] === 'Approved') ? 'notification-icon-leave' : 'notification-icon-rejected'; ?>">
                                <?php if ($row['Status'] === 'Approved'): ?>
                                    <i class="fa fa-check"></i>
                                <?php else: ?>
                                    <i class="fa fa-times"></i>
                                <?php endif; ?>
                            </div>
                            <div class="notification-content">
                                <p>
                                    <?= ($row['Status'] === 'Approved') 
                                        ? 'Your leave request has been approved.' 
                                        : 'Your leave request has been rejected.'; ?>
                                </p>
                                <span class="notification-time">
                                    <?= 'Applied on: ' . date('F j, Y', strtotime($row['AppliedDate'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile;
                } else { ?>
                                    <div class="modal-notification-content <?php echo ($allResult->num_rows == 0) ? 'no-notifications' : ''; ?>">
    <?php if ($allResult->num_rows > 0): ?>
        <!-- Display notifications -->
    <?php else: ?>
        <div class="no-notifications-message">
            No notifications available
        </div>
    <?php endif; ?>
</div>

                <?php }
                $allStmt->close();
            } else { ?>
                              <div class="modal-notification-content <?php echo ($allResult->num_rows == 0) ? 'no-notifications' : ''; ?>">
    <?php if ($allResult->num_rows > 0): ?>
        <!-- Display notifications -->
    <?php else: ?>
        <div class="no-notifications-message">
            No notifications available
        </div>
    <?php endif; ?>
</div>

            <?php } ?>
        </div>
        <button id="clearNotificationsBtn" class="clear-notifications-btn">Clear Notifications</button>
    </div>
</div>
<!-- End of All Notification Modal -->
                        <!-- Profile Dropdown -->
                        <div class="Profile-dropdown">
                <button class="Pdropdown-button">
                    <img src="<?= $profilePicture; ?>" alt="Profile" class="profile-icon">
                </button>
                <div class="Pdropdown-content">
                    <a href="settings.php">Settings</a>
                    <a href="../index.html">Log out</a>
                </div>
            </div>
            </div>
    </div>
    <!-- End of Header -->

    <!-- Success/Error Message Box -->
<?php if (!empty($successMsg)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.backgroundColor = "#d4edda";
            messageBox.style.color = "#155724";
            messageBox.style.border = "1px solid #c3e6cb";
            messageBox.innerHTML = "<?php echo $successMsg; ?>";

            setTimeout(function() {
                messageBox.style.display = "none";
            }, 5000);
        });
    </script>
<?php } elseif (!empty($errorMsg)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.backgroundColor = "#f8d7da";
            messageBox.style.color = "#721c24";
            messageBox.style.border = "1px solid #f5c6cb";
            messageBox.innerHTML = "<?php echo $errorMsg; ?>";

            setTimeout(function() {
                messageBox.style.display = "none";
            }, 5000);
        });
    </script>
<?php } ?>

    <div id="message-box" style="display: none; padding: 10px; margin-bottom: 10px; border-radius: 5px; text-align: center;"></div>
    <div class="container">

    <!-- Tab Navigation -->
    <div class="tabs">
        <div class="tab active" onclick="showTab(0)">
            <i class="fa-solid fa-user"></i> <span>Personal Information</span>
        </div>
        <div class="tab" onclick="showTab(1)">
            <i class="fa-solid fa-briefcase"></i> <span>Professional Information</span>
        </div>
        <div class="tab" onclick="showTab(2)">
            <i class="fa-solid fa-file"></i> <span>Academic Records</span>
        </div>
    </div>

   <!-- Form Sections -->
<form class="form-container" method="POST" action="" enctype="multipart/form-data">
    <!-- Personal Information -->
    <div class="form-section active">  
        <div class="group">
            <div class="input-group">
                <label class="label">First Name</label>
                <input type="text" name="FName" value="<?= $FName; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Last Name</label>
                <input type="text" name="LName" value="<?= $LName; ?>" readonly>
            </div>
        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Middle Name</label>
                <input type="text" name="MName" value="<?= $MName; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Contact Number</label>
                <input type="text" name="MobileNumber" value="<?= $MobileNumber; ?>" required>
            </div>

        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="<?= $emergency_contact_name; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Emergency Contact Number</label>
                <input type="text" name="emergency_contact_number" value="<?= $emergency_contact_number; ?>" required>
            </div>
        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Gender</label>
                <select name="Gender" disabled>
    <option value="Male" <?= ($Gender == 'Male') ? 'selected' : '' ?>>Male</option>
    <option value="Female" <?= ($Gender == 'Female') ? 'selected' : '' ?>>Female</option>
</select>
            </div>
            <div class="input-group">
                <label class="label">Date of Birth</label>
                <input type="date" name="DateOfBirth" value="<?= $DateOfBirth; ?>" readonly>
            </div>
        </div>

        <div class="group">

            <div class="input-group">
                <label class="label">Marital Status</label>
                <select name="MaritalStatus" required>
    <option value="Single" <?= ($MaritalStatus == 'Single') ? 'selected' : '' ?>>Single</option>
    <option value="Married" <?= ($MaritalStatus == 'Married') ? 'selected' : '' ?>>Married</option>
    <option value="Divorced" <?= ($MaritalStatus == 'Divorced') ? 'selected' : '' ?>>Divorced</option>
    <option value="Widowed" <?= ($MaritalStatus == 'Widowed') ? 'selected' : '' ?>>Widowed</option>
</select>

            </div>
            <div class="input-group">
                <label class="label">Email Address</label>
                <input type="email" name="Email" value="<?= $Email; ?>" required>
        </div>        

        </div>      

        <div class="group">
        <div class="input-group">
                <label class="label">Nationality</label>
                <input type="text" name="Nationality" value="<?= $Nationality; ?>" readonly>
            </div>
        <div class="input-group">
            <label class="label">Address</label>
            <input type="text" name="Address" value="<?= $Address; ?>" required>
        </div>
            
        </div>
        <div class="group">
        <div class="input-group">
                <label class="label">City</label>
                <input type="text" name="City" value="<?= $City; ?>" required>
            </div>
        <div class="input-group">
                <label class="label">ZIP Code</label>
                <input type="text" name="ZipCode" value="<?= $ZipCode; ?>" required>
            </div>
        </div>

        <div class="buttons">
        <button type="button" class="cancel" onclick="window.location.href='settings.php';">Cancel</button>
            <button type="button" class="next" onclick="showTab(1)">Next</button>
        </div>
    </div>

    <!-- Professional Information -->
    <div class="form-section">
        <div class="group">
            <div class="input-group">
                <label class="label">Employee ID</label>
                <input type="text" name="EmployeeID" value="<?= $EmployeeID; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Employee Status</label>
                <select name="EmployeeStatus" disabled>
    <option value="Part-time" <?= ($EmployeeStatus == 'Part-time') ? 'selected' : '' ?>>Part-time</option>
    <option value="Full-time" <?= ($EmployeeStatus == 'Full-time') ? 'selected' : '' ?>>Full-time</option>
</select>

            </div>
            

        </div>

        <div class="group">
 
        <div class="input-group">
                <label class="label">Department</label>
                <select name="Department" disabled>
    <option value="CICT" <?= ($Department == 'CICT') ? 'selected' : '' ?>>CICT</option>
    <option value="CAS" <?= ($Department == 'CAS') ? 'selected' : '' ?>>CAS</option>
    <option value="CBM" <?= ($Department == 'CBM') ? 'selected' : '' ?>>CBM</option>
    <option value="CCJ" <?= ($Department == 'CCJ') ? 'selected' : '' ?>>CCJ</option>
    <option value="COE" <?= ($Department == 'COE') ? 'selected' : '' ?>>COE</option>
    <option value="CHTM" <?= ($Department == 'CHTM') ? 'selected' : '' ?>>CHTM</option>
</select>
            </div>
            <div class="input-group">
            <label class="label">Joining Date</label>
            <input type="date" name="JoiningDate" value="<?= $JoiningDate; ?>" readonly>
        </div>
        </div>



        <div class="buttons">
            <button type="button" class="prev" onclick="showTab(0)">Previous</button>
            <button type="button" class="next" onclick="showTab(2)">Next</button>
        </div>
    </div>

    <!-- Academic Records -->
    <div class="form-section">
        <div class="group">
            <div class="input-group">
                <label class="label">Primary Level</label>
                <input type="text" name="PrimaryLevel" value="<?= $PrimaryLevel; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="PrimaryYearStarted" value="<?= $PrimaryYearStarted; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="PrimaryYearGraduated" value="<?= $PrimaryYearGraduated; ?>" readonly>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Secondary Level</label>
                <input type="text" name="SecondaryLevel" value="<?= $SecondaryLevel; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="SecondaryYearStarted" value="<?= $SecondaryYearStarted; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="SecondaryYearGraduated" value="<?= $SecondaryYearGraduated; ?>" readonly>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Tertiary Level</label>
                <input type="text" name="TertiaryLevel" value="<?= $TertiaryLevel; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="TertiaryYearStarted" value="<?= $TertiaryYearStarted; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="TertiaryYearGraduated" value="<?= $TertiaryYearGraduated; ?>" readonly>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Degree</label>
                <input type="text" name="Degree" value="<?= $Degree; ?>" readonly>
            </div>
            <div class="input-group">
                <label class="label">Course</label>
                <input type="text" name="Course" value="<?= $Course; ?>" readonly>
            </div>
        </div>

        <div class="buttons">
            <button type="button" class="prev" onclick="showTab(1)">Previous</button>
            <button type="submit" class="submit">Edit Profile</button>
        </div>
    </div>
</form>

</div>

<script src="../includes/InstructorSchedule.js"></script>
<script src="../includes/InstructorDashboard.js"></script>
</body>
</html>
