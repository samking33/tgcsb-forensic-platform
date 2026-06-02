"""
Dual Space / Clone App Analyzer
Detects cloned banking apps used in mule operations
"""

import os
import sys
import re
import json

# Force UTF-8 encoding for stdout to prevent Windows cp1252 errors
if sys.platform == "win32" and hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

# Banking apps list (from app_sessionizer.py)
BANKING_APPS = [
    "com.phonepe.app", "net.one97.paytm", "com.google.android.apps.nbu.paisa.user",
    "in.org.npci.upiapp", "com.amazon.mShop.android.shopping", "com.mobikwik_new",
    "com.freecharge.android", "com.sbi.lotusintouch", "com.axis.mobile",
    "com.icicibank.mobile", "com.hdfc.mobile", "com.pnb.mobile",
    "com.bankofbaroda.mpassbook", "com.canara.canaramobile", "com.unionbank.ebanking",
    "com.idbi.mobile", "com.boi.mobile", "com.indusind.mobile", "com.yesbank.mobile",
    "com.kotak.mobile", "com.citi.citimobile", "com.sc.mobile", "com.hsbc.hsbcindia",
    "com.rbl.mobile", "com.fi.money", "com.jupiter.money", "com.slice.app",
    "com.dreamplug.androidapp", "com.paypal.android.p2pmobile", "com.bharatpe.app",
    "com.phonepe.app.business"
]

def parse_dual_space_apps(dual_space_file="logs/dual_space_apps.txt"):
    """
    Parse dual space detection output
    """
    if not os.path.exists(dual_space_file):
        print(f"⚠️ Dual space file not found: {dual_space_file}")
        return {
            "main_apps": [],
            "dual_apps_999": [],
            "dual_apps_10": [],
            "cloned_apps": [],
            "cloned_banking_apps": [],
            "clone_count": 0,
            "banking_clone_count": 0
        }
    
    with open(dual_space_file, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    
    # Extract apps from each section
    main_section = re.search(r'=== MAIN PROFILE.*?===\n(.*?)(?:\n\n===|$)', content, re.DOTALL)
    dual_999_section = re.search(r'=== DUAL SPACE PROFILE \(User 999\).*?===\n(.*?)(?:\n\n===|$)', content, re.DOTALL)
    dual_10_section = re.search(r'=== DUAL SPACE PROFILE \(User 10\).*?===\n(.*?)(?:\n\n===|$)', content, re.DOTALL)
    
    # Parse package names
    def extract_packages(section_text):
        if not section_text or "Not available" in section_text:
            return []
        packages = re.findall(r'package:([^\s]+)', section_text)
        return packages
    
    main_apps = extract_packages(main_section.group(1) if main_section else "")
    dual_apps_999 = extract_packages(dual_999_section.group(1) if dual_999_section else "")
    dual_apps_10 = extract_packages(dual_10_section.group(1) if dual_10_section else "")

    # 🆕 FALBACK: Parse full package dump for combined UIDs (e.g., uid:10xxx,999xxx)
    full_dump_file = "logs/full_package_dump.txt"
    if os.path.exists(full_dump_file):
        print(f"   ℹ️ Parsing full package dump for hidden clones...")
        with open(full_dump_file, "r", encoding="utf-8", errors="replace") as f:
            full_dump_content = f.read()
            
        # Regex to find lines with package name and UIDs
        # Format: package:path/base.apk=com.package.name uid:10123,99910123
        matches = re.findall(r'package:[^=]+=([^\s]+)\s+uid:([0-9,]+)', full_dump_content)
        
        for pkg, uids in matches:
            if "999" in uids: # Check specifically for the '999' prefix in any of the UIDs
                 # The regex might capture 'uid:10123,99910123'. 
                 # We split by comma and check if any individual UID > 900000 or indicates dual profile
                 uid_list = uids.split(',')
                 is_dual = False
                 for u in uid_list:
                     if int(u) > 900000: # Standard parallel space UID offset
                         is_dual = True
                         break
                 
                 if is_dual:
                     if pkg not in dual_apps_999: # Avoid duplicates
                        dual_apps_999.append(pkg)

    # Find cloned apps (exist in both main and dual profiles)
    # Note: If it's in dual_apps_999, it IS a cloned app effectively, even if not in main (but usually it is)
    # Ideally, we verify it exists in main too, but for Mule Detection, ANY presence in Dual Space is the risk.
    all_dual_apps = set(dual_apps_999 + dual_apps_10)
    
    # Updated Logic: If it's in Dual Space, count it. 
    # Previous logic required it to be in Main AND Dual. 
    # But if hidden in main (unlikely) or just detected in Dual, it's still a Clone instance.
    cloned_apps = list(all_dual_apps)
    
    # Filter for banking apps
    cloned_banking_apps = [pkg for pkg in cloned_apps if pkg in BANKING_APPS]
    
    return {
        "main_apps": main_apps,
        "dual_apps_999": dual_apps_999,
        "dual_apps_10": dual_apps_10,
        "cloned_apps": cloned_apps,
        "cloned_banking_apps": cloned_banking_apps,
        "clone_count": len(cloned_apps),
        "banking_clone_count": len(cloned_banking_apps)
    }

def analyze_dual_space(output_file="logs/dual_space_analysis.json"):
    """
    Analyze dual space apps and generate mule risk assessment
    """
    result = parse_dual_space_apps()
    
    # Mule risk assessment
    mule_indicators = []
    mule_score = 0
    
    if result["banking_clone_count"] > 0:
        mule_score += 10  # Cloned banking apps are HIGHLY suspicious
        mule_indicators.append(f"{result['banking_clone_count']} cloned banking apps detected")
    
    if result["clone_count"] > 5:
        mule_score += 3
        mule_indicators.append(f"{result['clone_count']} total cloned apps")
    
    # Risk level
    if mule_score >= 10:
        risk_level = "CRITICAL"
    elif mule_score >= 5:
        risk_level = "HIGH"
    elif mule_score >= 2:
        risk_level = "MEDIUM"
    else:
        risk_level = "LOW"
    
    result["mule_assessment"] = {
        "risk_level": risk_level,
        "mule_score": mule_score,
        "indicators": mule_indicators,
        "is_dual_space_enabled": len(result["dual_apps_999"]) > 0 or len(result["dual_apps_10"]) > 0
    }
    
    # Save to JSON
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=4)
    
    print(f"👥 Dual Space Analysis:")
    print(f"   Main profile apps: {len(result['main_apps'])}")
    print(f"   Dual space apps: {len(result['dual_apps_999']) + len(result['dual_apps_10'])}")
    print(f"   Cloned apps: {result['clone_count']}")
    print(f"   Cloned banking apps: {result['banking_clone_count']}")
    print(f"   Mule Risk Level: {risk_level}")
    
    if result["cloned_banking_apps"]:
        print(f"   ⚠️  ALERT: Cloned banking apps detected!")
        print(f"   Apps: {', '.join(result['cloned_banking_apps'][:5])}")
    
    return result

if __name__ == "__main__":
    analyze_dual_space()
