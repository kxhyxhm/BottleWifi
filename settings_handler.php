<?php
// Function to read settings
function getSettings() {
    $defaultSettings = [
        'wifi_time' => 3600, // Default 1 hour in seconds
        'ssid' => 'BottleWifi',
        'security_mode' => 'WPA3-Personal',
        'channel' => 'Auto',
        'firewall_enabled' => true
    ];

    if (file_exists('wifi_settings.json')) {
        $settings = json_decode(file_get_contents('wifi_settings.json'), true);
        return array_merge($defaultSettings, $settings);
    }
    
    return $defaultSettings;
}

// Function to save settings
function saveSettings($settings) {
    file_put_contents('wifi_settings.json', json_encode($settings, JSON_PRETTY_PRINT));
}
?>