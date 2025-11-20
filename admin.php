<?php
// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Path to autoload.php from PHPMailer

session_start();
include 'includes/db.php'; // Ensure database connection

// Default Modal Flags
$show_code_verification_modal = false;
$show_reset_password_modal = false;
$reset_code = '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if 'user_id' and 'password' are set in the $_POST array before accessing them
    if (isset($_POST['user_id']) && isset($_POST['password'])) {
        $user_id = trim($_POST['user_id']);
        $password = trim($_POST['password']);

        if (!empty($user_id) && !empty($password)) {
            $sql = "SELECT * FROM admintbl WHERE UserID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();

                // Check if passwords match (if stored as plain text)
                if (password_verify($password, $row['Password'])) {
                    $_SESSION['admin_user'] = $row['UserID'];
                    header("Location: adminDashboard.php"); // Redirect to Admin Dashboard
                    exit();
                } else {
                    $error = "Invalid User ID or Password.";
                }
            } else {
                $error = "Invalid User ID or Password.";
            }
        } else {
            $error = "All fields are required.";
        }
    } 
}


// Handle forgot password
if (isset($_POST['forgot_password_email'])) {
    $email = trim($_POST['forgot_password_email']);
    if (!empty($email)) {
        // Check if email exists in the database
        $sql = "SELECT * FROM admintbl WHERE Email = ?";
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
            $update_sql = "UPDATE admintbl SET reset_password_code = ? WHERE Email = ?";
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
                    <p style='color: #000;'><strong>Dear " . $row['FName'] . " " . $row['LName'] . ",</strong></p>
                    <p style='color: #000;'>You recently requested to reset your password.</p>
                    <p style='color: #000;'>Your password reset code is: <strong>" . $random_code . "</strong></p>
                    <p style='color: #000;'>Please enter this code in the password reset page to proceed. For your security, this code will expire in 5 minutes.</p>
                    <p style='color: #000;'>If you did not request this change, please disregard this message.</p>
                    <p style='color: #000;'>Thank you,<br>
                    TCU EMS Support Team</p>
                ";

                $mail->send();
                $success_message = "A reset code has been sent to your email. Please check your inbox.";
                $show_code_verification_modal = true;  // Show code verification modal
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
        $show_reset_password_modal = true;  // Trigger showing reset password modal
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
            $update_sql = "UPDATE admintbl SET Password = ?, reset_password_code = NULL WHERE reset_password_code = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ss", $hashed_password, $_SESSION['reset_code']);
            $stmt->execute();

            // Clear the session reset code after successful password reset
            unset($_SESSION['reset_code']); // Clear the reset code from the session

            $success_message = "Your password has been reset successfully.";
        } else {
            $error_message = "Passwords do not match.";
        }
    } else {
        $error_message = "Please enter both the new password and confirm password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="src/favicon-logo.ico">
    <title>Login - TCU Portal</title>
    <link rel="stylesheet" href="includes/admin.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
        // JavaScript to hide success and error messages after 5 seconds
        setTimeout(function() {
            var successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.style.display = 'none';
            }

            var errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        }, 5000);

                // Function to show the modal
        function showModal(modalId) {
            var modal = document.getElementById(modalId);
            modal.style.display = "flex";
        }

        // Function to hide the modal
        function closeModal(modalId) {
            var modal = document.getElementById(modalId);
            modal.style.display = "none";
        }

        // Close the modal when the close button is clicked
        window.onload = function () {
            var closeBtns = document.querySelectorAll('.close');
            closeBtns.forEach(function(btn) {
                btn.addEventListener('click', function () {
                    closeModal(this.closest('.FPmodal').id);
                });
            })

            // Show the modals based on PHP condition
            <?php if (isset($show_code_verification_modal) && $show_code_verification_modal) : ?>
                showModal('VerificationModal'); // Show the verification modal
            <?php endif; ?>

            <?php if (isset($show_reset_password_modal) && $show_reset_password_modal) : ?>
                showModal('ResetPasswordModal'); // Show the reset password modal
            <?php endif; ?>
        }

    </script>
</head>
<body>
    <img src="src/tcu-bg.png" alt="Background Image" class="bg-image">
    <div class="overlay"></div>

    <div class="container">
        <!-- Home Icon -->
        <a href="index.html" class="home-icon">
        <i class="fas fa-home"></i>
        </a>


        <img src="src/tcu-logo.png" alt="Taguig City University Logo" class="logo">
        <h2>Taguig City University</h2>
        <h3 id="role-text">Administrator</h3>

        <!-- Display error message if login fails -->
        <?php if (isset($error)) : ?>
          <p id="errorMessage" style="color: red; font-weight: bold;"><?= $error ?></p>
         <?php endif; ?>

        <!-- Display success message for reset password -->
        <?php if (isset($success_message)) : ?>
            <p id="successMessage" style="color: #98fb98; font-weight: bold;"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (isset($error_message)) : ?>
            <p id="errorMessage" style="color: red; font-weight: bold;"><?= $error_message ?></p>
        <?php endif; ?>

        <form action="admin.php" method="POST">
            <label for="user_id">User ID</label>
            <input type="text" name="user_id" id="user_id" placeholder="User ID" required>

            <label for="password">Password</label>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <img src="src/eye-closed.png" id="toggle-password" class="toggle-password" onclick="togglePassword()" alt="Show Password">
            </div>

            <button type="submit">Login</button>
        </form>

            <!-- Forgot Password Link -->
        <div class="redirect-text">
            <p><a href="#" id="forgotPassword">Forgot Password?</a></p>
        </div>

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
        <?php if (isset($show_code_verification_modal) && $show_code_verification_modal) : ?>
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
        <?php if (isset($show_reset_password_modal) && $show_reset_password_modal) : ?>
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

</div>
    <script src="includes/admin.js"></script>
</body>
</html>
