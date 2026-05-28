<?php
// User dropdown menu component
?>

<style>
    .user-menu-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 999;
    }

    .user-menu-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ee9ad0;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .user-menu-btn:hover {
        background: #ec91db;
    }

    .user-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 200px;
        margin-top: 8px;
        overflow: hidden;
    }

    .dropdown-menu.active {
        display: block;
    }

    .dropdown-menu a,
    .dropdown-menu button {
        display: block;
        width: 100%;
        padding: 12px 16px;
        border: none;
        background: none;
        text-align: left;
        cursor: pointer;
        color: #333;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.2s;
    }

    .dropdown-menu a:hover,
    .dropdown-menu button:hover {
        background: #f5f5f5;
    }

    .dropdown-divider {
        height: 1px;
        background: #e0e0e0;
        margin: 0;
    }

    .dropdown-menu a.logout,
    .dropdown-menu button.logout {
        color: #e53935;
    }

    .dropdown-menu a.logout:hover,
    .dropdown-menu button.logout:hover {
        background: #ffebee;
    }

    .user-info {
        padding: 16px;
        background: #f9f9f9;
        border-bottom: 1px solid #e0e0e0;
    }

    .user-name {
        font-weight: 700;
        color: #333;
        font-size: 14px;
    }

    .user-email {
        font-size: 12px;
        color: #999;
        margin-top: 4px;
    }
</style>

<div class="user-menu-container">
    <button class="user-menu-btn" onclick="toggleUserMenu()">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
        </div>
        <?php echo htmlspecialchars($_SESSION['first_name']); ?>
        <span style="margin-left: 4px;">▼</span>
    </button>

    <div id="userDropdown" class="dropdown-menu">
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
        </div>

        <a href="profile.php">👤 Edit Profile</a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="users_manager.php">👥 View Users</a>
            <a href="api_settings.php">⚙️ Settings</a>
        <?php endif; ?>

        <div class="dropdown-divider"></div>

        <a href="login.php?logout=1" class="logout">🚪 Logout</a>
    </div>
</div>

<script>
    function toggleUserMenu() {
        let dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('active');
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        let menu = document.querySelector('.user-menu-container');
        if (!menu.contains(event.target)) {
            document.getElementById('userDropdown').classList.remove('active');
        }
    });
</script>
