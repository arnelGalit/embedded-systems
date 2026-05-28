<?php
require 'session_config.php';

// Check if admin and requesting JSON (API endpoint)
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    
    // ESP32 can access this without authentication
    $connection = new mysqli("localhost", "root", "", "finalprojectembedded");
    
    if ($connection->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }
    
    $result = $connection->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo json_encode($settings);
    $connection->close();
    exit();
}

// Otherwise, requires login and admin access
requireLogin();

if (!isAdmin()) {
    die("Access denied. Admin only.");
}

$success_msg = "";
$error_msg = "";

$connection = new mysqli("localhost", "root", "", "finalprojectembedded");

if ($connection->connect_error) {
    die("Database connection failed: " . $connection->connect_error);
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && $key !== 'submit') {
            $value = trim($value);
            
            $stmt = $connection->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("ss", $key, $value);
            
            if (!$stmt->execute()) {
                $error_msg = "Error updating settings: " . $stmt->error;
                $stmt->close();
                break;
            }
            $stmt->close();
        }
    }
    
    if (empty($error_msg)) {
        $success_msg = "Settings updated successfully!";
    }
}

// Get all settings
$result = $connection->query("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'description' => $row['description']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Flora Pulse</title>
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
            max-width: 800px;
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
            font-size: 28px;
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
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-light);
        }

        .setting-group {
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .label-with-help {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            font-weight: 400;
        }

        input[type="text"],
        input[type="password"],
        input[type="number"],
        input[type="url"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .setting-description {
            font-size: 12px;
            color: #888;
            margin-top: 6px;
            font-style: italic;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
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

        .info-box {
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
        }

        .info-box strong {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ System Settings</h1>
            <a href="live_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </header>

        <?php if (!empty($success_msg)): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="info-box">
                <strong>ESP32 API Endpoint:</strong><br>
                <code>http://localhost/plant_monitoring/api_settings.php?format=json</code><br>
                The ESP32 reads these settings from this endpoint on startup.
            </div>

            <form method="POST">
                <!-- WiFi Settings -->
                <div class="setting-group">
                    <div class="section-title">WiFi Configuration</div>

                    <div class="form-group">
                        <div class="label-with-help">
                            <label>WiFi Network Name (SSID) *</label>
                            <span class="help-text">Your WiFi network name</span>
                        </div>
                        <input type="text" name="wifi_ssid" value="<?php echo htmlspecialchars($settings['wifi_ssid']['value'] ?? ''); ?>" required>
                        <div class="setting-description"><?php echo htmlspecialchars($settings['wifi_ssid']['description'] ?? ''); ?></div>
                    </div>

                    <div class="form-group">
                        <div class="label-with-help">
                            <label>WiFi Password *</label>
                            <span class="help-text">Your WiFi password</span>
                        </div>
                        <input type="password" name="wifi_password" value="<?php echo htmlspecialchars($settings['wifi_password']['value'] ?? ''); ?>" required>
                        <div class="setting-description"><?php echo htmlspecialchars($settings['wifi_password']['description'] ?? ''); ?></div>
                    </div>
                </div>

                <!-- Server Settings -->
                <div class="setting-group">
                    <div class="section-title"> Server Configuration</div>

                    <div class="form-group">
                        <div class="label-with-help">
                            <label>Server Base URL *</label>
                            <span class="help-text">e.g., http://192.168.0.33</span>
                        </div>
                        <input type="text" name="server_url" value="<?php echo htmlspecialchars($settings['server_url']['value'] ?? ''); ?>" placeholder="http://192.168.0.33" required>
                        <div class="setting-description"><?php echo htmlspecialchars($settings['server_url']['description'] ?? ''); ?></div>
                    </div>

                    <div class="form-group">
                        <div class="label-with-help">
                            <label>API Endpoint Path *</label>
                            <span class="help-text">Path to sensor data insertion endpoint</span>
                        </div>
                        <input type="text" name="api_path" value="<?php echo htmlspecialchars($settings['api_path']['value'] ?? ''); ?>" placeholder="/temperature_monitor/insert_sensor.php" required>
                        <div class="setting-description"><?php echo htmlspecialchars($settings['api_path']['description'] ?? ''); ?></div>
                    </div>
                </div>

                <!-- Device Settings -->
                <div class="setting-group">
                    <div class="section-title">⏱Device Configuration</div>

                    <div class="form-group">
                        <div class="label-with-help">
                            <label>Database Insert Interval (milliseconds) *</label>
                            <span class="help-text">How often ESP32 sends data to database</span>
                        </div>
                        <input type="number" name="db_interval" value="<?php echo htmlspecialchars($settings['db_interval']['value'] ?? '30000'); ?>" min="5000" step="1000" required>
                        <div class="setting-description">Default: 30000ms (30 seconds). Minimum: 5000ms (5 seconds)</div>
                    </div>
                </div>

                <div class="buttons">
                    <button type="submit" class="btn-primary">💾 Save Settings</button>
                    <a href="live_dashboard.php" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancel</a>
                </div>
            </form>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <h3 style="font-size: 14px; color: #666; margin-bottom: 12px;">📝 How to use with ESP32:</h3>
                <pre style="background: #f5f5f5; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto;">
// In your ESP32 setup code, fetch settings:
HTTPClient http;
http.begin("http://192.168.0.33/plant_monitoring/api_settings.php?format=json");
int code = http.GET();
if (code == 200) {
    String payload = http.getString();
    // Parse JSON and use settings
}
http.end();</pre>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$connection->close();
include 'user_menu.php';
?>
