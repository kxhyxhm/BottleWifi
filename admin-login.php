<?php
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple authentication - you should use proper password hashing in production
    if ($username === 'admin' && $password === 'RKM_admin_bottlewifi') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — BottleWifi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f0fdf4;
            --card: #ffffff;
            --accent: #059669;
            --muted: #65a88a;
            --gradient-start: #34d399;
            --gradient-end: #059669;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg);
            background-image: radial-gradient(circle at 10px 10px, rgba(147, 197, 153, 0.1) 2px, transparent 0);
            background-size: 24px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-card {
            background: var(--card);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 4px solid rgba(5, 150, 105, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .title {
            color: var(--accent);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(5, 150, 105, 0.2);
            border-radius: 8px;
            background: transparent;
            color: var(--accent);
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: 0;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            text-decoration: none;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .error-message {
            color: var(--error);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 6px;
            display: <?php echo isset($error) ? 'block' : 'none'; ?>;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="header">
            <h1 class="title">Admin Login</h1>
        </div>

        <div class="error-message" id="error">
            <?php echo $error ?? ''; ?>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <a href="index.php" class="back-link">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Portal
        </a>
    </div>
</body>
</html>
