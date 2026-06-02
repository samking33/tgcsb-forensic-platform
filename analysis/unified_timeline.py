
import os
import json
import re
from datetime import datetime
from datetime import timedelta
from collections import Counter

# Improved regex for Logcat: 01-20 22:59:42.046 D/Tag(PID): Message OR 01-19 13:00:19.199 F/Tag ...
# We'll use a more flexible regex: Timestamp Priority/Tag: Message
LOGCAT_REGEX = re.compile(r'^(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})\s+([VDIWEF])\/([^\(:]+)(?:\(\s*\d+\))?:?\s+(.*)$')

def clean_string(text):
    """Sanitize string to remove non-printable characters."""
    if not text: return ""
    # Allow printables and common whitespace
    return re.sub(r'[^\x20-\x7E\n\r\t]', '', str(text))

def parse_logcat_line(line, year):
    # Try the flexible regex first
    match = LOGCAT_REGEX.match(line)
    if match:
        ts_str, priority, tag, message = match.groups()
        try:
            # Handle MM-DD format by appending inferred year
            ts = datetime.strptime(f"{year}-{ts_str}", "%Y-%m-%d %H:%M:%S.%f")
            return {
                "timestamp": ts.isoformat(),
                "priority": priority,
                "tag": clean_string(tag.strip()),
                "message": clean_string(message.strip())
            }
        except: pass
        
    # Fallback for standard threadtime format: Date Time PID TID Level Tag: Message
    # 01-20 23:36:22.123  1234  5678 I Tag : Message
    threadtime_regex = re.compile(r'^(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})\s+(\d+)\s+(\d+)\s+([VDIWEF])\s+([^:]+):\s+(.*)$')
    match = threadtime_regex.match(line)
    if match:
        ts_str, pid, tid, priority, tag, message = match.groups()
        try:
            ts = datetime.strptime(f"{year}-{ts_str}", "%Y-%m-%d %H:%M:%S.%f")
            return {
                "timestamp": ts.isoformat(),
                "priority": priority,
                "tag": clean_string(tag.strip()),
                "message": clean_string(message.strip())
            }
        except: pass

    return None

def infer_year_from_logs(logs_dir):
    """Scan SMS/Call logs to find the most frequent year."""
    years = Counter()
    
    # Check SMS Logs
    sms_path = os.path.join(logs_dir, "sms_logs.txt")
    if os.path.exists(sms_path):
        try:
            with open(sms_path, "r", encoding="utf-8", errors="replace") as f:
                for _ in range(500): # Scan first 500 lines
                    line = f.readline()
                    if not line: break
                    # "2023-01-01 12:00:00 | ..."
                    match = re.search(r'(\d{4})-\d{2}-\d{2}', line)
                    if match:
                        years[int(match.group(1))] += 1
        except: pass

    # Check Call Logs
    call_path = os.path.join(logs_dir, "call_logs.txt")
    if os.path.exists(call_path):
         try:
            with open(call_path, "r", encoding="utf-8", errors="replace") as f:
                for _ in range(500):
                    line = f.readline()
                    if not line: break
                    # "date=1684343434343" (Epoch ms)
                    match = re.search(r'date=(\d{13})', line)
                    if match:
                        ts = int(match.group(1)) / 1000
                        dt = datetime.fromtimestamp(ts)
                        years[dt.year] += 1
         except: pass

    if years:
        best_year = years.most_common(1)[0][0]
        print(f"Inferred Base Year for Logs: {best_year}")
        return best_year
    
    print(f"Could not infer year, defaulting to current year: {datetime.now().year}")
    return datetime.now().year

def generate_timeline(logs_dir="logs", output_file="logs/unified_timeline.json"):
    timeline = []
    
    # 0. Infer Year
    log_year = infer_year_from_logs(logs_dir)

    # 1. Process Logcat
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if os.path.exists(logcat_path):
        print(f"Processing Logcat: {logcat_path}")
        with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                parsed = parse_logcat_line(line, log_year)
                if parsed:
                    # Smart Classification
                    tag = parsed["tag"]
                    msg = parsed["message"]
                    
                    evt_type = "LOGCAT" # Default
                    evt_subtype = tag
                    
                    # 1. Power / Screen
                    if "PowerManagerService" in tag:
                        evt_type = "LOGCAT_POWER" # Catch ALL PowerManager logs
                        if "Waking up" in msg:
                            evt_subtype = "Screen On"
                        elif "Going to sleep" in msg:
                            evt_subtype = "Screen Off"
                        else:
                            evt_subtype = "Power Event" # Generic
                            
                    elif "DisplayPowerController" in tag:
                        evt_type = "LOGCAT_DEVICE"
                        evt_subtype = "Display Control"
                        # Try to match "BrightnessEvent: brt=0.615... (91.0%)"
                        if "BrightnessEvent" in msg:
                            p_match = re.search(r'\(([\d\.]+)%\)', msg)
                            if p_match:
                                evt_subtype = f"Brightness {p_match.group(1)}%"
                            else:
                                b_match = re.search(r'brt=([\d\.]+)', msg)
                                if b_match:
                                    val = float(b_match.group(1))
                                    percent = int(val * 100)
                                    evt_subtype = f"Brightness {percent}%"
                        elif "brightness=" in msg or "Brightness [" in msg:
                             b_match = re.search(r'(?:brightness=|Brightness \[)([\d\.]+)', msg)
                             if b_match:
                                val = float(b_match.group(1))
                                percent = int(val * 100) if val <= 1.0 else val
                                evt_subtype = f"Brightness {percent}%"

                    elif "BatteryService" in tag or "healthd" in tag or "NtChg" in tag or "battery" in tag.lower():
                        evt_type = "LOGCAT_POWER"
                        evt_subtype = "Battery Info"
                        l_match = re.search(r'level:?(\d+)', msg)
                        if l_match:
                            evt_subtype = f"Battery {l_match.group(1)}%"
                        elif "temp_region" in msg:
                            evt_subtype = "Battery Temperature Control"
                        elif "InGameStatus" in msg:
                            evt_subtype = "Charging Status"
                            
                    elif "DreamManager" in tag:
                        evt_type = "LOGCAT_POWER"
                        evt_subtype = "Doze/Sleep"

                    elif "Keyguard" in tag:
                         evt_type = "LOGCAT_DEVICE"
                         if "keyguardGoingAway" in msg:
                             evt_subtype = "User Present (Unlock)"
                         elif "onStartedWakingUp" in msg:
                             evt_subtype = "Keyguard Waking"
                         else:
                             evt_subtype = "Keyguard Event"

                    # 2. App Activity
                    elif "ActivityTaskManager" in tag or "ActivityManager" in tag:
                        evt_type = "LOGCAT_APP"
                        evt_subtype = "App Activity"
                        if "START u0" in msg:
                            evt_subtype = "App Launch"
                            c_match = re.search(r'cmp=([^ ]+)', msg)
                            if c_match:
                                evt_subtype = f"Launch: {c_match.group(1).split('/')[-1]}"
                        elif "Displayed" in msg:
                            evt_subtype = "App Displayed"
                            n_match = re.search(r'Displayed ([^:]+):', msg)
                            if n_match:
                                evt_subtype = f"Displayed: {n_match.group(1).split('/')[-1]}"
                        elif "Process died" in msg:
                            evt_subtype = "App Crash/Kill"

                    elif "PackageManager" in tag:
                        evt_type = "LOGCAT_APP"
                        evt_subtype = "Package Event"

                    # 3. Network
                    elif "WifiService" in tag or "ConnectivityService" in tag or "NetworkController" in tag:
                        evt_type = "LOGCAT_NET" # Mapped to Network in PHP
                    
                    # 4. Device / User Input
                    elif "WindowManager" in tag:
                        evt_type = "LOGCAT_DEVICE"
                        evt_subtype = "Window Manager"
                    elif "InputManager" in tag:
                         evt_type = "LOGCAT_DEVICE"
                         evt_subtype = "Input Event"
                    elif "SensorService" in tag:
                        evt_type = "LOGCAT_DEVICE"
                        evt_subtype = "Sensor Event"
                    
                    # 5. SIM/Carrier Events (CRITICAL FOR MULE DETECTION)
                    elif "SubscriptionController" in tag or "CarrierConfigLoader" in tag or "TelephonyRegistry" in tag:
                        evt_type = "LOGCAT_SIM"
                        evt_subtype = "SIM/Carrier Event"
                        # Flag suspicious SIM changes
                        if any(keyword in msg.lower() for keyword in ["sim loaded", "sim changed", "carrier changed", 
                                                                       "subscription changed", "iccid", "sim state changed"]):
                            evt_subtype = "⚠️ SIM Swap Detected"
                        elif "carrier config" in msg.lower():
                            evt_subtype = "Carrier Config Change"

                    # 5b. VoIP Call Detection (WhatsApp, Telegram, etc.)
                    # 5b. VoIP Call Detection (WhatsApp, Telegram, etc.)
                    elif (any(voip_app in tag.lower() or voip_app in msg.lower() for voip_app in 
                             ["voip", "whatsapp.voipcalling", "telegram.messenger.voip", "viber.voip", 
                              "discord.rtcconnection", "signal.calling"]) or 
                          ("msys" in tag.lower() and "[n wa]" in msg.lower())):
                        
                        evt_type = "VOIP"
                        
                        # Determine app
                        if "whatsapp" in tag.lower() or "whatsapp" in msg.lower() or "[n wa]" in msg.lower():
                            app_name = "WhatsApp"
                        elif "telegram" in tag.lower() or "telegram" in msg.lower():
                            app_name = "Telegram"
                        elif "instagram" in tag.lower() or "instagram" in msg.lower():
                            app_name = "Instagram"
                        elif "messenger" in tag.lower() or "messenger" in msg.lower():
                            app_name = "Facebook Messenger"
                        elif "discord" in tag.lower() or "discord" in msg.lower():
                            app_name = "Discord"
                        elif "signal" in tag.lower() or "signal" in msg.lower():
                            app_name = "Signal"
                        elif "viber" in tag.lower() or "viber" in msg.lower():
                            app_name = "Viber"
                        else:
                            app_name = "VoIP App"
                        
                        # Determine call state
                        if any(keyword in msg.lower() for keyword in ["incoming", "ringing", "call from"]):
                            evt_subtype = f"{app_name} Incoming Call"
                        elif any(keyword in msg.lower() for keyword in ["outgoing", "calling", "dialing"]):
                            evt_subtype = f"{app_name} Outgoing Call"
                        elif any(keyword in msg.lower() for keyword in ["ended", "disconnected", "call end"]):
                            evt_subtype = f"{app_name} Call Ended"
                        elif any(keyword in msg.lower() for keyword in ["missed", "declined", "rejected"]):
                            evt_subtype = f"{app_name} Missed Call"
                        elif "voipaudiomanager" in msg.lower() or "audio" in msg.lower() or "connection" in msg.lower():
                            evt_subtype = f"{app_name} Call Active/Connecting"
                        else:
                            evt_subtype = f"{app_name} Activity"
                    
                    # Generic VoIP detection via AudioManager / AudioEffectControlService (Integer Modes)
                    # Mode 2 = IN_CALL, Mode 3 = IN_COMMUNICATION
                    elif ("AudioManager" in tag or "AudioEffectControlService" in tag) and ("mode" in msg.lower() and "=" in msg):
                        # Matches "mode = 2", "mode=2", "mode=3"
                        if "mode = 2" in msg.lower() or "mode=2" in msg.lower():
                             evt_type = "VOIP"
                             evt_subtype = "Audio Mode: IN_CALL (Active Call)"
                        elif "mode = 3" in msg.lower() or "mode=3" in msg.lower():
                             evt_type = "VOIP"
                             evt_subtype = "Audio Mode: IN_COMMUNICATION (VoIP)"
                        elif "mode = 0" in msg.lower() or "mode=0" in msg.lower():
                             # Mode 0 is NORMAL (Call End usually)
                             # We only log it if we want to show end of calls clearly
                             evt_type = "VOIP" 
                             evt_subtype = "Audio Mode: NORMAL (Call Ended)"

                    # 5c. Telephony/Radio Events (For Fake Call Detection)
                    elif "Telecom" in tag or "InCall" in tag or "RIL" in tag or "GsmCdmaPhone" in tag:
                        evt_type = "LOGCAT_RADIO"
                        evt_subtype = "Radio/Telecom Handshake"
                        if "dial" in msg.lower() or "outgoing" in msg.lower():
                            evt_subtype = "System Outgoing Call"
                        elif "incoming" in msg.lower() or "ringing" in msg.lower():
                            evt_subtype = "System Incoming Call"
                    
                    # 6. Filter Noise (Optional - reduce generic log volume if needed)
                    # For now, we keep everything but categorize specific interesting events
                    
                    timeline.append({
                        "timestamp": parsed["timestamp"],
                        "type": evt_type,
                        "subtype": evt_subtype,
                        "content": clean_string(f"[{parsed['priority']}/{parsed['tag']}] {parsed['message']}"),
                        "severity": parsed["priority"]
                    })

    # Financial SMS patterns for flagging
    FINANCIAL_PATTERNS = {
        "OTP": re.compile(r'\b(?:OTP|otp|one.time.password|verification.code|auth.code)\b.*?\d{4,6}', re.I),
        "UPI": re.compile(r'\b(?:UPI|PhonePe|Paytm|GPay|Google.Pay|BHIM|Amazon.Pay|Cred|MobiKwik|Freecharge)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I),
        "BANK": re.compile(r'\b(?:credited|debited|transferred|withdrawn|deposited|balance|account|IFSC|NEFT|RTGS|IMPS)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I),
        "TRANSACTION": re.compile(r'\b(?:paid|sent|received|spent|purchase|bill|invoice|payment|txn|transaction)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I)
    }
    
    # Financial senders (banks, payment apps)
    FINANCIAL_SENDERS = [
        "sbi", "hdfc", "icici", "axis", "kotak", "paytm", "phonepe", "gpay", 
        "google pay", "upi", "bhim", "amazon pay", "cred", "mobikwik", "bank",
        "freecharge", "pnb", "bob", "canara", "union bank"
    ]

    def flag_financial_sms(content, sender=""):
        # Check content patterns first
        for flag, pattern in FINANCIAL_PATTERNS.items():
            if pattern.search(content):
                return f"FINANCIAL_{flag}"
        
        # If sender is from a financial institution, flag it
        sender_lower = sender.lower()
        if any(fin_sender in sender_lower for fin_sender in FINANCIAL_SENDERS):
            return "FINANCIAL_SENDER"
        
        return None
    
    def determine_notification_type(content):
        """Determine notification subtype from content."""
        content_lower = content.lower()
        if "otp" in content_lower or "verification code" in content_lower:
            return "OTP Notification"
        elif "alert" in content_lower:
            return "Alert"
        elif "reminder" in content_lower:
            return "Reminder"
        else:
            return "Notification"

    # 2. Process SMS (Existing logic preserved mostly, ensuring format match)
    sms_path = os.path.join(logs_dir, "sms_logs.txt")
    if os.path.exists(sms_path):
        print(f"Processing SMS: {sms_path}")
        with open(sms_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                if " | " in line:
                    parts = line.split(" | ")
                    if len(parts) >= 4:
                        try:
                            ts = datetime.strptime(parts[0].strip(), "%Y-%m-%d %H:%M:%S")
                            msg_type = parts[1].strip()
                            sender = parts[2].strip()
                            body = parts[3].strip()
                            content = f"SMS {msg_type}: {sender} - {body}"
                            
                            # Check if it's a notification-worthy SMS (OTP, alerts)
                            if any(kw in content.lower() for kw in ["otp", "code", "verification", "alert"]):
                                evt_type = "NOTIFICATION"
                                evt_subtype = determine_notification_type(content)
                            else:
                                evt_type = "SMS"
                                evt_subtype = msg_type
                            
                            # Check if it's financial
                            financial_flag = flag_financial_sms(content, sender)
                            if financial_flag:
                                # Override to FINANCIAL if it's a financial SMS
                                if evt_type == "NOTIFICATION" and "OTP" in financial_flag:
                                    evt_type = "FINANCIAL"
                                    evt_subtype = "OTP Received"
                                elif "SENDER" in financial_flag or "BANK" in financial_flag or "UPI" in financial_flag:
                                    evt_type = "FINANCIAL"
                                    if "BANK" in financial_flag:
                                        evt_subtype = "Bank Transaction"
                                    elif "UPI" in financial_flag:
                                        evt_subtype = "UPI Transaction"
                                    else:
                                        evt_subtype = "Financial Alert"
                                else:
                                    evt_subtype = f"{evt_subtype} ({financial_flag})"
                            
                            timeline.append({
                                "timestamp": ts.isoformat(),
                                "type": evt_type,
                                "subtype": clean_string(evt_subtype),
                                "content": clean_string(content),
                                "severity": "W" if evt_type == "FINANCIAL" else "I"
                            })
                        except: pass
                elif "address=" in line:
                    addr = re.search(r'address=([^,]+)', line)
                    body = re.search(r'body=(.*?)(?:, \w+=|$)', line)
                    date = re.search(r'date=(\d+)', line)
                    if addr and body and date:
                        try:
                            ts = datetime.fromtimestamp(int(date.group(1))/1000)
                            sender = addr.group(1)
                            msg_body = body.group(1)
                            content = f"SMS: {sender} - {msg_body}"
                            
                            # Check if notification-worthy
                            if any(kw in content.lower() for kw in ["otp", "code", "verification", "alert"]):
                                evt_type = "NOTIFICATION"
                                evt_subtype = determine_notification_type(content)
                            else:
                                evt_type = "SMS"
                                evt_subtype = "RAW"
                            
                            # Check financial
                            financial_flag = flag_financial_sms(content, sender)
                            if financial_flag:
                                if evt_type == "NOTIFICATION" and "OTP" in financial_flag:
                                    evt_type = "FINANCIAL"
                                    evt_subtype = "OTP Received"
                                elif "SENDER" in financial_flag or "BANK" in financial_flag or "UPI" in financial_flag:
                                    evt_type = "FINANCIAL"
                                    evt_subtype = "Bank Transaction" if "BANK" in financial_flag else "UPI Transaction" if "UPI" in financial_flag else "Financial Alert"
                                else:
                                    evt_subtype = f"{evt_subtype} ({financial_flag})"
                            
                            timeline.append({
                                "timestamp": ts.isoformat(),
                                "type": evt_type,
                                "subtype": clean_string(evt_subtype),
                                "content": clean_string(content),
                                "severity": "W" if evt_type == "FINANCIAL" else "I"
                            })
                        except: pass

    # 3. Process Calls
    call_path = os.path.join(logs_dir, "call_logs.txt")
    if os.path.exists(call_path):
        print(f"Processing Calls: {call_path}")
        with open(call_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                # Use stricter regexes that require a preceding space (or start) to avoid partial matches
                # e.g., match " number=" not "formatted_number="
                
                # Note: The file format is "key=value, key=value". So keys are preceded by space.
                number_match = re.search(r'(?:^|\s)number=([^,]+)', line)
                name_match = re.search(r'(?:^|\s)name=([^,]+)', line)
                duration_match = re.search(r'(?:^|\s)duration=([^,]+)', line)
                # match specific type key
                type_match = re.search(r'(?:^|\s)type=([^,]+)', line)
                date_match = re.search(r'(?:^|\s)date=(\d+)', line)
                
                # Component might be 'subscription_component_name' or others. We check specific ones.
                # But to detect "App" calls (WhatsApp), we look for any component string
                # We'll just look for 'component_name' substring safely or specific known keys
                # Actually, earlier debug showed "subscription_component_name" was present.
                # Let's search the whole line for "whatsapp" or "telegram" manually if regex fails.
                
                if number_match and date_match:
                    try:
                        ts = datetime.fromtimestamp(int(date_match.group(1))/1000)
                        
                        number = number_match.group(1)
                        name = name_match.group(0).split('=')[1] if name_match else "NULL" 
                        # Wait, group(1) is correct for (?:^|\s)key=([^,]+)
                        name = name_match.group(1) if name_match else "NULL"
                        
                        # Smart Name Logic
                        display_name = number
                        app_source = "Phone"
                        
                        # Detect App Source from entire line
                        if "whatsapp" in line.lower():
                            app_source = "WhatsApp"
                        elif "telegram" in line.lower():
                            app_source = "Telegram"
                        
                        if name != "NULL" and name != "":
                            display_name = name
                        
                        # "Use number instead of repeating a name" -> If name is just the number, use number.
                        if display_name == number:
                            display_name = number
                        
                        c_type = type_match.group(1) if type_match else "?"
                        if c_type == "1": type_str = "Incoming"
                        elif c_type == "2": type_str = "Outgoing"
                        elif c_type == "3": type_str = "Missed"
                        else: type_str = "Unknown"

                        if app_source != "Phone":
                             summary = f"{type_str} Call ({app_source}): {display_name}"
                        else:
                             summary = f"{type_str} Call: {display_name}"

                        timeline.append({
                            "timestamp": ts.isoformat(),
                            "type": "CALL",
                            "subtype": f"{type_str} ({app_source})",
                            "content": clean_string(f"{summary} (Dur: {duration_match.group(1) if duration_match else '0'}s)"),
                            "severity": "I"
                        })
                    except Exception as e:
                        # print(f"Error parsing call line: {e}") 
                        pass

    # 4. Process Notification Timeline (New)
    notif_path = os.path.join(logs_dir, "notification_timeline.json")
    if os.path.exists(notif_path):
        print(f"Processing Notifications: {notif_path}")
        try:
            with open(notif_path, "r", encoding="utf-8") as f:
                notif_data = json.load(f)
                for item in notif_data:
                    # Map categories
                    evt_type = "NOTIFICATION"
                    evt_subtype = item.get("category", "General")
                    
                    # Specific mapping to requested categories
                    flag = item.get("financial_flag", "")
                    if "OTP" in flag:
                        evt_type = "FINANCIAL"
                        evt_subtype = "OTP Received"
                    elif "BANK" in flag:
                        evt_type = "FINANCIAL" 
                        evt_subtype = "Bank Alert"
                    elif "UPI" in flag:
                        evt_type = "FINANCIAL"
                        evt_subtype = "UPI Transaction"
                    elif "TRANSACTION" in flag:
                        evt_type = "FINANCIAL"
                        evt_subtype = "General Transaction"
                    
                    timeline.append({
                        "timestamp": item.get("timestamp"),
                        "type": evt_type,
                        "subtype": clean_string(evt_subtype),
                        "content": clean_string(f"[{item.get('app_name', 'Unknown')}] {item.get('title', '')}: {item.get('text', '')}"),
                        "severity": "W" if evt_type == "FINANCIAL" else "I"
                    })
        except Exception as e:
            print(f"Error processing notifications: {e}")

    # 5. Process Mule/SIM Security Alerts (New)
    mule_path = os.path.join(logs_dir, "dual_space_analysis.json")
    if os.path.exists(mule_path):
        print(f"Processing Security Alerts: {mule_path}")
        try:
            with open(mule_path, "r", encoding="utf-8") as f:
                mule_data = json.load(f)
                
                # Risk Score Event
                if "risk_score" in mule_data:
                    score = mule_data["risk_score"]
                    if score > 0:
                        timeline.append({
                            # Use acquisition time (now) if no timestamp, but preferable to use file mod time if possible
                            "timestamp": datetime.now().isoformat(), 
                            "type": "SECURITY",
                            "subtype": "Risk Assessment",
                            "content": f"Device Risk Score: {score}/100 - {mule_data.get('risk_level', 'Unknown')}",
                            "severity": "E" if score > 70 else "W"
                        })
                
                # Cloned Apps
                if "cloned_apps" in mule_data:
                    for app in mule_data["cloned_apps"]:
                        timeline.append({
                            "timestamp": datetime.now().isoformat(),
                            "type": "SECURITY",
                            "subtype": "Cloned App",
                            "content": clean_string(f"Found Cloned/Dual-Space App: {app}"),
                            "severity": "W"
                        })
        except Exception as e:
            print(f"Error processing security alerts: {e}")

    # 6. Process Package Dump (Enhanced for Installer Source)
    pkg_path = os.path.join(logs_dir, "package_dump.txt")
    if os.path.exists(pkg_path):
        print(f"Processing Package Dump: {pkg_path}")
        current_pkg = None
        installer_source = "Unknown"
        
        try:
            with open(pkg_path, "r", encoding="utf-8", errors="replace") as f:
                for line in f:
                    line = line.strip()
                    if line.startswith("Package ["):
                        # Package [com.example.app] (12345)
                        match = re.search(r'Package \[([^\]]+)\]', line)
                        if match:
                            current_pkg = match.group(1)
                            installer_source = "Unknown" # Reset for new package
                            
                    elif current_pkg and "installerPackageName=" in line:
                        # installerPackageName=com.android.chrome
                        match = re.search(r'installerPackageName=([^ ]+)', line)
                        if match:
                            installer_source = match.group(1)
                            
                    elif current_pkg and "firstInstallTime=" in line:
                        # firstInstallTime=2023-05-20 10:00:00
                        ts_str = line.split("=")[1]
                        try:
                            # Classify Source
                            src_type = "Unknown Source"
                            src_severity = "W"
                            
                            if "com.android.vending" in installer_source:
                                src_type = "Play Store"
                                src_severity = "I"
                            elif "com.android.chrome" in installer_source:
                                src_type = "Chrome (Sideload)"
                                src_severity = "W" # Warning
                            elif "com.google.android.packageinstaller" in installer_source:
                                src_type = "Manual Install (APK)"
                                src_severity = "W"
                            elif "check.me" in installer_source or "shareit" in installer_source:
                                src_type = "File Share (P2P)"
                                src_severity = "W"

                            timeline.append({
                                "timestamp": ts_str,
                                "type": "APP_LIFECYCLE",
                                "subtype": f"Install from {src_type}",
                                "content": clean_string(f"App Installed: {current_pkg} (Source: {installer_source})"),
                                "severity": src_severity
                            })
                        except: pass
        except Exception as e:
            print(f"Error parse package dump: {e}")

    # 7. Logcat - Detect "Install Unknown Source" Permission Grant (The "Intent" Proof)
    if os.path.exists(logcat_path):
        with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
             for line in f:
                 if "REQUEST_INSTALL_PACKAGES" in line and ("allow" in line or "grant" in line):
                     # 01-20 10:07:00 ... AppOps: ... REQUEST_INSTALL_PACKAGES ... allow
                     try:
                        parsed = parse_logcat_line(line, log_year)
                        if parsed:
                            timeline.append({
                                "timestamp": parsed["timestamp"],
                                "type": "SECURITY",
                                "subtype": "Permission Grant",
                                "content": f"⚠️ PERMISSION GRANTED: 'Install form Unknown Sources' detected! (User allowed sideloading)",
                                "severity": "E" # ERROR/CRITICAL
                            })
                     except: pass
                     
    # 7. VoIP Call Enrichment - Correlate with nearby activity
    print("Enriching VoIP calls with contextual information...")
    voip_events = [evt for evt in timeline if evt.get("type") == "VOIP"]
    
    if voip_events:
        # Group VoIP events by proximity (within 30 seconds = same call session)
        from datetime import timedelta
        
        for voip_evt in voip_events:
            try:
                voip_time = datetime.fromisoformat(voip_evt["timestamp"])
                
                # Find nearby events (±30 seconds)
                nearby_start = voip_time - timedelta(seconds=30)
                nearby_end = voip_time + timedelta(seconds=30)
                
                nearby_activity = []
                whatsapp_notifications = []
                
                for evt in timeline:
                    if evt == voip_evt:
                        continue
                    
                    try:
                        evt_time = datetime.fromisoformat(evt["timestamp"])
                        
                        if nearby_start <= evt_time <= nearby_end:
                            # Check for WhatsApp-related activity
                            content = evt.get("content", "").lower()
                            subtype = evt.get("subtype", "").lower()
                            
                            if "whatsapp" in content or "whatsapp" in subtype:
                                time_diff = int((evt_time - voip_time).total_seconds())
                                nearby_activity.append({
                                    "time_offset": time_diff,
                                    "type": evt.get("type"),
                                    "content": evt.get("content", "")[:100]  # Truncate
                                })
                                
                                if "notification" in content:
                                    whatsapp_notifications.append({
                                        "time_offset": time_diff,
                                        "content": evt.get("content", "")[:100]
                                    })
                    except:
                        pass
                
                # Add metadata to VoIP event
                if nearby_activity or whatsapp_notifications:
                    voip_evt["metadata"] = voip_evt.get("metadata", {})
                    voip_evt["metadata"]["nearby_activity_count"] = len(nearby_activity)
                    voip_evt["metadata"]["nearby_notifications"] = whatsapp_notifications[:3]  # Max 3
                    voip_evt["metadata"]["correlation_confidence"] = "HIGH" if whatsapp_notifications else "MEDIUM"
                
            except Exception as e:
                print(f"Error enriching VoIP event: {e}")
                continue
    
    # Calculate VoIP call duration by grouping consecutive events
    if voip_events:
        voip_sessions = []
        current_session = []
        
        for i, evt in enumerate(voip_events):
            if not current_session:
                current_session.append(evt)
            else:
                try:
                    prev_time = datetime.fromisoformat(current_session[-1]["timestamp"])
                    curr_time = datetime.fromisoformat(evt["timestamp"])
                    
                    # If within 15 seconds, same session
                    if (curr_time - prev_time).total_seconds() <= 15:
                        current_session.append(evt)
                    else:
                        # New session
                        voip_sessions.append(current_session)
                        current_session = [evt]
                except:
                    pass
        
        if current_session:
            voip_sessions.append(current_session)
        
        # Add duration to each session
        for session in voip_sessions:
            if len(session) > 1:
                try:
                    start_time = datetime.fromisoformat(session[0]["timestamp"])
                    end_time = datetime.fromisoformat(session[-1]["timestamp"])
                    duration_seconds = int((end_time - start_time).total_seconds())
                    
                    # Add duration to all events in session
                    for evt in session:
                        evt["metadata"] = evt.get("metadata", {})
                        evt["metadata"]["call_duration_seconds"] = duration_seconds
                        evt["metadata"]["call_session_events"] = len(session)
                except:
                    pass

    # 8. Post-Processing: Detect "Ghost" Logs (Gaps in Timeline)
    # Reformatted logic: Only detect gaps between LOGCAT events.
    # SMS/Calls are sporadic and gaps there don't mean device is off.
    
    # Sort first
    timeline = [t for t in timeline if t.get("timestamp")]
    timeline.sort(key=lambda x: x["timestamp"])
    
    print("Analyzing timeline for Ghost Gaps (Logcat Only)...")
    ghost_events = []
    GAP_THRESHOLD_SECONDS = 0.5  # Lowered to detect small gaps (for demo/testing)
    
    # Filter only logcat events for gap detection
    logcat_events = [t for t in timeline if t.get("type", "").startswith("LOGCAT")]
    
    if len(logcat_events) > 1:
        for i in range(len(logcat_events) - 1):
            current_evt = logcat_events[i]
            next_evt = logcat_events[i+1]
            
            try:
                t1 = datetime.fromisoformat(current_evt["timestamp"])
                t2 = datetime.fromisoformat(next_evt["timestamp"])
                
                delta = (t2 - t1).total_seconds()
                
                if delta > GAP_THRESHOLD_SECONDS:
                    # Found a gap
                    minutes = int(delta / 60)
                    hours = round(delta / 3600, 1)
                    
                    gap_msg = f"{minutes} min" if minutes < 60 else f"{hours} hrs"
                    
                    # Create Ghost Event directly after the current event
                    # Timestamp = t1 + 1 second (so it appears right after)
                    ghost_ts = (t1 + timedelta(seconds=1)).isoformat()
                    
                    ghost_events.append({
                        "timestamp": ghost_ts,
                        "type": "GHOST",
                        "subtype": "Log Gap Detected",
                        "content": f"👻 GHOST GAP: No system logs for {gap_msg}. Possible device power-off or data removal.",
                        "severity": "W"
                    })
            except Exception: pass
            
    # Merge Ghost events
    timeline.extend(ghost_events)
    timeline.sort(key=lambda x: x["timestamp"])
    
    # Save to JSON
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(timeline, f, indent=4)
    
    print(f"Generated timeline with {len(timeline)} events.")

if __name__ == "__main__":
    generate_timeline()
