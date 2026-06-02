"""
WiFi & Bluetooth Beacon Map - Location Inference
Extracts WiFi SSIDs and Bluetooth device addresses to infer physical locations
"""

import os
import json
import re
from datetime import datetime
from collections import defaultdict

def analyze_beacons(logs_dir="logs", output_file="logs/beacon_map.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        print("Logcat file not found")
        return

    wifi_networks = []
    bluetooth_devices = []
    seen_wifi = set()
    seen_bt = set()
    
    # Enhanced regex patterns for WiFi
    wifi_patterns = [
        # SSID in connection logs
        re.compile(r'WifiManager.*SSID[:\s=]+["\']?([^"\'<>\s,]+)["\']?', re.I),
        re.compile(r'NetworkInfo.*SSID:\s*"?([^"<>,\s]+)"?', re.I),
        re.compile(r'WifiStateMachine.*mTargetNetworkId.*SSID:\s*"?([^"<>,\s]+)"?', re.I),
        re.compile(r'WifiConfig.*ssid[:\s=]+["\']([^"\']+)["\']', re.I),
        # Scan results
        re.compile(r'WifiNative.*scan_results.*SSID:\s*"?([^"\s]+)"?', re.I),
        re.compile(r'WifiScanner.*SSID[:\s=]+"?([^"\s<>,]+)"?', re.I),
    ]
    
    # Enhanced regex patterns for Bluetooth
    bt_patterns = [
        # Bluetooth MAC addresses and device names
        re.compile(r'BluetoothDevice.*\[([0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2})\]', re.I),
        re.compile(r'Bluetooth.*address[:\s=]+([0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2})', re.I),
        re.compile(r'BluetoothAdapter.*device.*name[:\s=]+"?([^"<>\s,]+)"?', re.I),
        re.compile(r'A2dpService.*device[:\s=]+([0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2})', re.I),
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
            
            # Search for WiFi SSIDs
            for pattern in wifi_patterns:
                matches = pattern.findall(line_content)
                for ssid in matches:
                    # Filter out empty, generic, or system SSIDs
                    if (len(ssid) > 2 and 
                        ssid not in ['null', 'unknown', 'UNKNOWN', '<unknown ssid>'] and
                        not ssid.startswith('0x')):
                        
                        # Create unique key for deduplication
                        key = f"{ssid}_{timestamp}"
                        if key not in seen_wifi:
                            wifi_networks.append({
                                "type": "WiFi",
                                "ssid": ssid,
                                "timestamp": timestamp or "unknown",
                                "line": line_no,
                                "raw": line_content[:200]
                            })
                            seen_wifi.add(key)
            
            # Search for Bluetooth devices
            for pattern in bt_patterns:
                matches = pattern.findall(line_content)
                for device in matches:
                    # Check if it's a MAC address
                    if ':' in device and len(device) == 17:
                        key = f"{device}_{timestamp}"
                        if key not in seen_bt:
                            bluetooth_devices.append({
                                "type": "Bluetooth",
                                "address": device,
                                "name": "Unknown",  # Name extraction can be enhanced
                                "timestamp": timestamp or "unknown",
                                "line": line_no,
                                "raw": line_content[:200]
                            })
                            seen_bt.add(key)
                    # Or if it's a device name
                    elif len(device) > 3 and ':' not in device:
                        key = f"{device}_{timestamp}"
                        if key not in seen_bt:
                            bluetooth_devices.append({
                                "type": "Bluetooth",
                                "address": "Unknown",
                                "name": device,
                                "timestamp": timestamp or "unknown",
                                "line": line_no,
                                "raw": line_content[:200]
                            })
                            seen_bt.add(key)
    
    # Aggregate by unique SSID/Device
    wifi_aggregated = defaultdict(lambda: {"count": 0, "first_seen": None, "last_seen": None, "contexts": []})
    bt_aggregated = defaultdict(lambda: {"count": 0, "first_seen": None, "last_seen": None, "contexts": []})
    
    for wifi in wifi_networks:
        ssid = wifi["ssid"]
        wifi_aggregated[ssid]["count"] += 1
        if not wifi_aggregated[ssid]["first_seen"]:
            wifi_aggregated[ssid]["first_seen"] = wifi["timestamp"]
        wifi_aggregated[ssid]["last_seen"] = wifi["timestamp"]
        if len(wifi_aggregated[ssid]["contexts"]) < 3:  # Keep max 3 contexts
            wifi_aggregated[ssid]["contexts"].append(wifi["raw"])
    
    for bt in bluetooth_devices:
        device_id = bt["address"] if bt["address"] != "Unknown" else bt["name"]
        bt_aggregated[device_id]["count"] += 1
        if not bt_aggregated[device_id]["first_seen"]:
            bt_aggregated[device_id]["first_seen"] = bt["timestamp"]
        bt_aggregated[device_id]["last_seen"] = bt["timestamp"]
        if bt["name"] != "Unknown":
            bt_aggregated[device_id]["name"] = bt["name"]
        if bt["address"] != "Unknown":
            bt_aggregated[device_id]["address"] = bt["address"]
        if len(bt_aggregated[device_id]["contexts"]) < 3:
            bt_aggregated[device_id]["contexts"].append(bt["raw"])
    
    # Prepare output
    output_data = {
        "wifi_networks": [
            {
                "ssid": ssid,
                "count": data["count"],
                "first_seen": data["first_seen"],
                "last_seen": data["last_seen"],
                "contexts": data["contexts"]
            }
            for ssid, data in sorted(wifi_aggregated.items(), key=lambda x: x[1]["count"], reverse=True)
        ],
        "bluetooth_devices": [
            {
                "identifier": device_id,
                "name": data.get("name", "Unknown"),
                "address": data.get("address", "Unknown"),
                "count": data["count"],
                "first_seen": data["first_seen"],
                "last_seen": data["last_seen"],
                "contexts": data["contexts"]
            }
            for device_id, data in sorted(bt_aggregated.items(), key=lambda x: x[1]["count"], reverse=True)
        ],
        "summary": {
            "total_wifi_networks": len(wifi_aggregated),
            "total_bluetooth_devices": len(bt_aggregated),
            "total_wifi_events": sum(d["count"] for d in wifi_aggregated.values()),
            "total_bluetooth_events": sum(d["count"] for d in bt_aggregated.values())
        }
    }
    
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output_data, f, indent=4)
    
    print(f"Extracted {len(wifi_aggregated)} WiFi networks and {len(bt_aggregated)} Bluetooth devices.")
    print(f"Total WiFi events: {output_data['summary']['total_wifi_events']}")
    print(f"Total Bluetooth events: {output_data['summary']['total_bluetooth_events']}")

if __name__ == "__main__":
    analyze_beacons()
