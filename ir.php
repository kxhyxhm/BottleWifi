<?php
/**
 * UPDATED IR Sensor Detection Endpoint with Session Management
 * Now enforces per-device internet access:
 * - When bottle is detected, creates a session with bottle_donated = true
 * - Session ID serves as verification token
 * - Only sessions with bottle_donated can grant internet access
 */

require_once 'session_manager.php';

header('Content-Type: application/json');

$detected = false;
$error = null;
$debug = [];
$sessionManager = new SessionManager();

// ============================================
// Step 1: Check if Python script exists
// ============================================
$pythonScript = __DIR__ . '/read_ir_sensor.py';

if (!file_exists($pythonScript)) {
    $error = "Python script not found at: {$pythonScript}";
    $debug['error_type'] = 'FILE_NOT_FOUND';
    $debug['expected_path'] = $pythonScript;
    
    echo json_encode([
        'detected' => false,
        'error' => $error,
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ============================================
// Step 2: Check if Python3 is installed
// ============================================
$pythonCheck = shell_exec("python3 --version 2>&1");
if (!$pythonCheck || strpos($pythonCheck, 'not found') !== false) {
    $error = "Python3 is not installed or not in PATH";
    $debug['error_type'] = 'PYTHON_NOT_FOUND';
    $debug['python_check_output'] = $pythonCheck ?: 'empty';
    
    echo json_encode([
        'detected' => false,
        'error' => $error,
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

$debug['python_version'] = trim($pythonCheck);

// ============================================
// Step 3: Execute Python script
// ============================================
$output = shell_exec("sudo python3 $pythonScript 2>&1");

if (!$output) {
    $error = "No output from Python script - check GPIO permissions and sensor wiring";
    $debug['error_type'] = 'NO_OUTPUT';
    $debug['possible_causes'] = [
        'GPIO permissions not configured',
        'Sensor not wired correctly',
        'Python script has runtime error',
        'GPIO library not installed'
    ];
    
    echo json_encode([
        'detected' => false,
        'error' => $error,
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ============================================
// Step 4: Validate JSON response
// ============================================
$sensorData = json_decode($output, true);

if ($sensorData === null) {
    $error = "Invalid JSON response from Python script";
    $debug['error_type'] = 'INVALID_JSON';
    $debug['raw_output'] = $output;
    $debug['json_error'] = json_last_error_msg();
    $debug['output_length'] = strlen($output);
    $debug['first_char'] = strlen($output) > 0 ? ord($output[0]) : null;
    
    echo json_encode([
        'detected' => false,
        'error' => $error,
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ============================================
// Step 5: Check for Python errors
// ============================================
if (isset($sensorData['error'])) {
    $error = $sensorData['error'];
    $debug['error_type'] = 'PYTHON_ERROR';
    $debug['python_error'] = $error;
    
    echo json_encode([
        'detected' => false,
        'error' => "Sensor error: {$error}",
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ============================================
// Step 6: Extract detection status
// ============================================
if (!isset($sensorData['detected'])) {
    $error = "Missing 'detected' field in sensor response";
    $debug['error_type'] = 'MISSING_FIELD';
    $debug['response_keys'] = array_keys($sensorData);
    
    echo json_encode([
        'detected' => false,
        'error' => $error,
        'debug' => $debug,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

$detected = $sensorData['detected'];
$verificationToken = null;

// ============================================
// Step 7: CREATE SESSION WITH BOTTLE STATUS
// ============================================
if ($detected) {
    error_log("[BOTTLE_DETECTED] " . date('Y-m-d H:i:s'));
    
    // Get device MAC address
    $clientMAC = getClientMAC();
    $clientIP = $_SERVER['REMOTE_ADDR'];
    
    // Generate session token for this bottle detection
    $verificationToken = bin2hex(random_bytes(16));
    
    // CREATE SESSION WITH bottle_donated = true
    // This is the key: only devices that drop a bottle get this flag
    $session = $sessionManager->createSessionWithBottle(
        $verificationToken,
        $clientIP,
        $clientMAC,
        5  // 5 minutes per bottle
    );
    
    error_log("[SESSION_CREATED] Token: {$verificationToken}, MAC: {$clientMAC}, IP: {$clientIP}, bottle_donated: true");
}

// ============================================
// Return successful response
// ============================================
echo json_encode([
    'detected' => $detected,
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => $detected ? 'bottle_detected' : 'waiting',
    'verification_token' => $verificationToken,
    'raw_pin_value' => $sensorData['pin_state'] ?? null,
    'debug' => [
        'python_version' => $debug['python_version'],
        'script_path' => $pythonScript,
        'script_exists' => true,
        'sensor_response' => $sensorData
    ]
]);

// ============================================
// Helper function to get device MAC address
// ============================================
function getClientMAC() {
    // Try to get MAC from various methods
    $mac = shell_exec("arp -a " . $_SERVER['REMOTE_ADDR'] . " 2>/dev/null | grep -oE '([0-9a-fA-F]{2}:){5}([0-9a-fA-F]{2})'");
    
    if (!$mac) {
        // Fallback: create MAC-like identifier from IP
        $ip_parts = explode('.', $_SERVER['REMOTE_ADDR']);
        $mac = sprintf('%02x:%02x:%02x:%02x:%02x:%02x', $ip_parts[0], $ip_parts[1], $ip_parts[2], $ip_parts[3], 0, 0);
    }
    
    return trim($mac);
}
?>
