import sys
import os
from collections import Counter
import re

# Add parent directory to path to import parsers
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from parsers import parse_sms_logs

def analyze_sms(logs_dir="logs"):
    log_path = os.path.join(logs_dir, "sms_logs.txt")
    if not os.path.exists(log_path):
        print(f"Error: {log_path} not found.")
        return

    with open(log_path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    sms_list = parse_sms_logs(content)
    
    print(f"Total SMS Analyzed: {len(sms_list)}")
    print("-" * 40)
    
    # By Type
    types = Counter(s['type'] for s in sms_list)
    print("Message Types:")
    for t, count in types.most_common():
        print(f"  {t}: {count}")
    print("-" * 40)
    
    # Top Contacts
    contacts = Counter(s.get('contact', 'Unknown') for s in sms_list)
    print("Top 10 Contacts:")
    for contact, count in contacts.most_common(10):
        print(f"  {contact}: {count}")
    print("-" * 40)
    
    # Keyword Analysis (Simple)
    keywords = ["OTP", "Bank", "Debit", "Credit", "Acct", "bal", "UPI", "Amazon", "Flipkart"]
    keyword_hits = Counter()
    
    for s in sms_list:
        msg = s.get('message', '').lower()
        for k in keywords:
            if k.lower() in msg:
                keyword_hits[k] += 1
                
    print("Keyword Hits (Financial/Transactional):")
    for k, count in keyword_hits.most_common():
        print(f"  {k}: {count}")
    print("-" * 40)
    
    # Latest 5 Messages
    print("Latest 5 Messages:")
    # Sort by date/time descending if possible, raw parse order usually implies reverse chrono in Android log dumps but let's trust list order for now or sort if needed.
    # The parser keeps file order. `dumpsys` usually outputs newest first or oldest first depending on command.
    # Let's inspect the dates of first few to guess or just print first 5.
    
    # Trying to sort by date just in case
    def get_sort_key(x):
        try:
            return f"{x['date']} {x['time']}"
        except:
            return ""
            
    sms_list.sort(key=get_sort_key, reverse=True)
    
    for s in sms_list[:5]:
        print(f"  {s.get('date')} {s.get('time')} | {s.get('contact')} | {s.get('type')} | {s.get('message')[:50]}...")

if __name__ == "__main__":
    analyze_sms()
