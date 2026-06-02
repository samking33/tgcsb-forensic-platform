
import os
import sys
import subprocess
import time

def run_script(script_name, cwd=None):
    """Run a python script and wait for it to complete."""
    print(f"\n[+] Running {script_name}...")
    try:
        if cwd:
            cmd = ["python", script_name]
            result = subprocess.run(cmd, cwd=cwd, check=False)
        else:
            cmd = ["python", script_name]
            result = subprocess.run(cmd, check=False)
        
        if result.returncode == 0:
            print(f"✅ {script_name} completed successfully.")
        else:
            print(f"⚠️ {script_name} exited with code {result.returncode}.")
    except Exception as e:
        print(f"❌ Error running {script_name}: {e}")

def main():
    print("="*50)
    print("   ANDROID FORENSICS - MASTER PROCESSING PIPELINE")
    print("="*50)
    print("This script will run all available extraction and analysis modules.")
    print("Ensure your device is connected via ADB if running extraction.")
    print("-" * 50)

    # Base directory is one level up from 'scripts' if this is in 'scripts/'
    # But usually we run from root. Let's assume we run from root.
    base_dir = os.getcwd()
    
    # 1. Extraction (Optional - requires device)
    # We'll try to run them, if they fail (no device), we continue to analysis of existing logs
    print("\n>>> PHASE 1: EXTRACTION (Requires Device)")
    run_script("scripts/enhanced_extraction.py")
    run_script("scripts/android_logs.py") # Extract Calls, SMS, Contacts
    
    # Run other specific extractors if they are standalone (usually called by main.py or enhanced_extraction)
    # But let's assume enhanced_extraction does the heavy lifting for dumpsys.
    # main.py calls get_logcat, get_sms_logs etc. 
    # If we want "pull everything", we should simulate main.py's extraction calls here if possible,
    # or just assume logs are present/extracted via main UI. 
    # The user said "pull everything", so let's try to run the basic extractors too if they exist as scripts.
    
    # 2. Parsing & Analysis
    print("\n>>> PHASE 2: PARSING & ANALYSIS")
    
    # Notifications
    run_script("analysis/notification_parser.py")
    
    # Dual Space / Mule Detection
    run_script("analysis/dual_space_analyzer.py")
    
    # Privacy Profiler
    run_script("analysis/privacy_analyzer.py") # Make sure this exists
    
    # Device Identifiers / 65B
    run_script("analysis/device_identifiers.py")
    
    # Social Graph
    run_script("analysis/social_graph.py")
    
    # Fake Log Detector (Ghost Tags) 🆕
    run_script("analysis/fake_log_detector.py")
    
    # 3. Timeline Generation (Aggregator)
    print("\n>>> PHASE 3: TIMELINE GENERATION")
    run_script("analysis/unified_timeline.py")
    
    print("\n" + "="*50)
    print("✅ PROCESSING COMPLETE")
    print("="*50)
    print("You can now view the results in the Web Interface:")
    print(" - Timeline: http://localhost:8000/pages/timeline.php")
    print(" - Social Graph: http://localhost:8000/pages/social-graph.php")
    print(" - Reports: http://localhost:8000/pages/extract-logs.php")

if __name__ == "__main__":
    main()
