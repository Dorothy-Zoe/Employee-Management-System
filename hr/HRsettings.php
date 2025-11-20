<?php
// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Path to autoload.php from PHPMailer

session_start();
include '../includes/db.php'; // Ensure database connection



if (!isset($_SESSION['hr_user'])) {
    die("Error: HR ID not found in session.");
}

$UserID = $_SESSION['hr_user'];

// Fetch employee details
$sql = "SELECT FName, LName, Email, PhoneNumber, ProfilePicture, Password FROM hrtbl WHERE UserID = ?";
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

// Validate fetched employee data sidebar and header
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . $employee['ProfilePicture'] : "../uploads/default.jpg";
$firstName = isset($employee['FName']) ? htmlspecialchars($employee['FName']) : 'Unknown';
$lastName = isset($employee['LName']) ? htmlspecialchars($employee['LName']) : 'User';
$email = isset($employee['Email']) ? htmlspecialchars($employee['Email']) : 'N/A';
$phoneNumber = isset($employee['PhoneNumber']) ? htmlspecialchars($employee['PhoneNumber']) : 'N/A';
$dbPassword = $employee['Password'] ?? '';
// End of sidebar and header validation

// Handle password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_new_password'];

    if (!password_verify($currentPassword, $dbPassword)) {
        $errorMsg = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $errorMsg = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "New passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateSql = "UPDATE hrtbl SET Password = ? WHERE UserID = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param("ss", $hashedPassword, $UserID);
            if ($updateStmt->execute()) {
                $successMsg = "Password change successful. Your new password has been saved.";
            } else {
                $errorMsg = "Error updating password.";
            }
            $updateStmt->close();
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['contact_number'])) {
    $updatedEmail = trim($_POST['email']);
    $updatedContactNumber = trim($_POST['contact_number']);

    // Validate email
    if (!filter_var($updatedEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $updatedContactNumber)) {
        $errorMsg = "Invalid contact number format. It should be between 10 to 15 digits.";
    } else {
        // Update the database
        $updateProfileSql = "UPDATE hrtbl SET Email = ?, PhoneNumber = ? WHERE UserID = ?";
        $updateProfileStmt = $conn->prepare($updateProfileSql);

        if ($updateProfileStmt) {
            $updateProfileStmt->bind_param("sss", $updatedEmail, $updatedContactNumber, $UserID);
            if ($updateProfileStmt->execute()) {
                $successMsg = "Profile updated successfully!";
                // Update session variables if needed
                $email = htmlspecialchars($updatedEmail);
                $phoneNumber = htmlspecialchars($updatedContactNumber);
            } else {
                $errorMsg = "Error updating profile.";
            }
            $updateProfileStmt->close();
        } else {
            $errorMsg = "Error preparing SQL statement.";
        }
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


$show_code_verification_modal = false; // Flag for code verification modal
$show_reset_password_modal = false; // Flag for reset password modal
$reset_code = ''; // Variable to store the reset code

// Handle forgot password
if (isset($_POST['forgot_password_email'])) {
    $email = trim($_POST['forgot_password_email']);
    if (!empty($email)) {
        // Check if email exists in the database
        $sql = "SELECT * FROM hrtbl WHERE Email = ?";
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
            $update_sql = "UPDATE hrtbl SET reset_password_code = ? WHERE Email = ?";
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
                $success_message = "A reset code has been sent to your email. Please check your inbox.";
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
            $update_sql = "UPDATE hrtbl SET Password = ?, reset_password_code = NULL WHERE reset_password_code = ?";
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

<!-- Display messages dynamically -->
<?php if (!empty($successMsg)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.color = "green";
            messageBox.innerHTML = "<?php echo $successMsg; ?>";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 5000);
        });
    </script>
<?php } elseif (!empty($errorMsg)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.style.color = "red";
            messageBox.innerHTML = "<?php echo $errorMsg; ?>";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 5000);
        });
    </script>
<?php } ?>

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
    <link rel="icon" type="image/x-icon" href="../src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/dashboard.css">
    <link rel="stylesheet" href="../includes/HRSettings.css">


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

        <a href="HRLeaveApplication.php" class="menu-item">
        <span class="icon icon-leave"></span> Leave Management
        </a>
        <a href="HRLeaveReport.php" class="menu-item">
            <span class="icon icon-schedule"></span> Leave Report
        </a>  
       
        <a href="HRsettings.php" class="menu-item active">
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
        <h1 class="header-title">Settings</h1>
        <div class="header-actions">
            <!-- Search Container
            <div class="search-container">
    <span class="icon icon-search"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Search anything here" onkeyup="searchTable()">
</div> -->
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

<!--SETTINGS-->
<div class="settings-container">
            <div class="settings-card">
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab" id="account-tab">Account Settings</div>
                    <div class="tab" id="notification-tab">Notification Settings</div>
                    <div class="tab" id="privacy-tab">Privacy & Settings</div>
                </div>
                <!-- End of Tabs -->
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

                    </div>
                    
                    <form method="POST" id="profileForm">
                        <div class="form-row">
                            <div class="form-column">
                                <label class="setting-label">Email Address</label>
                                <input type="email" class="input-field" name="email" value="<?php echo $email; ?>" required>
                            </div>
                            <div class="form-column">
                                <label class="setting-label">Contact Number</label>
                                <input type="tel" class="input-field" name="contact_number" value="<?php echo $phoneNumber; ?>" required>
                            </div>
                        </div>
                        
                        <div class="button-container">
                            <button type="submit" class="edit-profile-button">
                                Edit Profile
                            </button>
                        </div>
                    </form>

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
    <div class="modal-content">
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
        <div class="modal-content">
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
        <div class="modal-content">
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
                    

                    
                    <!-- Save Button -->
                    <div class="button-container">
                        <button type="button" class="save-button">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

</div>
<script src="../includes/HRSettings.js"></script>
<script src="../includes/HRDashboard.js"></script>
</body>
</html>
