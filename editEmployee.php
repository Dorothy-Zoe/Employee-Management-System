<?php
session_start();
include('includes/db.php');

if (!isset($_SESSION['admin_user'])) {
    die("Error: Employee ID not found in session.");
}

$UserID = $_SESSION['admin_user'];

// Notification Process
$sql = "SELECT l.ID, l.Status, l.AppliedDate, ns.AdminIsRead, CONCAT(e.FName, ' ', e.LName) AS EmployeeName 
    FROM Leavetable l
    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
    LEFT JOIN employeedetail e ON l.EmployeeID = e.EmployeeID
    ORDER BY l.AppliedDate DESC
    LIMIT 5"; // Limit to 5 most recent notifications
$notificationResult = $conn->query($sql);

if (!$notificationResult) {
    die("Error in SQL query: {$conn->error}");
}

// Mark notifications as read when the dropdown is opened
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markAllRead'])) {
    // Update all notifications to 'read' (AdminIsRead = 1)
    $updateSql = "UPDATE leave_notification_status SET AdminIsRead = 1";
    if (!$conn->query($updateSql)) {
        die("Error in SQL query: {$conn->error}");
    }
    // Return success response
    echo json_encode(['success' => true]);
    exit;
}

//End of Notification Process

// Fetch admin details for sidebar
$sql = "SELECT FName, LName, ProfilePicture FROM admintbl WHERE UserID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $UserID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Validate fetched admin data
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$adminprofilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default-profile.png";

// Initialize message variables
$successMsg = "";
$errorMsg = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $EmployeeID = $_POST['EmployeeID'];
    $FName = $_POST['FName'];
    $LName = $_POST['LName'];
    $MName = $_POST['MName'];
    $MobileNumber = $_POST['MobileNumber'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact_number = $_POST['emergency_contact_number'];
    $Gender = $_POST['Gender'];
    $DateOfBirth = $_POST['DateOfBirth'];
    $MaritalStatus = $_POST['MaritalStatus'];
    $Email = $_POST['Email'];
    $Nationality = $_POST['Nationality'];
    $Address = $_POST['Address'];
    $City = $_POST['City'];
    $ZipCode = $_POST['ZipCode'];
    $EmployeeStatus = $_POST['EmployeeStatus'];
    $Department = $_POST['Department'];
    $JoiningDate = $_POST['JoiningDate'];
    $PrimaryLevel = $_POST['PrimaryLevel'];
    $PrimaryYearStarted = $_POST['PrimaryYearStarted'];
    $PrimaryYearGraduated = $_POST['PrimaryYearGraduated'];
    $SecondaryLevel = $_POST['SecondaryLevel'];
    $SecondaryYearStarted = $_POST['SecondaryYearStarted'];
    $SecondaryYearGraduated = $_POST['SecondaryYearGraduated'];
    $TertiaryLevel = $_POST['TertiaryLevel'];
    $TertiaryYearStarted = $_POST['TertiaryYearStarted'];
    $TertiaryYearGraduated = $_POST['TertiaryYearGraduated'];
    $Degree = $_POST['Degree'];
    $Course = $_POST['Course'];

    // SQL Query to update employee details
    $sql = "UPDATE employeedetail SET 
        /*ProfilePicture = ?,*/ 
        FName = ?, LName = ?, MName = ?, MobileNumber = ?, 
        emergency_contact_name = ?, emergency_contact_number = ?, 
        Gender = ?, DateOfBirth = ?, MaritalStatus = ?, Email = ?, 
        Nationality = ?, Address = ?, City = ?, ZipCode = ?, 
        EmployeeStatus = ?, Department = ?, JoiningDate = ?, 
        PrimaryLevel = ?, PrimaryYearStarted = ?, PrimaryYearGraduated = ?, 
        SecondaryLevel = ?, SecondaryYearStarted = ?, SecondaryYearGraduated = ?, 
        TertiaryLevel = ?, TertiaryYearStarted = ?, TertiaryYearGraduated = ?, 
        Degree = ?, Course = ? 
        WHERE EmployeeID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssisisssssssissssiisiisiisss",
        //$profilePicture,
        $FName, $LName, $MName, $MobileNumber, 
        $emergency_contact_name, $emergency_contact_number, 
        $Gender, $DateOfBirth, $MaritalStatus, $Email, 
        $Nationality, $Address, $City, $ZipCode, 
        $EmployeeStatus, $Department, $JoiningDate, 
        $PrimaryLevel, $PrimaryYearStarted, $PrimaryYearGraduated, 
        $SecondaryLevel, $SecondaryYearStarted, $SecondaryYearGraduated, 
        $TertiaryLevel, $TertiaryYearStarted, $TertiaryYearGraduated, 
        $Degree, $Course, $EmployeeID
    );

    if ($stmt->execute()) {
        $successMsg = "Employee profile updated successfully!";
    } else {
        $errorMsg = "Error updating profile. Please try again.";
    }
}
if (!empty($successMsg)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.backgroundColor = "#d4edda";
            messageBox.style.color = "#155724";
            messageBox.style.border = "1px solid #c3e6cb";
            messageBox.innerHTML = "<?php echo $successMsg; ?>";

            // Hide the message after 5 seconds
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

            // Hide the message after 5 seconds
            setTimeout(function() {
                messageBox.style.display = "none";
            }, 5000);
        });
    </script>
<?php }
// Initialize variables to prevent errors
$profilePicture = $FName = $LName = $MName = "";
$MobileNumber = $emergency_contact_name = $emergency_contact_number = "";
$Gender = $DateOfBirth = $MaritalStatus = $Email = "";
$Nationality = $Address = $City = $ZipCode = "";
$EmployeeID = $EmployeeStatus = $Department = $JoiningDate = "";
$PrimaryLevel = $PrimaryYearStarted = $PrimaryYearGraduated = "";
$SecondaryLevel = $SecondaryYearStarted = $SecondaryYearGraduated = "";
$TertiaryLevel = $TertiaryYearStarted = $TertiaryYearGraduated = "";
$Degree = $Course = "";

// Initialize variables
$Password = "";

if ($row = $result->fetch_assoc()) {
    $Password = htmlspecialchars($row['Password']);
}

// Check if an Employee ID is provided in the URL
if (isset($_GET['EmployeeID'])) {
    $EmployeeID = $_GET['EmployeeID'];

    // Fetch employee details from the database
    $sql = "SELECT * FROM employeedetail WHERE EmployeeID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Assign database values to PHP variables
        //$profilePicture = htmlspecialchars($row['ProfilePicture']);
        $FName = htmlspecialchars($row['FName']);
        $LName = htmlspecialchars($row['LName']);
        $MName = htmlspecialchars($row['MName']);
        $MobileNumber = htmlspecialchars($row['MobileNumber']);
        $emergency_contact_name = htmlspecialchars($row['emergency_contact_name']);
        $emergency_contact_number = htmlspecialchars($row['emergency_contact_number']);
        $Gender = htmlspecialchars($row['Gender']);
        $DateOfBirth = htmlspecialchars($row['DateOfBirth']);
        $MaritalStatus = htmlspecialchars($row['MaritalStatus']);
        $Email = htmlspecialchars($row['Email']);
        $Nationality = htmlspecialchars($row['Nationality']);
        $Address = htmlspecialchars($row['Address']);
        $City = htmlspecialchars($row['City']);
        $ZipCode = htmlspecialchars($row['ZipCode']);
        $EmployeeStatus = htmlspecialchars($row['EmployeeStatus']);
        $Department = htmlspecialchars($row['Department']);
        $JoiningDate = htmlspecialchars($row['JoiningDate']);
        $PrimaryLevel = htmlspecialchars($row['PrimaryLevel']);
        $PrimaryYearStarted = htmlspecialchars($row['PrimaryYearStarted']);
        $PrimaryYearGraduated = htmlspecialchars($row['PrimaryYearGraduated']);
        $SecondaryLevel = htmlspecialchars($row['SecondaryLevel']);
        $SecondaryYearStarted = htmlspecialchars($row['SecondaryYearStarted']);
        $SecondaryYearGraduated = htmlspecialchars($row['SecondaryYearGraduated']);
        $TertiaryLevel = htmlspecialchars($row['TertiaryLevel']);
        $TertiaryYearStarted = htmlspecialchars($row['TertiaryYearStarted']);
        $TertiaryYearGraduated = htmlspecialchars($row['TertiaryYearGraduated']);
        $Degree = htmlspecialchars($row['Degree']);
        $Course = htmlspecialchars($row['Course']);
    } else {
        echo "<script>alert('Employee not found!');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCU EMS</title>
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/adminDashboard.css">
    <link rel="stylesheet" href="includes/addEmp.css">
    <script defer src="includes/addEmp.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <script src="includes/adminDashboard.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>



<!-- Sidebar -->
<div class="sidebar">
    <div class="logo-container">
        <img src="src/tcu-logo.png" alt="TCU Logo" class="logo">
        <span class="university-name">Taguig City University</span>
    </div>
    <div class="profile-section">
        <img src="<?= $adminprofilePicture; ?>" alt="Profile Picture" class="profile-image">
        <div class="profile-details">
            <span class="profile-name"><?= htmlspecialchars($fullName); ?></span>
            <span class="profile-role">Administrator</span>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="adminDashboard.php" class="menu-item">
            <span class="icon icon-dashboard"></span> Dashboard
        </a>
        <div class="dropdown">
    <a href="#" class="menu-item dropdown-toggle active">
        <span class="icon icon-employee"></span> Employees
        <span class="icon icon-right-arrow"></span>
    </a>
    <div class="dropdown-content">
        <a href="addEmployee.php" class="dropdown-item">Add Employee</a>
        <a href="viewEmployees.php" class="dropdown-item">View Employees</a>
    </div>
</div>

<div class="dropdown">
    <a href="#" class="menu-item dropdown-toggle">
        <span class="icon icon-reports"></span> Reports
        <span class="icon icon-right-arrow"></span>
    </a>
    <div class="dropdown-content">
    <a href="AdminLeaveReports.php" class="dropdown-item">Leave Reports</a>
    <a href="AdminScheduleReports.php" class="dropdown-item">Schedule Reports</a>
    </div>
</div>

        <a href="adminSchedule.php" class="menu-item">
            <span class="icon icon-schedule"></span> Schedule
        </a>
        <a href="adminSettings.php" class="menu-item">
            <span class="icon icon-settings"></span> Settings
        </a>
    </div>
    <a href="index.html" class="logout">
        <span class="icon icon-logout"></span> Log out
    </a>
</div>
    <!-- End of the Sidebar -->
<div class="main-content">
 <!-- Header -->
 <div class="header">
        <h1 class="header-title">Edit Employee</h1>
        <div class="header-actions">
            <!-- <div class="search-container">
                <span class="icon icon-search"></span>
                <input type="text" class="search-input" placeholder="Search anything here">
            </div> -->
            <!-- Notification -->
<div class="notif-dropdown">
    <span class="icon icon-notification header-icon" id="notificationDropdown"></span>
    <span id="unreadCount" class="unread-count"><?= $notificationResult->num_rows; ?></span> <!-- Unread count -->
    <div class="dropdown-content notification-dropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <span class="mark-all-read" id="markAllRead">Mark all as read</span>
        </div>
        <?php 
        $hasValidNotifications = false;
        if ($result->num_rows > 0): 
        ?>
            <?php while ($row = $notificationResult->fetch_assoc()): ?>
                <?php $hasValidNotifications = true; ?>
                <div class="notification-item <?= ($row['AdminIsRead'] == 0) ? 'unread' : 'read'; ?>" id="notif-<?= $row['ID']; ?>">
                    <div class="notification-icon <?= ($row['Status'] === 'Approved') ? 'notification-icon-leave' : (($row['Status'] === 'Rejected') ? 'notification-icon-rejected' : 'notification-icon-pending'); ?>">
                        <!-- Font Awesome icons for approved, rejected, and pending status -->
                        <?php if ($row['Status'] === 'Approved'): ?>
                            <i class="fa fa-check"></i> <!-- Green checkmark for approved -->
                        <?php elseif ($row['Status'] === 'Rejected'): ?>
                            <i class="fa fa-times"></i> <!-- Red cross for rejected -->
                        <?php else: ?>
                            <i class="fa fa-hourglass-half"></i> <!-- Clock icon for pending -->
                        <?php endif; ?>
                    </div>
                    <div class="notification-content">
                        <p>
                            <?= htmlspecialchars($row['EmployeeName']); ?> 
                            <?= ($row['Status'] === 'Approved') 
                                ? 'had their leave request approved.' 
                                : (($row['Status'] === 'Rejected') 
                                    ? 'had their leave request rejected.' 
                                    : 'has a pending leave request.'); ?>
                        </p>
                        <span class="notification-time">
                            <?= 'Applied on: ' . date('F j, Y', strtotime($row['AppliedDate'])); ?>
                        </span>
                    </div>
                </div>
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
<!-- End of Notification -->

            <!-- Profile Dropdown -->
            <div class="Profile-dropdown">
                <button class="Pdropdown-button">
                    <img src="<?= $adminprofilePicture; ?>" alt="Profile" class="profile-icon">
                </button>
                <div class="Pdropdown-content">
                    <a href="AdminSettings.php">Settings</a>
                    <a href="index.html">Log out</a>
                </div>
            </div>
            <!-- End of Profile Dropdown -->
        </div>
    </div>
    <!-- End of the Header -->
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

    <!-- Display Success Message -->
    <div id="message-box"></div>

   <!-- Form Sections -->
<form class="form-container" method="POST" action="" enctype="multipart/form-data">
    <!-- Personal Information -->
    <div class="form-section active">
    <!-- Profile Picture Section 
<div class="photo-upload">
    <input type="file" name="photo" id="photo" hidden>
    <label for="photo" class="upload-box">
        
    </label>
    <img id="preview" class="preview-image" src="uploads/<?= $profilePicture; ?>" alt="Profile Picture">
</div>-->

    
        <div class="group">
            <div class="input-group">
                <label class="label">First Name</label>
                <input type="text" name="FName" value="<?= $FName; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Last Name</label>
                <input type="text" name="LName" value="<?= $LName; ?>" required>
            </div>
        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Middle Name</label>
                <input type="text" name="MName" value="<?= $MName; ?>" required>
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
                <select name="Gender" required>
    <option value="Male" <?= ($Gender == 'Male') ? 'selected' : '' ?>>Male</option>
    <option value="Female" <?= ($Gender == 'Female') ? 'selected' : '' ?>>Female</option>
</select>
            </div>
            <div class="input-group">
                <label class="label">Date of Birth</label>
                <input type="date" name="DateOfBirth" value="<?= $DateOfBirth; ?>" required>
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
                <input type="text" name="Nationality" value="<?= $Nationality; ?>" required>
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
            <button type="button" class="cancel" onclick="window.location.href='viewEmployees.php';">Cancel</button>
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
                <label class="label">Password</label>
                <input type="password" name="Password" id="passwordField" value="********" readonly>
            </div>
            

        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Employee Status</label>
                <select name="EmployeeStatus" required>
    <option value="Part-time" <?= ($EmployeeStatus == 'Part-time') ? 'selected' : '' ?>>Part-time</option>
    <option value="Full-time" <?= ($EmployeeStatus == 'Full-time') ? 'selected' : '' ?>>Full-time</option>
</select>
            </div>
        <div class="input-group">
                <label class="label">Department</label>
                <select name="Department" required>
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
            <input type="date" name="JoiningDate" value="<?= $JoiningDate; ?>" required>
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
                <input type="text" name="PrimaryLevel" value="<?= $PrimaryLevel; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="PrimaryYearStarted" value="<?= $PrimaryYearStarted; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="PrimaryYearGraduated" value="<?= $PrimaryYearGraduated; ?>" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Secondary Level</label>
                <input type="text" name="SecondaryLevel" value="<?= $SecondaryLevel; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="SecondaryYearStarted" value="<?= $SecondaryYearStarted; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="SecondaryYearGraduated" value="<?= $SecondaryYearGraduated; ?>" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Tertiary Level</label>
                <input type="text" name="TertiaryLevel" value="<?= $TertiaryLevel; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="TertiaryYearStarted" value="<?= $TertiaryYearStarted; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="TertiaryYearGraduated" value="<?= $TertiaryYearGraduated; ?>" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Degree</label>
                <input type="text" name="Degree" value="<?= $Degree; ?>" required>
            </div>
            <div class="input-group">
                <label class="label">Course</label>
                <input type="text" name="Course" value="<?= $Course; ?>" required>
            </div>
        </div>

        <div class="buttons">
            <button type="button" class="prev" onclick="showTab(1)">Previous</button>
            <button type="submit" class="submit">Save Changes</button>
        </div>
    </div>
</form>

</div>

<script src="includes/adminHeader.js"></script>
</body>
</html>
