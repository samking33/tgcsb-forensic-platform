"""
App Usage Sessionizer - Screen Time Forensics
Calculates precise app usage durations from foreground/background events
"""

import os
import sys
import json
import re
from datetime import datetime, timedelta
from collections import defaultdict

# Force UTF-8 encoding for stdout to prevent Windows cp1252 errors
if sys.platform == "win32" and hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

# TGCSB Mule Hunter: Banking & Payment Apps Database
# Devices with >5 banking apps are flagged as "Suspected Mule Accounts"
BANKING_APPS = [
    # UPI Payment Apps
    "com.phonepe.app",                                    # PhonePe
    "net.one97.paytm",                                    # Paytm
    "com.google.android.apps.nbu.paisa.user",            # Google Pay
    "in.org.npci.upiapp",                                # BHIM UPI
    "com.amazon.mShop.android.shopping",                 # Amazon Pay
    "com.mobikwik_new",                                  # MobiKwik
    "com.freecharge.android",                            # FreeCharge
    
    # Major Indian Banks
    "com.sbi.lotusintouch",                              # State Bank of India
    "com.axis.mobile",                                   # Axis Bank
    "com.icicibank.mobile",                              # ICICI Bank
    "com.hdfc.mobile",                                   # HDFC Bank
    "com.pnb.mobile",                                    # Punjab National Bank
    "com.bankofbaroda.mpassbook",                        # Bank of Baroda
    "com.canara.canaramobile",                           # Canara Bank
    "com.unionbank.ebanking",                            # Union Bank
    "com.idbi.mobile",                                   # IDBI Bank
    "com.boi.mobile",                                    # Bank of India
    "com.indusind.mobile",                               # IndusInd Bank
    "com.yesbank.mobile",                                # Yes Bank
    "com.kotak.mobile",                                  # Kotak Mahindra Bank
    
    # International Banks (Common in India)
    "com.citi.citimobile",                               # Citibank
    "com.sc.mobile",                                     # Standard Chartered
    "com.hsbc.hsbcindia",                                # HSBC
    "com.rbl.mobile",                                    # RBL Bank
    
    # Digital Banks & Fintech
    "com.fi.money",                                      # Fi Money
    "com.jupiter.money",                                 # Jupiter
    "com.slice.app",                                     # Slice
    "com.dreamplug.androidapp",                          # Cred
    "com.paypal.android.p2pmobile",                      # PayPal
]

def analyze_app_sessions(logs_dir="logs", output_file="logs/app_sessions.json"):
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    
    foreground_events = []
    background_events = []
    sessions = []
    app_stats = defaultdict(lambda: {
        "total_duration": 0,
        "session_count": 0,
        "first_use": None,
        "last_use": None,
        "avg_session_duration": 0
    })

    # Only process logcat if it exists
    if os.path.exists(logcat_path):
        # Enhanced patterns for app lifecycle events
        # ActivityManager logs when apps move to foreground/background
        foreground_patterns = [
            re.compile(r'ActivityManager.*moveTaskToFront.*package[:\s=]+([a-z0-9\.]+)', re.I),
            re.compile(r'ActivityManager.*START.*([a-z0-9\.]+)/[^\s]+', re.I),
            re.compile(r'ActivityManager.*Displayed\s+([a-z0-9\.]+)/', re.I),
            re.compile(r'ActivityManager.*act=android\.intent\.action\.MAIN.*cmp=([a-z0-9\.]+)/', re.I),
        ]
        
        background_patterns = [
            re.compile(r'ActivityManager.*onPause.*([a-z0-9\.]+)', re.I),
            re.compile(r'ActivityManager.*onStop.*([a-z0-9\.]+)', re.I),
            re.compile(r'ActivityManager.*moveTaskToBack.*package[:\s=]+([a-z0-9\.]+)', re.I),
        ]
        
        # Timestamp regex
        TS_REGEX = re.compile(r'^(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})')
        
        try:
            with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
                for line_no, line in enumerate(f, 1):
                    line_content = line.strip()
                    
                    # Extract timestamp
                    ts_match = TS_REGEX.match(line)
                    if not ts_match:
                        continue
                        
                    current_year = datetime.now().year
                    try:
                        ts = datetime.strptime(f"{current_year}-{ts_match.group(1)}", "%Y-%m-%d %H:%M:%S.%f")
                    except:
                        continue
                    
                    # Search for foreground events
                    for pattern in foreground_patterns:
                        match = pattern.search(line_content)
                        if match:
                            package = match.group(1)
                            # Filter out system packages
                            if not package.startswith('com.android.') and not package.startswith('android'):
                                foreground_events.append({
                                    "timestamp": ts,
                                    "package": package,
                                    "event": "FOREGROUND",
                                    "line": line_no
                                })
                                break
                    
                    # Search for background events
                    for pattern in background_patterns:
                        match = pattern.search(line_content)
                        if match:
                            package = match.group(1)
                            if not package.startswith('com.android.') and not package.startswith('android'):
                                background_events.append({
                                    "timestamp": ts,
                                    "package": package,
                                    "event": "BACKGROUND",
                                    "line": line_no
                                })
                                break
        except Exception as e:
            print(f"Warning: Failed to read logcat: {e}")
        
        # Combine and sort events
        all_events = foreground_events + background_events
        all_events.sort(key=lambda x: x["timestamp"])
        
        # Calculate sessions
        app_states_tracker = {}  # Track current state of each app
        
        for event in all_events:
            package = event["package"]
            
            if event["event"] == "FOREGROUND":
                # Start a new session
                if package in app_states_tracker and app_states_tracker[package]["state"] == "FOREGROUND":
                    # Already in foreground, this might be a duplicate or new activity
                    continue
                
                app_states_tracker[package] = {
                    "state": "FOREGROUND",
                    "start_time": event["timestamp"],
                    "start_line": event["line"]
                }
            
            elif event["event"] == "BACKGROUND":
                # End the session
                if package in app_states_tracker and app_states_tracker[package]["state"] == "FOREGROUND":
                    start_time = app_states_tracker[package]["start_time"]
                    duration = (event["timestamp"] - start_time).total_seconds()
                    
                    # Only record sessions longer than 1 second
                    if duration > 1:
                        sessions.append({
                            "package": package,
                            "start_time": start_time.isoformat(),
                            "end_time": event["timestamp"].isoformat(),
                            "duration_seconds": round(duration, 2),
                            "duration_human": format_duration(duration),
                            "start_line": app_states_tracker[package]["start_line"],
                            "end_line": event["line"]
                        })
                    
                    app_states_tracker[package]["state"] = "BACKGROUND"
        
        # Calculate aggregated statistics per app
        for session in sessions:
            package = session["package"]
            app_stats[package]["total_duration"] += session["duration_seconds"]
            app_stats[package]["session_count"] += 1
            
            if not app_stats[package]["first_use"]:
                app_stats[package]["first_use"] = session["start_time"]
            app_stats[package]["last_use"] = session["end_time"]
        
        # Calculate averages
        for package in app_stats:
            if app_stats[package]["session_count"] > 0:
                app_stats[package]["avg_session_duration"] = round(
                    app_stats[package]["total_duration"] / app_stats[package]["session_count"], 2
                )
            app_stats[package]["total_duration_human"] = format_duration(app_stats[package]["total_duration"])
            app_stats[package]["avg_session_duration_human"] = format_duration(app_stats[package]["avg_session_duration"])
    else:
        print("Logcat file not found. Skipping usage analysis, proceeding to package scan...")

    # Continue to output generation...
    sorted_apps = sorted(
        [{"package": k, **v} for k, v in app_stats.items()],
        key=lambda x: x["total_duration"],
        reverse=True
    )
    
    # TGCSB Mule Hunter: Detect Banking Apps
    # 🆕 UPARADE: Check ALL installed packages, not just used ones
    installed_packages = get_installed_packages(logs_dir)
    
    # If we found installed packages, use that list for detection
    # Otherwise fallback to usage stats (backward compatibility)
    if installed_packages:
        all_banking_apps = [pkg for pkg in installed_packages if pkg in BANKING_APPS]
    else:
        all_banking_apps = [pkg for pkg in app_stats.keys() if pkg in BANKING_APPS]
        
    # Remove duplicates
    all_banking_apps = list(set(all_banking_apps))
    
    mule_suspected = len(all_banking_apps) > 5
    
    # Get detailed banking app stats (for those that have usage)
    banking_app_details = []
    for pkg in all_banking_apps:
        details = {
            "package": pkg,
            "status": "Installed",
            "total_duration": 0,
            "total_duration_human": "0s",
            "session_count": 0,
            "first_use": None,
            "last_use": None
        }
        
        # If we have usage stats, overlay them
        if pkg in app_stats:
            details.update({
                "status": "Active Usage",
                "total_duration": app_stats[pkg]["total_duration"],
                "total_duration_human": app_stats[pkg]["total_duration_human"],
                "session_count": app_stats[pkg]["session_count"],
                "first_use": app_stats[pkg]["first_use"],
                "last_use": app_stats[pkg]["last_use"]
            })
        banking_app_details.append(details)
    
    # Sort banking apps: Active first, then by name
    banking_app_details.sort(key=lambda x: (x["status"] == "Active Usage", x["package"]), reverse=True)
    
    # Prepare output
    output_data = {
        "sessions": sessions,
        "app_statistics": sorted_apps,
        "summary": {
            "total_sessions": len(sessions),
            "unique_apps": len(app_stats),
            "total_usage_time": sum(s["duration_seconds"] for s in sessions),
            "total_usage_time_human": format_duration(sum(s["duration_seconds"] for s in sessions)),
            
            # TGCSB Mule Hunter Fields
            "unique_banking_apps": len(all_banking_apps),
            "banking_apps_list": all_banking_apps,
            "banking_app_details": banking_app_details,
            "mule_suspected": mule_suspected,
            "mule_risk_level": "HIGH" if mule_suspected else ("MEDIUM" if len(all_banking_apps) >= 3 else "LOW"),
            "mule_detection_reason": f"Device has {len(all_banking_apps)} banking apps installed (threshold: >5)" if mule_suspected else None
        }
    }

    # Heuristic Detection (Find unknown financial apps)
    FINANCIAL_KEYWORDS = ['wallet', 'pay', 'bank', 'upi', 'crypto', 'coin', 'exchange', 'finance', 'money', 'loan', 'credit', 'card', 'invest', 'gold', 'cash', 'rupee', 'paisa']
    EXCLUDED_KEYWORDS = ['display', 'wallpaper', 'gameplay', 'backup', 'provider', 'service', 'setting']
    
    heuristic_apps = []
    
    for pkg in installed_packages:
        if pkg in all_banking_apps:
            continue
            
        pkg_lower = pkg.lower()
        is_suspect = False
        
        for kw in FINANCIAL_KEYWORDS:
            if kw in pkg_lower:
                # Check for exclusions
                if any(ex in pkg_lower for ex in EXCLUDED_KEYWORDS):
                    continue
                is_suspect = True
                break
        
        if is_suspect:
            heuristic_apps.append(pkg)
            
    output_data['summary']['heuristic_financial_apps'] = heuristic_apps
    if heuristic_apps:
        output_data['summary']['mule_risk_level'] = "HIGH" if len(heuristic_apps) > 3 or output_data['summary']['mule_risk_level'] == "HIGH" else "MEDIUM"
        print(f"   🔍 Heuristic Detection: Found {len(heuristic_apps)} suspected financial apps")
    
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output_data, f, indent=4)
    
    print(f"Analyzed {len(sessions)} app sessions across {len(app_stats)} apps.")
    print(f"Total screen time: {output_data['summary']['total_usage_time_human']}")
    print(f"\n🏦 TGCSB Mule Hunter:")
    print(f"   Banking Apps Installed: {len(all_banking_apps)}")
    print(f"   Mule Risk Level: {output_data['summary']['mule_risk_level']}")
    if mule_suspected:
        print(f"   ⚠️  ALERT: {output_data['summary']['mule_detection_reason']}")
        print(f"   Apps: {', '.join(all_banking_apps[:5])}{'...' if len(all_banking_apps) > 5 else ''}")

def get_installed_packages(logs_dir):
    """
    Parse full_package_dump.txt to get a complete list of installed packages.
    Returns a set of package names.
    """
    # Try both filenames to support different extraction scripts
    package_dump_path = os.path.join(logs_dir, "full_package_dump_utf8.txt")
    if not os.path.exists(package_dump_path):
        package_dump_path = os.path.join(logs_dir, "full_package_dump.txt")
        
    if not os.path.exists(package_dump_path):
        print(f"   ⚠️  Warning: Package dump file not found (checked utf8 and standard variants)")
        return []
        
    found_packages = set()
    try:
        print(f"   ℹ️  Reading package dump: {package_dump_path}")
        with open(package_dump_path, "r", encoding="utf-8", errors="replace") as f:
            content = f.read()
            
            # Robust Regex: Capture everything between '=' and ' uid:' or end of line/space
            # Format 1 (Standard): package:path/base.apk=com.package.name uid:10123
            # Format 2 (Fallback): package:com.package.name
            matches = re.findall(r'(?:=|package:)([a-zA-Z0-9_\.]+)', content)
            
            # Filter clean package names (must contain at least one dot)
            for pkg in matches:
                if '.' in pkg and len(pkg) > 5:
                    found_packages.add(pkg)
                    
        print(f"   ✅ Found {len(found_packages)} unique installed packages.")
        
    except Exception as e:
        print(f"   ⚠️ Error reading package dump: {e}")
        
    return list(found_packages)

def format_duration(seconds):
    """Convert seconds to human-readable format"""
    if seconds < 60:
        return f"{int(seconds)}s"
    elif seconds < 3600:
        minutes = int(seconds / 60)
        secs = int(seconds % 60)
        return f"{minutes}m {secs}s"
    else:
        hours = int(seconds / 3600)
        minutes = int((seconds % 3600) / 60)
        return f"{hours}h {minutes}m"

if __name__ == "__main__":
    analyze_app_sessions()
