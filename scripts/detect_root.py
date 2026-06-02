#!/usr/bin/env python3
"""
Root Detection Module - Android Forensic Tool
Detects if the connected Android device is rooted using multiple detection methods
"""

import subprocess
import json
import os
from datetime import datetime

def run_adb_command(command):
    """Execute ADB command and return output"""
    try:
        result = subprocess.run(
            f"adb shell {command}",
            shell=True,
            capture_output=True,
            text=True,
            timeout=10
        )
        return {
            "success": True,
            "output": result.stdout.strip(),
            "error": result.stderr.strip(),
            "returncode": result.returncode
        }
    except subprocess.TimeoutExpired:
        return {"success": False, "output": "", "error": "Command timeout", "returncode": -1}
    except Exception as e:
        return {"success": False, "output": "", "error": str(e), "returncode": -1}

def check_su_binary():
    """Check for su binary in common locations"""
    locations = [
        "/system/bin/su",
        "/system/xbin/su",
        "/sbin/su",
        "/system/su",
        "/system/bin/.ext/.su",
        "/system/usr/we-need-root/su-backup",
        "/system/xbin/mu"
    ]
    
    found_locations = []
    for location in locations:
        result = run_adb_command(f"ls {location}")
        if result["success"] and "No such file" not in result["output"] and result["returncode"] == 0:
            found_locations.append(location)
    
    return {
        "detected": len(found_locations) > 0,
        "locations": found_locations,
        "method": "SU Binary Check"
    }

def check_root_management_apps():
    """Check for root management apps like Magisk, SuperSU, KingRoot"""
    root_apps = {
        "Magisk": "com.topjohnwu.magisk",
        "SuperSU": "eu.chainfire.supersu",
        "KingRoot": "com.kingroot.kinguser",
        "KingoRoot": "com.kingoapp.root"
    }
    
    detected_apps = []
    result = run_adb_command("pm list packages")
    
    if result["success"]:
        packages = result["output"]
        for app_name, package_name in root_apps.items():
            if package_name in packages:
                detected_apps.append(app_name)
    
    return {
        "detected": len(detected_apps) > 0,
        "apps": detected_apps,
        "method": "Root Management Apps"
    }

def check_dangerous_properties():
    """Check for dangerous build properties indicating root"""
    dangerous_props = [
        "ro.debuggable",
        "ro.secure",
        "ro.build.tags"
    ]
    
    suspicious_values = []
    
    for prop in dangerous_props:
        result = run_adb_command(f"getprop {prop}")
        if result["success"]:
            value = result["output"]
            
            # ro.debuggable should be 0, ro.secure should be 1, ro.build.tags should be release-keys
            if prop == "ro.debuggable" and value == "1":
                suspicious_values.append(f"{prop}=1 (should be 0)")
            elif prop == "ro.secure" and value == "0":
                suspicious_values.append(f"{prop}=0 (should be 1)")
            elif prop == "ro.build.tags" and value != "release-keys":
                suspicious_values.append(f"{prop}={value} (should be release-keys)")
    
    return {
        "detected": len(suspicious_values) > 0,
        "findings": suspicious_values,
        "method": "Build Properties"
    }

def check_selinux_status():
    """Check SELinux enforcement status"""
    result = run_adb_command("getenforce")
    
    if result["success"]:
        status = result["output"]
        # Rooted devices often have SELinux set to Permissive
        is_permissive = status.lower() == "permissive"
        
        return {
            "detected": is_permissive,
            "status": status,
            "method": "SELinux Status"
        }
    
    return {
        "detected": False,
        "status": "Unknown",
        "method": "SELinux Status"
    }

def check_busybox():
    """Check for BusyBox installation (common on rooted devices)"""
    result = run_adb_command("which busybox")
    
    if result["success"] and result["output"] and "not found" not in result["output"]:
        return {
            "detected": True,
            "path": result["output"],
            "method": "BusyBox Detection"
        }
    
    return {
        "detected": False,
        "path": None,
        "method": "BusyBox Detection"
    }

def check_dangerous_directories():
    """Check for common root-related directories"""
    dangerous_dirs = [
        "/data/local/tmp",
        "/data/local/su",
        "/sbin"
    ]
    
    writable_dirs = []
    
    for dir_path in dangerous_dirs:
        # Try to create a test file
        result = run_adb_command(f"touch {dir_path}/.root_test 2>&1")
        if result["success"] and "Permission denied" not in result["output"]:
            writable_dirs.append(dir_path)
            # Clean up
            run_adb_command(f"rm {dir_path}/.root_test")
    
    return {
        "detected": len(writable_dirs) > 0,
        "directories": writable_dirs,
        "method": "Writable System Directories"
    }

def check_test_keys():
    """Check if device is signed with test-keys"""
    result = run_adb_command("getprop ro.build.tags")
    
    if result["success"]:
        tags = result["output"]
        is_test_keys = "test-keys" in tags
        
        return {
            "detected": is_test_keys,
            "tags": tags,
            "method": "Build Tags"
        }
    
    return {
        "detected": False,
        "tags": "Unknown",
        "method": "Build Tags"
    }

def detect_root_status(output_file="logs/root_status.json"):
    """
    Comprehensive root detection using multiple methods
    Returns detailed root status information
    """
    print("\n" + "="*60)
    print("  🔍 ROOT DETECTION ANALYSIS")
    print("="*60 + "\n")
    
    # Create logs directory if it doesn't exist
    os.makedirs("logs", exist_ok=True)
    
    # Check if device is connected
    device_check = run_adb_command("getprop ro.build.version.release")
    if not device_check["success"]:
        print("❌ No device connected via ADB")
        return None
    
    android_version = device_check["output"]
    print(f"📱 Android Version: {android_version}\n")
    
    # Run all detection methods
    checks = {
        "su_binary": check_su_binary(),
        "root_apps": check_root_management_apps(),
        "build_properties": check_dangerous_properties(),
        "selinux": check_selinux_status(),
        "busybox": check_busybox(),
        "dangerous_dirs": check_dangerous_directories(),
        "test_keys": check_test_keys()
    }
    
    # Calculate overall root status
    detection_count = sum(1 for check in checks.values() if check["detected"])
    is_rooted = detection_count > 0
    
    # Create comprehensive report
    root_status = {
        "timestamp": datetime.now().isoformat(),
        "android_version": android_version,
        "is_rooted": is_rooted,
        "confidence": "High" if detection_count >= 3 else "Medium" if detection_count >= 2 else "Low" if detection_count >= 1 else "Not Rooted",
        "detection_count": detection_count,
        "total_checks": len(checks),
        "checks": checks,
        "summary": []
    }
    
    # Display results
    print("🔍 Detection Results:")
    print("-" * 60)
    
    for check_name, check_data in checks.items():
        status = "✅ DETECTED" if check_data["detected"] else "⚪ Not Found"
        method = check_data["method"]
        print(f"  {status:20} | {method}")
        
        if check_data["detected"]:
            # Add details to summary
            if check_name == "su_binary" and check_data.get("locations"):
                root_status["summary"].append(f"SU binary found: {', '.join(check_data['locations'])}")
            elif check_name == "root_apps" and check_data.get("apps"):
                root_status["summary"].append(f"Root apps: {', '.join(check_data['apps'])}")
            elif check_name == "build_properties" and check_data.get("findings"):
                root_status["summary"].append(f"Suspicious properties: {', '.join(check_data['findings'])}")
            elif check_name == "selinux":
                root_status["summary"].append(f"SELinux: {check_data.get('status')}")
            elif check_name == "busybox":
                root_status["summary"].append(f"BusyBox found: {check_data.get('path')}")
    
    print("-" * 60)
    print(f"\n📊 Overall Assessment:")
    print(f"   Root Status: {'🔴 ROOTED' if is_rooted else '🟢 NOT ROOTED'}")
    print(f"   Confidence: {root_status['confidence']}")
    print(f"   Detections: {detection_count}/{len(checks)}")
    
    if root_status["summary"]:
        print(f"\n⚠️  Root Indicators:")
        for indicator in root_status["summary"]:
            print(f"   • {indicator}")
    
    print("\n" + "="*60 + "\n")
    
    # Save to JSON
    with open(output_file, "w") as f:
        json.dump(root_status, f, indent=4)
    
    print(f"💾 Root status saved to: {output_file}\n")
    
    return root_status

if __name__ == "__main__":
    detect_root_status()
