#!/usr/bin/env python3
"""
IR Sensor Reading Script for Raspberry Pi
Reads GPIO pin for object/bottle detection
The sensor outputs HIGH when object is detected (bottle near)
Usage: python3 read_ir_sensor.py
"""

import RPi.GPIO as GPIO
import time
import json
import sys

# GPIO Configuration
IR_PIN = 2  # Change this to your actual GPIO pin

def setup_gpio():
    """Initialize GPIO"""
    GPIO.setmode(GPIO.BCM)
    GPIO.setup(IR_PIN, GPIO.IN)

def read_sensor():
    """
    Read IR sensor
    Returns True if object/bottle is detected (GPIO HIGH)
    Returns False if no object (GPIO LOW)
    """
    try:
        pin_state = GPIO.input(IR_PIN)
        return pin_state == 1  # 1 = object detected, 0 = no object
    except Exception as e:
        print(f"Error reading sensor: {e}", file=sys.stderr)
        return False

def cleanup():
    """Clean up GPIO"""
    GPIO.cleanup()

if __name__ == "__main__":
    try:
        setup_gpio()
        detected = read_sensor()
        
        # Output JSON for PHP to parse
        output = {
            'detected': detected,
            'pin': IR_PIN,
            'status': 'bottle_detected' if detected else 'waiting'
        }
        print(json.dumps(output))
        
    except KeyboardInterrupt:
        print("Interrupted", file=sys.stderr)
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        output = {'detected': False, 'error': str(e)}
        print(json.dumps(output))
    finally:
        cleanup()
