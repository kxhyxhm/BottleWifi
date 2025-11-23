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
    $mac = isset($_GET['mac']) ? $_GET['mac'] : '';
    $duration = isset($_GET['duration']) ? $_GET['duration'] : 5;
    
    if ($subaction === 'list') {
        $cmd = "python3 {$scriptsDir}/wifi_control.py list";
    } else {
        $cmd = "python3 {$scriptsDir}/wifi_control.py {$subaction} {$mac} {$duration}";
    }
    
    $output = shell_exec($cmd);
    
    if ($output) {
        $data = json_decode($output, true);
        echo json_encode($data);
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
