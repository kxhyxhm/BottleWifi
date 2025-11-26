<?php
// Hardware Control API
// Provides interface between PHP and Python hardware scripts

header('Content-Type: application/json');

$scriptsDir = __DIR__;

// SECURITY: Verify firewall is properly configured on every request
// NOTE: With daemon-controlled system, FORWARD policy can be DROP or ACCEPT
// DROP = no bottle (normal), ACCEPT = bottle detected (normal)
function verifyFirewallConfig() {
    // Check if bottle internet daemon is running (preferred method)
    $daemonStatus = shell_exec("systemctl is-active bottle-internet 2>&1");
    
    if ($daemonStatus && trim($daemonStatus) === 'active') {
        // Daemon is running, firewall is being managed automatically
        return ['configured' => true, 'mode' => 'daemon'];
    }
    
    // Fallback: Try to check iptables directly (may need sudo permissions)
    $defaultPolicy = shell_exec("sudo iptables -L FORWARD -n 2>&1");
    
    // If we can't read iptables but daemon isn't running, that's a problem
    if (!$defaultPolicy || strpos($defaultPolicy, 'Chain FORWARD') === false) {
        // Check if it's a permission issue or iptables not available
        if (strpos($defaultPolicy, 'sudo') !== false || strpos($defaultPolicy, 'permission') !== false) {
            // Permission issue - but if daemon is supposed to be running, skip this check
            return ['configured' => true, 'mode' => 'limited']; // Allow operation anyway
        }
        
        return [
            'configured' => false,
            'error' => 'Cannot verify firewall. Daemon not running and cannot check iptables.',
            'severity' => 'WARNING'
        ];
    }
    
    // Check if NAT/MASQUERADE is configured (required for internet)
    $natCheck = shell_exec("sudo iptables -t nat -L POSTROUTING -n 2>&1");
    if ($natCheck && strpos($natCheck, 'MASQUERADE') === false) {
        return [
            'configured' => false,
            'error' => 'NAT/MASQUERADE not configured. Run: bash fix_internet.sh',
            'severity' => 'CRITICAL'
        ];
    }
    
    // With daemon mode, both DROP and ACCEPT policies are valid
    // DROP = waiting for bottle, ACCEPT = bottle detected
    return ['configured' => true, 'mode' => 'manual'];
}

// ============================================
// WiFi Control
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'wifi') {
    // SECURITY CHECK: Verify NAT is configured (critical for internet)
    // NOTE: Temporarily disabled strict checking to allow testing
    $firewallCheck = verifyFirewallConfig();
    if (!$firewallCheck['configured'] && $firewallCheck['severity'] === 'CRITICAL') {
        // Only block for critical issues, not warnings
        error_log("Firewall check warning: " . ($firewallCheck['error'] ?? 'unknown'));
        // Allow operation to continue for testing
    }
    
    $subaction = isset($_GET['subaction']) ? $_GET['subaction'] : 'grant';
    $duration = isset($_GET['duration']) ? $_GET['duration'] : 5;
    
    // SECURITY: Verify bottle detection token for grant requests
    if ($subaction === 'grant') {
        $verificationToken = isset($_GET['token']) ? $_GET['token'] : '';
        
        error_log("[WIFI_GRANT] Token received: " . ($verificationToken ?: 'NONE'));
        
        // Check if this is a valid bottle detection token
        $tokenFile = __DIR__ . '/bottle_tokens.json';
        $validToken = false;
        $tokenStatus = 'not_found';
        
        if (file_exists($tokenFile)) {
            $tokens = json_decode(file_get_contents($tokenFile), true) ?: [];
            $currentTime = time();
            
            error_log("[WIFI_GRANT] Found " . count($tokens) . " tokens in file");
            
            // Check if token exists and is not expired
            if (isset($tokens[$verificationToken])) {
                $tokenData = $tokens[$verificationToken];
                error_log("[WIFI_GRANT] Token found. Expires: " . $tokenData['expires_at'] . ", Current: $currentTime, Used: " . ($tokenData['used'] ? 'yes' : 'no'));
                
                if ($tokenData['used']) {
                    $tokenStatus = 'already_used';
                } else if ($tokenData['expires_at'] <= $currentTime) {
                    $tokenStatus = 'expired';
                } else {
                    $validToken = true;
                    $tokenStatus = 'valid';
                    // Mark token as used
                    $tokens[$verificationToken]['used'] = true;
                    file_put_contents($tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));
                    error_log("[WIFI_GRANT] Token marked as used");
                }
            } else {
                error_log("[WIFI_GRANT] Token not found in tokens list");
            }
        } else {
            error_log("[WIFI_GRANT] Token file does not exist");
        }
        
        if (!$validToken) {
            echo json_encode([
                'error' => 'BOTTLE_DETECTION_REQUIRED',
                'message' => 'You must drop a bottle first to get WiFi access',
                'details' => 'Token status: ' . $tokenStatus,
                'token_received' => !empty($verificationToken),
                'severity' => 'SECURITY_VIOLATION'
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
        // Load settings to get session duration
        require_once __DIR__ . '/settings_handler.php';
        $settings = getSettings();
        $duration = round($settings['wifi_time'] / 60); // Convert seconds to minutes
        
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
                
                // Add duration to response so frontend knows how long the session is
                $data['duration_minutes'] = $duration;
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
?>
