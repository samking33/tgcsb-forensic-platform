
import os
import json
import re
from collections import Counter

def analyze_network(logs_dir="logs", output_file="logs/network_activity.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        return

    connections = []
    ip_pattern = r'\b(?:\d{1,3}\.){3}\d{1,3}\b'
    # Improved domain pattern to avoid catching things like 'ActivityManager.java'
    domain_pattern = r'\b([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*(?:\.(?:com|org|net|edu|gov|io|info|biz|me|ly|tv|ai)))\b'

    # Filter out system and common noise domains
    SYSTEM_DOMAINS = [
        'android.com', 'google.com', 'googleapis.com', 'gstatic.com', 
        'localhost', '127.0.0.1', '0.0.0.0', '::1',
        'apple.com', 'icloud.com' # Just in case cross-platform logs appear
    ]

    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            # Look for network-y keywords
            if any(k in line.lower() for k in ["socket", "http", "dns", "connect", "wget", "curl"]):
                ips = re.findall(ip_pattern, line)
                domains = re.findall(domain_pattern, line, re.I)
                
                for ip in ips:
                    if ip not in SYSTEM_DOMAINS:
                        connections.append({"type": "IP", "value": ip, "context": line.strip()})
                
                for domain in domains:
                    domain = domain.lower()
                    if not any(sys_d in domain for sys_d in SYSTEM_DOMAINS):
                        # Filter out source files (.java, .so, etc)
                        if not domain.endswith(('.java', '.so', '.cpp', '.h', '.xml', '.png', '.jpg')):
                            connections.append({"type": "Domain", "value": domain, "context": line.strip()})

    # Count frequencies
    counts = Counter(c['value'] for c in connections)
    
    # Unique results with hit counts
    unique_conns = []
    seen = set()
    for c in connections:
        if c['value'] not in seen:
            unique_conns.append({
                "type": c['type'],
                "value": c['value'],
                "hits": counts[c['value']],
                "last_context": c['context']
            })
            seen.add(c['value'])

    # Sort by hits descending
    unique_conns.sort(key=lambda x: x["hits"], reverse=True)

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(unique_conns, f, indent=4)
    
    print(f"Detected {len(unique_conns)} unique external connections.")

if __name__ == "__main__":
    analyze_network()
