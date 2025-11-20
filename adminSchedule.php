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

// Validate fetched employee data for sidebar
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default.jpg";
// END PROCESS FOR SIDEBAR


// Fetch schedule details with employee information
$sql = "SELECT 
            s.ID, 
            e.FName, e.LName, -- Employee Name
            s.Department, s.Course,s.Subject_Code, s.Subject_Description, 
            s.Course_Type, s.Units, s.Section, s.RoomLab, 
            s.Days, s.StartTime, s.EndTime 
        FROM schedule s
        JOIN employeedetail e ON s.EmployeeID = e.EmployeeID";  // Ensure correct table join

$result = mysqli_query($conn, $sql);

$displayData = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $displayData[] = $row;
    }
} else {
    die("Query Failed: " . mysqli_error($conn));  // Debugging error
}

//Filter and Pagination
// Retrieve filters from GET request

// Fetch distinct sections from the schedule table
$sections = [];
$sqlSections = "SELECT DISTINCT Section FROM schedule ORDER BY Section ASC";
$resultSections = mysqli_query($conn, $sqlSections);

if ($resultSections) {
    while ($row = mysqli_fetch_assoc($resultSections)) {
        $sections[] = $row['Section'];
    }
} else {
    die("Query Failed: " . mysqli_error($conn));
}

$filterDay = isset($_GET['filterDay']) ? $_GET['filterDay'] : '';
$filterSection = isset($_GET['filterSection']) ? $_GET['filterSection'] : '';
$entriesPerPage = isset($_GET['entries']) ? (int)$_GET['entries'] : 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $entriesPerPage;

// Construct SQL query with filters and pagination
$sql = "SELECT 
            s.ID, 
            e.FName, e.LName, 
            s.Department, s.Course, s.Subject_Code, s.Subject_Description, 
            s.Course_Type, s.Units, s.Section, s.RoomLab, 
            s.Days, s.StartTime, s.EndTime 
        FROM schedule s
        JOIN employeedetail e ON s.EmployeeID = e.EmployeeID 
        WHERE 1=1";

if (!empty($filterDay)) {
    $sql .= " AND s.Days = '" . mysqli_real_escape_string($conn, $filterDay) . "'";
}
if (!empty($filterSection)) {
    $sql .= " AND s.Section = '" . mysqli_real_escape_string($conn, $filterSection) . "'";
}

$sql .= " LIMIT $offset, $entriesPerPage";

$result = mysqli_query($conn, $sql);
$displayData = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $displayData[] = $row;
    }
} else {
    die("Query Failed: " . mysqli_error($conn));
}

// Count total records for pagination
$countSql = "SELECT COUNT(*) AS total FROM schedule s JOIN employeedetail e ON s.EmployeeID = e.EmployeeID WHERE 1=1";

if (!empty($filterDay)) {
    $countSql .= " AND s.Days = '" . mysqli_real_escape_string($conn, $filterDay) . "'";
}
if (!empty($filterSection)) {
    $countSql .= " AND s.Section = '" . mysqli_real_escape_string($conn, $filterSection) . "'";
}

$countResult = mysqli_query($conn, $countSql);
$totalEntries = ($countResult) ? mysqli_fetch_assoc($countResult)['total'] : 0;
$totalPages = ceil($totalEntries / $entriesPerPage);
$startIndex = $offset;
$endIndex = min($offset + $entriesPerPage, $totalEntries);


// Add Employee Schedule Process
// Fetch employees from employeedetail table
$query = "SELECT EmployeeID, CONCAT(FName, ' ', LName) AS fullname FROM employeedetail ORDER BY FName ASC";
$result = $conn->query($query);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "add_schedule") {
    $employee_id = $_POST['employee_id'];
    $department = $_POST['department'];
    $course = $_POST['course'];
    $section = $_POST['section'];
    $subject_description = $_POST['subject_description'];
    $subject_code = $_POST['subject_code'];
    $course_type = $_POST['course_type'];
    $units = $_POST['units'];
    $room = $_POST['room'];
    $days = $_POST['days'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Prepare the SQL query
    $insertQuery = "INSERT INTO schedule (EmployeeID, Department, Course, Section, Subject_Description, Subject_Code, Course_Type, Units, RoomLab, Days, StartTime, EndTime) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insertQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("sssssssissss", 
    $employee_id, $department, $course, $section, 
    $subject_description, $subject_code, $course_type, $units, 
    $room, $days, $start_time, $end_time);

    // Execute query
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Schedule added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding schedule!";
    }

    $stmt->close();
    $conn->close();
    header("Location: adminSchedule.php"); // Refresh to show message
    exit();
}

// Edit Schedule Process
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "edit_schedule") {
    // Update the list of required fields to include the new ones
    $requiredFields = [
        'editScheduleId', 'course_type', 'room', 'days', 'start_time', 'end_time',
        'department', 'course', 'section', 'subject_code', 'subject_description', 'units'
    ];

    // Check if any required field is empty
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $_SESSION['error_message'] = "Error: Missing required field '$field'.";
            header("Location: adminSchedule.php");
            exit();
        }
    }

    // Collect values from the form
    $scheduleID        = $_POST['editScheduleId'];
    $course_type       = $_POST['course_type'];
    $room              = $_POST['room'];
    $days              = $_POST['days'];
    $start_time        = $_POST['start_time'];
    $end_time          = $_POST['end_time'];
    $department        = $_POST['department'];
    $course            = $_POST['course'];
    $section           = $_POST['section'];
    $subject_code      = $_POST['subject_code'];
    $subject_desc      = $_POST['subject_description'];
    $units             = $_POST['units'];

    // Update the query to include the new fields
    $updateQuery = "UPDATE schedule 
                    SET Course_Type = ?, RoomLab = ?, Days = ?, StartTime = ?, EndTime = ?, 
                        Department = ?, Course = ?, Section = ?, Subject_Code = ?, 
                        Subject_Description = ?, Units = ? 
                    WHERE ID = ?";

    // Prepare and execute the update statement
    if ($stmt = $conn->prepare($updateQuery)) {
        $stmt->bind_param("sssssssssssi", 
            $course_type, $room, $days, $start_time, $end_time, 
            $department, $course, $section, $subject_code, $subject_desc, $units, $scheduleID);

        // Check if the query executed successfully
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Schedule updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating schedule: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Prepare failed: " . $conn->error;
    }

    // Redirect to the admin schedule page
    header("Location: adminSchedule.php");
    exit();
}

// ==========================
// Fetch schedule data for editing
// ==========================
if (isset($_GET['edit'])) {
    $scheduleID = $_GET['edit'];
    $fetchQuery = "SELECT s.*, 
                          e.Department, e.Course, e.Section, e.Subject_Code, e.Subject_Description, e.Units,
                          CONCAT(e.FName, ' ', e.LName) AS ProfessorName 
                   FROM schedule s 
                   JOIN employeedetail e ON s.EmployeeID = e.EmployeeID 
                   WHERE s.ID = ?";
    $stmt = $conn->prepare($fetchQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $scheduleID);
    $stmt->execute();
    $result = $stmt->get_result();
    $scheduleData = $result->fetch_assoc();
    $stmt->close();
} else {
    $scheduleData = null;
}


//Delete Schedule Process
if (isset($_GET['id'])) {
    $scheduleID = $_GET['id'];

    // Prepare the SQL query to delete the schedule
    $deleteQuery = "DELETE FROM schedule WHERE ID = ?";
    $stmt = $conn->prepare($deleteQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("i", $scheduleID);

    // Execute query
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Schedule deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting schedule!";
    }

    $stmt->close();
    header("Location: adminSchedule.php"); // Refresh to show message
    exit();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/adminDashboard.css">
    <link rel="stylesheet" href="includes/viewEmp.css">
    <script src="includes/adminDashboard.js"></script>
    <script src="includes/adminSchedule.js"></script>
    <link rel="stylesheet" href="includes/adminSchedule.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>TCU EMS</title>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo-container">
        <img src="src/tcu-logo.png" alt="TCU Logo" class="logo">
        <span class="university-name">Taguig City University</span>
    </div>
    <div class="profile-section">
        <img src="<?= $profilePicture; ?>" alt="Profile Picture" class="profile-image">
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
    <a href="#" class="menu-item dropdown-toggle">
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

        <a href="adminSchedule.php" class="menu-item active">
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
        <h1 class="header-title">Schedule</h1>
        <div class="header-actions">
            <!-- Search Container -->
            <div class="search-container">
    <span class="icon icon-search"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Search anything here" onkeyup="searchTable()">
</div>
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
<!-- Modal for all notifications -->
<div id="allNotificationsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeModal">&times;</span>
        <h2>All Notifications</h2>

        <div class="all-notifications-list">
            <?php 
            $allNotificationsSql = "SELECT l.ID, l.Status, l.AppliedDate, ns.IsRead, CONCAT(e.FName, ' ', e.LName) AS EmployeeName 
                                    FROM Leavetable l
                                    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
                                    LEFT JOIN employeedetail e ON l.EmployeeID = e.EmployeeID
                                    WHERE l.Status IN ('Approved', 'Rejected', 'Pending')
                                    ORDER BY l.AppliedDate DESC";
            $allStmt = $conn->prepare($allNotificationsSql);
            if ($allStmt) {
                $allStmt->execute();
                $allResult = $allStmt->get_result();
                if ($allResult->num_rows > 0) {
                    while ($row = $allResult->fetch_assoc()): ?>
                        <div class="notification-item ">
                            <div class="notification-icon <?= ($row['Status'] === 'Approved') ? 'notification-icon-leave' : (($row['Status'] === 'Rejected') ? 'notification-icon-rejected' : 'notification-icon-pending'); ?>">
                                <?php if ($row['Status'] === 'Approved'): ?>
                                    <i class="fa fa-check"></i>
                                <?php elseif ($row['Status'] === 'Rejected'): ?>
                                    <i class="fa fa-times"></i>
                                <?php else: ?>
                                    <i class="fa fa-hourglass-half"></i>
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
                    <?php endwhile;
                } else { ?>
                    <div class="no-notifications-message">
                        No notifications available
                    </div>
                <?php }
                $allStmt->close();
            } else { ?>
                <div class="no-notifications-message">
                    Error fetching notifications.
                </div>
            <?php } ?>
        </div>
        <button id="clearNotificationsBtn" class="clear-notifications-btn">Clear Notifications</button>
    </div>
</div>
<!-- End of Notification Modal -->
  <!-- Profile Dropdown -->
            <div class="Profile-dropdown">
                <button class="Pdropdown-button">
                    <img src="<?= $profilePicture; ?>" alt="Profile" class="profile-icon">
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

  <!-- Display messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div id="success-alert" class="success-alert">
        <?= $_SESSION['success_message']; ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div id="error-alert" class="error-alert">
        <?= $_SESSION['error_message']; ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>


<!-- Content Area -->
<div class="schedule-container">
            <div class="schedule-header">

          
                <!--ENTRIES-->
                <div class="schedule-controls">
                    <div class="entries-control">
                        <span>Show</span>
                        <select id="entriesDropdown" onchange="changeEntries(this.value)">
                            <option value="15" >15</option>
                            <option value="25" >25</option>
                            <option value="50" >50</option>
                            <option value="100" >100</select>
                        <span>entries</span>
                    </div>

<!-- Add Schedule Button -->
<button class="add-schedule-btn" id="openModalBtn">
    <i class="fas fa-plus"></i> Add New Schedule
</button>


<!-- Add Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModalBtn">&times;</span>
        <h2>Add Schedule</h2>
        <form method="POST" action="adminSchedule.php">
        <input type="hidden" name="action" value="add_schedule">
            <div class="form-container">
                <div class="form-group">
                    <label>Professor Name</label>
                    <select name="employee_id" required>
                        <option value="">Select Professor</option>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <option value="<?= $row['EmployeeID']; ?>"><?= $row['fullname']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
    <option value="">Select Department</option>
    <?php
    // Query to get ENUM values from database
    $query = "SHOW COLUMNS FROM employeedetail LIKE 'department'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    
    // Extract ENUM values
    preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
    $enumValues = str_getcsv($matches[1], ",", "'");

    // Generate dropdown options
    foreach ($enumValues as $value) {
        echo "<option value='$value'>$value</option>";
    }
    ?>
</select>
                </div>
                <div class="form-group">
    <label>Course</label>
    <input type="text" name="course" placeholder="Ex: BSCS/BSIS" required>
</div>
<div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" placeholder="Ex: 3A" required>
                </div>
                <div class="form-group">
    <label>Subject Description</label>
    <input type="text" name="subject_description" placeholder="Ex: Introduction to OOP" required>
</div>
<div class="form-group">
    <label>Subject Code</label>
    <input type="text" name="subject_code" placeholder="Ex: OOP 109" required>
</div>
<div class="form-group">
    <label>Course Type</label>
    <input type="text" name="course_type" placeholder="Ex: Lecture/Laboratory" required>
</div>
<div class="form-group">
                    <label>Units</label>
                    <input type="text" name="units" placeholder="Ex: 3" required>
                </div>


                <div class="form-group">
                    <label>Room/Laboratory</label>
                    <input type="text" name="room" placeholder="Ex: Room 408/Lab 102" required>
                </div>

                <div class="form-group">
                    <label>Day(s)</label>
                    <input type="text" name="days" placeholder="Ex: Tuesday" required>
                </div>

                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" id="closeModalBtn">Cancel</button>
                <button type="submit" class="save-btn">Save Schedule</button>
            </div>
        </form>
    </div>
</div>


                </div>
<!-- FILTER CONTROL -->
<div class="filter-controls">
    <select id="filterDay" onchange="applyFilters()">
        <option value="">All Days</option>
        <option value="Monday" <?= ($filterDay == "Monday") ? 'selected' : ''; ?>>Monday</option>
        <option value="Tuesday" <?= ($filterDay == "Tuesday") ? 'selected' : ''; ?>>Tuesday</option>
        <option value="Wednesday" <?= ($filterDay == "Wednesday") ? 'selected' : ''; ?>>Wednesday</option>
        <option value="Thursday" <?= ($filterDay == "Thursday") ? 'selected' : ''; ?>>Thursday</option>
        <option value="Friday" <?= ($filterDay == "Friday") ? 'selected' : ''; ?>>Friday</option>
        <option value="Saturday" <?= ($filterDay == "Saturday") ? 'selected' : ''; ?>>Saturday</option>
        <option value="Sunday" <?= ($filterDay == "Sunday") ? 'selected' : ''; ?>>Sunday</option>
    </select>

    <select id="filterSection" onchange="applyFilters()">
    <option value="">All Sections</option>
    <?php foreach ($sections as $section): ?>
        <option value="<?= htmlspecialchars($section); ?>" <?= ($filterSection == $section) ? 'selected' : ''; ?>>
            <?= htmlspecialchars($section); ?>
        </option>
    <?php endforeach; ?>
</select>

</div>

            </div>
            <!--TABLE-->
            <div class="schedule-table-container">
    <table class="schedule-table" id="dataTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Course</th>
                <th>Section</th>
                <th>Subject Code</th>
                <th>Subject Description</th>
                <th>Course Type</th>
                <th>Units</th>
                <th>Room/Lab</th>
                <th>Day(s)</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($displayData)): ?>
                <?php foreach ($displayData as $schedule): ?>
                    <tr>
                        <td><?= htmlspecialchars($schedule['FName'] . ' ' . $schedule['LName']); ?></td>
                        <td><?= htmlspecialchars($schedule['Department']); ?></td>
                        <td><?= htmlspecialchars($schedule['Course']); ?></td>
                        <td><?= htmlspecialchars($schedule['Section']); ?></td>
                        <td><?= htmlspecialchars($schedule['Subject_Code']); ?></td>
                        <td><?= htmlspecialchars($schedule['Subject_Description']); ?></td>
                        <td><?= htmlspecialchars($schedule['Course_Type']); ?></td>
                        <td><?= htmlspecialchars($schedule['Units']); ?></td>
                        <td><?= htmlspecialchars($schedule['RoomLab']); ?></td>
                        <td><?= htmlspecialchars($schedule['Days']); ?></td>
                        <td><?= date("g:i A", strtotime($schedule['StartTime'])); ?></td>
                        <td><?= date("g:i A", strtotime($schedule['EndTime'])); ?></td>

                        <td>
                            <div class="table-dropdown-action">
                                <button class="table-btn-action">Actions <i class="fas fa-caret-down"></i></button>
                                <div class="table-dropdown-content">
                                <a href="#" onclick='openEditScheduleModal(<?= json_encode($schedule, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
    Edit Schedule
</a>

  

<div id="editScheduleModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeEditModal">&times;</span>
        <h2>Edit Schedule</h2>
        <form id="editScheduleForm" method="POST" action="adminSchedule.php">
            <input type="hidden" name="action" value="edit_schedule">
            <input type="hidden" name="editScheduleId" value="<?= isset($scheduleData['ID']) ? htmlspecialchars($scheduleData['ID']) : '' ?>">

            <div class="form-container">
                <div class="form-group">
                    <label>Professor Name</label>
                    <input type="text" id="editProfessor" readonly
                        value="<?= isset($scheduleData['FName'], $scheduleData['LName']) ? htmlspecialchars($scheduleData['FName'] . ' ' . $scheduleData['LName']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select id="editDepartment" name="department">
                        <option value="CICT" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'CICT' ? 'selected' : '' ?>>CICT</option>
                        <option value="CAS" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'CAS' ? 'selected' : '' ?>>CAS</option>
                        <option value="CBM" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'CBM' ? 'selected' : '' ?>>CBM</option>
                        <option value="CCJ" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'CCJ' ? 'selected' : '' ?>>CCJ</option>
                        <option value="COE" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'COE' ? 'selected' : '' ?>>COE</option>
                        <option value="CHTM" <?= isset($scheduleData['Department']) && $scheduleData['Department'] === 'CHTM' ? 'selected' : '' ?>>CHTM</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Course</label>
                    <input type="text" id="editCourse" name="course"
                        value="<?= isset($scheduleData['Course']) ? htmlspecialchars($scheduleData['Course']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Section</label>
                    <input type="text" id="editSection" name="section"
                        value="<?= isset($scheduleData['Section']) ? htmlspecialchars($scheduleData['Section']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Subject Description</label>
                    <input type="text" id="editSubject" name="subject_description"
                        value="<?= isset($scheduleData['Subject_Description']) ? htmlspecialchars($scheduleData['Subject_Description']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" id="editSubjectCode" name="subject_code"
                        value="<?= isset($scheduleData['Subject_Code']) ? htmlspecialchars($scheduleData['Subject_Code']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Course Type</label>
                    <input type="text" id="editCourseType" name="course_type"
                        value="<?= isset($scheduleData['Course_Type']) ? htmlspecialchars($scheduleData['Course_Type']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Units</label>
                    <input type="text" id="editUnits" name="units"
                        value="<?= isset($scheduleData['Units']) ? htmlspecialchars($scheduleData['Units']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Room/Laboratory</label>
                    <input type="text" id="editRoom" name="room"
                        value="<?= isset($scheduleData['RoomLab']) ? htmlspecialchars($scheduleData['RoomLab']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Day(s)</label>
                    <input type="text" id="editDays" name="days"
                        value="<?= isset($scheduleData['Days']) ? htmlspecialchars($scheduleData['Days']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" id="editStartTime" name="start_time"
                        value="<?= isset($scheduleData['StartTime']) ? htmlspecialchars($scheduleData['StartTime']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" id="editEndTime" name="end_time"
                        value="<?= isset($scheduleData['EndTime']) ? htmlspecialchars($scheduleData['EndTime']) : '' ?>">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="cancel-btn" id="cancelEditModal">Cancel</button>
                <button type="submit" class="save-btn">Update Schedule</button>
            </div>
        </form>
    </div>
</div>



  <a href="adminSchedule.php?id=<?= urlencode($schedule['ID']); ?>"
     onclick="return confirm('Are you sure you want to delete this schedule?');"
     style="color: red;">
     Delete Schedule
  </a>
</div>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="12">No schedule found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<!-- Pagination -->
<div class="pagination-info">
    Showing <?= ($totalEntries > 0) ? ($startIndex + 1) : 0; ?> to <?= $endIndex; ?> of <?= $totalEntries; ?> entries
</div>

<div class="pagination">
    <button class="pagination-btn" <?= ($currentPage <= 1) ? 'disabled' : ''; ?> onclick="changePage(<?= max(1, $currentPage - 1); ?>)">Previous</button>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <button class="pagination-btn <?= ($i == $currentPage) ? 'active' : ''; ?>" onclick="changePage(<?= $i; ?>)"><?= $i; ?></button>
    <?php endfor; ?>

    <button class="pagination-btn" <?= ($currentPage >= $totalPages) ? 'disabled' : ''; ?> onclick="changePage(<?= min($totalPages, $currentPage + 1); ?>)">Next</button>
</div>

        </div>


</div>


</div>
<script src="includes/adminHeader.js"></script>
</body>
</html>
