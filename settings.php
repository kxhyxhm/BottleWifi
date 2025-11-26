<?php
session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

require_once 'settings_handler.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'wifi_time' => intval($_POST['wifi_time']) * ($_POST['time_unit'] === 'hours' ? 3600 : 60),
        'ssid' => $_POST['ssid'],
        'security_mode' => $_POST['security_mode'],
        'channel' => $_POST['channel'],
        'firewall_enabled' => isset($_POST['firewall'])
    ];
    
    saveSettings($settings);
    $success = true;
}

// Get current settings
$settings = getSettings();
$wifi_time_hours = floor($settings['wifi_time'] / 3600);
$wifi_time_minutes = floor(($settings['wifi_time'] % 3600) / 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — BottleWifi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .time-control {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .time-input {
            width: 100px !important;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-card {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="dust-container"></div>
    <div class="dashboard">
        <!-- Include the same sidebar as dashboard.php -->
        <aside class="sidebar">
            <div class="logo">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                </svg>
                <h1>BottleWifi</h1>
            </div>

            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link active">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Settings
                        </a>
                    </li>
                    <!-- Other nav items... -->
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="dashboard.php" class="back-button">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </a>
                    <h2 class="welcome">Network Settings</h2>
                </div>
                <div class="date" id="current-date"></div>
            </header>

            <?php if (isset($success)): ?>
            <div class="success-message">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Settings saved successfully
            </div>
            <?php endif; ?>

            <div class="settings-grid">
                <div class="setting-card">
                    <h3>WiFi Time Control</h3>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label>Session Duration</label>
                            <div class="time-control">
                                <input type="number" name="wifi_time" class="form-input time-input" 
                                    value="<?php echo $wifi_time_hours > 0 ? $wifi_time_hours : $wifi_time_minutes; ?>" 
                                    min="1" required>
                                <select name="time_unit" class="form-input">
                                    <option value="hours" <?php echo $wifi_time_hours > 0 ? 'selected' : ''; ?>>Hours</option>
                                    <option value="minutes" <?php echo $wifi_time_hours === 0 ? 'selected' : ''; ?>>Minutes</option>
                                </select>
                            </div>
                            <p style="font-size: 0.875rem; color: var(--muted); margin-top: 0.5rem;">
                                Set the duration for each WiFi session
                            </p>
                        </div>

                        <div class="form-group">
                            <label>Network Name (SSID)</label>
                            <input type="text" name="ssid" class="form-input" 
                                value="<?php echo htmlspecialchars($settings['ssid']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Security Mode</label>
                            <select name="security_mode" class="form-input">
                                <option value="WPA3-Personal" <?php echo $settings['security_mode'] === 'WPA3-Personal' ? 'selected' : ''; ?>>WPA3-Personal</option>
                                <option value="WPA2-Personal" <?php echo $settings['security_mode'] === 'WPA2-Personal' ? 'selected' : ''; ?>>WPA2-Personal</option>
                                <option value="WPA/WPA2-Personal" <?php echo $settings['security_mode'] === 'WPA/WPA2-Personal' ? 'selected' : ''; ?>>WPA/WPA2-Personal</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Channel</label>
                            <select name="channel" class="form-input">
                                <option value="Auto" <?php echo $settings['channel'] === 'Auto' ? 'selected' : ''; ?>>Auto</option>
                                <?php for($i = 1; $i <= 11; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $settings['channel'] == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="firewall" 
                                    <?php echo $settings['firewall_enabled'] ? 'checked' : ''; ?>>
                                Enable Firewall
                            </label>
                        </div>

                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>

                <div class="setting-card">
                    <h3>Current Status</h3>
                    <div style="color: var(--muted); margin-top: 1rem;">
                        <p>
                            <strong>Active Session Time:</strong> 
                            <?php 
                            if ($settings['wifi_time'] >= 3600) {
                                echo floor($settings['wifi_time'] / 3600) . ' hours';
                            } else {
                                echo floor($settings['wifi_time'] / 60) . ' minutes';
                            }
                            ?>
                        </p>
                        <p style="margin-top: 0.5rem;">
                            <strong>Network Name:</strong> 
                            <?php echo htmlspecialchars($settings['ssid']); ?>
                        </p>
                        <p style="margin-top: 0.5rem;">
                            <strong>Security:</strong> 
                            <?php echo htmlspecialchars($settings['security_mode']); ?>
                        </p>
                        <p style="margin-top: 0.5rem;">
                            <strong>Channel:</strong> 
                            <?php echo htmlspecialchars($settings['channel']); ?>
                        </p>
                        <p style="margin-top: 0.5rem;">
                            <strong>Firewall:</strong> 
                            <?php echo $settings['firewall_enabled'] ? 'Enabled' : 'Disabled'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>
