"""
Device Identifier Parser for Section 65B Certificate
Extracts IMEI, Serial Number, and device properties
"""

import os
import re
import json
import hashlib
import hmac
from datetime import datetime

def parse_device_identifiers(identifiers_file="logs/device_identifiers.txt"):
    """
    Parse device identifiers from dumpsys iphonesubinfo and getprop
    """
    if not os.path.exists(identifiers_file):
        print(f"⚠️ Device identifiers file not found: {identifiers_file}")
        return {
            "imei": "UNAVAILABLE",
            "serial_number": "UNAVAILABLE",
            "model": "Unknown",
            "manufacturer": "Unknown",
            "android_version": "Unknown"
        }
    
    with open(identifiers_file, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    
    # Extract IMEI/MEID
    imei_match = re.search(r'Device ID\s*[=:]\s*(\d+)', content)
    meid_match = re.search(r'Device MEID\s*[=:]\s*([0-9A-Fa-f]+)', content)
    imei = imei_match.group(1) if imei_match else (meid_match.group(1) if meid_match else "UNAVAILABLE")
    
    # Extract Serial Number
    serial_match = re.search(r'\[ro\.serialno\]:\s*\[([^\]]+)\]', content)
    serial = serial_match.group(1) if serial_match else "UNAVAILABLE"
    
    # Extract Device Properties
    model_match = re.search(r'\[ro\.product\.model\]:\s*\[([^\]]+)\]', content)
    manufacturer_match = re.search(r'\[ro\.product\.manufacturer\]:\s*\[([^\]]+)\]', content)
    android_match = re.search(r'\[ro\.build\.version\.release\]:\s*\[([^\]]+)\]', content)
    sdk_match = re.search(r'\[ro\.build\.version\.sdk\]:\s*\[([^\]]+)\]', content)
    build_match = re.search(r'\[ro\.build\.id\]:\s*\[([^\]]+)\]', content)
    
    return {
        "imei": imei,
        "serial_number": serial,
        "model": model_match.group(1) if model_match else "Unknown",
        "manufacturer": manufacturer_match.group(1) if manufacturer_match else "Unknown",
        "android_version": android_match.group(1) if android_match else "Unknown",
        "sdk_version": sdk_match.group(1) if sdk_match else "Unknown",
        "build_id": build_match.group(1) if build_match else "Unknown"
    }

def generate_chain_of_custody(officer_id, evidence_hash, timestamp, secret_key="TGCSB_FORENSIC_KEY_2026"):
    """
    Generate cryptographic chain of custody
    Links Officer ID to Evidence Hash with timestamp
    """
    message = f"{officer_id}|{evidence_hash}|{timestamp}"
    signature = hmac.new(
        secret_key.encode(),
        message.encode(),
        hashlib.sha256
    ).hexdigest()
    
    return {
        "officer_id": officer_id,
        "evidence_hash": evidence_hash,
        "timestamp": timestamp,
        "custody_signature": signature,
        "verification_message": f"HMAC-SHA256('{message}', SECRET_KEY)"
    }

def calculate_master_hash(logs_dir="logs"):
    """
    Calculate a master hash of all evidence file hashes
    """
    evidence_files = [
        "android_logcat.txt",
        "call_logs.txt",
        "sms_logs.txt",
        "notification_history.txt",
        "device_identifiers.txt",
        "dual_space_apps.txt",
        "usage_stats.txt"
    ]
    
    all_hashes = []
    for filename in evidence_files:
        filepath = os.path.join(logs_dir, filename)
        if os.path.exists(filepath):
            sha256 = hashlib.sha256()
            with open(filepath, 'rb') as f:
                while True:
                    chunk = f.read(8192)
                    if not chunk:
                        break
                    sha256.update(chunk)
            all_hashes.append(sha256.hexdigest())
    
    # Create master hash from all individual hashes
    master_hash = hashlib.sha256('|'.join(all_hashes).encode()).hexdigest()
    return master_hash

def generate_section_65b_data(officer_id="IO_TGCSB_001", case_number="TGCSB/2026/123456"):
    """
    Generate complete Section 65B certificate data
    """
    device_info = parse_device_identifiers()
    timestamp = datetime.now().isoformat()
    master_hash = calculate_master_hash()
    
    chain_of_custody = generate_chain_of_custody(
        officer_id=officer_id,
        evidence_hash=master_hash,
        timestamp=timestamp
    )
    
    section_65b_data = {
        "acquisition_time": timestamp,
        "acquisition_date": datetime.now().strftime("%d/%m/%Y"),
        "acquisition_time_only": datetime.now().strftime("%H:%M:%S"),
        "device_identifiers": device_info,
        "chain_of_custody": chain_of_custody,
        "examiner": officer_id,
        "case_number": case_number,
        "master_evidence_hash": master_hash
    }
    
    # Save to JSON
    with open("logs/section_65b_data.json", "w", encoding="utf-8") as f:
        json.dump(section_65b_data, f, indent=4)
    
    print(f"📜 Section 65B Certificate Data Generated:")
    print(f"   IMEI: {device_info['imei']}")
    print(f"   Serial: {device_info['serial_number']}")
    print(f"   Model: {device_info['manufacturer']} {device_info['model']}")
    print(f"   Android: {device_info['android_version']}")
    print(f"   Master Hash: {master_hash[:32]}...")
    print(f"   Chain of Custody: {chain_of_custody['custody_signature'][:32]}...")
    
    return section_65b_data

if __name__ == "__main__":
    generate_section_65b_data()
