
import subprocess
import os
import sys
from datetime import datetime

def run_all_analysis():
    print("=" * 60)
    print("  ANDROID FORENSIC TOOL - Analysis Orchestrator")
    print("  Starting comprehensive forensic analysis...")
    print("=" * 60)
    print(f"\nStarted at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
    
    scripts = [
        {"path": "analysis/unified_timeline.py", "name": "Unified Timeline Generator"},
        {"path": "analysis/privacy_analyzer.py", "name": "Privacy Profiler"},
        {"path": "analysis/pii_detector.py", "name": "PII Leak Detector"},
        {"path": "analysis/network_analyzer.py", "name": "Network Analyzer"},
        {"path": "analysis/social_graph.py", "name": "Social Link Graph"},
        {"path": "analysis/power_forensics.py", "name": "Power Forensics"},
        {"path": "analysis/intent_hunter.py", "name": "Intent & URL Hunter"},
        {"path": "analysis/beacon_map.py", "name": "WiFi & Bluetooth Beacon Map"},
        {"path": "analysis/clipboard_forensics.py", "name": "Clipboard Reconstruction"},
        {"path": "analysis/app_sessionizer.py", "name": "App Usage Sessionizer"}
    ]
    
    results = {"success": [], "failed": [], "total": len(scripts)}
    
    for idx, script in enumerate(scripts, 1):
        print(f"\n[{idx}/{len(scripts)}] Running: {script['name']}")
        print("-" * 60)
        
        try:
            # Get absolute path
            abs_path = os.path.abspath(script['path'])
            
            if not os.path.exists(abs_path):
                print(f"  ⚠️  WARNING: Script not found: {abs_path}")
                results["failed"].append({"name": script['name'], "error": "Script not found"})
                continue
            
            # Run script and capture output
            result = subprocess.run(
                [sys.executable, abs_path],
                capture_output=True,
                text=True,
                timeout=60  # 60 second timeout per script
            )
            
            # Print output
            if result.stdout:
                for line in result.stdout.strip().split('\n'):
                    print(f"  {line}")
            
            # Check for errors
            if result.returncode != 0:
                print(f"  ❌ FAILED with exit code {result.returncode}")
                if result.stderr:
                    print(f"  Error details: {result.stderr[:200]}")
                results["failed"].append({"name": script['name'], "error": f"Exit code {result.returncode}"})
            else:
                print(f"  ✅ SUCCESS")
                results["success"].append(script['name'])
                
        except subprocess.TimeoutExpired:
            print(f"  ⏱️  TIMEOUT: Script exceeded 60 second limit")
            results["failed"].append({"name": script['name'], "error": "Timeout"})
        except Exception as e:
            print(f"  ❌ ERROR: {str(e)}")
            results["failed"].append({"name": script['name'], "error": str(e)})
    
    # Print summary
    print("\n" + "=" * 60)
    print("  ANALYSIS SUMMARY")
    print("=" * 60)
    print(f"\n  Total Scripts:  {results['total']}")
    print(f"  ✅ Successful:  {len(results['success'])}")
    print(f"  ❌ Failed:      {len(results['failed'])}")
    
    if results['failed']:
        print(f"\n  Failed modules:")
        for failure in results['failed']:
            print(f"    - {failure['name']}: {failure['error']}")
    
    print(f"\n  Completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60)
    
    # Generate hash verification (if evidence_hasher exists)
    if os.path.exists("analysis/evidence_hasher.py"):
        print("\n[Post-Analysis] Generating evidence hashes...")
        try:
            subprocess.run([sys.executable, "analysis/evidence_hasher.py", "hash"], timeout=30)
            print("  ✅ Evidence hashes generated")
        except Exception as e:
            print(f"  ⚠️  Hash generation failed: {e}")
    
    return len(results['failed']) == 0

if __name__ == "__main__":
    success = run_all_analysis()
    sys.exit(0 if success else 1)
