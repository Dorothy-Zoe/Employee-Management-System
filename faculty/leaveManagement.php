<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['employee_user'])) {
    die("Error: Employee ID not found in session.");
}

$EmployeeID = $_SESSION['employee_user']; // Employee ID should now be a string

// Fetch employee details
$sql = "SELECT FName, LName, ProfilePicture FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $EmployeeID); // Changed "i" (integer) to "s" (string)
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error: {$conn->error}");
}

$fullName = isset($employee['FName']) ? htmlspecialchars($employee['FName']) : "Unknown";
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . htmlspecialchars($employee['ProfilePicture']) : "default_profile.png";


// Determine gender of the employee
$sql = "SELECT Gender FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $EmployeeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $employeeDetails = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error in SQL query: {$conn->error}");
}

$gender = isset($employeeDetails['Gender']) ? strtolower($employeeDetails['Gender']) : null;

// Set default leave balances based on gender
$defaultLeaveBalances = [
    'Sick Leave' => 10,
    'Emergency Leave' => 7,
];

if ($gender === 'female') {
    $defaultLeaveBalances['Maternity Leave'] = 60;
} elseif ($gender === 'male') {
    $defaultLeaveBalances['Paternity Leave'] = 7;
}

// Fetch existing leave balances
foreach ($defaultLeaveBalances as $type => $total) {
    if (!isset($leaveBalances[$type])) {
        $leaveBalances[$type] = ['used' => 0, 'total' => $total];
    }
}

// Fetch existing leave applications
$sql = "SELECT LeaveType, StartDate, EndDate, Duration, AppliedDate, Status, Reason, Attachment 
        FROM LeaveTable WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $EmployeeID); 
    $stmt->execute();
    $result = $stmt->get_result();
    $leaveApplications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Error in SQL query: " . $conn->error);
}

// Handle Leave Application Submission
$successMessage = $errorMessage = "";

if (isset($_POST['apply_leave'])) {
    $leaveType = $_POST['leave_type'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = $_POST['reason'];
    $status = "Pending"; // Default leave status

    // Check if the leave type is allowed for the employee's gender
    if (($gender === 'male' && !in_array($leaveType, ['Sick Leave', 'Paternity Leave', 'Emergency Leave'])) || 
        ($gender === 'female' && !in_array($leaveType, ['Sick Leave', 'Maternity Leave', 'Emergency Leave']))) {
        $errorMessage = "You are not allowed to apply for this type of leave.";
    } else {
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $duration = $endDateObj->diff($startDateObj)->days + 1;

        $availableLeave = $leaveBalances[$leaveType]['total'] ?? $defaultLeaveBalances[$leaveType];
        $usedLeave = $leaveBalances[$leaveType]['used'] ?? 0;

        if ($duration > ($availableLeave - $usedLeave)) {
            $errorMessage = "You do not have enough $leaveType balance.";
        } else {
            // Handle file upload
            $filePath = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = "../uploads/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . "_" . basename($_FILES['attachment']['name']);
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $filePath = "{$uploadDir}{$fileName}";
                $fileSize = $_FILES['attachment']['size'];

                $allowedTypes = ['pdf'];
                $maxFileSize = 20 * 1024 * 1024; // 20MB

                if (!in_array($fileType, $allowedTypes)) {
                    $errorMessage = "Only PDF files are allowed.";
                } elseif ($fileSize > $maxFileSize) {
                    $errorMessage = "File size exceeds 20MB limit.";
                } elseif (!move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
                    $errorMessage = "Error uploading file.";
                }
            } else {
                $filePath = NULL; // No file uploaded
            }

            if (empty($errorMessage)) {
                $appliedDate = date("Y-m-d");
                $conn->begin_transaction();

                try {
                    // Insert leave request
                    $sql = "INSERT INTO LeaveTable (EmployeeID, LeaveType, StartDate, EndDate, AppliedDate, Status, Reason, Attachment) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssss", $EmployeeID, $leaveType, $startDate, $endDate, $appliedDate, $status, $reason, $filePath);
                    $stmt->execute();
                    $leaveID = $stmt->insert_id; // Get the ID of the newly inserted leave
                    $stmt->close();

                    // Insert into leave_notification_status table
                    $notifSql = "INSERT INTO leave_notification_status (EmployeeID, LeaveID, IsRead,HRIsread,AdminIsRead) VALUES (?, ?, 0,0,0)";
                    $notifStmt = $conn->prepare($notifSql);
                    $notifStmt->bind_param("si", $EmployeeID, $leaveID);
                    $notifStmt->execute();
                    $notifStmt->close();

                    // Update leave balance only if status is approved
                    if ($status === "Approved") {
                        $sql = "INSERT INTO EmployeeLeaveBalance (EmployeeID, LeaveType, UsedLeave, TotalLeave)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE UsedLeave = UsedLeave + VALUES(UsedLeave)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssii", $EmployeeID, $leaveType, $duration, $availableLeave);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $successMessage = "Leave request submitted successfully!";
                    header("Refresh:3; url=leaveManagement.php");
                } catch (Exception $e) {
                    $conn->rollback();
                    $errorMessage = "Error processing request: " . $e->getMessage();
                }
            }
        }
    }
}

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
    <title>Taguig City University - Leave Management</title>
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/leavemanagement.css">
    <script src="../includes/InstructorLeave.js"></script>
    <!--For notification-->
    <link rel="stylesheet" href="../includes/InstructorSchedule.css">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!--SIDEBAR-->
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
            <a href="schedule.php" class="menu-item">
                <span class="icon icon-schedule"></span>
                Schedule
            </a>
            <a href="leaveManagement.php" class="menu-item active">
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
   <!--MAIN CONTENT-->
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
<!-- End of Notification Modal -->
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
   <!--APPLY LEAVE -->
        <div class="leave-form-container">
            <h2 class="leave-form-title">Apply for Leave</h2>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
    <div class="form-group">
        <label class="form-label">Leave Type</label>
        <select name="leave_type" class="form-control" required>
            <option value="">Select Leave Type</option>
            <?php foreach ($leaveBalances as $type => $data): ?>
                <option value="<?php echo htmlspecialchars($type); ?>">
                    <?php echo htmlspecialchars($type); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
    <label class="form-label">Start Date</label>
    <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d'); ?>">
</div>

<div class="form-group">
    <label class="form-label">End Date</label>
    <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d'); ?>">
</div>


    <div class="form-group">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control" rows="4" required></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">Attach Document (PDF only)</label>
        <input type="file" name="attachment" class="form-control" accept=".pdf">
    </div>

    <button type="submit" name="apply_leave" class="submit-btn">Submit Application</button>
</form>
      </div>
           
          <!--HISTORY-->
          <div class="leave-section">
                <div class="leave-header">
                    <h2 class="leave-title">Leave Applications History</h2>
                    <span class="icon icon-more options-icon"></span>
                </div>
                
                <table class="applications-table" id="dataTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Applied On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveApplications as $application): ?>
                            <tr>
    <td><?php echo isset($application['LeaveType']) ? htmlspecialchars($application['LeaveType']) : 'N/A'; ?></td>
    <td><?php echo isset($application['StartDate']) ? date('M d, Y', strtotime($application['StartDate'])) : 'N/A'; ?></td>
    <td><?php echo isset($application['EndDate']) ? date('M d, Y', strtotime($application['EndDate'])) : 'N/A'; ?></td>
    <td><?php echo isset($application['Reason']) ? htmlspecialchars($application['Reason']) : 'N/A'; ?></td>
    <td><?php echo isset($application['AppliedDate']) ? date('M d, Y', strtotime($application['AppliedDate'])) : 'N/A'; ?></td>
    <td>
        <?php 
            $status = isset($application['Status']) ? $application['Status'] : 'N/A';
            $statusClass = ($status == 'Approved') ? 'status-approved' :
                           (($status == 'Pending') ? 'status-pending' :
                           (($status == 'Rejected') ? 'status-rejected' : 'status-unknown'));
        ?>
        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
    </td>
</tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

    </div>
    <script src="../includes/InstructorSchedule.js"></script>
    <script src="../includes/InstructorDashboard.js"></script>
</body>
</html>