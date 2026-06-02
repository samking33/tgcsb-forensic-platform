import json

with open('logs/unified_timeline.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Analyze different event types
sms = [e for e in data if e['type'] == 'SMS']
calls = [e for e in data if e['type'] == 'CALL']
logcat = [e for e in data if e['type'].startswith('LOGCAT')]

print("=" * 80)
print("TIMELINE DATA ANALYSIS")
print("=" * 80)

if sms:
    print(f"\nSMS: {len(sms)} events")
    print(f"  First: {sms[0]['timestamp']}")
    print(f"  Last:  {sms[-1]['timestamp']}")

if calls:
    print(f"\nCalls: {len(calls)} events")
    print(f"  First: {calls[0]['timestamp']}")
    print(f"  Last:  {calls[-1]['timestamp']}")

if logcat:
    print(f"\nLogcat: {len(logcat)} events")
    print(f"  First: {logcat[0]['timestamp']}")
    print(f"  Last:  {logcat[-1]['timestamp']}")

print(f"\nTotal events: {len(data)}")
