
import json
from collections import Counter
from datetime import datetime

def analyze_timeline(file_path):
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except FileNotFoundError:
        print(f"File not found: {file_path}")
        return

    print(f"Total Events: {len(data)}")

    type_counts = Counter(item.get('type', 'Unknown') for item in data)
    severity_counts = Counter(item.get('severity', 'Unknown') for item in data)
    subtype_counts = Counter(item.get('subtype', 'Unknown') for item in data)

    print("\n--- Event Types ---")
    for evt_type, count in type_counts.most_common():
        print(f"{evt_type}: {count}")

    print("\n--- Severity Levels ---")
    for sev, count in severity_counts.most_common():
        print(f"{sev}: {count}")

    print("\n--- Top 10 Subtypes ---")
    for sub, count in subtype_counts.most_common(10):
        print(f"{sub}: {count}")

    print("\n--- High Severity Events (E) ---")
    errors = [item for item in data if item.get('severity') == 'E']
    if errors:
        for err in errors[:10]: # Show first 10
            print(f"[{err['timestamp']}] {err['type']}/{err['subtype']}: {err['content']}")
        if len(errors) > 10:
            print(f"... and {len(errors) - 10} more.")
    else:
        print("No High Severity events found.")

    print("\n--- Security Events ---")
    security_events = [item for item in data if item.get('type') == 'SECURITY']
    if security_events:
        for sec in security_events[:10]:
            print(f"[{sec['timestamp']}] {sec['subtype']}: {sec['content']}")
    else:
        print("No Security events found.")

    print("\n--- Ghost Gaps ---")
    ghost_events = [item for item in data if item.get('type') == 'GHOST']
    print(f"Total Ghost Gaps: {len(ghost_events)}")
    for ghost in ghost_events[:5]:
        print(f"[{ghost['timestamp']}] {ghost['content']}")

if __name__ == "__main__":
    analyze_timeline("logs/unified_timeline.json")
