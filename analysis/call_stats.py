import sys
import os
from collections import Counter
from datetime import datetime

# Add parent directory to path to import parsers
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from parsers import parse_call_logs

def analyze_calls(logs_dir="logs"):
    log_path = os.path.join(logs_dir, "call_logs.txt")
    if not os.path.exists(log_path):
        print(f"Error: {log_path} not found.")
        return

    with open(log_path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    calls = parse_call_logs(content)
    
    print(f"Total Calls Analyzed: {len(calls)}")
    print("-" * 40)
    
    # By Type
    types = Counter(c['type'] for c in calls)
    print("Call Types:")
    for t, count in types.most_common():
        print(f"  {t}: {count}")
    print("-" * 40)
    
    # Top Contacts
    contacts = Counter(c.get('contact', 'Unknown') for c in calls)
    print("Top 10 Contacts:")
    for contact, count in contacts.most_common(10):
        print(f"  {contact}: {count}")
    print("-" * 40)
    
    # Longest Calls
    # Need to parse duration "M:SS" to seconds
    def duration_to_seconds(dur_str):
        try:
            parts = dur_str.split(':')
            if len(parts) == 2:
                return int(parts[0]) * 60 + int(parts[1])
            return 0
        except:
            return 0

    calls_with_dur = []
    for c in calls:
        sec = duration_to_seconds(c.get('duration', '0:00'))
        c['duration_seconds'] = sec
        calls_with_dur.append(c)
        
    calls_with_dur.sort(key=lambda x: x['duration_seconds'], reverse=True)
    
    print("Longest 5 Calls:")
    for c in calls_with_dur[:5]:
        print(f"  {c.get('contact')} - {c.get('duration')} ({c.get('date')} {c.get('time')} - {c.get('type')})")

if __name__ == "__main__":
    analyze_calls()
