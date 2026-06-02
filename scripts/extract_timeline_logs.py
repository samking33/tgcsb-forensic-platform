#!/usr/bin/env python3
"""
Forensic Timeline Log Extraction
Extracts device behavior logs via ADB with timestamp and timezone capture
"""

import subprocess
import os
import sys
from datetime import datetime
from pathlib import Path

class TimelineLogExtractor:
    def __init__(self, output_dir="logs/timeline"):
        self.output_dir = output_dir
        self.timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        self.session_dir = None
        
    def check_adb_connection(self):
        """Verify ADB device is connected"""
        try:
            result = subprocess.run(
                ["adb", "devices"],
                capture_output=True,
                text=True,
                check=True
            )
            
            lines = result.stdout.strip().split('\n')
            devices = [line for line in lines if '\tdevice' in line]
            
            if not devices:
                print("❌ No ADB device connected")
                return False
                
            print(f"✓ ADB device connected: {devices[0].split()[0]}")
            return True
            
        except Exception as e:
            print(f"❌ ADB error: {e}")
            return False
    
    def create_session_directory(self):
        """Create timestamped output directory"""
        self.session_dir = Path(self.output_dir) / self.timestamp
        self.session_dir.mkdir(parents=True, exist_ok=True)
        print(f"✓ Created session directory: {self.session_dir}")
        
    def capture_device_timezone(self):
        """MANDATORY: Capture device timezone for UTC normalization"""
        metadata = {}
        
        try:
            # Get timezone
            result = subprocess.run(
                ["adb", "shell", "getprop", "persist.sys.timezone"],
                capture_output=True,
                text=True,
                check=True,
                timeout=5
            )
            metadata['device_timezone'] = result.stdout.strip()
            
            # Get device time
            result = subprocess.run(
                ["adb", "shell", "date"],
                capture_output=True,
                text=True,
                check=True,
                timeout=5
            )
            metadata['device_time'] = result.stdout.strip()
            
            # Get acquisition time (host)
            metadata['acquisition_time_utc'] = datetime.utcnow().isoformat() + 'Z'
            metadata['acquisition_time_local'] = datetime.now().isoformat()
            
            # Save metadata
            metadata_file = self.session_dir / "acquisition_metadata.txt"
            with open(metadata_file, 'w', encoding='utf-8') as f:
                f.write(f"Device Timezone: {metadata['device_timezone']}\n")
                f.write(f"Device Time: {metadata['device_time']}\n")
                f.write(f"Acquisition Time (UTC): {metadata['acquisition_time_utc']}\n")
                f.write(f"Acquisition Time (Local): {metadata['acquisition_time_local']}\n")
            
            print(f"✓ Captured timezone: {metadata['device_timezone']}")
            return metadata
            
        except Exception as e:
            print(f"⚠ Warning: Could not capture timezone - {e}")
            return None
    
    def extract_logcat(self):
        """Extract logcat with threadtime format from all buffers"""
        print("\n→ Extracting logcat (all buffers, threadtime)...")
        
        output_file = self.session_dir / "logcat_threadtime.txt"
        
        try:
            # -b main,system,events,radio: all relevant buffers
            # -d: dump and exit
            # -v threadtime: timestamp format for parsing
            result = subprocess.run(
                ["adb", "logcat", "-b", "main", "-b", "system", "-b", "events", "-b", "radio", "-d", "-v", "threadtime"],
                capture_output=True,
                text=True,
                check=True,
                timeout=30
            )
            
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(result.stdout)
            
            line_count = len(result.stdout.split('\n'))
            print(f"  ✓ Saved logcat: {line_count} lines")
            return True
            
        except subprocess.TimeoutExpired:
            print("  ❌ Timeout extracting logcat")
            return False
        except Exception as e:
            print(f"  ❌ Error extracting logcat: {e}")
            return False
    
    def extract_dumpsys(self, service, filename):
        """Extract dumpsys for specific service"""
        output_file = self.session_dir / filename
        
        try:
            result = subprocess.run(
                ["adb", "shell", "dumpsys", service],
                capture_output=True,
                text=True,
                check=True,
                timeout=15
            )
            
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(result.stdout)
            
            size_kb = len(result.stdout) / 1024
            print(f"  ✓ {service}: {size_kb:.1f} KB")
            return True
            
        except Exception as e:
            print(f"  ❌ {service}: {e}")
            return False
    
    def extract_all_dumpsys(self):
        """Extract all required dumpsys services"""
        print("\n→ Extracting dumpsys services...")
        
        services = [
            ("activity", "dumpsys_activity.txt"),
            ("power", "dumpsys_power.txt"),
            ("batterystats", "dumpsys_batterystats.txt"),
            ("connectivity", "dumpsys_connectivity.txt"),
            ("wifi", "dumpsys_wifi.txt"),
            ("telephony.registry", "dumpsys_telephony.txt")
        ]
        
        success_count = 0
        for service, filename in services:
            if self.extract_dumpsys(service, filename):
                success_count += 1
        
        print(f"  Summary: {success_count}/{len(services)} services extracted")
        return success_count > 0
    
    def run_extraction(self):
        """Execute full extraction sequence"""
        print("=" * 60)
        print("   FORENSIC TIMELINE LOG EXTRACTION")
        print("=" * 60)
        
        # Step 1: Check ADB
        if not self.check_adb_connection():
            return False
        
        # Step 2: Create output directory
        self.create_session_directory()
        
        # Step 3: MANDATORY - Capture timezone
        timezone_metadata = self.capture_device_timezone()
        if not timezone_metadata:
            print("\n⚠ WARNING: Timezone capture failed - timestamps may be ambiguous")
        
        # Step 4: Extract logcat
        logcat_success = self.extract_logcat()
        
        # Step 5: Extract dumpsys
        dumpsys_success = self.extract_all_dumpsys()
        
        # Summary
        print("\n" + "=" * 60)
        if logcat_success and dumpsys_success:
            print("✓ EXTRACTION COMPLETE")
            print(f"  Output: {self.session_dir}")
            print("=" * 60)
            return True
        else:
            print("⚠ EXTRACTION INCOMPLETE")
            print(f"  Partial data saved to: {self.session_dir}")
            print("=" * 60)
            return False

def main():
    extractor = TimelineLogExtractor()
    success = extractor.run_extraction()
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()
