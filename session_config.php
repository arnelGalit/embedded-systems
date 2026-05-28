<?php
session_start();

// Database connection
$connection = new mysqli("localhost", "root", "", "finalprojectembedded");

if ($connection->connect_error) {
    die("Database connection failed: " . $connection->connect_error);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Get current user data
function getCurrentUser() {
    global $connection;
    if (!isLoggedIn()) return null;
    
    $result = $connection->query("SELECT * FROM users WHERE id = " . $_SESSION['user_id']);
    return $result->fetch_assoc();
}

// Validate password strength
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[a-zA-Z]/', $password)) {
        $errors[] = "Password must contain at least one letter";
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*...)";
    }
    
    return $errors;
}
?>
