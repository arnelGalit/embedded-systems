<?php
// Setup admin user password
$connection = new mysqli("localhost", "root", "", "finalprojectembedded");

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Password: admin123 (for testing)
$password_hash = password_hash("admin123", PASSWORD_BCRYPT);

$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $connection->prepare($sql);
$stmt->bind_param("s", $password_hash);

if ($stmt->execute()) {
    echo "Admin password set successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$connection->close();
?>
