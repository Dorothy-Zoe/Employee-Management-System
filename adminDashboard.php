<?php
// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Path to autoload.php from PHPMailer

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

// Validate fetched employee data
$fullName = isset($employee['FName']) ? $employee['FName'] . ' ' . $employee['LName'] : 'Unknown User';
$profilePicture = (!empty($employee['ProfilePicture'])) ? $employee['ProfilePicture'] : "src/default.jpg";

// Query to get the count of employees per department
$sql = "SELECT Department, COUNT(*) AS count FROM employeedetail GROUP BY Department";
$result = $conn->query($sql);

// Store data in an array
$departmentCounts = [];
while ($row = $result->fetch_assoc()) {
    $departmentCounts[$row['Department']] = $row['count'];
}

// Default value if department does not exist in query
$departments = [
    "CAS" => 0,
    "CBM" => 0,
    "CCJ" => 0,
    "COE" => 0,
    "CHTM" => 0,
    "CICT" => 0
];

// Merge with actual data from DB
foreach ($departments as $dept => $count) {
    if (isset($departmentCounts[$dept])) {
        $departments[$dept] = $departmentCounts[$dept];
    }
}


// Notification Process
$sql = "SELECT l.ID, l.Status, l.AppliedDate, ns.AdminIsRead, CONCAT(e.FName, ' ', e.LName) AS EmployeeName 
    FROM Leavetable l
    LEFT JOIN leave_notification_status ns ON l.ID = ns.LeaveID
    LEFT JOIN employeedetail e ON l.EmployeeID = e.EmployeeID
    ORDER BY l.AppliedDate DESC
    LIMIT 5"; // Limit to 5 most recent notifications
$result = $conn->query($sql);

if (!$result) {
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
        $sql = "SELECT Email, FName, LName FROM admintbl WHERE UserID = ?";
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
            <p><span class='section-title'>Employee Position:</span> Administrator</p>
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
    <link rel="icon" type="image/x-icon" href="src/favicon-logo.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/adminDashboard.css">

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
        <a href="adminDashboard.php" class="menu-item active">
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
            <!-- <a href="AdminSettings.php"><span class="icon icon-settings-header header-icon"></span></a> -->

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

    <div class="profile-banner">
        <img src="src/tcu-bg.jpg" alt="Campus" class="banner-image">
        <div class="banner-content">
            <img src="<?= $profilePicture; ?>" alt="Profile Picture" class="banner-profile-image">
            <div class="banner-details">
                <h2 class="banner-name"><?= htmlspecialchars($fullName); ?></h2>
                <p class="banner-role">Administrator</p>
            </div>
        </div>

    </div>
 

    
<!-- Content Area -->
<div class="college-grid mb-4">
    <a href="viewEmployees.php?Department=CAS" class="college-card card-cas">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['CAS'] ?></div>
        </div>
        <div class="card-title">College of Arts and Sciences (CAS)</div>
    </a>

    <a href="viewEmployees.php?Department=CBM" class="college-card card-cbm">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['CBM'] ?></div>
        </div>
        <div class="card-title">College of Business Management (CBM)</div>
    </a>

    <a href="viewEmployees.php?Department=CCJ" class="college-card card-ccj">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['CCJ'] ?></div>
        </div>
        <div class="card-title">College of Criminal Justice (CCJ)</div>
    </a>

    <a href="viewEmployees.php?Department=COE" class="college-card card-coe">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['COE'] ?></div>
        </div>
        <div class="card-title">College of Education (COE)</div>
    </a>

    <a href="viewEmployees.php?Department=CHTM" class="college-card card-chtm">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['CHTM'] ?></div>
        </div>
        <div class="card-title">College of Hospitality and Tourism Management (CHTM)</div>
    </a>

    <a href="viewEmployees.php?Department=CICT" class="college-card card-cict">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="card-number"><?= $departments['CICT'] ?></div>
        </div>
        <div class="card-title">College of Information and Communication Technology (CICT)</div>
    </a>
</div>

<?php include_once('includes/FeedbackForm.php');?>
</div>
<script src="includes/adminDashboard.js"></script>
<script src="includes/adminHeader.js"></script>
</body>
</html>
