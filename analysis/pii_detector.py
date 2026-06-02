
import os
import json
import re

def detect_pii(logs_dir="logs", output_file="logs/pii_leaks.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        return

    leaks = []
    pii_patterns = {
        "Email Address": r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
        "Auth/Bearer Token": r'auth_token[=\s\']+[a-zA-Z0-9._-]+|Bearer\s+[a-zA-Z0-9._-]+|access_token[=\s\']+[a-zA-Z0-9._-]+',
        "GPS Coordinates": r'(?:lat|latitude|lon|longitude)[^0-9.-]+([-+]?\d+\.\d+)',
        "API/Secret Key": r'(?:api_key|apikey|secret_key|app_secret|client_secret)[=\s\':]+([a-zA-Z0-9_-]{16,})',
        "Credential": r'(?:password|passwd|pwd|secret)[=\s\':]+([^\s,;]{4,})',
        "IMEI/DeviceID": r'\b\d{15}\b|deviceId[=\s\']+(\d{15})'
    }

    # Common tags to ignore (noise)
    IGNORE_TAGS = ['InputMethodManager', 'ViewRootImpl', 'Choreographer']

    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line_no, line in enumerate(f, 1):
            # Basic sanity check to avoid binary/junk lines
            if len(line) > 1000 or not any(c.isalnum() for c in line[:10]):
                continue
                
            skip = False
            for tag in IGNORE_TAGS:
                if tag in line:
                    skip = True
                    break
            if skip: continue

            for label, pattern in pii_patterns.items():
                match = re.search(pattern, line, re.I)
                if match:
                    # For password/keys, don't show the full match to keep forensic JSON somewhat clean, 
                    # but keep it in 'content'
                    leaks.append({
                        "line": line_no,
                        "type": label,
                        "value": match.group(1) if match.groups() else match.group(0),
                        "content": line.strip()
                    })

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(leaks, f, indent=4)
    
    print(f"Detected {len(leaks)} potential PII leaks.")

if __name__ == "__main__":
    detect_pii()
