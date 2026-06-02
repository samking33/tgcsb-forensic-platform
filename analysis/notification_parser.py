"""
Notification History Parser for Android 13/14
Recovers banking alerts from notification buffer even if SMS was deleted
"""

import os
import re
import json
from datetime import datetime

# Financial patterns (same as unified_timeline.py)
FINANCIAL_PATTERNS = {
    "OTP": re.compile(r'\b(?:OTP|otp|one.time.password|verification.code|auth.code)\b.*?\d{4,6}', re.I),
    "UPI": re.compile(r'\b(?:UPI|PhonePe|Paytm|GPay|Google.Pay|BHIM|Amazon.Pay|Cred|MobiKwik|Freecharge)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I),
    "BANK": re.compile(r'\b(?:credited|debited|transferred|withdrawn|deposited|balance|account|IFSC|NEFT|RTGS|IMPS)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I),
    "TRANSACTION": re.compile(r'\b(?:paid|sent|received|spent|purchase|bill|invoice|payment|txn|transaction)\b.*?(?:Rs\.?|INR|₹|rupee|rupees)\s*\d+', re.I)
}

def parse_notification_buffer(notification_file="logs/notification_history.txt"):
    """
    Parse dumpsys notification output for financial alerts
    """
    if not os.path.exists(notification_file):
        print(f"⚠️ Notification file not found: {notification_file}")
        return None # Return None to indicate failure/no-op
    
    with open(notification_file, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    
    notifications = []
    
    notifications = []
    
    # Robust Pattern Attempt based on Android 12-14 dumpsys format
    # Look for "NotificationRecord" and then capture until the next "NotificationRecord" or end of block
    # We will then search WITHIN that block for pkg, title, text
    
    # Split content by "NotificationRecord" to get chunks
    chunks = content.split("NotificationRecord")
    
    for chunk in chunks[1:]: # Skip first empty chunk before first match
        try:
            # 1. Extract Package (usually near start: pkg=com.foo.bar)
            pkg_match = re.search(r'pkg=([^\s]+)', chunk)
            pkg = pkg_match.group(1) if pkg_match else "unknown"
            
            # 2. Extract Title (android.title=String (...) or title=...)
            # Android 14: extras={ ... android.title=String (My Title) ... }
            title = ""
            title_match = re.search(r'android\.title\s*=\s*String\s*\((.*?)\)', chunk)
            if not title_match:
                title_match = re.search(r'title\s*=\s*(.*?)[\n\r]', chunk)
            
            if title_match:
                title = title_match.group(1).strip()

            # 3. Extract Text (android.text=String (...) or text=...)
            text = ""
            text_match = re.search(r'android\.text\s*=\s*String\s*\((.*?)\)', chunk)
            if not text_match:
                text_match = re.search(r'text\s*=\s*(.*?)[\n\r]', chunk)
            
            if text_match:
                text = text_match.group(1).strip()
            
            # If still empty, try to match exact "text=Ra. 1.00" style if formatted differently
            if not text:
                 # fallback for simple dump
                 simple_text = re.search(r'text=([^\n]+)', chunk)
                 if simple_text: text = simple_text.group(1).strip()

            # 4. Extract Timestamp (when=1234567890)
            timestamp = datetime.now().isoformat() # Default to now if not found
            when_match = re.search(r'when=(\d+)', chunk)
            if when_match:
                try:
                    ts_millis = int(when_match.group(1))
                    if ts_millis > 0:
                        timestamp = datetime.fromtimestamp(ts_millis / 1000.0).isoformat()
                except:
                    pass

            # Combine title and text for pattern matching
            notification_content = f"{title} {text}"
            
            # Check for financial patterns
            for flag_type, flag_pattern in FINANCIAL_PATTERNS.items():
                if flag_pattern.search(notification_content):
                    notifications.append({
                        "package": pkg,
                        "title": title,
                        "text": text,
                        "content": notification_content,
                        "financial_flag": f"NOTIFICATION_{flag_type}",
                        "source": "notification_buffer",
                        "type": "NOTIFICATION",
                        "subtype": f"Banking Alert ({flag_type})",
                        "timestamp": timestamp 
                    })
                    break # Found a category, stop checking others
                    
        except Exception:
            continue

    return notifications

def save_notification_timeline(output_file="logs/notification_timeline.json"):
    """
    Save parsed notifications to JSON for timeline integration
    """
    notifications = parse_notification_buffer()
    
    if notifications is None:
         print("⚠️ No notification data to process. Skipping save to avoid overwriting existing timeline.")
         return

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(notifications, f, indent=4)
    
    print(f"📊 Parsed {len(notifications)} financial notifications")
    print(f"   Saved to: {output_file}")
    
    # Print summary
    if notifications:
        otp_count = sum(1 for n in notifications if "OTP" in n["financial_flag"])
        upi_count = sum(1 for n in notifications if "UPI" in n["financial_flag"])
        bank_count = sum(1 for n in notifications if "BANK" in n["financial_flag"])
        
        print(f"   - OTP notifications: {otp_count}")
        print(f"   - UPI notifications: {upi_count}")
        print(f"   - Bank notifications: {bank_count}")
    
    return notifications

if __name__ == "__main__":
    save_notification_timeline()
