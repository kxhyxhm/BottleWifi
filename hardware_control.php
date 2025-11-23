<?php
// Hardware Control API
// Provides interface between PHP and Python hardware scripts

header('Content-Type: application/json');

$scriptsDir = __DIR__;

// ============================================
// WiFi Control
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'wifi') {
    $subaction = isset($_GET['subaction']) ? $_GET['subaction'] : 'grant';
    $duration = isset($_GET['duration']) ? $_GET['duration'] : 5;
    
    if ($subaction === 'list') {
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py list 2>&1";
    } else if ($subaction === 'grant') {
        // Grant access to all connected devices (no MAC needed)
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py grant all {$duration} 2>&1";
    } else {
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py {$subaction} all 2>&1";
    }
    
    $output = shell_exec($cmd);
    
    if ($output) {
        $data = json_decode($output, true);
        if ($data === null) {
            echo json_encode(['error' => 'Invalid response', 'raw_output' => substr($output, 0, 500)]);
        } else {
            echo json_encode($data);
        }
    } else {
        echo json_encode(['error' => 'Failed to control WiFi']);
    }
    exit();
}

// ============================================
// Sensor Status
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'sensor') {
    $cmd = "python3 {$scriptsDir}/read_ir_sensor.py";
    $output = shell_exec($cmd);
    
    if ($output) {
        $data = json_decode($output, true);
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Failed to read sensor']);
    }
    exit();
}

// Default response
echo json_encode(['error' => 'Invalid action']);
?>
