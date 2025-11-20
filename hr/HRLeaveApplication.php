<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['hr_user'])) {
    die("Error: HR ID not found in session.");
}

$UserID = $_SESSION['hr_user'];

// Fetch employee details
$sql = "SELECT FName, LName, ProfilePicture FROM hrtbl WHERE UserID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $UserID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die(sprintf("Error in SQL query: %s", $conn->error));
}

// Validate fetched employee data
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . $employee['ProfilePicture'] : "../uploads/default.jpg";

// Fetch leave reports for pagination
$recordsPerPage = 15; // Number of records to display per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number
$startIndex = ($currentPage - 1) * $recordsPerPage; // Calculate the starting index

// Fetch total number of leave reports
$totalEntriesQuery = "SELECT COUNT(*) as total FROM leavetable";
$totalEntriesResult = $conn->query($totalEntriesQuery);
$totalEntries = $totalEntriesResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalEntries / $recordsPerPage);

// Fetch leave reports with pagination
$sql = "SELECT lt.*, et.FName, et.LName 
    FROM leavetable lt
    JOIN employeedetail et ON lt.EmployeeID = et.EmployeeID
    LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ii", $startIndex, $recordsPerPage);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Leave report filtering by date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    $fromDate = $_POST['from'];
    $toDate = $_POST['to'];

    $sql = "SELECT lt.*, et.FName, et.LName 
            FROM leavetable lt
            JOIN employeedetail et ON lt.EmployeeID = et.EmployeeID
            WHERE lt.StartDate >= ? AND lt.EndDate <= ?
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);

    $alertMessage = ""; // Default value

    if ($stmt) {
        $stmt->bind_param("ssii", $fromDate, $toDate, $startIndex, $recordsPerPage);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            $alertMessage = "No leave request data found for the selected date range.";
        }
    } else {
        die("Error in SQL query: " . $conn->error);
    }
    
}

// Approve or decline leave request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leaveID = $_POST['leave_id'];        // Leave ID to identify the specific leave request
    $employeeID = $_POST['employee_id'];  // Employee ID to make sure we are updating the correct employee's leave
    $action = $_POST['action'];           // Action for approval or decline

    // Determine the status based on the action
    if ($action === 'approve') {
        $updateSql = "UPDATE leavetable SET Status = 'Approved' WHERE ID = ? AND EmployeeID = ?";
        $statusMessage = "Leave request approved successfully!";
    } elseif ($action === 'decline') {
        $updateSql = "UPDATE leavetable SET Status = 'Rejected' WHERE ID = ? AND EmployeeID = ?";
        $statusMessage = "Leave request rejected successfully!";
    } else {
        die("Error: Invalid action specified.");
    }

    // Prepare and execute the update query
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt) {
        $updateStmt->bind_param("ii", $leaveID, $employeeID);
        if ($updateStmt->execute()) {
            // If approved, update the EmployeeLeaveBalance table
            if ($action === 'approve') {
                // Fetch the leave details
                $leaveDetailsSql = "SELECT LeaveType, Duration FROM leavetable WHERE ID = ?";
                $leaveDetailsStmt = $conn->prepare($leaveDetailsSql);
                if ($leaveDetailsStmt) {
                    $leaveDetailsStmt->bind_param("i", $leaveID);
                    $leaveDetailsStmt->execute();
                    $leaveDetailsResult = $leaveDetailsStmt->get_result();
                    $leaveDetails = $leaveDetailsResult->fetch_assoc();
                    $leaveDetailsStmt->close();

                    if ($leaveDetails) {
                        $leaveType = $leaveDetails['LeaveType'];
                        $usedLeave = $leaveDetails['Duration'];

                        // Default leave balances
                        $defaultLeaveBalances = [
                            'Sick Leave' => 10,
                            'Maternity Leave' => 60,
                            'Paternity Leave' => 7,
                            'Emergency Leave' => 7
                        ];

                        $totalLeave = isset($defaultLeaveBalances[$leaveType]) ? $defaultLeaveBalances[$leaveType] : 0;

                        // Update the EmployeeLeaveBalance table
                        $balanceUpdateSql = "INSERT INTO EmployeeLeaveBalance (EmployeeID, LeaveType, TotalLeave, UsedLeave, RemainingLeave)
                                             VALUES (?, ?, ?, ?, ?)
                                             ON DUPLICATE KEY UPDATE 
                                             UsedLeave = UsedLeave + VALUES(UsedLeave),
                                             RemainingLeave = TotalLeave - UsedLeave";
                        $balanceUpdateStmt = $conn->prepare($balanceUpdateSql);
                        if ($balanceUpdateStmt) {
                            $remainingLeave = $totalLeave - $usedLeave;
                            $balanceUpdateStmt->bind_param("ssiii", $employeeID, $leaveType, $totalLeave, $usedLeave, $remainingLeave);
                            $balanceUpdateStmt->execute();
                            $balanceUpdateStmt->close();
                        } else {
                            $_SESSION['error_message'] = "Error updating leave balance: " . $conn->error;
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Error fetching leave details: " . $conn->error;
                }
            }

            // Success, redirect with a success message
            $_SESSION['success_message'] = $statusMessage;
        } else {
            // Failure, set error message
            $_SESSION['error_message'] = "Error updating leave request.";
        }
        $updateStmt->close();
    } else {
        die("Error in SQL query: " . $conn->error);
    }

    // Redirect to the same page to refresh and display updated status
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// Status Filter
if (isset($_GET['Status']) && !empty($_GET['Status'])) {
    $statusFilter = $_GET['Status'];

    
    $startIndex = 0;  // Example start index, modify based on your pagination logic
    $recordsPerPage = 10;  // Example records per page, modify as needed

    $sql = "SELECT lt.*, et.FName, et.LName 
            FROM leavetable lt
            JOIN employeedetail et ON lt.EmployeeID = et.EmployeeID
            WHERE lt.Status = ?
            LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sii", $statusFilter, $startIndex, $recordsPerPage);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        die("Error in SQL query: " . $conn->error);
    }
}

// Fetch pending leave requests with notification status
$sql = "SELECT l.ID, l.Status, l.AppliedDate, ed.FName, ed.LName, ns.HRIsRead 
    FROM leavetable l
    JOIN employeedetail ed ON l.EmployeeID = ed.EmployeeID
    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
    WHERE l.Status = 'Pending'
    ORDER BY l.AppliedDate DESC
    LIMIT 5";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->execute();
    $notifResult = $stmt->get_result();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Mark notifications as read when the dropdown is opened
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markAllRead'])) {
    // Update notifications status to 'read' (HRIsRead = 1)
    $updateSql = "UPDATE leave_notification_status 
                  SET HRIsRead = 1 
                  WHERE LeaveID IN (SELECT ID FROM leavetable WHERE Status = 'Pending')";
    $updateStmt = $conn->prepare($updateSql);

    if ($updateStmt) {
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        die("Error in SQL query: {$conn->error}");
    }
}

// Count unread notifications
 $unreadQuery = "SELECT COUNT(*) as unreadCount 
FROM leave_notification_status ns
JOIN leavetable l ON ns.LeaveID = l.ID
WHERE ns.HRIsRead = 0 AND l.Status = 'Pending'";
$unreadResult = $conn->query($unreadQuery);
$unreadCount = $unreadResult ? $unreadResult->fetch_assoc()['unreadCount'] : 0;
//echo $unreadCount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="../src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/HRLeaveApplication.css">
    <script src="../includes/adminDashboard.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>TCU EMS</title>
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
            <span class="profile-role">HR</span>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="hrDashboard.php" class="menu-item">
            <span class="icon icon-dashboard"></span> Dashboard
        </a>

        <a href="HRLeaveApplication.php" class="menu-item active">
        <span class="icon icon-leave"></span> Leave Management
        </a>

        <a href="HRLeaveReport.php" class="menu-item">
            <span class="icon icon-schedule"></span> Leave Report
        </a>  

        <a href="HRsettings.php" class="menu-item">
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
        <h1 class="header-title">Leave Application</h1>
        <div class="header-actions">

            <!-- Search Container -->
            <div class="search-container">
    <span class="icon icon-search"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Search anything here" onkeyup="searchTable()">
</div>

<!-- <a href="HRsettings.php"><span class="icon icon-settings-header header-icon"></span></a> -->
          <!-- Notification -->
          <div class="notif-dropdown">
                <span class="icon icon-notification header-icon" id="notificationDropdown"></span>
                <span id="unreadCount" class="unread-count"></span> <!-- Unread count -->
                <div class="dropdown-content notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read" id="markAllRead" onclick="markAllNotificationsRead()">Mark all as read</span>
                        <script>
                            function markAllNotificationsRead() {
                                fetch(window.location.href, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'markAllRead=1'
                                }).then(response => {
                                    if (response.ok) {
                                        location.reload();
                                    } else {
                                        console.error('Failed to mark notifications as read');
                                    }
                                }).catch(error => console.error('Error:', error));
                            }
                        </script>
                    </div>
                    <?php 
                    $hasPendingNotifications = false;
                    if ($notifResult->num_rows > 0): 
                    ?>
                        <?php while ($notifRow = $notifResult->fetch_assoc()): ?>
                            <?php if ($notifRow['Status'] === 'Pending'): ?> <!-- Include only pending notifications -->
                                <?php $hasPendingNotifications = true; ?>
                                <div class="notification-item <?= $notifRow['HRIsRead'] ? '' : 'unread'; ?>">
                                    <div class="notification-icon notification-icon-pending">
                                        <i class="fa fa-hourglass-half"></i> <!-- Hourglass icon for pending -->
                                    </div>
                                    <div class="notification-content">
                                        <p>
                                            Pending leave request from <?= htmlspecialchars($notifRow['FName'] . ' ' . $notifRow['LName']); ?>.
                                        </p>
                                        <span class="notification-time">
                                            Applied on: <?= date('F j, Y', strtotime($notifRow['AppliedDate'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <?php if (!$hasPendingNotifications): ?>
                        <div class="notification-item">
                            <div class="notification-content">
                                <p>No pending leave requests</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="HRLeaveApplication.php?Status=Pending" class="view-all-notifications">View all pending requests</a>
                </div>
            </div>
            <!-- Profile Dropdown -->
            <div class="Profile-dropdown">
                <button class="Pdropdown-button">
                    <img src="<?= $profilePicture; ?>" alt="Profile" class="profile-icon">
                </button>
                <div class="Pdropdown-content">
                    <a href="HRsettings.php">Settings</a>
                    <a href="../index.html">Log out</a>
                </div>
            </div>
        </div>
    </div>
    <!-- End of the Header -->

<!--TABLE-->
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
            <select id="filterStatus">
                <option value="">All Status</option>
                <option value="Pending" <?= isset($_GET['Status']) && $_GET['Status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Approved" <?= isset($_GET['Status']) && $_GET['Status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= isset($_GET['Status']) && $_GET['Status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>

            <button id="applyFilter" class="filter-btn">Apply Filter</button>

            
        </div>
    </div>
</div>

<!-- END FILTER CONTROL -->
    <!--TABLE-->
    <div class="schedule-table-container">
        <table class="schedule-table" id="dataTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Duration(s)</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>File Attachment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['FName'] . ' ' . $row['LName']) ?></td>
                    <td><?= htmlspecialchars($row['Duration']) ?> Day(s)</td>
                    <td><?= htmlspecialchars(date("F j, Y", strtotime($row['StartDate']))) ?></td>
                    <td><?= htmlspecialchars(date("F j, Y", strtotime($row['EndDate']))) ?></td>
                    <td><?= htmlspecialchars($row['LeaveType']) ?></td>
                    <td><?= htmlspecialchars($row['Reason']) ?></td>
                    <td>
    <?php if (!empty($row['Attachment'])): ?>
        <a href="../uploads/<?= htmlspecialchars($row['Attachment']); ?>" target="_blank" class="file-viewer-link">View File</a>
    <?php else: ?>
        <span class="no-attachment">No Attachment</span>
    <?php endif; ?>
</td>

                    <td>
                        <?php 
                            $status = isset($row['Status']) ? $row['Status'] : 'N/A';
                            $statusClass = ($status == 'Approved') ? 'status-approved' :
                                           (($status == 'Pending') ? 'status-pending' :
                                           (($status == 'Rejected') ? 'status-rejected' : 'status-unknown'));
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                    </td>
                    <td>
    <?php if ($row['Status'] == 'Pending'): ?>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" >
            <input type="hidden" name="leave_id" value="<?= htmlspecialchars($row['ID']); ?>">  <!-- Changed LeaveID to ID -->
            <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['EmployeeID']); ?>">
            <button type="submit" name="action" value="approve" class="action-btn approve-btn">Approve</button>
            <button type="submit" name="action" value="decline" class="action-btn decline-btn">Decline</button>
        </form>
    <?php else: ?>
        <span class="status-finalized <?= strtolower($row['Status']); ?>">
    <?= htmlspecialchars($row['Status']); ?>
</span>


    <?php endif; ?>
</td>

                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9">No leave records found.</td></tr>
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
<!-- END OF TABLE -->
</div>
<script src="../includes/HRLeaveApplication.js"></script>
</body>
</html>
