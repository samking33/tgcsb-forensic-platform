"""
Enhanced Log Extraction for Better Forensic Coverage
Adds dumpsys commands for App Sessionizer, Beacon Map, and Power Forensics
"""

import subprocess
import os
import sys

# Force UTF-8 encoding for stdout to prevent Windows cp1252 errors
if sys.platform == "win32" and hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

def get_usage_stats():
    """
    Extract app usage statistics for App Sessionizer
    Provides screen time, app launch counts, and usage durations
    """
    try:
        print("📊 Extracting usage statistics...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "usagestats"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/usage_stats.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Usage statistics extracted")
    except Exception as e:
        print(f"⚠️  Failed to extract usage stats: {e}")

def get_recent_tasks():
    """
    Extract recent task history for App Sessionizer
    Shows recently used apps and their activities
    """
    try:
        print("📋 Extracting recent tasks...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "activity", "recents"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/recent_tasks.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Recent tasks extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract recent tasks: {e}")

def get_wifi_networks():
    """
    Extract WiFi network information for Beacon Map
    Includes configured networks, scan results, and connection history
    """
    try:
        print("📡 Extracting WiFi networks...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "wifi"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/wifi_dump.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ WiFi data extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract WiFi data: {e}")

def get_bluetooth_devices():
    """
    Extract Bluetooth device information for Beacon Map
    Includes paired devices, connection history, and device names
    """
    try:
        print("🔵 Extracting Bluetooth devices...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "bluetooth_manager"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/bluetooth_dump.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Bluetooth data extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract Bluetooth data: {e}")

def get_battery_history():
    """
    Extract battery history for Power Forensics
    Provides charging events, battery drain patterns, and power consumption
    """
    try:
        print("🔋 Extracting battery history...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "batterystats"],
           capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/battery_history.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Battery history extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract battery history: {e}")

def get_network_stats():
    """
    Extract network statistics for Network Intelligence
    Provides data usage by app, network types, and connection history
    """
    try:
        print("🌐 Extracting network statistics...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "netstats"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/network_stats.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Network statistics extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract network stats: {e}")

def get_notification_history():
    """
    🆕 ANDROID 13/14: Extract notification history buffer
    Recovers banking alerts even if SMS was deleted
    Critical for UPI/OTP fraud investigation
    """
    try:
        print("🔔 Extracting notification history (Android 13/14)...")
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "notification", "--noredact"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=30
        )
        with open("logs/notification_history.txt", "w", encoding="utf-8") as f:
            f.write(result.stdout)
        print("✅ Notification history extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract notification history: {e}")

def get_device_identifiers():
    """
    🆕 Extract IMEI and Serial Number for Section 65B Certificate
    Required for legal compliance in Indian courts
    """
    try:
        print("📱 Extracting device identifiers (IMEI, Serial)...")
        
        # Get IMEI via dumpsys iphonesubinfo
        imei_result = subprocess.run(
            ["adb", "shell", "dumpsys", "iphonesubinfo"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=10
        )
        
        # Get device properties
        props_result = subprocess.run(
            ["adb", "shell", "getprop"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=10
        )
        
        # Save both outputs
        with open("logs/device_identifiers.txt", "w", encoding="utf-8") as f:
            f.write("=== IMEI/MEID INFO ===\n")
            f.write(imei_result.stdout)
            f.write("\n\n=== DEVICE PROPERTIES ===\n")
            f.write(props_result.stdout)
        
        print("✅ Device identifiers extracted")
    except Exception as e:
        print(f"⚠️ Failed to extract device identifiers: {e}")

def get_dual_space_apps():
    """
    🆕 Detect Dual Space / Cloned Apps (Mule Account Detection)
    Mules use app cloning to run multiple banking app instances
    """
    try:
        print("👥 Detecting dual space / cloned apps...")
        
        # Get apps in main profile (user 0)
        result_main = subprocess.run(
            ["adb", "shell", "pm", "list", "packages", "--user", "0"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=True,
            timeout=15
        )
        
        # Get apps in secondary profiles (user 10, 999)
        # User 999 is commonly used for "Dual Apps" on Xiaomi/Samsung
        result_dual_999 = subprocess.run(
            ["adb", "shell", "pm", "list", "packages", "--user", "999"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=False,  # Don't fail if user doesn't exist
            timeout=15
        )
        
        result_dual_10 = subprocess.run(
            ["adb", "shell", "pm", "list", "packages", "--user", "10"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=False,
            timeout=15
        )
        
        # Save all results
        with open("logs/dual_space_apps.txt", "w", encoding="utf-8") as f:
            f.write("=== MAIN PROFILE (User 0) ===\n")
            f.write(result_main.stdout)
            f.write("\n\n=== DUAL SPACE PROFILE (User 999) ===\n")
            f.write(result_dual_999.stdout if result_dual_999.returncode == 0 else "Not available\n")
            f.write("\n\n=== DUAL SPACE PROFILE (User 10) ===\n")
            f.write(result_dual_10.stdout if result_dual_10.returncode == 0 else "Not available\n")
        print("✅ Dual space detection complete")
    except Exception as e:
        print(f"⚠️ Failed to detect dual space apps: {e}")

def get_all_packages_with_uids():
    """
    🆕 Extract ALL packages with UIDs to detect hidden clones
    This bypasses issues where 'pm list packages --user 999' returns empty.
    """
    try:
        print("📦 Extracting full package list with UIDs...")
        result = subprocess.run(
            ["adb", "shell", "pm", "list", "packages", "-f", "-U"],
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            check=False, # Verify manually
            timeout=20
        )
        
        output_content = result.stdout.strip()
        
        # Fallback if empty or failed
        if result.returncode != 0 or not output_content:
            print("⚠️ Complex package list failed (empty/error). Trying simple fallback...")
            result_fallback = subprocess.run(
                ["adb", "shell", "pm", "list", "packages"],
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                check=True,
                timeout=20
            )
            output_content = result_fallback.stdout.strip()
            
        if not output_content:
             print("❌ CRITICAL: Failed to get ANY package list.")
        
        with open("logs/full_package_dump.txt", "w", encoding="utf-8") as f:
            f.write(output_content)
            
        print(f"✅ Full package list extracted ({len(output_content.splitlines())} entries)")
    except Exception as e:
        print(f"⚠️ Failed to extract full package list: {e}")

def get_detailed_system_dump():
    """
    EXTREME EXTRACTION: Pulls deep system state
    - System Properties (getprop)
    - Process List (ps -A)
    - Settings (System/Secure/Global)
    - Dumpsys: Window, Alarm, Package, Location, Account, Clipboard, Content, Mount, JobScheduler
    """
    print("\n" + "-"*60)
    print("🛠️ DEEP SYSTEM STATE EXTRACTION")
    print("-" * 60)
    
    commands = [
        ("getprop", ["adb", "shell", "getprop"], "system_properties.txt"),
        ("ps -A", ["adb", "shell", "ps", "-A"], "process_list.txt"),
        ("mount", ["adb", "shell", "mount"], "mount_points.txt"),
        ("df -h", ["adb", "shell", "df", "-h"], "disk_usage.txt"),
        
        # Settings Databases
        ("settings global", ["adb", "shell", "settings", "list", "global"], "settings_global.txt"),
        ("settings secure", ["adb", "shell", "settings", "list", "secure"], "settings_secure.txt"),
        ("settings system", ["adb", "shell", "settings", "list", "system"], "settings_system.txt"),
        
        # Dumpsys Services (Heavy)
        ("dumpsys clipboard", ["adb", "shell", "dumpsys", "clipboard"], "dump_clipboard.txt"),
        ("dumpsys account", ["adb", "shell", "dumpsys", "account"], "dump_account.txt"),
        ("dumpsys location", ["adb", "shell", "dumpsys", "location"], "dump_location.txt"),
        ("dumpsys alarm", ["adb", "shell", "dumpsys", "alarm"], "dump_alarm.txt"),
        ("dumpsys window", ["adb", "shell", "dumpsys", "window"], "dump_window.txt"),
        ("dumpsys package", ["adb", "shell", "dumpsys", "package"], "dump_package.txt"), # Full package dump
        ("dumpsys jobscheduler", ["adb", "shell", "dumpsys", "jobscheduler"], "dump_jobscheduler.txt"),
        ("dumpsys content", ["adb", "shell", "dumpsys", "content"], "dump_content.txt"),
        ("dumpsys input", ["adb", "shell", "dumpsys", "input"], "dump_input.txt"),
        ("dumpsys dropbox", ["adb", "shell", "dumpsys", "dropbox", "--print"], "dump_dropbox.txt") # Crash history
    ]

    for name, cmd, outfile in commands:
        try:
            print(f"📥 Extracting {name}...")
            res = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="replace", check=False, timeout=60)
            if res.returncode == 0:
                with open(os.path.join("logs", outfile), "w", encoding="utf-8") as f:
                    f.write(res.stdout)
                print(f"   ✅ Saved to {outfile}")
            else:
                print(f"   ⚠️ Command failed: {res.stderr[:100]}...")
        except Exception as e:
            print(f"   ❌ Error executing {name}: {e}")

def get_logcat_dump():
    """
    Extract Android Logcat for Fake Log Detection
    Captures:
    - Main (App crashes, system messages)
    - Radio (Telephony, Call states)
    - Events (Activity manager, screen on/off)
    """
    try:
        print("📜 Extracting System Logs (Logcat)...")
        buffers = [
            ("main", "android_logcat.txt"),
            ("radio", "android_logcat_radio.txt"),
            ("events", "android_logcat_events.txt")
        ]
        
        for buffer_name, outfile in buffers:
            print(f"   - Dumping {buffer_name} buffer...")
            result = subprocess.run(
                ["adb", "logcat", "-b", buffer_name, "-d"],
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                check=False,
                timeout=15
            )
            with open(os.path.join("logs", outfile), "w", encoding="utf-8") as f:
                f.write(result.stdout)
                
        print("✅ Logcat extracted successfully")
    except Exception as e:
        print(f"⚠️ Failed to extract logcat: {e}")
def update_progress(percent, message):
    """
    Update extraction progress for frontend smooth bar
    """
    try:
        import json
        import os
        # Ensure logs dir exists
        os.makedirs("logs", exist_ok=True)
        
        data = {"progress": percent, "status": message}
        # Use absolute path if possible or relative
        with open("logs/extraction_progress.json", "w", encoding="utf-8") as f:
            json.dump(data, f)
            f.flush()
            os.fsync(f.fileno()) # Force write to disk
    except Exception as e:
        with open("logs/python_progress_error.txt", "a") as f:
            f.write(f"Error updating progress: {e}\n")

def extract_all_enhanced_data():
    """
    Main function to extract all enhanced forensic data
    Call this during log extraction phase
    """
    print("\n" + "="*60)
    print("🔬 ENHANCED FORENSIC DATA EXTRACTION")
    print("="*60 + "\n")
    
    update_progress(10, "Extracting App Usage Stats...")
    # App Sessionizer enhancements
    get_usage_stats()
    
    update_progress(12, "Extracting Recent Tasks...")
    get_recent_tasks()
    
    update_progress(15, "Scanning WiFi Networks...")
    # Beacon Map enhancements
    get_wifi_networks()
    
    update_progress(18, "Scanning Bluetooth Devices...")
    get_bluetooth_devices()
    
    update_progress(20, "Extracting Battery History...")
    # Power Forensics enhancements
    get_battery_history()
    
    update_progress(22, "Extracting Network Stats...")
    # Network Intelligence enhancements
    get_network_stats()
    
    # 🆕 ANDROID 13/14 ENHANCEMENTS
    print("\n" + "-"*60)
    print("🆕 FOOL-PROOF FORENSICS")
    print("-" * 60 + "\n")
    
    update_progress(25, "Extracting Notification History...")
    get_notification_history()  # UPI/OTP Correlator
    
    update_progress(28, "Extracting Device Identifiers...")
    get_device_identifiers()    # Section 65B Certificate
    
    update_progress(30, "Detecting Dual Space Apps...")
    get_dual_space_apps()       # Mule Account Scanner
    
    update_progress(32, "Extracting Full Package List...")
    get_all_packages_with_uids() # 🆕 Full Package Dump (The Fix)
    
    update_progress(34, "Extracting System Logs (Ghost Detection)...")
    get_logcat_dump()
    
    # 🔥 PULL EVERYTHING (Deep Dump)
    update_progress(35, "Creating Deep System Dump...")
    get_detailed_system_dump()
    
    update_progress(40, "Enhanced Extraction Complete")
    
    print("\n" + "="*60)
    print("✅ ENHANCED EXTRACTION COMPLETE")
    print("="*60 + "\n")

if __name__ == "__main__":
    os.makedirs("logs", exist_ok=True)
    extract_all_enhanced_data()
