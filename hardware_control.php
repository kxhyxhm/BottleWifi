<?php
/**
 * UPDATED Hardware Control API
 * Now enforces per-device internet access control
 * 
 * KEY CHANGE: Checks if device has active bottle-donated session
 * before granting WiFi access via iptables rules
 */

require_once 'session_manager.php';

header('Content-Type: application/json');

$scriptsDir = __DIR__;
$sessionManager = new SessionManager();

// SECURITY: Verify firewall is properly configured on every request
function verifyFirewallConfig() {
    $defaultPolicy = shell_exec("sudo iptables -L FORWARD -n | grep 'Chain FORWARD' 2>&1");
    
    if (!$defaultPolicy) {
        return [
            'configured' => false,
            'error' => 'Cannot read firewall rules. Check sudo permissions.',
            'severity' => 'CRITICAL'
        ];
    }
    
    if (strpos($defaultPolicy, '(policy ACCEPT)') !== false) {
        return [
            'configured' => false,
            'error' => 'Firewall default policy is ACCEPT - all devices have unrestricted access',
            'fix' => 'Run: bash fix_firewall.sh',
            'severity' => 'CRITICAL'
        ];
    }
    
    return ['configured' => true];
}

// ============================================
// WiFi Control - UPDATED WITH SESSION CHECKS
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'wifi') {
    // SECURITY CHECK: Verify firewall before processing any WiFi requests
    $firewallCheck = verifyFirewallConfig();
    if (!$firewallCheck['configured']) {
        echo json_encode([
            'error' => 'FIREWALL MISCONFIGURED',
            'details' => $firewallCheck['error'],
            'fix' => $firewallCheck['fix'] ?? 'Contact administrator',
            'severity' => $firewallCheck['severity'],
            'blocked' => true
        ]);
        exit();
    }
    
    $subaction = isset($_GET['subaction']) ? $_GET['subaction'] : 'grant';
    $duration = isset($_GET['duration']) ? $_GET['duration'] : 5;
    
    // ============================================
    // GRANT WiFi - WITH BOTTLE VALIDATION
    // ============================================
    if ($subaction === 'grant') {
        $verificationToken = isset($_GET['token']) ? $_GET['token'] : '';
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $clientMAC = getClientMAC();
        
        // CRITICAL SECURITY CHECK: Verify session has bottle_donated flag
        $sessionManager->cleanupExpiredSessions();
        
        $session = $sessionManager->getActiveSessionByMAC($clientMAC);
        
        if (!$session) {
            // No valid session with bottle for this device
            echo json_encode([
                'error' => 'NO_BOTTLE_DETECTED',
                'message' => 'This device has not donated a bottle yet',
                'details' => 'You must drop a bottle to get internet access. Devices without bottle donations cannot access internet.',
                'severity' => 'SECURITY_VIOLATION',
                'blocked' => true
            ]);
            error_log("[ACCESS_DENIED] MAC: {$clientMAC}, IP: {$clientIP}, Reason: No bottle-donated session");
            exit();
        }
        
        if (!$session['bottle_donated']) {
            // Session exists but bottle_donated is false
            echo json_encode([
                'error' => 'BOTTLE_REQUIRED',
                'message' => 'Internet access requires bottle donation',
                'details' => 'Your device is connected but must drop a bottle to get internet access',
                'severity' => 'SECURITY_VIOLATION',
                'blocked' => true
            ]);
            error_log("[ACCESS_DENIED] MAC: {$clientMAC}, IP: {$clientIP}, Reason: bottle_donated = false");
            exit();
        }
        
        // Session is valid and has bottle_donated = true, grant WiFi access
        if (!$sessionManager->grantInternetAccess($session['session_id'], $clientMAC)) {
            echo json_encode([
                'error' => 'SESSION_INVALID',
                'message' => 'Cannot grant access - session invalid or expired',
                'severity' => 'ERROR'
            ]);
            exit();
        }
        
        // Grant iptables rule for this MAC address
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py grant {$clientMAC} {$duration} 2>&1";
        $output = shell_exec($cmd);
        
        if ($output) {
            $data = json_decode($output, true);
            if ($data && isset($data['success']) && $data['success']) {
                error_log("[ACCESS_GRANTED] MAC: {$clientMAC}, IP: {$clientIP}, Duration: {$duration}min, bottle_donated: true");
                echo json_encode([
                    'success' => true,
                    'message' => 'WiFi access granted',
                    'mac' => $clientMAC,
                    'duration' => $duration,
                    'session_id' => $session['session_id'],
                    'bottle_donated' => true
                ]);
                exit();
            } else {
                echo json_encode($data ?: ['error' => 'Failed to grant access']);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Command execution failed']);
            exit();
        }
    }
    
    // ============================================
    // REVOKE WiFi
    // ============================================
    if ($subaction === 'revoke') {
        $mac = isset($_GET['mac']) ? $_GET['mac'] : '';
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py revoke {$mac} 2>&1";
        $output = shell_exec($cmd);
        
        if ($output) {
            $data = json_decode($output, true);
            echo json_encode($data ?: ['error' => 'Invalid response', 'raw_output' => substr($output, 0, 500)]);
        } else {
            echo json_encode(['error' => 'Failed to revoke access']);
        }
        exit();
    }
    
    echo json_encode(['error' => 'Invalid subaction']);
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

// ============================================
// Session Stats - For Admin Dashboard
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    $stats = $sessionManager->getStats();
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Default response
echo json_encode(['error' => 'Invalid action']);

// ============================================
// Helper function to get device MAC address
// ============================================
function getClientMAC() {
    $mac = shell_exec("arp -a " . $_SERVER['REMOTE_ADDR'] . " 2>/dev/null | grep -oE '([0-9a-fA-F]{2}:){5}([0-9a-fA-F]{2})'");
    
    if (!$mac) {
        $ip_parts = explode('.', $_SERVER['REMOTE_ADDR']);
        $mac = sprintf('%02x:%02x:%02x:%02x:%02x:%02x', $ip_parts[0], $ip_parts[1], $ip_parts[2], $ip_parts[3], 0, 0);
    }
    
    return trim($mac);
}
?>
