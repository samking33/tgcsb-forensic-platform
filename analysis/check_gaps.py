import json
from datetime import datetime

# Load timeline
with open('logs/unified_timeline.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Filter logcat events
logcat_events = [e for e in data if e['type'].startswith('LOGCAT')]
print(f"Total logcat events: {len(logcat_events)}")

if len(logcat_events) < 2:
    print("Not enough logcat events to analyze gaps")
    exit()

# Calculate gaps
gaps = []
for i in range(len(logcat_events) - 1):
    try:
        t1 = datetime.fromisoformat(logcat_events[i]['timestamp'])
        t2 = datetime.fromisoformat(logcat_events[i+1]['timestamp'])
        gap_seconds = (t2 - t1).total_seconds()
        gaps.append({
            'gap_seconds': gap_seconds,
            'gap_minutes': gap_seconds / 60,
            'gap_hours': gap_seconds / 3600,
            'after_event': i,
            'time1': logcat_events[i]['timestamp'],
            'time2': logcat_events[i+1]['timestamp']
        })
    except:
        pass

# Sort by gap size (largest first)
gaps.sort(key=lambda x: x['gap_seconds'], reverse=True)

print(f"\nTop 10 largest gaps between logcat events:")
print("=" * 80)
for i, gap in enumerate(gaps[:10], 1):
    if gap['gap_hours'] >= 1:
        duration = f"{gap['gap_hours']:.1f} hours"
    elif gap['gap_minutes'] >= 1:
        duration = f"{gap['gap_minutes']:.1f} minutes"
    else:
        duration = f"{gap['gap_seconds']:.1f} seconds"
    
    print(f"{i}. Gap: {duration}")
    print(f"   From: {gap['time1']}")
    print(f"   To:   {gap['time2']}")
    print()

# Stats
print("\nGap Statistics:")
print("=" * 80)
gaps_over_5min = [g for g in gaps if g['gap_seconds'] > 300]
gaps_over_1hour = [g for g in gaps if g['gap_seconds'] > 3600]
gaps_over_1day = [g for g in gaps if g['gap_seconds'] > 86400]

print(f"Gaps > 5 minutes: {len(gaps_over_5min)}")
print(f"Gaps > 1 hour: {len(gaps_over_1hour)}")
print(f"Gaps > 1 day: {len(gaps_over_1day)}")

if len(gaps) > 0:
    max_gap = gaps[0]
    if max_gap['gap_hours'] >= 1:
        max_duration = f"{max_gap['gap_hours']:.1f} hours"
    elif max_gap['gap_minutes'] >= 1:
        max_duration = f"{max_gap['gap_minutes']:.1f} minutes"
    else:
        max_duration = f"{max_gap['gap_seconds']:.1f} seconds"
    print(f"Largest gap: {max_duration}")
