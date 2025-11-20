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

// Fetch employee details for sidebar
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
$adminprofilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default-profile.png";

// FILTER PROCESS

// Set the number of records per page
$recordsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1; // Prevent invalid page numbers

// Calculate the starting index for the LIMIT clause
$startIndex = ($currentPage - 1) * $recordsPerPage;

// Get selected department from URL
$departmentFilter = isset($_GET['Department']) && $_GET['Department'] !== '' ? $_GET['Department'] : null;

// Build the SQL query
$sql = "SELECT FName, LName, EmployeeID, Department, MobileNumber, Email, Address, JoiningDate, EmployeeStatus 
        FROM employeedetail";
$params = [];
$types = "";

if ($departmentFilter) {
    $sql .= " WHERE Department = ?";
    $params[] = $departmentFilter;
    $types .= "s";
}

$sql .= " LIMIT ?, ?";
$params[] = $startIndex;
$params[] = $recordsPerPage;
$types .= "ii";

// Prepare and execute query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get total number of employees (for pagination)
$sqlTotal = "SELECT COUNT(*) AS total FROM employeedetail";
if ($departmentFilter) {
    $sqlTotal .= " WHERE Department = ?";
}

$totalStmt = $conn->prepare($sqlTotal);
if ($departmentFilter) {
    $totalStmt->bind_param("s", $departmentFilter);
}
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalEntries = $totalRow['total'];
$totalPages = ($totalEntries > 0) ? ceil($totalEntries / $recordsPerPage) : 1;

// DELETE EMPLOYEE PROCESS
if (isset($_GET['delete_id'])) {
    $employeeId = $_GET['delete_id'];

    if (!empty($employeeId)) {
        $conn->begin_transaction();
        try {
            $tables = ['employeeleavebalance', 'leavetable', 'schedule', 'employeedetail'];
            foreach ($tables as $table) {
                $sql = "DELETE FROM $table WHERE EmployeeID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $employeeId);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            header("Location: viewEmployees.php?success=deleted");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error deleting employee: " . $e->getMessage() . "');</script>";
        }
    }
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
    <link rel="stylesheet" href="includes/adminViewEmp.css">
    <link rel="stylesheet" href="includes/addEmp.css">
    <link rel="stylesheet" href="includes/viewEmp.css">
    <script src="includes/adminDashboard.js"></script>
    <script src="includes/viewEmp.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        <h1 class="header-title">List of Employees</h1>
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

    <!-- Table -->
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
                    <div class="filter-controls">
    <?php
        $sql = "SELECT DISTINCT Department FROM employeedetail";
        $resultDepartments = $conn->query($sql);
    ?>
    
    <select id="filterDepartment">
        <option value="">All Departments</option>
        <?php while ($row = $resultDepartments->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['Department']) ?>" 
                <?= isset($_GET['Department']) && $_GET['Department'] == $row['Department'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['Department']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button id="applyFilter" class="filter-btn">Apply Filter</button>
</div>




                </div>
<!-- END FILTER CONTROL -->
            </div>

            <!--TABLE-->
            <div class="schedule-table-container">
    <table class="schedule-table" id="dataTable">
        <thead>
            <tr>

                <th>Name</th>
                <th>Employee ID</th>
                <th>Department</th>
                <th>Contact Number</th>
                <th>Email</th>
                <th>Address</th>
                <th>Date Hired</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>

                    <td><?= htmlspecialchars($row['FName'] . ' ' . $row['LName']) ?></td>
                    <td><?= htmlspecialchars($row['EmployeeID']) ?></td>
                    <td><?= htmlspecialchars($row['Department']) ?></td>
                    <td><?= htmlspecialchars($row['MobileNumber']) ?></td>
                    <td><?= htmlspecialchars($row['Email']) ?></td>
                    <td><?= htmlspecialchars($row['Address']) ?></td>
                    <td><?= htmlspecialchars($row['JoiningDate']) ?></td>
                    <td><?= htmlspecialchars($row['EmployeeStatus']) ?></td>
                    <td>
                    <div class="table-dropdown-action">
                                        <button class="table-btn-action">Actions <i class="fas fa-caret-down"></i></button>
                                        <div class="table-dropdown-content">
                                        <a href="editEmployee.php?EmployeeID=<?= urlencode($row['EmployeeID']); ?>">
                                             Edit Profile
                                         </a>
                                            <a href="viewEmployees.php?delete_id=<?= urlencode($row['EmployeeID']); ?>" 
                                                 onclick="return confirm('Are you sure you want to delete this employee?');" style="color: red;">
                                                 Delete Profile
                                            </a>
                                        </div>
                                    </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="10">No employees found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-info">
    Showing <?php echo ($totalEntries > 0) ? ($startIndex + 1) : 0; ?> to 
    <?php echo min($startIndex + $recordsPerPage, $totalEntries); ?> 
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
          <!--END OF TABLE-->
</div>

<script src="includes/adminHeader.js"></script>
</body>
</html>
