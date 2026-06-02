
import os
import json
import re

def analyze_intents(logs_dir="logs", output_file="logs/intent_hunter.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        return

    findings = []
    
    # 1. URL Pattern
    url_regex = re.compile(r'(https?://[^\s<>"]+|content://[^\s<>"]+|file://[^\s<>"]+)')
    
    # 2. Intent Pattern (common ActivityManager output)
    # act=android.intent.action.VIEW dat=https://... cmp=...
    intent_regex = re.compile(r'act=([a-zA-Z0-9\._]+)(?:\s+dat=([^\s]+))?(?:\s+cmp=([^\s]+))?')

    seen_items = set()

    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line_no, line in enumerate(f, 1):
            line_content = line.strip()
            
            # Search for Intents
            intent_match = intent_regex.search(line_content)
            if intent_match:
                action, data, component = intent_match.groups()
                # Filter trivial intents
                if action not in ['android.intent.action.MAIN'] and (data or component):
                    item_key = f"{action}|{data}|{component}"
                    if item_key not in seen_items:
                        findings.append({
                            "type": "INTENT",
                            "action": action,
                            "data": data or "N/A",
                            "component": component or "N/A",
                            "line": line_no,
                            "raw": line_content
                        })
                        seen_items.add(item_key)

            # Search for loose URLs not captured above
            urls = url_regex.findall(line_content)
            for url in urls:
                if len(url) > 10 and "android.com" not in url and "schemas.android.com" not in url:
                    # Look if we already captured this in intent
                    if not any(f['data'] == url for f in findings if f['type'] == 'INTENT'):
                        if url not in seen_items:
                            findings.append({
                                "type": "URL",
                                "action": "Discovery",
                                "data": url,
                                "component": "N/A",
                                "line": line_no,
                                "raw": line_content
                            })
                            seen_items.add(url)

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(findings, f, indent=4)
    
    print(f"Hunted down {len(findings)} intents/URLs.")

if __name__ == "__main__":
    analyze_intents()
