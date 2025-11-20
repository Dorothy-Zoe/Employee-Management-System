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

// Validate fetched employee data for sidebar
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default.jpg";


// Fetch schedule data
$filterDay = isset($_POST['day']) ? $_POST['day'] : '';
$filterSection = isset($_POST['section']) ? $_POST['section'] : '';

// Fetch distinct sections for the filter dropdown
$sections = [];
$sectionQuery = "SELECT DISTINCT Section FROM schedule";
$sectionResult = $conn->query($sectionQuery);

if ($sectionResult && $sectionResult->num_rows > 0) {
    while ($row = $sectionResult->fetch_assoc()) {
        $sections[] = $row['Section'];
    }
}

// Pagination variables
$recordsPerPage = isset($_POST['entries']) ? (int)$_POST['entries'] : 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$startIndex = ($currentPage - 1) * $recordsPerPage;

// Base SQL query
$sql = "SELECT SQL_CALC_FOUND_ROWS s.FName, s.LName, sc.Department, sc.Course, sc.Section, sc.Subject_Code, sc.Subject_Description, sc.Course_Type, sc.Units, sc.RoomLab, sc.Days, sc.StartTime, sc.EndTime 
    FROM schedule sc
    JOIN employeedetail s ON sc.EmployeeID = s.EmployeeID";

// Apply filters if provided
$conditions = [];
$params = [];
$types = '';

if (!empty($filterDay)) {
    $conditions[] = "sc.Days LIKE ?";
    $params[] = '%' . $filterDay . '%';
    $types .= 's';
}

if (!empty($filterSection)) {
    $conditions[] = "sc.Section = ?";
    $params[] = $filterSection;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add pagination
$sql .= " LIMIT ?, ?";
$params[] = $startIndex;
$params[] = $recordsPerPage;
$types .= 'ii';

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Get total entries for pagination
    $totalEntriesResult = $conn->query("SELECT FOUND_ROWS() AS total");
    $totalEntries = $totalEntriesResult->fetch_assoc()['total'];
    $totalPages = ceil($totalEntries / $recordsPerPage);
} else {
    die("Error in SQL query: " . $conn->error);
}

// Generate PDF report
if (isset($_POST['generate_report'])) {
    require_once 'tcpdf/tcpdf.php';

    $sql = "SELECT s.FName, s.LName, sc.Department, sc.Course, sc.Section, sc.Subject_Code, sc.Subject_Description, 
                   sc.Course_Type, sc.Units, sc.RoomLab, sc.Days, sc.StartTime, sc.EndTime 
            FROM schedule sc
            JOIN employeedetail s ON sc.EmployeeID = s.EmployeeID";

    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($filterDay)) {
        $conditions[] = "sc.Days LIKE ?";
        $params[] = '%' . $filterDay . '%';
        $types .= 's';
    }

    if (!empty($filterSection)) {
        $conditions[] = "sc.Section = ?";
        $params[] = $filterSection;
        $types .= 's';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('times', 'B', 12);
        $pdf->Cell(0, 10, 'Employee Management System Schedule Report', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('times', '', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(35, 8, 'Name', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Department', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Course', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Section', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Subject Code', 1, 0, 'C', 1);
        $pdf->Cell(35, 8, 'Subject Description', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Course Type', 1, 0, 'C', 1);
        $pdf->Cell(10, 8, 'Units', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Room/Lab', 1, 0, 'C', 1);
        $pdf->Cell(17, 8, 'Days', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Start Time', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'End Time', 1, 1, 'C', 1);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pdf->Cell(35, 8, $row['FName'] . ' ' . $row['LName'], 1);
                $pdf->Cell(25, 8, $row['Department'], 1);
                $pdf->Cell(25, 8, $row['Course'], 1);
                $pdf->Cell(20, 8, $row['Section'], 1);
                $pdf->Cell(25, 8, $row['Subject_Code'], 1);
                $pdf->Cell(35, 8, $row['Subject_Description'], 1);
                $pdf->Cell(20, 8, $row['Course_Type'], 1);
                $pdf->Cell(10, 8, $row['Units'], 1);
                $pdf->Cell(20, 8, $row['RoomLab'], 1);
                $pdf->Cell(17, 8, $row['Days'], 1);
                $pdf->Cell(20, 8, date("g:i A", strtotime($row['StartTime'])), 1);
                $pdf->Cell(20, 8, date("g:i A", strtotime($row['EndTime'])), 1, 1);
            }
        } else {
            $pdf->Cell(270, 10, 'No schedule records found.', 1, 1, 'C');
        }

        // Add footer
        $pdf->Ln(10);
        $pdf->SetFont('times', 'I', 10);
        $pdf->Cell(0, 10, 'Generated by Penthouse - Employee Management System', 0, 1, 'C');

        ob_end_clean();
        $pdf->Output('EMS Schedule_Report.pdf', 'D');
        exit;
    } else {
        die("Error in SQL query: " . $conn->error);
    }
}

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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/adminDashboard.css">
    <link rel="stylesheet" href="includes/AdminLeaveReport.css">
    <script src="includes/AdminLeaveReport.js"></script>
    <script src="includes/adminDashboard.js"></script>
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
        <a href="adminDashboard.php" class="menu-item ">
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
    <a href="#" class="menu-item dropdown-toggle active">
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
        <h1 class="header-title">Schedule Report</h1>
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
<?php if (!empty($alertMessage)): ?>
    <div class="alert alert-warning" id="alertMessage">
        <?= htmlspecialchars($alertMessage) ?>
    </div>

    <script>
      // Auto-hide alert after 5 seconds (5000 ms)
      setTimeout(function () {
        const alertBox = document.getElementById("alertMessage");
        if (alertBox) {
          alertBox.style.transition = "opacity 0.5s ease";
          alertBox.style.opacity = 0;
          setTimeout(() => alertBox.style.display = "none", 500); // hide after fade out
        }
      }, 5000);
    </script>
<?php endif; ?>

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
                <form method="POST">
  <div class="filter-controls">
    
    <!-- Day Filter -->

    <select name="day" id="filterDay">
      <option value="">All Days</option>
      <option value="Monday" <?= ($filterDay == "Monday") ? 'selected' : ''; ?>>Monday</option>
      <option value="Tuesday" <?= ($filterDay == "Tuesday") ? 'selected' : ''; ?>>Tuesday</option>
      <option value="Wednesday" <?= ($filterDay == "Wednesday") ? 'selected' : ''; ?>>Wednesday</option>
      <option value="Thursday" <?= ($filterDay == "Thursday") ? 'selected' : ''; ?>>Thursday</option>
      <option value="Friday" <?= ($filterDay == "Friday") ? 'selected' : ''; ?>>Friday</option>
      <option value="Saturday" <?= ($filterDay == "Saturday") ? 'selected' : ''; ?>>Saturday</option>
      <option value="Sunday" <?= ($filterDay == "Sunday") ? 'selected' : ''; ?>>Sunday</option>
    </select>

    <!-- Section Filter -->
    <select name="section" id="filterSection">
      <option value="">All Sections</option>
      <?php foreach ($sections as $section): ?>
        <option value="<?= htmlspecialchars($section); ?>" <?= ($filterSection == $section) ? 'selected' : ''; ?>>
          <?= htmlspecialchars($section); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Apply Filter Button -->
    <button type="submit" name="filter" class="filter-btn">
      <i class="fas fa-filter"></i> Apply Filter
    </button>

    <!-- Optional: Generate Report Button -->
    <button type="submit" name="generate_report" class="report-btn">
      <i class="fas fa-download"></i> Generate Report
    </button>
    
  </div>
</form>
            </div>
<!-- END FILTER CONTROL -->
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
            <th>Days</th>
            <th>Start Time</th>
            <th>End Time</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['FName'] . ' ' . $row['LName']) ?></td>
            <td><?= htmlspecialchars($row['Department']) ?></td>
            <td><?= htmlspecialchars($row['Course']) ?></td>
            <td><?= htmlspecialchars($row['Section']) ?></td>
            <td><?= htmlspecialchars($row['Subject_Code']) ?></td>
            <td><?= htmlspecialchars($row['Subject_Description']) ?></td>
            <td><?= htmlspecialchars($row['Course_Type']) ?></td>
            <td><?= htmlspecialchars($row['Units']) ?></td>
            <td><?= htmlspecialchars($row['RoomLab']) ?></td>
            <td><?= htmlspecialchars($row['Days']) ?></td>
            <td><?= date("g:i A", strtotime($row['StartTime'])); ?></td>
            <td><?= date("g:i A", strtotime($row['EndTime'])); ?></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="11">No schedule records found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

</div>

<div class="pagination-info">
    Showing <?php echo ($totalEntries > 0) ? ($startIndex + 1) : 0; ?> to 
    <?php echo min($startIndex + $recordsPerPage, $totalEntries); ?> 
    of <?php echo $totalEntries; ?> entries
</div>



        </div>
          <!--END OF TABLE-->
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
<!-- End of main content -->
</div>
<script src="includes/adminHeader.js"></script>
</body>
</html>
