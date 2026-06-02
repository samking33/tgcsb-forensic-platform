import os
import sys

# Setup paths
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(parent_dir)

# Import Extraction Modules
from scripts.enhanced_extraction import get_dual_space_apps, get_all_packages_with_uids, get_usage_stats

# Import Analysis Modules
# We need to ensure analysis folder is importable
# It is in parent_dir/analysis
from analysis.dual_space_analyzer import analyze_dual_space
from analysis.app_sessionizer import analyze_app_sessions

def main():
    print("🕵️‍♂️ Starting Targeted Mule Hunter Scan...")
    
    # 1. Extraction (Fresh Data)
    print("\n--- PHASE 1: EXTRACTION ---")
    get_dual_space_apps()
    get_all_packages_with_uids()
    get_usage_stats()
    
    # 2. Analysis
    print("\n--- PHASE 2: ANALYSIS ---")
    analyze_app_sessions() # Updates app_sessions.json (used by Mule Hunter UI)
    analyze_dual_space() # Updates dual_space_analysis.json
    
    print("\n✅ Mule Scan Complete")

if __name__ == "__main__":
    main()
