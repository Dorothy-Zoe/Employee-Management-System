<?php
// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Path to autoload.php from PHPMailer

session_start();
include '../includes/db.php'; // Ensure database connection

if (!isset($_SESSION['employee_user'])) {
    die("Error: Employee ID not found in session.");
}

$EmployeeID = $_SESSION['employee_user'];

// Fetch employee details (including password)
$sql = "SELECT FName, LName, Email, MobileNumber, ProfilePicture, EmployeeID, Department, Password FROM EmployeeDetail WHERE EmployeeID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $EmployeeID); // EmployeeID is a string, so use "s"
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error: " . $conn->error);
}

// Validate fetched employee data
$fullName = isset($employee['FName']) && isset($employee['LName']) ? htmlspecialchars($employee['FName'] . " " . $employee['LName']) : 'Unknown User';
$firstName = isset($employee['FName']) ? htmlspecialchars($employee['FName']) : 'Unknown';
$lastName = isset($employee['LName']) ? htmlspecialchars($employee['LName']) : 'User';
$email = isset($employee['Email']) ? htmlspecialchars($employee['Email']) : 'N/A';
$mobileNumber = isset($employee['MobileNumber']) ? htmlspecialchars($employee['MobileNumber']) : 'N/A';
$department = isset($employee['Department']) ? htmlspecialchars($employee['Department']) : 'N/A';
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . htmlspecialchars($employee['ProfilePicture']) : "../src/default-profile.png";
$dbPassword = isset($employee['Password']) ? $employee['Password'] : '';

// Fetch current password
$sql = "SELECT Password FROM EmployeeDetail WHERE EmployeeID = ?";
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

if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_new_password'];

    if (!password_verify($currentPassword, $employee['Password'])) {
        $errorMsg = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $errorMsg = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "New passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateSql = "UPDATE EmployeeDetail SET Password = ? WHERE EmployeeID = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param("ss", $hashedPassword, $EmployeeID);
            if ($updateStmt->execute()) {
                $successMsg = "Password change successful. Your new password has been saved.";
            } else {
                $errorMsg = "Error updating password.";
            }
            $updateStmt->close();
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

$show_code_verification_modal = false; // Flag for code verification modal
$show_reset_password_modal = false; // Flag for reset password modal
$reset_code = ''; // Variable to store the reset code

// Handle forgot password
if (isset($_POST['forgot_password_email'])) {
    $email = trim($_POST['forgot_password_email']);
    if (!empty($email)) {
        // Check if email exists in the database
        $sql = "SELECT * FROM employeedetail WHERE Email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            
            // Generate random code
            $random_code = rand(100000, 999999);

            // Store the reset code in the session
            $_SESSION['reset_code'] = $random_code;

            // Save the reset code in the database
            $update_sql = "UPDATE employeedetail SET reset_password_code = ? WHERE Email = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ss", $random_code, $email);
            $stmt->execute();

            // Send reset code to user's email using PHPMailer
            $mail = new PHPMailer(true); // Create a new PHPMailer instance
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to Gmail
                $mail->SMTPAuth = true;
                $mail->Username = 'klarerivera25@gmail.com'; // Your Gmail address
                $mail->Password = 'bztg uiur xzho wslv'; // Your Gmail app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587; // TCP port to connect to

                // Recipients
                $mail->setFrom('no-reply@tcu.edu.ph', 'TCU EMS Portal'); // Sender's email and name
                $mail->addAddress($email); // Add the user's email

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Code';

                // Body with personalized message
                $mail->Body = "
                <p><strong>Dear " . $row['FName'] . " " . $row['LName'] . ",</strong></p>
                <p>You recently requested to reset your password.</p>
                <p>Your password reset code is: <strong>" . $random_code . "</strong></p>
                <p>Please enter this code in the password reset page to proceed. For your security, this code will expire in 5 minutes.</p>
                <p>If you did not request this change, please disregard this message.</p>
                <p>Thank you,<br>
                TCU EMS Support Team</p>
                ";

                $mail->send();
                // $success_message = "A reset code has been sent to your email. Please check your inbox.";
                // Flag to show the verification modal
                $_SESSION['show_code_verification_modal'] = true;
            } catch (Exception $e) {
                $error_message = "Failed to send email. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error_message = "No account found with that email address.";
        }
    } else {
        $error_message = "Please enter a valid email address.";
    }
}


// Handle code verification
if (isset($_POST['verification_code'])) {
    $verification_code = trim($_POST['verification_code']);
    $reset_password_code = $_SESSION['reset_code']; // Get the reset code from the session

    if (!empty($verification_code) && $verification_code == $reset_password_code) {
        // Code verified, show Reset Password modal
        $_SESSION['show_reset_password_modal'] = true;  // Trigger showing reset password modal
    } else {
        $error_message = "Invalid verification code.";
    }
}



// Handle reset password (no need to verify the reset code again)
if (isset($_POST['new_password']) && isset($_POST['confirm_new_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_new_password = trim($_POST['confirm_new_password']);
    
    if (!empty($new_password) && !empty($confirm_new_password)) {
        if ($new_password === $confirm_new_password) {
            // Update the password without verifying the code again, since it's already verified
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE employeedetail SET Password = ?, reset_password_code = NULL WHERE reset_password_code = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ss", $hashed_password, $_SESSION['reset_code']);
            $stmt->execute();

            // Clear the session reset code after successful password reset
            unset($_SESSION['reset_code']); // Clear the reset code from the session

            $success_message = "Password change successful. Your new password has been saved.";
        } else {
            $error_message = "Passwords do not match.";
        }
    } else {
        $error_message = "Please enter both the new password and confirm password.";
    }
}
?>




<!-- For Forgot Password Process -->
<?php if (isset($success_message) && !empty($success_message)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.color = "green";
            messageBox.innerHTML = "<?php echo $success_message; ?>";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 5000); // Hide after 5 seconds
        });
    </script>
<?php } elseif (isset($error_message) && !empty($error_message)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.color = "red";
            messageBox.innerHTML = "<?php echo $error_message; ?>";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 5000); // Hide after 5 seconds
        });
    </script>
<?php } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../src/favicon-logo.ico">
    <title>Taguig City University - Settings</title>
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/settings.css">
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
            <a href="leaveManagement.php" class="menu-item">
                <span class="icon icon-leave"></span>
                Leave Management
               
            </a>
            <a href="settings.php" class="menu-item active">
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

 <!--SETTINGS-->
        <div class="settings-container">
            <div class="settings-card">
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab" id="account-tab">Account Settings</div>
                    <div class="tab" id="notification-tab">Notification Settings</div>
                    <div class="tab" id="privacy-tab">Privacy & Settings</div>
                </div>
                 <!-- Display message for Forgat Password Process -->
                    <div id="message-box" class="message-box" style="display: none; position: fixed; 
                    top: 20%; left: 50%; transform: translateX(-50%); background-color: #f8f9fa; 
                    padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); font-weight: bold;">
                    </div>
                <!-- End of Display message for Forgat Password -->


                <!-- Account Settings Content -->
                <div class="settings-content" id="account-settings">
                    <h2 class="setting-title">Account Settings</h2>
                    
                    <div class="form-row">
                        <div class="form-column">
                            <label class="setting-label">Full Name</label>
                            <div class="form-row" style="margin-top: 8px;">
                            <div class="form-column">
                        <input type="text" class="input-field" name="first_name" value="<?php echo $firstName; ?>" readonly>
                    </div>
                    <div class="form-column">
                        <input type="text" class="input-field" name="last_name" value="<?php echo $lastName; ?>" readonly>
                    </div>

                            </div>
                        </div>
                        <div class="form-column" style="margin-top: 8px;">
                            <label class="setting-label">Email Address</label>
                            <input type="email" class="input-field" name="email" value="<?php echo $email; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
            <div class="form-column">
                <label class="setting-label">Contact Number</label>
                <input type="tel" class="input-field" name="contact_number" value="<?php echo $mobileNumber; ?>"readonly>
            </div>
            <div class="form-column">
                <label class="setting-label">Employee ID</label>
                <input type="text" class="input-field" name="employee_id" value="<?php echo $EmployeeID; ?>" readonly>
            </div>
            <div class="form-column">
                <label class="setting-label">Department</label>
                <input type="text" class="input-field" name="department" value="<?php echo $department; ?>" readonly>
            </div>
        </div>
                    
        <div class="button-container">
            <button type="button" class="edit-profile-button" onclick="window.location.href='editProfile.php';">
                Edit Profile
            </button>
        </div>

                    <!--CHANGE PASSWORD-->
                    <div class="password-section">
    <h2 class="setting-label">Change Password</h2>

    <form method="POST" id="passwordForm">
        <div class="form-row">
            <div class="form-column" style="padding-top:10px; font-size: 20px;">
                <label class="setting-description">Current Password</label>
                <input type="password" class="input-field" name="current_password" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-column">
                <label class="setting-description">New Password</label>
                <input type="password" class="input-field" name="new_password" id="new_password" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-column">
                <label class="setting-description">Confirm New Password</label>
                <input type="password" class="input-field" name="confirm_new_password" id="confirm_new_password" required>
            </div>
        </div>

<!-- Forgot Password Link --> 
<div class="redirect-text">
    <p><a href="#" id="forgotPassword">Forgot Password?</a></p>
</div>


        <div class="button-container">
            <button type="submit" class="save-button" id="save-password" name="change_password">Save Changes</button>
        </div>
    </form>
    </div>
 </div>
 <!-- End of Account Settings -->
                
<!-- Forgot Password Modal (Hidden by Default) -->
<div id="ForgotPasswordModal" class="FPmodal">
    <div class="fmodal-content">
        <span class="close">&times;</span>
        <h2 class="modalTitle">Forgot Password?</h2>
        <p>Enter your email to reset your password.</p>
        <form action="" method="POST" id="forgotPasswordForm">
            <input type="email" id="emailInput" name="forgot_password_email" placeholder="Enter your email" required>
            <button type="submit" id="submitForgotPassword">Submit</button>
        </form>
    </div>
</div>

<!-- Modal for verifying reset code -->
<?php if (isset($_SESSION['show_code_verification_modal']) && $_SESSION['show_code_verification_modal']) : ?>
    <div id="VerificationModal" class="FPmodal">
        <div class="fmodal-content">
            <span class="close">&times;</span>
            <h2 class="modalTitle">Verify Your Reset Code</h2>
            <form action="" method="POST">
                <p for="verification_code">Enter the code sent to your email</p>
                <input type="text" id="verification_code" name="verification_code" placeholder="Enter the code" required>
                <input type="hidden" name="reset_password_code" value="<?= $_SESSION['reset_code'] ?>"> <!-- Pass the reset code -->
                <button type="submit" id="submitVerifyCode">Verify Code</button>
            </form>
        </div>
    </div>
<?php endif; ?>


<!-- Reset Password Form -->
<?php if (isset($_SESSION['show_reset_password_modal']) && $_SESSION['show_reset_password_modal']) : ?>
    <div id="ResetPasswordModal" class="FPmodal">
        <div class="fmodal-content">
            <span class="close" onclick="closeModal('ResetPasswordModal')">&times;</span>
            <h2 class="modalTitle">Reset Your Password</h2>
            <form action="" method="POST">
                <p class="resetpassword" for="new_password">New Password</p>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>

                <p class="resetpassword" for="confirm_new_password">Confirm New Password</p>
                <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="Confirm new password" required>

                <button type="submit" id="submitResetPassword">Reset Password</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<script>
   window.onload = function() {
    // Check if the verification modal flag is set
    <?php if (isset($_SESSION['show_code_verification_modal']) && $_SESSION['show_code_verification_modal']) : ?>
        // Show the verification modal if the flag is true
        document.getElementById('VerificationModal').style.display = 'flex';
        // Unset the session variable so it doesn't show again on refresh
        <?php unset($_SESSION['show_code_verification_modal']); ?>
    <?php endif; ?>

    // Check if the reset password modal flag is set
    <?php if (isset($_SESSION['show_reset_password_modal']) && $_SESSION['show_reset_password_modal']) : ?>
        // Show the reset password modal if the flag is true
        document.getElementById('ResetPasswordModal').style.display = 'flex';
        // Unset the session variable so it doesn't show again on refresh
        <?php unset($_SESSION['show_reset_password_modal']); ?>
    <?php endif; ?>

    // Close the modal when the close button is clicked
    var closeBtns = document.querySelectorAll('.close');
    closeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = this.closest('.FPmodal');
            modal.style.display = 'none';
        });
    });

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target.classList.contains('FPmodal')) {
            event.target.style.display = 'none';
        }
    }
}
 
</script>


                <!-- Notification Settings Content -->
                <div class="settings-content" id="notification-settings">
                    <h2 class="setting-title">Notification Settings</h2>
                    
                    <!-- Email Notifications -->
                    <div class="setting-item">
                        <div class="setting-item-header">
                            <label class="setting-label">Receive notifications in my email</label>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p class="setting-description">
                            Get notified about schedule updates, leave approvals, and important announcements in your email inbox.
                        </p>
                    </div>
                    
                    <!-- System Alerts -->
                    <div class="setting-item">
                        <div class="setting-item-header">
                            <label class="setting-label">Receive system alerts</label>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p class="setting-description">
                            Enable in-app pop-up notifications for real-time updates on schedule changes, leave requests, and university announcements.
                        </p>
                    </div>
                    
                    <!-- Additional Options -->
                    <div class="preferences">
                        <h3 class="setting-label" style="margin-bottom: 16px;">Notification Preferences</h3>
                        
                        <div class="preference-item">
                            <input type="checkbox" id="schedule-updates" checked>
                            <label for="schedule-updates">Schedule updates</label>
                        </div>
                        <div class="preference-item">
                            <input type="checkbox" id="leave-requests" checked>
                            <label for="leave-requests">Leave request status</label>
                        </div>
                        <div class="preference-item">
                            <input type="checkbox" id="system-maintenance" checked>
                            <label for="system-maintenance">System maintenance alerts</label>
                        </div>
                        <div class="preference-item">
                            <input type="checkbox" id="university-announcements" checked>
                            <label for="university-announcements">University announcements</label>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="button-container">
                        <button type="button" class="save-button">Save Changes</button>
                    </div>
                </div>
                
                <!-- Privacy Settings Content -->
                <div class="settings-content" id="privacy-settings">
                    <h2 class="setting-title">Privacy & Settings</h2>
                    
                    <!-- <div class="privacy-title">Privacy & Security</div> -->
                    
                    <!-- Two-Factor Authentication -->
                    <div class="setting-item">
                        <div class="setting-item-header">
                            <label class="setting-label">Two-Factor Authentication</label>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p class="setting-description">
                            Enable two-factor authentication
                        </p>
                    </div>
                    
                    <!-- Data Sharing -->
                    <div class="setting-item">
                        <div class="setting-item-header">
                            <label class="setting-label">Data Sharing</label>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p class="setting-description">
                            Allow system to share your data with other university services
                        </p>
                    </div>
                    
                    <!-- Activity Logs -->
                    <div class="setting-item">
                        <div class="setting-item-header">
                            <label class="setting-label">Activity Logs</label>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p class="setting-description">
                            Keep records of your login activities and system usage
                        </p>
                    </div>
                    
                    <!-- Additional Privacy Options -->
                    <div class="preferences">
                        <h3 class="setting-label" style="margin-bottom: 16px;">Profile Visibility</h3>
                        
                        <div class="preference-item">
                            <input type="checkbox" id="show-email" checked>
                            <label for="show-email">Show email address to other faculty members</label>
                        </div>
                        <div class="preference-item">
                            <input type="checkbox" id="show-phone" checked>
                            <label for="show-phone">Show contact number to other faculty members</label>
                        </div>
                        <div class="preference-item">
                            <input type="checkbox" id="show-schedule">
                            <label for="show-schedule">Allow students to view my office hours</label>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="button-container">
                        <button type="button" class="save-button">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
       
    </div>

    <script src="../includes/settings.js"></script>
    <script src="../includes/InstructorSchedule.js"></script>
    <script src="../includes/InstructorDashboard.js"></script>
</body>
</html>