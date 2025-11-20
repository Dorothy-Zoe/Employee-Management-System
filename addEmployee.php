<?php
session_start();
include('includes/db.php');

if (!isset($_SESSION['admin_user'])) {
    die("Error: Employee ID not found in session.");
}

$UserID = $_SESSION['admin_user'];

// Fetch employee details
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

// Validate fetched employee data
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$adminprofilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default-profile.png";

// Function to fetch ENUM values
function getEnumValues($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($query);
    if (!$result || $result->num_rows === 0) {
        return [];
    }
    $row = $result->fetch_assoc();
    if (preg_match("/^enum\((.+)\)$/", $row['Type'], $matches)) {
        return str_getcsv($matches[1], ",", "'");
    }
    return [];
}

// Fetch ENUM values for dropdowns
$departments = getEnumValues($conn, 'employeedetail', 'Department');
$genders = getEnumValues($conn, 'employeedetail', 'Gender');
$marital_statuses = getEnumValues($conn, 'employeedetail', 'MaritalStatus');
$employee_statuses = getEnumValues($conn, 'employeedetail', 'EmployeeStatus');

$successMsg = "";
$errorMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_FILES["photo"]["name"])) {
        $targetDir = "uploads/";
        $profilePicture = $targetDir . basename($_FILES["photo"]["name"]); // Store full path
        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $profilePicture)) {
            $errorMsg = "File upload failed!";
            $profilePicture = $targetDir . "default.jpg"; // Fallback in case of error
        }
    } else {
        $profilePicture = "uploads/default.jpg"; // Store full path
    }
    // Collecting form data
    $fName = $_POST["first_name"];
    $lName = $_POST["last_name"];
    $mName = $_POST["middle_name"];
    $mobile = $_POST["contact_number"];
    $email = $_POST["email"];
    $emergencyContactName = $_POST["emergency_contact_name"];
    $emergencyContactNumber = $_POST["emergency_contact_number"];
    $gender = $_POST["gender"];
    $dateofbirth = date('Y-m-d', strtotime($_POST["dob"]));
    $nationality = $_POST["nationality"];
    $maritalStatus = $_POST["marital_status"];
    $address = $_POST["address"];
    $city = $_POST["city"];
    $zipCode = $_POST["zip_code"];
    $employeeID = $_POST["employee_id"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // Hashing password
    $employeeStatus = $_POST["employee_status"];
    $department = $_POST["department"];
    $joiningDate = date('Y-m-d', strtotime($_POST["joining_date"]));
    $primaryLevel = $_POST["primary_level"];
    $primaryStart = $_POST["primary_start"];
    $primaryGrad = $_POST["primary_grad"];
    $secondaryLevel = $_POST["secondary_level"];
    $secondaryStart = $_POST["secondary_start"];
    $secondaryGrad = $_POST["secondary_grad"];
    $tertiaryLevel = $_POST["tertiary_level"];
    $tertiaryStart = $_POST["tertiary_start"];
    $tertiaryGrad = $_POST["tertiary_grad"];
    $degree = $_POST["degree"];
    $course = $_POST["course"];

    // Insert into database
    $query = "INSERT INTO employeedetail 
        (ProfilePicture, FName, LName, Mname, MobileNumber, Email, emergency_contact_name, emergency_contact_number, Gender, 
        DateOfBirth, Nationality, MaritalStatus, 
        Address, City, ZipCode, EmployeeID, Password, EmployeeStatus, Department, JoiningDate, 
        PrimaryLevel, PrimaryYearStarted, PrimaryYearGraduated, 
        SecondaryLevel, SecondaryYearStarted, SecondaryYearGraduated, 
        TertiaryLevel, TertiaryYearStarted, TertiaryYearGraduated, Degree, Course) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssssississsssssssssssiisiisiiss",
        $profilePicture, $fName, $lName, $mName, $mobile, $email, 
        $emergencyContactName, $emergencyContactNumber, $gender, 
        $dateofbirth, $nationality, $maritalStatus, $address, 
        $city, $zipCode, $employeeID, $password, 
        $employeeStatus, $department, $joiningDate,
        $primaryLevel, $primaryStart, $primaryGrad,
        $secondaryLevel, $secondaryStart, $secondaryGrad,
        $tertiaryLevel, $tertiaryStart, $tertiaryGrad, $degree, $course
    );

    if ($stmt->execute()) {
        $successMsg = "Employee record added successfully!";
    } else {
        $errorMsg = "Error: " . $stmt->error;
    }

    $stmt->close();
} 

// Notification Process
$sql = "SELECT l.ID, l.Status, l.AppliedDate, ns.AdminIsRead, CONCAT(e.FName, ' ', e.LName) AS EmployeeName 
    FROM Leavetable l
    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
    LEFT JOIN employeedetail e ON l.EmployeeID = e.EmployeeID
    ORDER BY l.AppliedDate DESC
    LIMIT 5"; // Limit to 5 most recent notifications
$result = $conn->query($sql);

if (!$result) {
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

$conn->close();
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
<?php } ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCU EMS</title>
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/adminDashboard.css">
    <link rel="stylesheet" href="includes/addEmp.css">
    <script defer src="includes/addEmp.js"></script>
    <script defer src="includes/adminDashboard.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
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
        <a href="AdminSettings.php" class="menu-item">
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
        <h1 class="header-title">Add Employee</h1>
        <div class="header-actions">
            <!-- <div class="search-container">
                <span class="icon icon-search"></span>
                <input type="text" class="search-input" placeholder="Search anything here">
            </div> -->
            <!-- <a href="AdminSettings.php"><span class="icon icon-settings-header header-icon"></span></a> -->
            
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

   <!-- Form Sections -->
<form class="form-container" method="POST" action="" enctype="multipart/form-data">
    <!-- Personal Information -->
    <div class="form-section active">
    <div class="photo-upload">
    <input type="file" name="photo" id="photo" hidden>
    <label for="photo" class="upload-box">
        <i class="fa-solid fa-camera"></i>
    </label>
    <img id="preview" class="preview-image" src="" alt="Image Preview" style="display: none;">
</div>



        
        <div class="group">
            <div class="input-group">
                <label class="label">First Name</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="input-group">
                <label class="label">Last Name</label>
                <input type="text" name="last_name"  required>
            </div>
        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Middle Name</label>
                <input type="text" name="middle_name"  required>
            </div>
            <div class="input-group">
                <label class="label">Contact Number</label>
                <input type="text" name="contact_number"  required>
            </div>

        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" required>
            </div>
            <div class="input-group">
                <label class="label">Emergency Contact Number</label>
                <input type="text" name="emergency_contact_number" required>
            </div>
        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Gender</label>
                <select name="gender" required>
                    <option value="" disabled selected>Select Gender</option>
                    <?php foreach ($genders as $gender) { ?>
                        <option value="<?= htmlspecialchars($gender) ?>"><?= htmlspecialchars($gender) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="input-group">
                <label class="label">Date of Birth</label>
                <input type="date" name="dob" required>
            </div>
        </div>

        <div class="group">

            <div class="input-group">
                <label class="label">Marital Status</label>
                <select name="marital_status" required>
                    <option value="" disabled selected>Select Marital Status</option>
                    <?php foreach ($marital_statuses as $status) { ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="input-group">
                <label class="label">Email Address</label>
                <input type="email" name="email" required>
        </div>        

        </div>      

        <div class="group">
        <div class="input-group">
                <label class="label">Nationality</label>
                <input type="text" name="nationality" required>
            </div>
        <div class="input-group">
            <label class="label">Address</label>
            <input type="text" name="address" required>
        </div>
            
        </div>
        <div class="group">
        <div class="input-group">
                <label class="label">City</label>
                <input type="text" name="city" required>
            </div>
        <div class="input-group">
                <label class="label">ZIP Code</label>
                <input type="text" name="zip_code" required>
            </div>
        </div>

        <div class="buttons">
            <button type="button" class="cancel">Cancel</button>
            <button type="button" class="next" onclick="showTab(1)">Next</button>
        </div>
    </div>

    <!-- Professional Information -->
    <div class="form-section">
        <div class="group">
            <div class="input-group">
                <label class="label">Employee ID</label>
                <input type="text" name="employee_id" required>
            </div>
            <div class="input-group">
                <label class="label">Password</label>
                <input type="text" name="password" required>
            </div>
            

        </div>

        <div class="group">
        <div class="input-group">
                <label class="label">Employee Status</label>
                <select name="employee_status" required>
                    <option value="" disabled selected>Select Employee Status</option>
                    <?php foreach ($employee_statuses as $status) { ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php } ?>
                </select>
            </div>
        <div class="input-group">
                <label class="label">Department</label>
                <select name="department" required>
                    <option value="" disabled selected>Select Department</option>
                    <?php foreach ($departments as $department) { ?>
                        <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="input-group">
            <label class="label">Joining Date</label>
            <input type="date" name="joining_date" required>
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
                <input type="text" name="primary_level" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="primary_start" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="primary_grad" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Secondary Level</label>
                <input type="text" name="secondary_level" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="secondary_start" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="secondary_grad" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Tertiary Level</label>
                <input type="text" name="tertiary_level" required>
            </div>
            <div class="input-group">
                <label class="label">Year Started</label>
                <input type="text" name="tertiary_start" required>
            </div>
            <div class="input-group">
                <label class="label">Year Graduated</label>
                <input type="text" name="tertiary_grad" required>
            </div>
        </div>

        <div class="group">
            <div class="input-group">
                <label class="label">Degree</label>
                <input type="text" name="degree" required>
            </div>
            <div class="input-group">
                <label class="label">Course</label>
                <input type="text" name="course" required>
            </div>
        </div>

        <div class="buttons">
            <button type="button" class="prev" onclick="showTab(1)">Previous</button>
            <button type="submit" class="submit">Add</button>
        </div>
    </div>
</form>

</div>

<script src="includes/adminHeader.js"></script>
</body>
</html>
