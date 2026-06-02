"""
Clipboard & Input Reconstruction - Transient Data Recovery
Extracts clipboard events and input method manager activity
"""

import os
import json
import re
from datetime import datetime

def analyze_clipboard(logs_dir="logs", output_file="logs/clipboard_forensics.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        print("Logcat file not found")
        return

    clipboard_events = []
    ime_events = []
    seen_clipboard = set()
    seen_ime = set()
    
    # Enhanced clipboard patterns
    clipboard_patterns = [
        # ClipboardService logs that sometimes leak actual text
        re.compile(r'ClipboardService.*setPrimaryClip.*text[:\s=]+"?([^"<>\n]{3,100})"?', re.I),
        re.compile(r'ClipboardService.*clip.*data[:\s=]+"?([^"<>\n]{3,100})"?', re.I),
        # Apps accessing clipboard
        re.compile(r'ClipboardManager.*getPrimaryClip.*uid[:\s=]+([\d]+)', re.I),
        # Clipboard with package attribution
        re.compile(r'ClipboardService.*from package[:\s=]+([a-z0-9\.]+)', re.I),
    ]
    
    # Input Method Manager patterns
    ime_patterns = [
        # Text input events (sometimes logs text length or context)
        re.compile(r'InputMethodManager.*updateSelection.*text length[:\s=]+([\d]+)', re.I),
        re.compile(r'InputMethodManager.*setText.*length[:\s=]+([\d]+)', re.I),
        re.compile(r'InputMethodManager.*commitText.*"([^"]{1,50})"', re.I),
        # Keyboard suggestions (can reveal typed patterns)
        re.compile(r'LatinIME.*suggestion.*"([^"]{1,30})"', re.I),
        re.compile(r'InputMethod.*prediction.*word[:\s=]+"([^"]{1,30})"', re.I),
    ]
    
    # Password/sensitive input patterns
    sensitive_patterns = [
        re.compile(r'password|passwd|pwd', re.I),
        re.compile(r'otp|2fa|mfa|verification', re.I),
        re.compile(r'cvv|credit.*card|debit.*card', re.I),
        re.compile(r'pin|passcode', re.I),
    ]
    
    # Timestamp regex
    TS_REGEX = re.compile(r'^(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})')
    
    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line_no, line in enumerate(f, 1):
            line_content = line.strip()
            
            # Extract timestamp
            ts_match = TS_REGEX.match(line)
            timestamp = None
            if ts_match:
                current_year = datetime.now().year
                try:
                    ts = datetime.strptime(f"{current_year}-{ts_match.group(1)}", "%Y-%m-%d %H:%M:%S.%f")
                    timestamp = ts.isoformat()
                except:
                    pass
            
            # Check for sensitive context
            is_sensitive = any(pattern.search(line_content) for pattern in sensitive_patterns)
            
            # Search for clipboard events
            for pattern in clipboard_patterns:
                matches = pattern.findall(line_content)
                for match in matches:
                    if len(match) > 2 and match not in ['null', 'NULL']:
                        # Extract package if present
                        package_match = re.search(r'from package[:\s=]+([a-z0-9\.]+)', line_content, re.I)
                        package = package_match.group(1) if package_match else "Unknown"
                        
                        key = f"{match}_{timestamp}"
                        if key not in seen_clipboard:
                            clipboard_events.append({
                                "type": "CLIPBOARD",
                                "content": match[:100],  # Truncate long content
                                "package": package,
                                "is_sensitive": is_sensitive,
                                "timestamp": timestamp or "unknown",
                                "line": line_no,
                                "raw": line_content[:300]
                            })
                            seen_clipboard.add(key)
            
            # Search for IME events
            for pattern in ime_patterns:
                matches = pattern.findall(line_content)
                for match in matches:
                    if isinstance(match, str) and len(match) > 0:
                        key = f"{match}_{timestamp}"
                        if key not in seen_ime:
                            # Determine event type
                            event_type = "TEXT_LENGTH" if match.isdigit() else "TEXT_CONTENT"
                            
                            ime_events.append({
                                "type": "IME",
                                "event_type": event_type,
                                "content": match if not match.isdigit() else f"{match} chars",
                                "is_sensitive": is_sensitive,
                                "timestamp": timestamp or "unknown",
                                "line": line_no,
                                "raw": line_content[:300]
                            })
                            seen_ime.add(key)
    
    # Sort by timestamp
    clipboard_events.sort(key=lambda x: x["timestamp"])
    ime_events.sort(key=lambda x: x["timestamp"])
    
    # Prepare output
    output_data = {
        "clipboard_events": clipboard_events,
        "ime_events": ime_events,
        "summary": {
            "total_clipboard_events": len(clipboard_events),
            "total_ime_events": len(ime_events),
            "sensitive_clipboard_events": sum(1 for e in clipboard_events if e["is_sensitive"]),
            "sensitive_ime_events": sum(1 for e in ime_events if e["is_sensitive"])
        }
    }
    
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output_data, f, indent=4)
    
    print(f"Extracted {len(clipboard_events)} clipboard events and {len(ime_events)} IME events.")
    print(f"Sensitive clipboard events: {output_data['summary']['sensitive_clipboard_events']}")
    print(f"Sensitive IME events: {output_data['summary']['sensitive_ime_events']}")

if __name__ == "__main__":
    analyze_clipboard()
