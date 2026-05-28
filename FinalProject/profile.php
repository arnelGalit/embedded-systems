<?php
require 'session_config.php';
requireLogin();

$user = getCurrentUser();
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email)) {
                $error_msg = "First name, last name, and email are required";
            } else {
                $stmt = $connection->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $email, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_msg = "Profile updated successfully!";
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    $user = getCurrentUser();
                } else {
                    $error_msg = "Error updating profile: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_msg = "All password fields are required";
            } elseif (!password_verify($current_password, $user['password'])) {
                $error_msg = "Current password is incorrect";
            } elseif ($new_password !== $confirm_password) {
                $error_msg = "New password and confirmation do not match";
            } else {
                $validation_errors = validatePassword($new_password);
                if (!empty($validation_errors)) {
                    $error_msg = implode("<br>", $validation_errors);
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $connection->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $success_msg = "Password changed successfully!";
                        $user = getCurrentUser();
                    } else {
                        $error_msg = "Error changing password";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Flora Pulse</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #C94C6D;
            --primary-light: #FADDE8;
            --primary-dark: #A03B57;
            --body-bg: #faf6f8;
            --card-bg: #ffffff;
            --shadow: 0 4px 18px rgba(201,76,109,0.07);
            --radius: 14px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--body-bg);
            min-height: 100vh;
            padding: 24px 16px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            color: var(--primary);
            font-size: 24px;
        }

        .back-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: var(--primary-dark);
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 40px;
        }

        .success {
            background: #FADDE8;
            color: #C94C6D;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #C94C6D;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-danger {
            background: #e53935;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .password-btn {
            background: #1976d2;
            color: white;
            width: 100%;
            margin-top: 10px;
        }

        .password-btn:hover {
            background: #1565c0;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            color: var(--green);
            margin-bottom: 20px;
        }

        .close-btn {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #000;
        }

        .password-requirements {
            background: var(--green-light);
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 13px;
            color: #333;
        }

        .password-requirements li {
            margin: 4px 0 4px 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Edit Profile</h1>
            <a href="live_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </header>

        <?php if (!empty($success_msg)): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="profile-header">
                <div class="profile-pic">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p style="color: #999; margin-top: 4px;"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Middle Name (Optional)</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="buttons">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="live_dashboard.php" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">Close</a>
                </div>
            </form>

            <button class="password-btn" onclick="openPasswordModal()">Change Password</button>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="login.php?logout=1" style="color: #e53935; text-decoration: none; font-weight: 600;">Logout</a>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closePasswordModal()">&times;</span>
            <div class="modal-header">Change Password</div>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div class="password-requirements">
                    <strong>Password must contain:</strong>
                    <ul>
                        <li>At least 8 characters</li>
                        <li>At least one number (0-9)</li>
                        <li>At least one letter (a-z, A-Z)</li>
                        <li>At least one special character (!@#$%^&*...)</li>
                    </ul>
                </div>

                <div class="buttons" style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">Change Password</button>
                    <button type="button" class="btn-secondary" onclick="closePasswordModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal() {
            document.getElementById('passwordModal').classList.add('active');
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('passwordModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>
