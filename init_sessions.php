<?php
/**
 * Emergency Database Initializer
 * Creates wifi_sessions.json if it doesn't exist
 * Access via: http://10.6.6.1/init_sessions.php
 */

header('Content-Type: text/html; charset=utf-8');

$sessionsFile = __DIR__ . '/wifi_sessions.json';
$success = false;
$error = null;

if (!file_exists($sessionsFile)) {
    // Try to create the file
    $result = @file_put_contents($sessionsFile, '[]');
    
    if ($result !== false) {
        @chmod($sessionsFile, 0664);
        $success = true;
    } else {
        $error = "Failed to create file. Directory may not be writable.";
    }
} else {
    $success = true;
    $error = "File already exists.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Sessions File</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f0fdf4;
        }
        .box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .success {
            color: #059669;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .error {
            color: #ef4444;
            font-size: 18px;
        }
        .info {
            color: #6b7280;
            margin-top: 20px;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #059669;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($success): ?>
            <div class="success">✓ Success!</div>
            <p>The <code>wifi_sessions.json</code> file has been created.</p>
            <p class="info">File location: <code><?php echo $sessionsFile; ?></code></p>
            <p class="info">The dashboard will now be able to track active WiFi sessions.</p>
            <a href="dashboard.php">Go to Dashboard</a>
        <?php else: ?>
            <div class="error">✗ Failed</div>
            <p><?php echo htmlspecialchars($error); ?></p>
            <p class="info">
                <strong>Alternative solution:</strong><br>
                The file will be created automatically when the first user drops a bottle and gets WiFi access through <code>hardware_control.php</code>.
            </p>
            <a href="dashboard.php">Go to Dashboard Anyway</a>
        <?php endif; ?>
    </div>
</body>
</html>
