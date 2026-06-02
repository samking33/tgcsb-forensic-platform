import os
import json
import re
from datetime import datetime
import time

LOGS_DIR = "logs"
REPORT_FILE = os.path.join(LOGS_DIR, "fake_log_report.json")

def parse_logcat_timestamps(file_path):
    """
    Parses timestamps from logcat files relative to current year.
    Returns: List of timestamps (unix epoch seconds) where radio activity occurred.
    """
    activity_timestamps = []
    
    if not os.path.exists(file_path):
        return []

    # Regex for standard logcat time: 02-03 16:29:10.123
    # We ignore year, assumes current year
    time_pattern = re.compile(r"^(\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})")
    current_year = datetime.now().year

    # Filter for relevant tags
    relevant_tags = ["Radio", "CallManager", "GsmCdmaPhone", "InCallUI", "Telecom", "SmsDispatch", "InboundSmsHandler", "ActivityManager"]
    
    try:
        with open(file_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                # Check for relevant tags first optimization
                if not any(tag in line for tag in relevant_tags):
                    continue
                
                match = time_pattern.search(line)
                if match:
                    dt_str = f"{current_year}-{match.group(1)}"
                    try:
                        dt = datetime.strptime(dt_str, "%Y-%m-%d %H:%M:%S")
                        # Store tuple (timestamp, log_content)
                        activity_timestamps.append((dt.timestamp(), line.strip()))
                    except ValueError:
                        continue
    except Exception as e:
        print(f"Error parsing logcat {file_path}: {e}")

    # Sort by timestamp
    return sorted(activity_timestamps, key=lambda x: x[0])

# ... (Parsing functions remain same)

def verify_logs(logs, system_activity):
    """
    Cross-references logs with system activity.
    Logic: Is there a system event within +/- 60 seconds of the log?
    """
    if not system_activity:
        return {log['id']: {"status": "unverified", "reason": "No system logs available (Old log?)"} for log in logs}

    results = {}
    system_timestamps = [x[0] for x in system_activity] # Extract timestamps for binary search
    
    min_sys_time = system_activity[0][0]
    max_sys_time = system_activity[-1][0]

    for log in logs:
        ts = log['timestamp']
        
        # Check out of range
        if ts < min_sys_time:
            results[log['id']] = {"status": "unverified", "reason": "Historic log (System logs unavailable)"}
            continue

        duration = log.get('duration', 0)
        window_start = ts - 30
        window_end = ts + duration + 30
        
        found = False
        proof = None
        
        import bisect
        idx = bisect.bisect_left(system_timestamps, window_start)
        
        # Check if any event in window
        # We need to scan from idx forward
        scan_idx = idx
        while scan_idx < len(system_activity):
            sys_ts, content = system_activity[scan_idx]
            if sys_ts > window_end:
                break
            # Match!
            found = True
            proof = content # Capture specific log line
            break
            scan_idx += 1
        
        if found:
            results[log['id']] = {
                "status": "verified", 
                "reason": "Correlated with system radio events",
                "proof": proof
            }
        else:
            if min_sys_time <= ts <= max_sys_time:
                results[log['id']] = {"status": "fake", "reason": "No radio traces found during this time"}
            else:
                results[log['id']] = {"status": "unverified", "reason": "No system data"}

    return results

def main():
    print("🕵️  Mule Hunter: Fake Log Detector Running...")
    
    # 1. Parse valid system radio times
    radio_times = parse_logcat_timestamps(os.path.join(LOGS_DIR, "android_logcat_radio.txt"))
    # Fallback to main logcat if radio is empty
    if not radio_times:
        print("   ⚠️  Radio buffer empty, falling back to main logcat...")
        radio_times = parse_logcat_timestamps(os.path.join(LOGS_DIR, "android_logcat.txt"))
    
    print(f"   ✅ Loaded {len(radio_times)} system radio events")

    # 2. Parse User Logs
    calls = parse_call_logs()
    sms = parse_sms_logs()
    print(f"   ✅ Loaded {len(calls)} calls and {len(sms)} SMS messages")

    # 3. Verify
    verification_results = {}
    verification_results.update(verify_logs(calls, radio_times))
    verification_results.update(verify_logs(sms, radio_times))

    # 4. Save Report
    report = {
        "metadata": {
            "generated_at": datetime.now().isoformat(),
            "system_log_count": len(radio_times),
            "system_log_start": datetime.fromtimestamp(radio_times[0]).isoformat() if radio_times else None,
            "system_log_end": datetime.fromtimestamp(radio_times[-1]).isoformat() if radio_times else None
        },
        "verification": verification_results
    }
    
    with open(REPORT_FILE, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2)
    
    print(f"✅ Fake Log Report generated: {REPORT_FILE}")

if __name__ == "__main__":
    main()
