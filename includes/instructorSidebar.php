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
        <a href="instructorDashboard.php" class="menu-item active">
            <span class="icon icon-dashboard"></span> Dashboard
        </a>
        <a href="../faculty/schedule.php" class="menu-item">
            <span class="icon icon-schedule"></span> Schedule
        </a>
        <a href="../faculty/leaveManagement.php" class="menu-item">
            <span class="icon icon-leave"></span> Leave Management
          
        </a>
        <a href="../faculty/settings.php" class="menu-item">
            <span class="icon icon-settings"></span> Settings
        </a>
    </div>
    <a href="../index.html" class="logout">
        <span class="icon icon-logout"></span> Log out
    </a>
</div>