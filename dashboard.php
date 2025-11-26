<?php
session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Load data
$recyclingFile = __DIR__ . '/recycling_data.json';
$sessionsFile = __DIR__ . '/wifi_sessions.json';

$recyclingData = file_exists($recyclingFile) ? json_decode(file_get_contents($recyclingFile), true) : [];
$sessions = file_exists($sessionsFile) ? json_decode(file_get_contents($sessionsFile), true) : [];

// Calculate statistics
$totalBottles = count($recyclingData);
$currentTime = time();

// Get active sessions
$activeSessions = array_filter($sessions, function($session) use ($currentTime) {
    return $session['expires_at'] > $currentTime;
});

// Get unique users and their bottle counts
$userBottleCounts = [];
foreach ($recyclingData as $entry) {
    $mac = $entry['mac'] ?? $entry['ip'] ?? 'Unknown';
    if (!isset($userBottleCounts[$mac])) {
        $userBottleCounts[$mac] = [
            'mac' => $mac,
            'ip' => $entry['ip'] ?? 'Unknown',
            'bottles' => 0,
            'last_seen' => $entry['timestamp'] ?? null
        ];
    }
    $userBottleCounts[$mac]['bottles']++;
    
    // Update last seen
    if (isset($entry['timestamp'])) {
        $entryTime = strtotime($entry['timestamp']);
        $lastSeenTime = $userBottleCounts[$mac]['last_seen'] ? strtotime($userBottleCounts[$mac]['last_seen']) : 0;
        if ($entryTime > $lastSeenTime) {
            $userBottleCounts[$mac]['last_seen'] = $entry['timestamp'];
        }
    }
}

// Sort by bottle count descending
usort($userBottleCounts, function($a, $b) {
    return $b['bottles'] - $a['bottles'];
});

// Bottles over time (last 7 days)
$dailyBottles = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyBottles[$date] = 0;
}

foreach ($recyclingData as $entry) {
    if (isset($entry['timestamp'])) {
        $date = date('Y-m-d', strtotime($entry['timestamp']));
        if (isset($dailyBottles[$date])) {
            $dailyBottles[$date]++;
        }
    }
}

$totalUsers = count($userBottleCounts);
$activeUsers = count($activeSessions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BottleWifi Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f0fdf4;
            --card: #ffffff;
            --accent: #059669;
            --muted: #65a88a;
            --gradient-start: #34d399;
            --gradient-end: #059669;
            --border: rgba(5, 150, 105, 0.1);
            --text-primary: #064e3b;
            --text-secondary: #6b7280;
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
            padding: 2rem 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            color: var(--accent);
            font-size: 2rem;
            font-weight: 700;
        }

        .nav-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid var(--accent);
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: 0;
        }

        .btn-secondary {
            background: transparent;
            color: var(--accent);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 4px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            color: var(--accent);
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 4px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card-title {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 0.75rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .time-remaining {
            color: var(--accent);
            font-weight: 600;
        }

        .chart-container {
            height: 200px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 1rem 0;
        }

        .chart-bar {
            flex: 1;
            background: linear-gradient(180deg, var(--gradient-start), var(--gradient-end));
            border-radius: 8px 8px 0 0;
            position: relative;
            min-height: 20px;
            transition: all 0.3s;
        }

        .chart-bar:hover {
            opacity: 0.8;
        }

        .chart-label {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .chart-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .refresh-info {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍾 BottleWifi Dashboard</h1>
            <div class="nav-buttons">
                <a href="settings.php" class="btn btn-secondary">Settings</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                    </svg>
                </div>
                <div class="stat-label">Total Bottles</div>
                <div class="stat-value"><?php echo $totalBottles; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                </div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                </div>
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?php echo $activeUsers; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/>
                    </svg>
                </div>
                <div class="stat-label">Avg Bottles/User</div>
                <div class="stat-value"><?php echo $totalUsers > 0 ? round($totalBottles / $totalUsers, 1) : 0; ?></div>
            </div>
        </div>

        <!-- Bottles Over Time Chart -->
        <div class="card">
            <h2 class="card-title">📊 Bottles Over Last 7 Days</h2>
            <?php if ($totalBottles > 0): ?>
                <div class="chart-container">
                    <?php 
                    $maxBottles = max(array_values($dailyBottles)) ?: 1;
                    foreach ($dailyBottles as $date => $count): 
                        $height = ($count / $maxBottles) * 100;
                        $displayDate = date('M j', strtotime($date));
                    ?>
                        <div class="chart-bar" style="height: <?php echo $height; ?>%;" title="<?php echo $displayDate; ?>: <?php echo $count; ?> bottles">
                            <span class="chart-value"><?php echo $count; ?></span>
                            <span class="chart-label"><?php echo $displayDate; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No bottle data yet</div>
            <?php endif; ?>
        </div>

        <!-- Active WiFi Sessions -->
        <div class="card">
            <h2 class="card-title">
                <span class="status-dot"></span>
                Active WiFi Sessions
            </h2>
            <?php if (count($activeSessions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Device (MAC)</th>
                            <th>IP Address</th>
                            <th>Time Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSessions as $session): 
                            $timeRemaining = $session['expires_at'] - $currentTime;
                            $minutes = floor($timeRemaining / 60);
                            $seconds = $timeRemaining % 60;
                        ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($session['mac']); ?></code></td>
                                <td><?php echo htmlspecialchars($session['ip']); ?></td>
                                <td class="time-remaining"><?php echo sprintf('%d:%02d', $minutes, $seconds); ?></td>
                                <td><span class="badge badge-active">Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No active WiFi sessions</div>
            <?php endif; ?>
        </div>

        <!-- User Bottle Counts -->
        <div class="card">
            <h2 class="card-title">🏆 Top Users by Bottles</h2>
            <?php if (count($userBottleCounts) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Device (MAC)</th>
                            <th>IP Address</th>
                            <th>Bottles</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach (array_slice($userBottleCounts, 0, 20) as $user): 
                        ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td><code><?php echo htmlspecialchars($user['mac']); ?></code></td>
                                <td><?php echo htmlspecialchars($user['ip']); ?></td>
                                <td><strong style="color: var(--accent);"><?php echo $user['bottles']; ?></strong></td>
                                <td><?php echo $user['last_seen'] ? date('M j, g:i A', strtotime($user['last_seen'])) : 'Unknown'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No users yet</div>
            <?php endif; ?>
        </div>

        <div class="refresh-info">
            📡 Dashboard auto-refreshes every 30 seconds | Last updated: <?php echo date('g:i:s A'); ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>
