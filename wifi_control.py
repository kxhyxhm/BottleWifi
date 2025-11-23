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
    Uses iptables rules to control access
    """
    try:
        # Example: Add firewall rule to allow access
        # This depends on your WiFi setup (hostapd, dnsmasq, etc.)
        
        # For a basic setup, you might use:
        # iptables -A FORWARD -m mac --mac-source <MAC> -j ACCEPT
        
        cmd = f"sudo iptables -A FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        subprocess.run(cmd, shell=True, check=True)
        
        # Schedule revocation after duration
        revoke_cmd = f"sudo iptables -D FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        subprocess.run(f"echo '{revoke_cmd}' | at now + {duration_minutes} minutes", 
                      shell=True, check=False)
        
        return {
            'success': True,
            'message': f'WiFi access granted for {duration_minutes} minutes',
            'mac': mac_address,
            'expires_at': (datetime.now() + timedelta(minutes=duration_minutes)).isoformat()
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

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
