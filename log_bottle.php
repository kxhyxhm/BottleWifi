<?php
// Bottle recycling history tracking
// Logs each recycling event with timestamp and details

header('Content-Type: application/json');

$dataFile = 'recycling_data.json';

// Initialize empty data structure if file doesn't exist
if (!file_exists($dataFile)) {
    $initialData = [
        'total_bottles' => 0,
        'total_minutes_distributed' => 0,
        'sessions' => [],
        'created_date' => date('Y-m-d H:i:s')
    ];
    file_put_contents($dataFile, json_encode($initialData, JSON_PRETTY_PRINT));
}

// Handle POST request to log a recycling event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'log_bottle') {
        $recyclingData = json_decode(file_get_contents($dataFile), true);
        
        $recyclingData['total_bottles']++;
        $recyclingData['total_minutes_distributed'] += 5; // 1 bottle = 5 minutes
        
        $recyclingData['sessions'][] = [
            'bottle_number' => $recyclingData['total_bottles'],
            'timestamp' => date('Y-m-d H:i:s'),
            'minutes_granted' => 5,
            'device_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100)
        ];
        
        file_put_contents($dataFile, json_encode($recyclingData, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true,
            'message' => 'Bottle logged successfully',
            'total_bottles' => $recyclingData['total_bottles']
        ]);
        exit();
    }
}

// Handle GET request to retrieve statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recyclingData = json_decode(file_get_contents($dataFile), true);
    
    echo json_encode([
        'total_bottles' => $recyclingData['total_bottles'],
        'total_minutes' => $recyclingData['total_minutes_distributed'],
        'session_count' => count($recyclingData['sessions']),
        'latest_sessions' => array_slice($recyclingData['sessions'], -10) // Last 10 sessions
    ]);
    exit();
}
?>
