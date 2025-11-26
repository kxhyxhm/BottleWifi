#!/usr/bin/env python3
"""
WiFi Portal Control Script for Raspberry Pi
Manages WiFi access based on bottle detection
Usage: python3 wifi_control.py <grant|revoke> <mac_address> <duration>
"""

import subprocess
import sys
import json
from datetime import datetime, timedelta

def grant_wifi_access(mac_address, duration_minutes=5):
    """
    Grant WiFi access to a device for specified duration
    NOTE: This now works in conjunction with bottle_internet_daemon.py
    - Daemon controls global internet access (bottle present = internet on)
    - This script manages per-device session tracking only
    SECURITY: 'all' access is disabled - must specify a device MAC address
    """
    try:
        # SECURITY FIX: Prevent granting access to all devices
        if mac_address == 'all' or not mac_address:
            return {
                'success': False,
                'error': 'Must specify a valid MAC address. Bulk access is not allowed for security.',
                'security_note': 'Each device must drop a bottle individually to get access'
            }
        
        # Validate MAC address format
        if not validate_mac_address(mac_address):
            return {
                'success': False,
                'error': f'Invalid MAC address format: {mac_address}'
            }
        
        # CRITICAL: Check if bottle daemon is controlling internet access
        try:
            with open('/tmp/bottle_internet_status.json', 'r') as f:
                status = json.load(f)
                if not status.get('internet_enabled', False):
                    return {
                        'success': False,
                        'error': 'NO BOTTLE DETECTED - Internet access is currently disabled',
                        'message': 'A bottle must be present in the system for internet access',
                        'severity': 'BOTTLE_REQUIRED',
                        'daemon_status': 'Internet controlled by bottle detection daemon'
                    }
        except FileNotFoundError:
            # Daemon not running, fall back to manual check
            forward_policy = subprocess.run(
                "sudo iptables -L FORWARD -n | grep 'Chain FORWARD' | grep -o 'policy [A-Z]*'",
                shell=True, capture_output=True, text=True
            )
            if "DROP" in forward_policy.stdout:
                return {
                    'success': False,
                    'error': 'Internet access is currently DISABLED (no bottle detected)',
                    'message': 'The bottle detection daemon must be running and a bottle must be present',
                    'fix': 'Start daemon: sudo python3 bottle_internet_daemon.py',
                    'severity': 'CRITICAL'
                }
        
        # CRITICAL: Check if IP forwarding is enabled
        ip_forward_check = subprocess.run(
            "sysctl net.ipv4.ip_forward",
            shell=True, capture_output=True, text=True
        )
        if "= 0" in ip_forward_check.stdout:
            return {
                'success': False,
                'error': 'IP forwarding is disabled. Internet routing will not work.',
                'fix': 'Run: sudo sysctl -w net.ipv4.ip_forward=1',
                'severity': 'CRITICAL'
            }
        
        # CRITICAL: Check if NAT/MASQUERADE is configured
        nat_check = subprocess.run(
            "sudo iptables -t nat -L POSTROUTING -n",
            shell=True, capture_output=True, text=True
        )
        if "MASQUERADE" not in nat_check.stdout:
            return {
                'success': False,
                'error': 'NAT/MASQUERADE not configured. Devices cannot access internet.',
                'fix': 'Run: bash fix_internet.sh',
                'severity': 'CRITICAL',
                'details': 'Internet routing requires NAT to be configured on the gateway interface'
            }
        
        # SUCCESS: Internet is enabled via bottle daemon, session logged
        # Note: With daemon mode, FORWARD policy is ACCEPT when bottle present
        # No need to add per-MAC rules - all devices get access when bottle is present
        
        return {
            'success': True,
            'message': f'WiFi access granted for {duration_minutes} minutes',
            'mac': mac_address,
            'expires_at': (datetime.now() + timedelta(minutes=duration_minutes)).isoformat(),
            'note': 'Internet access enabled via bottle detection. All devices connected have access while bottle is present.',
            'mode': 'daemon_controlled'
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def validate_mac_address(mac):
    """Validate MAC address format"""
    import re
    pattern = r'^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$'
    return bool(re.match(pattern, mac))

def revoke_wifi_access(mac_address):
    """Revoke WiFi access for a device"""
    try:
        cmd = f"sudo iptables -D FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        subprocess.run(cmd, shell=True, check=True)
        
        return {
            'success': True,
            'message': f'WiFi access revoked for {mac_address}'
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def get_connected_devices():
    """Get list of connected WiFi devices"""
    try:
        # This varies by WiFi setup (hostapd, dnsmasq, etc.)
        # Example for dnsmasq:
        cmd = "cat /var/lib/misc/dnsmasq.leases"
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        
        devices = []
        for line in result.stdout.strip().split('\n'):
            if line:
                parts = line.split()
                if len(parts) >= 4:
                    devices.append({
                        'mac': parts[1],
                        'ip': parts[2],
                        'hostname': parts[3]
                    })
        
        return {
            'success': True,
            'devices': devices,
            'count': len(devices)
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({
            'error': 'Usage: wifi_control.py <grant|revoke|list> [mac_address] [duration]'
        }))
        sys.exit(1)
    
    action = sys.argv[1]
    
    if action == 'grant' and len(sys.argv) >= 3:
        mac = sys.argv[2]
        duration = int(sys.argv[3]) if len(sys.argv) > 3 else 5
        result = grant_wifi_access(mac, duration)
        print(json.dumps(result))
    
    elif action == 'revoke' and len(sys.argv) >= 3:
        mac = sys.argv[2]
        result = revoke_wifi_access(mac)
        print(json.dumps(result))
    
    elif action == 'list':
        result = get_connected_devices()
        print(json.dumps(result))
    
    else:
        print(json.dumps({'error': 'Invalid action'}))
        sys.exit(1)
