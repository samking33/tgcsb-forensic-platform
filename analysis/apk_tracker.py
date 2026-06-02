
import os
import json
import re
from datetime import datetime

def analyze_apk_movements(logs_dir="logs", output_file="logs/apk_analysis.json"):
    """
    APK Tracker: Specifically filters for APK-related lifecycle events.
    1. Downloads (Network/Intents)
    2. Installs (Package Manager)
    3. Sideloaded Apps (from Package Dump)
    """
    
    events = []
    
    # 1. Check for Downloads (Intents & Network)
    intent_path = os.path.join(logs_dir, "intent_hunter.json")
    if os.path.exists(intent_path):
        try:
            with open(intent_path, "r", encoding="utf-8") as f:
                intents = json.load(f)
                for item in intents:
                    data = item.get("data", "").lower()
                    if ".apk" in data:
                        events.append({
                            "stage": "DOWNLOAD_ATTEMPT",
                            "timestamp": "See Log Line " + str(item.get("line")),
                            "details": f"Intent to view/download APK: {data}",
                            "source": item.get("action"),
                            "risk": "HIGH"
                        })
        except: pass
    
    # 2. Check for Installs (From Unified Timeline)
    timeline_path = os.path.join(logs_dir, "unified_timeline.json")
    if os.path.exists(timeline_path):
        try:
            with open(timeline_path, "r", encoding="utf-8") as f:
                timeline = json.load(f)
                for item in timeline:
                    if item.get("type") == "APP_LIFECYCLE":
                        content = item.get("content", "")
                        subtype = item.get("subtype", "")
                        
                        events.append({
                            "stage": "INSTALLATION",
                            "timestamp": item.get("timestamp"),
                            "details": content,
                            "source": subtype,
                            "risk": "CRITICAL" if "Sideload" in subtype or "Unknown" in subtype else "LOW"
                        })
                    
                    if "application/vnd.android.package-archive" in item.get("content", ""):
                         events.append({
                            "stage": "MANUAL_OPEN",
                            "timestamp": item.get("timestamp"),
                            "details": "User manually opened an APK file",
                            "source": "File Manager / Downloads",
                            "risk": "MEDIUM"
                        })
        except: pass

    # 3. Scan Raw Logcat for DownloadManager
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if os.path.exists(logcat_path):
        try:
            with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
                for line in f:
                    if "DownloadManager" in line and ".apk" in line:
                         events.append({
                            "stage": "DOWNLOAD_MANAGER",
                            "timestamp": "Log Timestamp",
                            "details": line.strip()[:150],
                            "source": "System Download Manager",
                            "risk": "MEDIUM"
                        })
        except: pass

    # 4. NEW: Scan Package Dump for Sideloaded Apps (installerPackageName=null)
    package_dump_path = os.path.join(logs_dir, "dump_package.txt")
    if os.path.exists(package_dump_path):
        try:
            with open(package_dump_path, "r", encoding="utf-8", errors="replace") as f:
                current_package = None
                package_data = {}
                
                for line in f:
                    # Detect package start (note: has leading spaces)
                    if "Package [" in line and "] (" in line:
                        # Process previous package if exists
                        if current_package and package_data.get("installer") == "null" and not package_data.get("is_system", False):
                            events.append({
                                "stage": "SIDELOADED_APP",
                                "timestamp": package_data.get("install_time", "Unknown"),
                                "details": f"Sideloaded App: {current_package}",
                                "source": "Manual Installation (No Play Store)",
                                "risk": "CRITICAL"
                            })
                        
                        # Start new package
                        match = re.search(r'Package \[([^\]]+)\]', line)
                        if match:
                            current_package = match.group(1)
                            package_data = {}
                    
                    # Collect package data
                    elif current_package:
                        # Check if system app
                        if "pkgFlags=[ SYSTEM" in line or "privateFlags=[ SYSTEM" in line or "flags=[ SYSTEM" in line:
                            package_data["is_system"] = True
                        
                        # Extract installer
                        if "installerPackageName=" in line:
                            match = re.search(r'installerPackageName=([^\s]+)', line)
                            if match:
                                package_data["installer"] = match.group(1)
                        
                        # Extract install time
                        if "firstInstallTime=" in line:
                            match = re.search(r'firstInstallTime=(.+)', line)
                            if match:
                                package_data["install_time"] = match.group(1).strip()
                
                # Process last package
                if current_package and package_data.get("installer") == "null" and not package_data.get("is_system", False):
                    events.append({
                        "stage": "SIDELOADED_APP",
                        "timestamp": package_data.get("install_time", "Unknown"),
                        "details": f"Sideloaded App: {current_package}",
                        "source": "Manual Installation (No Play Store)",
                        "risk": "CRITICAL"
                    })
        except Exception as e:
            print(f"Error scanning package dump: {e}")

    output = {
        "summary": {
            "total_events": len(events),
            "sideloads_detected": sum(1 for e in events if e["risk"] == "CRITICAL")
        },
        "events": events
    }

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output, f, indent=4)
    
    if len(events) == 0:
        print("✅ APK Hunter scan complete.")
        print("   No APK downloads or sideloads detected - device appears clean!")
    else:
        print(f"⚠️  APK Hunter scan complete. Found {len(events)} events.")
        print(f"   High-risk sideloads: {output['summary']['sideloads_detected']}")

if __name__ == "__main__":
    analyze_apk_movements()
