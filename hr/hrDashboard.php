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
$sql = "SELECT FName, LName, ProfilePicture FROM hrtbl WHERE UserID = ?";
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
$profilePicture = (!empty($employee['ProfilePicture'])) ? "../" . $employee['ProfilePicture'] : "../uploads/default.jpg";

// Fetch leave status counts
// Query to get the count of leave requests based on status
$query = "SELECT status, COUNT(*) as total FROM leavetable GROUP BY status";
$result = mysqli_query($conn, $query);

$leaveStatus = array('Pending' => 0, 'Approved' => 0, 'Rejected' => 0);

// Loop through the results to get the count for each status
while($row = mysqli_fetch_assoc($result)) {
    $leaveStatus[$row['status']] = $row['total'];
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

// Feedback Form Process
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $feedback_category = $_POST['feedback_category'];
    $page_or_module = $_POST['page_or_module'];
    $feedback_message = $_POST['feedback_message'];
    $priority_level = $_POST['priority_level'];
    $consent_for_follow_up = $_POST['consent_for_follow_up'];
    $star_rating = $_POST['star_rating'];

    // Handle file upload
    if ($_FILES['screenshot']['error'] == 0) {
        $file_name = $_FILES['screenshot']['name'];
        $file_tmp = $_FILES['screenshot']['tmp_name'];
        $file_path = "../uploads/" . $file_name;
        move_uploaded_file($file_tmp, $file_path);  // Save the file to the server
    } else {
        $file_path = null;
    }

    // If consent for follow-up is "Yes", fetch the user's email from EmployeeDetail
    if ($consent_for_follow_up == "Yes") {
        // Fetch user's email from hrtbl table
        $sql = "SELECT Email, FName, LName FROM hrtbl WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $UserID);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $user_email = $user['Email'];
            $user_name = $user['FName'] . ' ' . $user['LName'];
        } else {
            $user_email = 'User did not consent to be contacted';
            $user_name = 'Anonymous';
        }
    } else {
        $user_email = 'User did not consent to be contacted';  // No email if consent is not given
        $user_name = 'Anonymous';
    }

    // Insert feedback data into the database
    $sql = "INSERT INTO feedback (UserID, feedback_category, feedback_message, screenshot, priority_level, consent_for_follow_up, star_rating)
            VALUES ('$UserID', '$feedback_category', '$feedback_message', '$file_path', '$priority_level', '$consent_for_follow_up', '$star_rating')";

    if ($conn->query($sql) === TRUE) {
        // Fetch the created_at timestamp for the feedback
        $feedback_id = $conn->insert_id; // Get the last inserted feedback ID
        $sql = "SELECT created_at FROM feedback WHERE feedback_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedback = $result->fetch_assoc();
        $submitted_on = $feedback['created_at']; // Store the timestamp

        // Convert the timestamp to 12-hour format (with AM/PM)
        $submitted_on = date('F j, Y h:i A', strtotime($submitted_on)); // Month Day, Year, 12-hour format with AM/PM



        // Success message
        $success_message = "Feedback submitted successfully! Submitted on: " . $submitted_on;
        $message_class = "success-message";  // Set the success class
    } else {
        // Set error message if there's an issue
        $error_message = "Error: " . $conn->error;
        $message_class = "error-message";  // Set the error class
    }

    // Map star rating to text
    $rating_labels = [
        1 => "Poor",
        2 => "Fair",
        3 => "Good",
        4 => "Very Good",
        5 => "Excellent"
    ];
    $star_rating_text = isset($rating_labels[$star_rating]) ? $rating_labels[$star_rating] : "No rating provided";

    // Now send a professional email using PHPMailer
    $to = "klarerivera25@gmail.com";  // Support Gmail
    $subject = "New Feedback Submission - " . ucfirst($feedback_category);  // Include category in subject

    // Start building the message
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #000; }
            .email-content { margin: 20px; }
            .email-content p { font-size: 14px; line-height: 1.2; margin: 10px 0; text-decoration: none; color: #000;}
            .email-content .section-title { font-weight: bold; color: #004b8d; text-decoration: none; }
            .email-content span {text-decoration: none; }
            .footer { font-size: 12px; color: #777; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='email-content'>
            <p><strong>Hello Support Team,</strong></p>
            <p>A new feedback entry has been submitted through the Employee Management System. Please review the details below and take appropriate action if needed:</p>
            
            <p><span class='section-title'>Submitted By:</span> $user_name</p>
            <p><span class='section-title'>Employee Position:</span> HR</p>
            <p><span class='section-title'>Email:</span> $user_email</p>
            <p><span class='section-title'>Category:</span> $feedback_category</p>
            <p><span class='section-title'>Page/Module:</span> " . ($page_or_module ? $page_or_module : 'No message provided') . "</p>
            <p><span class='section-title'>Priority Level:</span> $priority_level</p>
            <p><span class='section-title'>Message:</span><br>" . nl2br($feedback_message) . "</p>
            <p><span class='section-title'>User Satisfaction Rating:</span> $star_rating_text</p>
            <p><span class='section-title'>Submitted On:</span> " . $submitted_on . "</p>

            <p><span class='section-title'>Screenshot:</span><br> " . ($file_path ? "<img src='cid:screenshot' alt='Screenshot' style='max-width: 100%; height: auto;'/>" : "No screenshot uploaded.") . "</p>
        </div>
        <div class='footer'>
            <p>This feedback was submitted through the Feedback Form of the system. Please review the details carefully and take any necessary actions. If the user has agreed to follow-up, ensure a timely response is provided.</p>
            <p>Best regards,<br>System Notification Bot<br>Employee Management System</p>
        </div>
    </body>
    </html>
    ";

    // Instantiate PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to Gmail
        $mail->SMTPAuth = true;
        $mail->Username = 'blobshark25@gmail.com'; // Your Gmail address
        $mail->Password = 'lqrp avem mfmg smaz'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587; // TCP port to connect to

        // Recipients
        $mail->setFrom('no-reply@tcu.edu.ph', 'TCU EMS');  // Replace with TCU EMS as sender
        $mail->addAddress($to);  // Add the recipient's email address

        // Add custom headers to mark the email as important
        $mail->addCustomHeader('X-Priority', '1'); // 1 = High
        $mail->addCustomHeader('Importance', 'High');

        // Attachments (Optional: Attach file if uploaded)
        if ($file_path) {
            // Attach the screenshot and give it a CID (Content-ID)
            $mail->addEmbeddedImage($file_path, 'screenshot', $file_name);
        }

        // Content
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Send email
        $mail->send();
    } catch (Exception $e) {
        $error_message = "There was an error sending your feedback. Mailer Error: {$mail->ErrorInfo}";
    }
}

?>

<?php if (isset($success_message) && !empty($success_message)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.className = "message-box success-message";  // Apply the success class
            messageBox.innerHTML = "<?php echo $success_message; ?>"; // Display the success message
            setTimeout(() => {
                messageBox.style.display = "none";  // Hide after 5 seconds
            }, 5000);
        });
    </script>
<?php } elseif (isset($error_message) && !empty($error_message)) { ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let messageBox = document.getElementById("message-box");
            messageBox.style.display = "block";
            messageBox.className = "message-box error-message";  // Apply the error class
            messageBox.innerHTML = "<?php echo $error_message; ?>"; // Display the error message
            setTimeout(() => {
                messageBox.style.display = "none";  // Hide after 5 seconds
            }, 5000);
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
    <link rel="stylesheet" href="../includes/HRDashboard.css">
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
        <a href="hrDashboard.php" class="menu-item active">
            <span class="icon icon-dashboard"></span> Dashboard
        </a>

        <a href="HRLeaveApplication.php" class="menu-item">
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

    <!-- Message Box for Success or Error -->
<div id="message-box" class="message-box" style="display: none; position: fixed; top: 2%; left: 55%; transform: translateX(-50%); background-color: #f8f9fa; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); font-weight: bold;">
    <!-- Success or Error message will be dynamically inserted here -->
</div>
<div class="main-content">
 <!-- Header -->
 <div class="header">
        <h1 class="header-title">Dashboard</h1>
        <div class="header-actions">
            <!-- <div class="search-container">
                <span class="icon icon-search"></span>
                <input type="text" class="search-input" placeholder="Search anything here">
            </div> -->
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

    <div class="profile-banner">
        <img src="../src/tcu-bg.jpg" alt="Campus" class="banner-image">
        <div class="banner-content">
            <img src="<?= $profilePicture; ?>" alt="Profile Picture" class="banner-profile-image">
            <div class="banner-details">
                <h2 class="banner-name"><?= htmlspecialchars($fullName); ?></h2>
                <p class="banner-role">HR</p>
            </div>
        </div>


    </div>
<!-- Analytics (Dashboard) -->
<div class="leave-grid mb-4">
    <!-- New Pending Leave Requests -->
    <a href="HRLeaveApplication.php?Status=Pending" class="leave-card card-pending">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="card-number"><?= $leaveStatus['Pending'] ?></div>
        </div>
        <div class="card-title">New Leave Requests (Pending)</div>
    </a>

    <!-- Approved Leave Requests -->
    <a href="HRLeaveApplication.php?Status=Approved" class="leave-card card-approved">
        <div class="card-header"> 
            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="card-number"><?= $leaveStatus['Approved'] ?></div>
        </div>
        <div class="card-title">Approved Leave</div>
    </a>

    <!-- Rejected Leave Requests -->
    <a href="HRLeaveApplication.php?Status=Rejected" class="leave-card card-rejected">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-times-circle"></i></div>
            <div class="card-number"><?= $leaveStatus['Rejected'] ?></div>
        </div>
        <div class="card-title">Rejected Leave</div>
    </a>
</div>
<!-- End of Analytics -->


<?php include_once('../includes/FeedbackForm.php');?>
</div>
<script src="../includes/HRDashboard.js"></script>
</body>
</html>
