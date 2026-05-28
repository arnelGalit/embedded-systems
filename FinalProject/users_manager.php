<?php
require 'session_config.php';
requireLogin();

if (!isAdmin()) {
    die("Access denied. Admin only.");
}

$success_msg = "";
$error_msg = "";

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'user');

        $errors = [];

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
            $errors[] = "First name, last name, username, email, and password are required";
        }

        if (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters";
        }

        $validation_errors = validatePassword($password);
        if (!empty($validation_errors)) {
            $errors = array_merge($errors, $validation_errors);
        }

        if (empty($errors)) {
            // Check if username exists
            $stmt = $connection->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error_msg = "Username or email already exists";
            } else {
                // Validate role
                if (!in_array($role, ['user', 'admin'])) {
                    $role = 'user';
                }
                
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $connection->prepare("INSERT INTO users (first_name, middle_name, last_name, username, password, email, contact_number, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $first_name, $middle_name, $last_name, $username, $hashed_password, $email, $contact_number, $role);

                if ($stmt->execute()) {
                    $success_msg = "User created successfully!";
                } else {
                    $error_msg = "Error creating user: " . $stmt->error;
                }
            }
            $stmt->close();
        } else {
            $error_msg = implode("<br>", $errors);
        }
    }
}

// Get all users
$result = $connection->query("SELECT id, first_name, middle_name, last_name, username, email, contact_number, role, created_at FROM users ORDER BY created_at DESC");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Flora Pulse</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
        }

        h1 {
            color: var(--primary);
            font-size: 28px;
            margin: 0;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
            position: absolute;
            right: 0;
            top: 0;
        }

        button, a.btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 11px 20px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(201, 76, 109, 0.2);
        }

        .btn-secondary {
            background: #ec43d3;
            color: #ffffff;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-secondary:hover {
            background: #e587b5;
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

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        tbody tr:hover {
            background: var(--green-light);
        }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-admin {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-user {
            background: #e3f2fd;
            color: #0277bd;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #999;
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
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
            margin: auto;
        }

        .modal-header {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .close-btn {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            cursor: pointer;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .password-requirements {
            background: var(--primary-light);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 12px;
            color: #333;
        }

        .password-requirements li {
            margin: 3px 0 3px 18px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .modal-buttons button {
            flex: 1;
            padding: 12px;
        }

        /* Responsive Modal */
        @media (max-width: 600px) {
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            h1 {
                font-size: 22px;
            }

            .btn-group {
                width: 100%;
                gap: 10px;
            }

            button, a.btn {
                flex: 1;
                justify-content: center;
                padding: 12px 16px;
                font-size: 13px;
            }

            .modal.active {
                padding: 10px;
            }

            .modal-content {
                padding: 24px;
                max-height: 95vh;
            }

            .modal-header {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>👥 User Management</h1>
            <div class="btn-group">
                <button class="btn-primary" onclick="openCreateUserModal()">+ Create New User</button>
                <a href="live_dashboard.php" class="btn-secondary">← Back to Dashboard</a>
            </div>
        </header>

        <?php if (!empty($success_msg)): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['contact_number'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-records">No users found</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Create New User
                <span class="close-btn" onclick="closeCreateUserModal()">&times;</span>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="create_user">

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Middle Name (Optional)</label>
                    <input type="text" name="middle_name">
                </div>

                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" minlength="3" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="tel" name="contact_number">
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="user" selected>User (Regular Access)</option>
                        <option value="admin">Admin (Full Access)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                    <div class="password-requirements">
                        <strong>Password must contain:</strong>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>At least one number</li>
                            <li>At least one letter</li>
                            <li>At least one special character (!@#$%^&*...)</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-primary">Create User</button>
                    <button type="button" class="btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.add('active');
        }

        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('createUserModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>
