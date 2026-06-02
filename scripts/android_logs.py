import subprocess
import time
from datetime import datetime, timedelta
import os
import sys

# Fix Windows encoding issues with emoji characters
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except AttributeError:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

# Ensure logs directory exists
os.makedirs("logs", exist_ok=True)

def get_logcat():
    """
    Extract Android logcat logs using auto-detected buffer duration.
    Detects the oldest available log and extracts all available logs.
    Falls back to 7 days if detection fails.
    """
    from scripts.detect_log_buffer import detect_buffer
    
    try:
        # Try to detect the actual buffer duration
        buffer_info = detect_buffer()
        
        if buffer_info['success'] and buffer_info['oldest_timestamp']:
            # Use the detected oldest timestamp
            since = buffer_info['oldest_timestamp'].strftime("%m-%d %H:%M:%S.000")
            print(f"📊 Detected log buffer: {buffer_info['duration_hours']:.2f} hours ({buffer_info['duration_days']:.2f} days)")
            print(f"   Oldest log: {buffer_info['oldest_timestamp']}")
        else:
            # Fallback to 7 days if detection fails
            since = (datetime.now() - timedelta(days=7)).strftime("%m-%d %H:%M:%S.000")
            print(f"⚠️ Could not detect buffer duration: {buffer_info.get('error', 'Unknown error')}")
            print(f"   Using fallback: 7 days")
        
        with open("logs/android_logcat.txt", "w", encoding="utf-8") as f:
            # Extract ALL available logs with -b all to capture main, system, radio, events, crash
            subprocess.run(["adb", "logcat", "-b", "all", "-d", "-v", "time"],
                           stdout=f, check=True)
        
        return buffer_info  # Return info for GUI display
    
    except FileNotFoundError:
        error_msg = "⚠️ ADB not found. Please install Android SDK Platform Tools.\n"
        print(error_msg)
        with open("logs/android_logcat.txt", "w", encoding="utf-8") as f:
            f.write(error_msg)
        return {'success': False, 'error': 'ADB not found'}
    
    except Exception as e:
        error_msg = f"⚠️ Failed to extract logcat: {str(e)}\n"
        print(error_msg)
        with open("logs/android_logcat.txt", "w", encoding="utf-8") as f:
            f.write(error_msg)
        return {'success': False, 'error': str(e)}

def get_call_logs():
    """
    Extract Android call logs using the adb content query command.
    """
    try:
        result = subprocess.run(
            ["adb", "shell", "content", "query", "--uri", "content://call_log/calls"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True
        )
        output = result.stdout if result.stdout else "⚠️ No call logs found."
    except Exception as e:
        output = f"⚠️ Failed to extract call logs: {str(e)}"
    with open("logs/call_logs.txt", "w", encoding="utf-8") as f:
        f.write(output)

def get_sms_logs():
    """
    Extract Android SMS logs using the adb content query command.
    """
    try:
        result = subprocess.run(
            ["adb", "shell", "content", "query", "--uri", "content://sms"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True
        )
        output = result.stdout if result.stdout else "⚠️ No SMS logs found."
    except Exception as e:
        output = f"⚠️ Failed to extract SMS logs: {str(e)}"
    print("🔍 STDOUT:\n", output)
    with open("logs/sms_logs.txt", "w", encoding="utf-8") as f:
        f.write(output)

def get_location_logs():
    """
    Extract Android location history using dumpsys location.
    """
    try:
        # dumpsys location provides last known locations and other location state
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "location"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True
        )
        output = result.stdout if result.stdout else "⚠️ No location logs found."
    except Exception as e:
        output = f"⚠️ Failed to extract location logs: {str(e)}"
    
    with open("logs/location_logs.txt", "w", encoding="utf-8") as f:
        f.write(output)

def get_contacts():
    """
    Extract Android contacts using the adb content query command.
    Retrieves contact names, phone numbers, and email addresses.
    """
    try:
        result = subprocess.run(
            ["adb", "shell", "content", "query", "--uri", "content://com.android.contacts/data"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30  # Add timeout to prevent hanging
        )
        output = result.stdout if result.stdout else "⚠️ No contacts found."
    except subprocess.TimeoutExpired:
        output = "⚠️ Contact extraction timed out. This may happen on devices with many contacts."
    except Exception as e:
        output = f"⚠️ Failed to extract contacts: {str(e)}"
    
    with open("logs/contacts.txt", "w", encoding="utf-8") as f:
        f.write(output)
    
    print(f"📇 Contact extraction complete")

def trigger_location_update():
    """
    Triggers a location update by launching Google Maps.
    """
    try:
        print("🚀 Launching Google Maps to trigger location update...")
        subprocess.run(
            ["adb", "shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", "geo:0,0"],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )
        return True
    except Exception as e:
        print(f"⚠️ Failed to launch Maps: {e}")
        return False

def monitor_logs(callback):
    """
    Continuously monitor logs from 'adb logcat -v time'
    and call the provided callback for each line.
    Added error-handling to avoid crashes if the callback fails.
    """
    try:
        process = subprocess.Popen(
            ['adb', 'logcat', '-v', 'time'],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            encoding='utf-8',
            errors='replace'
        )
        try:
            while True:
                line = process.stdout.readline()
                if not line:
                    # Means process ended or no device output
                    break
                try:
                    callback(line.rstrip('\n'))
                except Exception as cb_e:
                    print("Error in callback:", cb_e)
                # Sleep briefly to avoid CPU hogging
                time.sleep(0.01)
        except KeyboardInterrupt:
            pass
        except Exception as e:
            print("Error in monitor_logs:", e)
        finally:
            try:
                process.terminate()
            except Exception as term_e:
                print("Error terminating process:", term_e)
    except FileNotFoundError:
        error_msg = "⚠️ ADB not found. Cannot start live monitoring. Please install Android SDK Platform Tools."
        print(error_msg)
        callback(error_msg)
    except Exception as e:
        error_msg = f"⚠️ Failed to start monitoring: {str(e)}"
        print(error_msg)
        callback(error_msg)

if __name__ == "__main__":
    print("📲 Starting Android Log Extraction...")
    get_sms_logs()
    get_call_logs()
    get_contacts()
    get_location_logs()
    get_logcat()
    print("✅ Log extraction complete.")
