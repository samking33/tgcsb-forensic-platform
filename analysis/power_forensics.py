
import os
import json
import re
from datetime import datetime

def analyze_power(logs_dir="logs", output_file="logs/power_forensics.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        return

    events = []
    
    # Regex for power events
    patterns = {
        "SCREEN_ON": r'android.intent.action.SCREEN_ON|DisplayPowerController: Screening on',
        "SCREEN_OFF": r'android.intent.action.SCREEN_OFF|DisplayPowerController: Screening off',
        "USER_PRESENT": r'android.intent.action.USER_PRESENT|Keyguard: keyguardGoingAway',
        "PLUGGED_AC": r'BatteryService: update:.*plugged: ac|BatteryService: Power source is AC',
        "PLUGGED_USB": r'BatteryService: update:.*plugged: usb|BatteryService: Power source is USB',
        "UNPLUGGED": r'BatteryService: update:.*plugged: none|BatteryService: Power source is battery',
        "SHUTDOWN": r'ShutdownThread: Running shutdown',
        "BOOT": r'SystemServer: Entered the Android system server'
    }

    # Timestamp regex
    TS_REGEX = re.compile(r'^(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})')

    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            ts_match = TS_REGEX.match(line)
            if ts_match:
                current_year = datetime.now().year
                try:
                    ts = datetime.strptime(f"{current_year}-{ts_match.group(1)}", "%Y-%m-%d %H:%M:%S.%f")
                    
                    for event_type, pattern in patterns.items():
                        if re.search(pattern, line, re.I):
                            events.append({
                                "timestamp": ts.isoformat(),
                                "event": event_type,
                                "raw": line.strip()
                            })
                            # One event per line usually enough for power states
                            break 
                except: continue

    # Sort
    events.sort(key=lambda x: x["timestamp"])

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(events, f, indent=4)
    
    print(f"Extracted {len(events)} power usage events.")

if __name__ == "__main__":
    analyze_power()
