"""
detect_log_buffer.py

Module for detecting the available log buffer duration on connected Android devices.
Queries the device to determine the oldest available log entry and calculates
the time span of available logs.
"""

import subprocess
import re
from datetime import datetime, timedelta
import sys

# Fix Windows encoding issues with emoji characters
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except AttributeError:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


def detect_buffer():
    """
    Detect the available log buffer on the connected Android device.
    
    Returns:
        dict: {
            'success': bool,
            'oldest_timestamp': datetime or None,
            'duration_hours': float or None,
            'duration_days': float or None,
            'error': str or None
        }
    """
    try:
        # Try to get the oldest log entry using adb logcat -t 1
        # This gets the oldest entry in the buffer
        result = subprocess.run(
            ['adb', 'logcat', '-d', '-v', 'time', '-t', '1'],
            capture_output=True,
            text=True,
            encoding='utf-8',
            errors='replace',
            timeout=10
        )
        
        if result.returncode != 0:
            return {
                'success': False,
                'oldest_timestamp': None,
                'duration_hours': None,
                'duration_days': None,
                'error': 'ADB command failed. Is device connected?'
            }
        
        output = result.stdout.strip()
        
        if not output:
            return {
                'success': False,
                'oldest_timestamp': None,
                'duration_hours': None,
                'duration_days': None,
                'error': 'No logs found in buffer'
            }
        
        # Parse timestamp from logcat output
        # Format: MM-DD HH:MM:SS.mmm
        timestamp_match = re.search(r'(\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})', output)
        
        if not timestamp_match:
            return {
                'success': False,
                'oldest_timestamp': None,
                'duration_hours': None,
                'duration_days': None,
                'error': 'Could not parse timestamp from log'
            }
        
        # Parse the timestamp
        timestamp_str = timestamp_match.group(1)
        current_year = datetime.now().year
        
        try:
            oldest_time = datetime.strptime(f"{current_year}-{timestamp_str}", "%Y-%m-%d %H:%M:%S")
            
            # Handle year rollover: if parsed time is in the future, it's from last year
            if oldest_time > datetime.now():
                oldest_time = oldest_time.replace(year=current_year - 1)
            
            # Calculate duration
            now = datetime.now()
            duration = now - oldest_time
            duration_hours = duration.total_seconds() / 3600
            duration_days = duration.total_seconds() / 86400
            
            return {
                'success': True,
                'oldest_timestamp': oldest_time,
                'duration_hours': duration_hours,
                'duration_days': duration_days,
                'error': None
            }
            
        except ValueError as e:
            return {
                'success': False,
                'oldest_timestamp': None,
                'duration_hours': None,
                'duration_days': None,
                'error': f'Timestamp parsing error: {str(e)}'
            }
    
    except subprocess.TimeoutExpired:
        return {
            'success': False,
            'oldest_timestamp': None,
            'duration_hours': None,
            'duration_days': None,
            'error': 'ADB command timed out'
        }
    
    except FileNotFoundError:
        return {
            'success': False,
            'oldest_timestamp': None,
            'duration_hours': None,
            'duration_days': None,
            'error': 'ADB not found. Please install Android SDK Platform Tools'
        }
    
    except Exception as e:
        return {
            'success': False,
            'oldest_timestamp': None,
            'duration_hours': None,
            'duration_days': None,
            'error': f'Unexpected error: {str(e)}'
        }


def get_device_info():
    """
    Get basic device information.
    
    Returns:
        dict: {
            'success': bool,
            'device_model': str or None,
            'android_version': str or None,
            'error': str or None
        }
    """
    try:
        # Get device model
        model_result = subprocess.run(
            ['adb', 'shell', 'getprop', 'ro.product.model'],
            capture_output=True,
            text=True,
            timeout=15
        )
        
        # Get Android version
        version_result = subprocess.run(
            ['adb', 'shell', 'getprop', 'ro.build.version.release'],
            capture_output=True,
            text=True,
            timeout=15
        )
        
        # Get Kernel version
        kernel_result = subprocess.run(
            ['adb', 'shell', 'uname', '-r'],
            capture_output=True,
            text=True,
            timeout=15
        )
        
        device_model = model_result.stdout.strip() if model_result.returncode == 0 else 'Unknown'
        android_version = version_result.stdout.strip() if version_result.returncode == 0 else 'Unknown'
        kernel_version = kernel_result.stdout.strip() if kernel_result.returncode == 0 else 'Unknown'
        
        return {
            'success': True,
            'device_model': device_model,
            'android_version': android_version,
            'kernel_version': kernel_version,
            'error': None
        }
    
    except FileNotFoundError:
        return {
            'success': False,
            'device_model': None,
            'android_version': None,
            'error': 'ADB not found. Please install Android SDK Platform Tools'
        }
    
    except subprocess.TimeoutExpired:
        return {
            'success': False,
            'device_model': None,
            'android_version': None,
            'error': 'ADB command timed out'
        }
        
    except Exception as e:
        return {
            'success': False,
            'device_model': None,
            'android_version': None,
            'error': f'Could not get device info: {str(e)}'
        }


if __name__ == '__main__':
    # Test the detection
    print("Testing log buffer detection...")
    buffer_info = detect_buffer()
    
    if buffer_info['success']:
        print(f"✅ Success!")
        print(f"   Oldest log: {buffer_info['oldest_timestamp']}")
        print(f"   Duration: {buffer_info['duration_hours']:.2f} hours ({buffer_info['duration_days']:.2f} days)")
    else:
        print(f"❌ Failed: {buffer_info['error']}")
    
    print("\nTesting device info...")
    device_info = get_device_info()
    
    if device_info['success']:
        print(f"✅ Device: {device_info['device_model']}")
        print(f"   Android: {device_info['android_version']}")
    else:
        print(f"❌ Failed: {device_info['error']}")
