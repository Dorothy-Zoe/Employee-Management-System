<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['employee_user'])) {
    die("Error: Employee ID not found in session.");
}

$EmployeeID = $_SESSION['employee_user'];

// Fetch employee details (including password)
$sql = "SELECT FName, LName, Email, MobileNumber, ProfilePicture, EmployeeID, Department, Password 
        FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error: " . $conn->error);
}

// Validate fetched employee data
$fullName = isset($employee['FName'], $employee['LName']) ? htmlspecialchars($employee['FName'] . " " . $employee['LName']) : 'Unknown User';
$profilePicture = !empty($employee['ProfilePicture']) ? "../" . htmlspecialchars($employee['ProfilePicture']) : "../src/default-profile.png";

// Set default values for filters
$filterDay = isset($_GET['filterDay']) ? $_GET['filterDay'] : '';
$filterSection = isset($_GET['filterSection']) ? $_GET['filterSection'] : '';

// Set default values for pagination
$recordsPerPage = isset($_GET['entries']) ? (int) $_GET['entries'] : 15; // Default 15 records per page
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Build query to count total records based on filters
$countSQL = "SELECT COUNT(*) AS total FROM schedule WHERE employeeID = ?";
$params = [$EmployeeID];
$types = "s";

if (!empty($filterDay)) {
    $countSQL .= " AND days = ?";
    $params[] = $filterDay;
    $types .= "s";
}
if (!empty($filterSection)) {
    $countSQL .= " AND section = ?";
    $params[] = $filterSection;
    $types .= "s";
}

$countStmt = $conn->prepare($countSQL);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$result = $countStmt->get_result();
$row = $result->fetch_assoc();
$totalEntries = $row['total'];
$countStmt->close();

// Calculate pagination variables
$totalPages = ($totalEntries > 0) ? ceil($totalEntries / $recordsPerPage) : 1;
$startIndex = ($currentPage - 1) * $recordsPerPage;
$endIndex = min($startIndex + $recordsPerPage, $totalEntries);

// Fetch employee's schedule with filters
$scheduleSQL = "SELECT course, department, section, units, course_type, roomlab, subject_code, subject_description, days, starttime, endtime 
                FROM schedule WHERE employeeID = ?";

$params = [$EmployeeID];
$types = "s";

if (!empty($filterDay)) {
    $scheduleSQL .= " AND days = ?";
    $params[] = $filterDay;
    $types .= "s";
}

if (!empty($filterSection)) {
    $scheduleSQL .= " AND section = ?";
    $params[] = $filterSection;
    $types .= "s";
}

$scheduleSQL .= " ORDER BY days, starttime LIMIT ?, ?";
$params[] = $startIndex;
$params[] = $recordsPerPage;
$types .= "ii";

$scheduleStmt = $conn->prepare($scheduleSQL);
$scheduleStmt->bind_param($types, ...$params);
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

$displayData = [];
while ($row = $scheduleResult->fetch_assoc()) {
    $displayData[] = $row;
}

$scheduleStmt->close();

// Initialize arrays for dropdowns to avoid errors
$days = [];
$sections = [];

// Fetch unique days from the schedule
$daysSQL = "SELECT DISTINCT days FROM schedule WHERE employeeID = ?";
$daysStmt = $conn->prepare($daysSQL);
$daysStmt->bind_param("s", $EmployeeID);
$daysStmt->execute();
$daysResult = $daysStmt->get_result();
while ($row = $daysResult->fetch_assoc()) {
    $days[] = $row['days'];
}
$daysStmt->close();

// Fetch unique sections from the schedule
$sectionsSQL = "SELECT DISTINCT section FROM schedule WHERE employeeID = ?";
$sectionsStmt = $conn->prepare($sectionsSQL);
$sectionsStmt->bind_param("s", $EmployeeID);
$sectionsStmt->execute();
$sectionsResult = $sectionsStmt->get_result();
while ($row = $sectionsResult->fetch_assoc()) {
    $sections[] = $row['section'];
}
$sectionsStmt->close();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../src/favicon-logo.ico">
    <title>Taguig City University - Schedule</title>
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/InstructorSchedule.css">
    <script src="../includes/InstructorSchedule.js"></script>
     <!--For notification-->
     <link rel="stylesheet" href="../includes/InstructorSchedule.css">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
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
                <span class="icon icon-dashboard"></span>
                Dashboard
            </a>
            <a href="schedule.php" class="menu-item active">
                <span class="icon icon-schedule"></span>
                Schedule
            </a>
            <a href="leaveManagement.php" class="menu-item">
                <span class="icon icon-leave"></span>
                Leave Management
            </a>
            <a href="settings.php" class="menu-item">
                <span class="icon icon-settings"></span>
                Settings
            </a>
        </div>
        <a href="../index.html" class="logout">
            <span class="icon icon-logout"></span>
            Log out
        </a>
    </div>

    <div class="main-content">
        <!-- Header -->
<div class="header">
        <h1 class="header-title">Dashboard</h1>
        <div class="header-actions">
 <!-- Search Container-->
 <div class="search-container">
    <span class="icon icon-search"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Search anything here" onkeyup="searchTable()">
</div> 

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
                            <option value="100" >100</option>
                        </select>
                        <span>entries</span>
                    </div>
<!-- FILTER CONTROL -->
<div class="filter-controls">
    <!-- Filter by Day -->
    <select id="filterDay">
        <option value="">All Days</option>
        <?php foreach ($days as $day): ?>
            <option value="<?php echo htmlspecialchars($day); ?>" 
                <?php echo ($filterDay == $day) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($day); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Filter by Section -->
    <select id="filterSection">
        <option value="">All Sections</option>
        <?php foreach ($sections as $section): ?>
            <option value="<?php echo htmlspecialchars($section); ?>" 
                <?php echo ($filterSection == $section) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($section); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="button" class="filter-btn" onclick="applyFilters()">Apply Filters</button>
</div>
                </div>




            </div>
            <!--TABLE-->
            <div class="schedule-table-container">
    <table class="schedule-table" id="dataTable">
        <thead>
            <tr>
                <th>Department</th>
                <th>Course</th>
                <th>Subject Code</th>
                <th>Subject Description</th>
                <th>Course Type</th>
                <th>Units</th>
                <th>Section</th>
                <th>Room/Lab</th>
                <th>Day(s)</th>
                <th>Start Time</th>
                <th>End Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($displayData)): ?>
                <?php foreach ($displayData as $index => $schedule): ?>
                <tr>
                    <td><?php echo htmlspecialchars($schedule['department']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['course']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['subject_code']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['subject_description']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['course_type']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['units']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['section']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['roomlab']); ?></td>
                    <td><?php echo htmlspecialchars($schedule['days']); ?></td>
                    <td><?= date("g:i A", strtotime($schedule['starttime'])); ?></td>
                    <td><?= date("g:i A", strtotime($schedule['endtime'])); ?></td>

                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="12">No schedule found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-info">
    Showing <?php echo ($totalEntries > 0) ? ($startIndex + 1) : 0; ?> to 
    <?php echo ($totalEntries > 0) ? $endIndex : 0; ?> 
    of <?php echo $totalEntries; ?> entries
</div>

<div class="pagination">
    <button class="pagination-btn" <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?> 
        onclick="changePage(<?php echo max(1, $currentPage - 1); ?>)">
        Previous
    </button>
    
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <button class="pagination-btn <?php echo ($i == $currentPage) ? 'active' : ''; ?>" 
            onclick="changePage(<?php echo $i; ?>)">
            <?php echo $i; ?>
        </button>
    <?php endfor; ?>

    <button class="pagination-btn" <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?> 
        onclick="changePage(<?php echo min($totalPages, $currentPage + 1); ?>)">
        Next
    </button>
</div>


        </div>
    </div>

    <script src="../includes/InstructorSchedule.js"></script>
    <script src="../includes/InstructorDashboard.js"></script>
</body>
</html>