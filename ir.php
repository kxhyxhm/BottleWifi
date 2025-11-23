<?php
// IR Sensor Detection Endpoint
// Returns JSON with detection status

header('Content-Type: application/json');

// TODO: Replace this with your actual sensor integration
// Depending on your setup, this could be:
// 1. Shell command to read GPIO (Raspberry Pi)
// 2. Serial port read (Arduino)
// 3. File read from sensor output
// 4. API call to another service

$detected = false;

// ============================================
// Test multiple GPIO pins to find the sensor
// ============================================
// Try common GPIO pins (2, 3, 4, 17, 27, etc.)
$pinsToTest = [2, 3, 4, 17, 22, 27];

foreach ($pinsToTest as $pin) {
    $output = shell_exec("gpio read $pin 2>/dev/null");
    if (trim($output) == '1') {
        $detected = true;
        // Log which pin triggered detection for debugging
        error_log("IR detected on GPIO pin: $pin");
        break;
    }
}

// ============================================
// EXAMPLE 2: Read from a sensor file
// ============================================
// Disabled - using GPIO instead
/*
$sensorFile = '/tmp/ir_sensor_status.txt';
if (file_exists($sensorFile)) {
    $content = trim(file_get_contents($sensorFile));
    $detected = ($content === 'detected' || $content === '1');
}
*/

// ============================================
// EXAMPLE 3: Serial port (Arduino via USB)
// ============================================
// Disabled - using GPIO instead
/*
$portPath = '/dev/ttyUSB0'; // or 'COM3' on Windows
if (function_exists('fopen')) {
    $port = fopen($portPath, 'r');
    if ($port) {
        $data = fread($port, 10);
        fclose($port);
        $detected = (strpos($data, 'detected') !== false || strpos($data, '1') !== false);
    }
}
*/

// ============================================
// EXAMPLE 4: Execute a Python script
// ============================================
// Disabled - using GPIO instead
/*
$output = shell_exec("python3 /path/to/read_sensor.py 2>/dev/null");
$detected = (trim($output) === 'true' || trim($output) === '1');
*/

// ============================================
// EXAMPLE 5: Check a local API endpoint
// ============================================
// Disabled - using GPIO instead
/*
$response = @file_get_contents('http://localhost:8888/sensor');
if ($response) {
    $data = json_decode($response, true);
    $detected = isset($data['detected']) && $data['detected'] === true;
}
*/

// Return JSON response
echo json_encode([
    'detected' => $detected,
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => $detected ? 'bottle_detected' : 'waiting'
]);
?>
