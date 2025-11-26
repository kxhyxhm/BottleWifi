<?php
// Function to read settings
function getSettings() {
    $settingsFile = __DIR__ . '/wifi_settings.json';
    
    $defaultSettings = [
        'wifi_time' => 3600, // Default 1 hour in seconds
        'ssid' => 'BottleWifi',
        'security_mode' => 'WPA3-Personal',
        'channel' => 'Auto',
        'firewall_enabled' => true
    ];

    if (file_exists($settingsFile)) {
        $content = file_get_contents($settingsFile);
        if ($content !== false) {
            $settings = json_decode($content, true);
            if (is_array($settings)) {
                return array_merge($defaultSettings, $settings);
            }
        }
    }
    
    return $defaultSettings;
}

// Function to save settings
function saveSettings($settings) {
    $settingsFile = __DIR__ . '/wifi_settings.json';
    $result = file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log("Failed to write settings to $settingsFile");
        return false;
    }
    return true;
}
?>
