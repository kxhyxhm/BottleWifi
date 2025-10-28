<?php
// Initialize session for potential admin check
session_start();
require_once 'settings_handler.php';

// Function to format time remaining
function formatTimeRemaining($seconds) {
    if ($seconds < 60) {
        return $seconds . " seconds";
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . " minutes";
    } else {
        return floor($seconds / 3600) . " hours " . floor(($seconds % 3600) / 60) . " minutes";
    }
}

// Get WiFi settings
$settings = getSettings();
$sessionDuration = $settings['wifi_time'];

// Check if there's an active session
$isConnected = isset($_SESSION['wifi_connected']) && $_SESSION['wifi_connected'] === true;
$timeRemaining = isset($_SESSION['connect_time']) ? 
    ($_SESSION['connect_time'] + $sessionDuration) - time() : 
    $sessionDuration;

// Handle connection request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_terms'])) {
    $_SESSION['wifi_connected'] = true;
    $_SESSION['connect_time'] = time();
    $_SESSION['device_mac'] = $_SERVER['REMOTE_ADDR']; // In production, get actual MAC address
    $isConnected = true;
    $timeRemaining = 3600; // 1 hour in seconds
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to BottleWifi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f0fdf4;
            --card: #ffffff;
            --accent: #059669;
            --muted: #65a88a;
            --gradient-start: #34d399;
            --gradient-end: #059669;
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
            flex-direction: column;
        }

        .header {
            background: var(--card);
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--accent);
            text-decoration: none;
        }

        .logo svg {
            width: 32px;
            height: 32px;
        }

        .logo span {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .admin-link {
            padding: 0.5rem 1rem;
            color: var(--muted);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .admin-link:hover {
            background: rgba(5, 150, 105, 0.1);
            color: var(--accent);
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: var(--card);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 4px solid rgba(5, 150, 105, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-title {
            color: var(--accent);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .status-message {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        .terms {
            text-align: left;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(5, 150, 105, 0.05);
            border-radius: 12px;
            font-size: 0.875rem;
            color: var(--muted);
        }

        .connect-btn {
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: 0;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .connect-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .timer {
            font-size: 2rem;
            font-weight: 600;
            color: var(--accent);
            margin: 1rem 0;
        }

        .dust-container {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .dust {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(250, 204, 21, 0.2);
            border-radius: 50%;
        }

        @keyframes float-up {
            0% {
                transform: translateY(100%) translateX(0) scale(0);
                opacity: 0;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100%) translateX(var(--tx)) scale(1);
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0.75rem;
            }

            .logo span {
                display: none;
            }

            .card {
                margin: 1rem;
                padding: 1.25rem;
                border-radius: 16px;
                border-width: 2px;
            }

            .welcome-title {
                font-size: 1.25rem;
            }

            .terms {
                padding: 0.75rem;
                font-size: 0.813rem;
            }

            .timer {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 0.5rem;
            }

            .logo svg {
                width: 24px;
                height: 24px;
            }

            .admin-link {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }

            .main-content {
                padding: 1rem;
            }

            .card {
                margin: 0;
                padding: 1rem;
                border-radius: 12px;
            }

            .welcome-title {
                font-size: 1.125rem;
            }

            .status-message {
                font-size: 0.875rem;
            }

            .connect-btn {
                width: 100%;
                padding: 0.625rem 1rem;
            }
        }

        @media (min-width: 769px) {
            .card {
                transform: translateY(0);
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .card:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
            </svg>
            <span>BottleWifi</span>
        </a>
        <a href="login.html" class="admin-link">Admin Login</a>
    </header>

    <main class="main-content">
        <div class="card">
            <div class="dust-container"></div>
            <?php if (!$isConnected): ?>
            <h1 class="welcome-title">Welcome to BottleWifi</h1>
            <p class="status-message">Connect to our free WiFi service</p>
            
            <div class="terms">
                <p><strong>Terms of Service:</strong></p>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>1 hour of free internet access</li>
                    <li>Fair usage policy applies</li>
                    <li>No illegal activities allowed</li>
                    <li>Speed may vary based on usage</li>
                </ul>
            </div>

            <form method="POST" action="">
                <label style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <input type="checkbox" name="accept_terms" required>
                    <span style="color: var(--muted); font-size: 0.875rem;">I accept the terms of service</span>
                </label>
                <button type="submit" class="connect-btn">Connect to WiFi</button>
            </form>
            <?php else: ?>
            <h1 class="welcome-title">Connected to BottleWifi</h1>
            <p class="status-message">Time Remaining:</p>
            <div class="timer" id="countdown"><?php echo formatTimeRemaining($timeRemaining); ?></div>
            <p style="color: var(--muted); font-size: 0.875rem;">You can now browse the internet</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Dust animation
        function createDust() {
            const container = document.querySelector('.dust-container');
            for (let i = 0; i < 30; i++) {
                const dust = document.createElement('div');
                dust.className = 'dust';
                const size = Math.random() * 3 + 1;
                const startX = Math.random() * 100;
                const tx = (Math.random() - 0.5) * 50;
                const duration = Math.random() * 3 + 2;
                const delay = Math.random() * 2;
                
                dust.style.cssText = `
                    left: ${startX}%;
                    width: ${size}px;
                    height: ${size}px;
                    --tx: ${tx}px;
                    animation: float-up ${duration}s ease-in infinite ${delay}s;
                `;
                
                container.appendChild(dust);
            }
        }

        // Initialize dust animation
        document.addEventListener('DOMContentLoaded', createDust);

        // Countdown timer
        <?php if ($isConnected): ?>
        function updateTimer() {
            let timeLeft = <?php echo $timeRemaining; ?>;
            const timerElement = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                timeLeft--;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = 'index.php?expired=1';
                    return;
                }

                let hours = Math.floor(timeLeft / 3600);
                let minutes = Math.floor((timeLeft % 3600) / 60);
                let seconds = timeLeft % 60;

                let display = '';
                if (hours > 0) {
                    display += hours + ' hours ';
                }
                if (minutes > 0 || hours > 0) {
                    display += minutes + ' minutes ';
                }
                display += seconds + ' seconds';

                timerElement.textContent = display;
            }, 1000);
        }

        updateTimer();
        <?php endif; ?>
    </script>
</body>
</html>