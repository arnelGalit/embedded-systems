<?php
session_start();

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: live_dashboard.php");
    exit();
}

// Database connection
$connection = new mysqli("localhost", "root", "", "finalprojectembedded");

if ($connection->connect_error) {
    die("Database connection failed: " . $connection->connect_error);
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        // Check user in database
        $stmt = $connection->prepare("SELECT id, password, first_name, last_name, email, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Password correct - set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: live_dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flora Pulse - Smart Plant Care</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: url('plantmonitoringbg.png') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Dark overlay for readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1;
            pointer-events: none;
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(201, 76, 109, 0.2);
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(201, 76, 109, 0.2), 0 0 1px rgba(255, 255, 255, 0.5) inset;
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header .logo {
            width: 220px;
            height: auto;
            margin: 0 auto 12px;
            display: block;
            animation: floatLogo 3s ease-in-out infinite;
            max-width: 100%;
        }

        @keyframes floatLogo {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .login-header h1 {
            font-size: 24px;
            color: #C94C6D;
            margin-bottom: 6px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .login-header p {
            color: #999;
            font-size: 14px;
            font-weight: 500;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #C94C6D;
            background: white;
            box-shadow: 0 0 0 3px rgba(201, 76, 109, 0.1);
            transform: translateY(-2px);
        }

        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #aaa;
        }

        .error {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            border-left: 4px solid #c62828;
            animation: shake 0.3s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #C94C6D 0%, #A03B57 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(201, 76, 109, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(201, 76, 109, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(201, 76, 109, 0.3);
        }

        .divider {
            text-align: center;
            margin: 24px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
        }

        .divider span {
            background: white;
            padding: 0 10px;
            color: #999;
            font-size: 13px;
            position: relative;
        }

        .demo-info {
            background: linear-gradient(135deg, #FADDE8 0%, #FCE7F3 100%);
            border: 1px solid #F3B5D1;
            border-radius: 12px;
            padding: 14px;
            font-size: 13px;
            color: #A03B57;
        }

        .demo-info strong {
            color: #C94C6D;
            font-weight: 700;
        }

        .demo-info code {
            background: rgba(255, 255, 255, 0.7);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 12px;
        }

        .demo-item {
            margin: 6px 0;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 20px;
            }

            .login-header .logo {
                width: 180px;
            }

            .login-header p {
                font-size: 13px;
            }

            input[type="text"],
            input[type="password"] {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <img src="florapulse.png" alt="Flora Pulse" class="logo">
                <p>Smart Plant Care System</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="login-btn">🔓 Login</button>
            </form>

            <div class="divider">
                <span>Demo Account</span>
            </div>

            <div class="demo-info">
                <strong>Try these credentials:</strong>
                <div class="demo-item">👤 Username: <code>admin</code></div>
                <div class="demo-item">🔐 Password: <code>admin123</code></div>
            </div>
        </div>
    </div>
</body>
</html>
