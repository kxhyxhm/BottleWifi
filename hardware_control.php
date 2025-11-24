<?php
// Hardware Control API
// Provides interface between PHP and Python hardware scripts
// UPDATED: Now enforces per-device internet access based on bottle donations

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
// WiFi Control
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
    
    // SECURITY: Verify bottle detection token for grant requests
    if ($subaction === 'grant') {
        $verificationToken = isset($_GET['token']) ? $_GET['token'] : '';
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $clientMAC = getClientMAC();
        
        // CRITICAL SECURITY CHECK: Verify device has bottle-donated session
        $sessionManager->cleanupExpiredSessions();
        
        // Get session by verification token
        $allSessions = $sessionManager->getAllSessions();
        $session = isset($allSessions[$verificationToken]) ? $allSessions[$verificationToken] : null;
        
        if (!$session) {
            echo json_encode([
                'error' => 'NO_BOTTLE_DETECTED',
                'message' => 'This device has not donated a bottle yet',
                'details' => 'You must drop a bottle to get internet access. No valid session found.',
                'severity' => 'SECURITY_VIOLATION',
                'blocked' => true
            ]);
            error_log("[ACCESS_DENIED] MAC: {$clientMAC}, IP: {$clientIP}, Reason: No session for token");
            exit();
        }
        
        if (!$session['bottle_donated']) {
            echo json_encode([
                'error' => 'BOTTLE_REQUIRED',
                'message' => 'Internet access requires bottle donation',
                'details' => 'Session exists but bottle_donated = false',
                'severity' => 'SECURITY_VIOLATION',
                'blocked' => true
            ]);
            error_log("[ACCESS_DENIED] MAC: {$clientMAC}, IP: {$clientIP}, Reason: bottle_donated = false");
            exit();
        }
        
        if ($session['expires_at'] < time()) {
            echo json_encode([
                'error' => 'SESSION_EXPIRED',
                'message' => 'Bottle detection session has expired',
                'severity' => 'ERROR'
            ]);
            exit();
        }
        
        // Mark session as internet granted
        if (!$sessionManager->grantInternetAccess($verificationToken, $clientMAC)) {
            echo json_encode([
                'error' => 'GRANT_FAILED',
                'message' => 'Failed to mark session as granted'
            ]);
            exit();
        }
    }
    
    if ($subaction === 'list') {
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py list 2>&1";
        $output = shell_exec($cmd);
        
        if ($output) {
            $data = json_decode($output, true);
            echo json_encode($data ?: ['error' => 'Invalid response', 'raw_output' => substr($output, 0, 500)]);
        } else {
            echo json_encode(['error' => 'Failed to list devices']);
        }
        exit();
    } else if ($subaction === 'grant') {
        // Get the MAC address of the device making the request
        $clientIP = $_SERVER['REMOTE_ADDR'];
        
        // Try to get MAC from ARP table
        $arp = shell_exec("arp -n {$clientIP} 2>&1");
        $mac = null;
        
        if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $arp, $matches)) {
            $mac = strtoupper(str_replace('-', ':', $matches[0]));
        } else {
            // Try ip neigh command
            $ipneigh = shell_exec("ip neigh show {$clientIP} 2>&1");
            if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $ipneigh, $matches)) {
                $mac = strtoupper(str_replace('-', ':', $matches[0]));
            }
        }
        
        if (!$mac) {
            echo json_encode([
                'error' => 'Could not determine device MAC address',
                'ip' => $clientIP,
                'debug' => 'Device must be in ARP table. Ensure device is connected to WiFi.'
            ]);
            exit();
        }
        
        // Check if this device already has access
        $sessionsFile = __DIR__ . '/wifi_sessions.json';
        $sessions = [];
        if (file_exists($sessionsFile)) {
            $sessions = json_decode(file_get_contents($sessionsFile), true) ?: [];
        }
        
        // Check for existing active session
        $currentTime = time();
        $hasActiveSession = false;
        foreach ($sessions as $session) {
            if ($session['mac'] === $mac && $session['expires_at'] > $currentTime) {
                $hasActiveSession = true;
                echo json_encode([
                    'error' => 'Device already has active WiFi access',
                    'mac' => $mac,
                    'expires_at' => date('Y-m-d H:i:s', $session['expires_at']),
                    'time_remaining' => $session['expires_at'] - $currentTime,
                    'message' => 'Please wait for your current session to expire before dropping another bottle'
                ]);
                exit();
            }
        }
        
        // Additional security: Verify firewall rule doesn't already exist
        $checkRule = shell_exec("sudo iptables -C FORWARD -m mac --mac-source {$mac} -j ACCEPT 2>&1");
        if (strpos($checkRule, 'Bad rule') === false && !$hasActiveSession) {
            // Rule exists but no session - remove orphaned rule
            shell_exec("sudo iptables -D FORWARD -m mac --mac-source {$mac} -j ACCEPT 2>&1");
        }
        
        // Grant access to specific MAC address only
        $cmd = "sudo python3 {$scriptsDir}/wifi_control.py grant {$mac} {$duration} 2>&1";
        $output = shell_exec($cmd);
        
        if ($output) {
            $data = json_decode($output, true);
            if ($data && isset($data['success']) && $data['success']) {
                // Log the session
                $sessions[] = [
                    'mac' => $mac,
                    'ip' => $clientIP,
                    'granted_at' => $currentTime,
                    'expires_at' => $currentTime + ($duration * 60),
                    'duration_minutes' => $duration
                ];
                file_put_contents($sessionsFile, json_encode($sessions, JSON_PRETTY_PRINT));
                
                echo json_encode($data);
            } else {
                echo json_encode($data ?: ['error' => 'Failed to grant access', 'raw_output' => substr($output, 0, 500)]);
            }
        } else {
            echo json_encode(['error' => 'Failed to execute WiFi control']);
        }
        exit();
    } else if ($subaction === 'revoke') {
        // Get MAC address from parameter or client IP
        $mac = isset($_GET['mac']) ? $_GET['mac'] : null;
        
        if (!$mac) {
            $clientIP = $_SERVER['REMOTE_ADDR'];
            $arp = shell_exec("arp -n {$clientIP} 2>&1");
            if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $arp, $matches)) {
                $mac = strtoupper(str_replace('-', ':', $matches[0]));
            }
        }
        
        if (!$mac) {
            echo json_encode(['error' => 'Could not determine device MAC address']);
            exit();
        }
        
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

// Default response
echo json_encode(['error' => 'Invalid action']);

// ============================================
// Helper function to get device MAC address
// ============================================
function getClientMAC() {
    $mac = shell_exec("arp -a " . $_SERVER['REMOTE_ADDR'] . " 2>/dev/null | grep -oE '([0-9a-fA-F]{2}:){5}([0-9a-fA-F]{2})'");
    
    if (!$mac) {
        // Fallback: create identifier from IP
        $ip_parts = explode('.', $_SERVER['REMOTE_ADDR']);
        $mac = sprintf('%02x:%02x:%02x:%02x:%02x:%02x', 
            $ip_parts[0], $ip_parts[1], $ip_parts[2], $ip_parts[3], 0, 0);
    }
    
    return trim($mac);
}
?>
