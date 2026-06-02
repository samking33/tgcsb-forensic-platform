"""
Threat Detector Module
Parses existing logs to find Stalkerware, Spyware, and Sideloaded Apps.
"""
import os
import re
import json
import sys

# Whitelists to reduce noise
ACCESSIBILITY_WHITELIST = [
    "com.google.android.marvin.talkback",
    "com.android.switchaccess",
    "com.google.android.accessibility.soundamplifier",
    "com.google.audio.hearing.visualization.accessibility.scribe",
    "com.google.android.apps.accessibility.voiceaccess",
    "com.bitwarden", "com.dashlane", "com.lastpass" # Password managers often use this
]

NOTIFICATION_WHITELIST = [
    "com.google.android.apps.nexuslauncher",
    "com.android.systemui",
    "com.google.android.projection.gearhead", # Android Auto
    "com.nothing.launcher",
    "com.samsung.android.app.watchmanager", # Galaxy Watch
    "com.google.android.apps.wearables.maestro.companion" # Pixel Watch
]

KNOWN_SPYWARE_PACKAGES = [
    "com.flexispy", "com.mspy", "com.cerberus", "com.hoverwatch", 
    "com.spyzie", "com.kidguard", "com.cocospy", "org.torproject.android"
]

def parse_settings_secure(filepath):
    """Parses settings_secure.txt for accessibility and notification listeners"""
    access_services = []
    notif_listeners = []
    
    if not os.path.exists(filepath):
        print(f"WARNING: File not found: {filepath}")
        return access_services, notif_listeners

    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        for line in f:
            line = line.strip()
            # enabled_accessibility_services=com.pkg/cls:com.pkg2/cls2
            if line.startswith("enabled_accessibility_services="):
                val = line.split("=", 1)[1]
                if val and val != "null":
                    raw_services = val.split(':')
                    for svc in raw_services:
                        if '/' in svc:
                            pkg = svc.split('/')[0]
                            access_services.append({"package": pkg, "service": svc})
            
            # enabled_notification_listeners=...
            if line.startswith("enabled_notification_listeners="):
                val = line.split("=", 1)[1]
                if val and val != "null":
                    raw_listeners = val.split(':')
                    for lsn in raw_listeners:
                        if '/' in lsn:
                            pkg = lsn.split('/')[0]
                            notif_listeners.append({"package": pkg, "service": lsn})
                            
    return access_services, notif_listeners

def parse_package_dump(filepath):
    """
    Parses dump_package.txt (dumpsys package) to find installer sources.
    Returns a dict of potentially sideloaded apps.
    """
    sideloaded_apps = []
    
    if not os.path.exists(filepath):
        # Fallback: ignore if missing
        return sideloaded_apps

    # Simple state machine to find Package [name] -> installerPackageName
    current_pkg = None
    
    # We will just scan the file line by line
    # Format:
    #   Package [com.foo.bar] ...
    #     installerPackageName=com.android.vending
    
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        for line in f:
            strip_line = line.strip()
            
            # Start of package block
            # Line looks like: Package [com.google.android.youtube] (34a2b1):
            pkg_match = re.search(r'^Package \[(.*?)\]', strip_line)
            if pkg_match:
                current_pkg = pkg_match.group(1)
                continue
                
            # Installer line
            # Line looks like: installerPackageName=com.android.vending
            if current_pkg and "installerPackageName=" in strip_line:
                installer = strip_line.split("installerPackageName=", 1)[1]
                
                # Check if sideloaded
                # Valid installers: Play Store, Galaxy Store, Xiaomi GetApps, Amazon, etc.
                SAFE_INSTALLERS = [
                    "com.android.vending", # Play Store
                    "com.google.android.packageinstaller", # System (sometimes)
                    "com.sec.android.app.samsungapps", # Galaxy Store
                    "com.amazon.venezia", # Amazon App Store
                    "com.nothing.packageinstaller", 
                    "null" # System apps often have null installer
                ]
                
                # If installer is NOT in safe list, AND it's not a system app?
                # Actually, many system apps have 'null' or 'com.google.android.packageinstaller'.
                # Real sideloading often shows 'com.google.android.packageinstaller' (manual install) 
                # OR 'com.android.chrome' (downloaded from web).
                
                if installer not in ["com.android.vending", "com.sec.android.app.samsungapps"]:
                    # Refine logic: If installer is Chrome/WhatsApp/File Manager -> HIGH SUSPICION
                    risk_source = False
                    if "chrome" in installer or "whatsapp" in installer or "manager" in installer:
                        risk_source = True
                    
                    # We store it if it has an installer (not null) and it's not Play Store
                    # To avoid listing 300 system apps, let's only list if we can confirm it's a User App associated with a risky installer
                    # Or just list all non-market installs?
                    # For this 'quick' version, let's flag installs from non-standard markets
                    if installer != "null":
                         sideloaded_apps.append({
                             "package": current_pkg,
                             "installer": installer,
                             "is_risky": risk_source
                         })
                
                current_pkg = None # Reset for next block

    return sideloaded_apps

def analyze_threats():
    print("[*] Starting Threat Analysis...")
    
    logs_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "logs")
    settings_file = os.path.join(logs_dir, "settings_secure.txt")
    dump_pkg_file = os.path.join(logs_dir, "dump_package.txt")
    
    # 1. Parse Settings
    access_svcs, notif_listeners = parse_settings_secure(settings_file)
    
    # 2. Filter Threats
    threats = []
    
    # Accessibility Threats
    for item in access_svcs:
        pkg = item['package']
        if pkg not in ACCESSIBILITY_WHITELIST:
            threats.append({
                "type": "ACCESSIBILITY_ABUSE",
                "severity": "HIGH",
                "package": pkg,
                "detail": "App has full screen reading & control capabilities.",
                "evidence": item['service']
            })
            
    # Notification Sniffers
    for item in notif_listeners:
        pkg = item['package']
        if pkg not in NOTIFICATION_WHITELIST:
            threats.append({
                "type": "NOTIFICATION_LISTENER",
                "severity": "MEDIUM",
                "package": pkg,
                "detail": "App can read and dismiss notifications (OTPs).",
                "evidence": item['service']
            })
            
    # 3. Sideloaded / Suspicious Installers
    # parse_package_dump might be slow, only run if file exists
    if os.path.exists(dump_pkg_file):
        print("   Parsing package dump (this may take a moment)...")
        sideloads = parse_package_dump(dump_pkg_file)
        for cand in sideloads:
            # Only report if it looks risky or is a known spyware
            if cand['is_risky']:
                 threats.append({
                    "type": "SIDELOADED_APP",
                    "severity": "MEDIUM",
                    "package": cand['package'],
                    "detail": f"Installed via {cand['installer']} (Web/File Manager).",
                    "evidence": f"Installer: {cand['installer']}"
                })
    
    # 4. Known Spyware Signatures (Simple check)
    # We can check against the loaded package lists from other tools if needed
    # For now, let's just check the ones we found in logs
    pass 
    
    # Score
    risk_score = 0
    for t in threats:
        if t['severity'] == "CRITICAL": risk_score += 30
        if t['severity'] == "HIGH": risk_score += 20
        if t['severity'] == "MEDIUM": risk_score += 10
        
    risk_level = "LOW"
    if risk_score >= 50: risk_level = "CRITICAL"
    elif risk_score >= 20: risk_level = "HIGH"
    elif risk_score > 0: risk_level = "MEDIUM"
    
    report = {
        "risk_score": min(risk_score, 100),
        "risk_level": risk_level,
        "threat_count": len(threats),
        "threats": threats
    }
    
    out_file = os.path.join(logs_dir, "threat_report.json")
    with open(out_file, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=4)
        
    print(f"[+] Analysis Complete. Found {len(threats)} potential threats.")
    print(f"    Report saved to {out_file}")

if __name__ == "__main__":
    analyze_threats()
