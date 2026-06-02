"""
Evidence Hash Verification System
Generates and verifies SHA-256 hashes for all extracted log files
"""

import os
import json
import hashlib
from datetime import datetime

class EvidenceHasher:
    def __init__(self, logs_dir="logs"):
        self.logs_dir = logs_dir
        self.metadata_file = os.path.join(logs_dir, "evidence_metadata.json")
        self.metadata = self.load_metadata()
    
    def load_metadata(self):
        """Load existing metadata or create new"""
        if os.path.exists(self.metadata_file):
            with open(self.metadata_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        return {"files": {}, "created": datetime.now().isoformat()}
    
    def save_metadata(self):
        """Save metadata to file"""
        self.metadata["last_updated"] = datetime.now().isoformat()
        with open(self.metadata_file, 'w', encoding='utf-8') as f:
            json.dump(self.metadata, f, indent=4)
    
    def hash_file(self, filepath):
        """Generate SHA-256 hash for a file"""
        sha256 = hashlib.sha256()
        
        try:
            with open(filepath, 'rb') as f:
                # Read in chunks to handle large files
                for chunk in iter(lambda: f.read(8192), b''):
                    sha256.update(chunk)
            return sha256.hexdigest()
        except Exception as e:
            print(f"Error hashing {filepath}: {e}")
            return None
    
    def register_file(self, filepath, description=""):
        """Register a file and generate its hash"""
        if not os.path.exists(filepath):
            print(f"File not found: {filepath}")
            return False
        
        file_hash = self.hash_file(filepath)
        if not file_hash:
            return False
        
        file_size = os.path.getsize(filepath)
        file_mtime = datetime.fromtimestamp(os.path.getmtime(filepath)).isoformat()
        
        filename = os.path.basename(filepath)
        
        self.metadata["files"][filename] = {
            "hash": file_hash,
            "size_bytes": file_size,
            "created": datetime.now().isoformat(),
            "modified": file_mtime,
            "description": description,
            "verified": True,
            "last_verification": datetime.now().isoformat()
        }
        
        self.save_metadata()
        print(f"Registered: {filename} (SHA-256: {file_hash[:16]}...)")
        return True
    
    def verify_file(self, filepath):
        """Verify a file's integrity against stored hash"""
        filename = os.path.basename(filepath)
        
        if filename not in self.metadata["files"]:
            print(f"No metadata found for {filename}")
            return False
        
        stored_hash = self.metadata["files"][filename]["hash"]
        current_hash = self.hash_file(filepath)
        
        if not current_hash:
            return False
        
        if stored_hash == current_hash:
            # Update verification timestamp
            self.metadata["files"][filename]["verified"] = True
            self.metadata["files"][filename]["last_verification"] = datetime.now().isoformat()
            self.save_metadata()
            print(f"✓ VERIFIED: {filename}")
            return True
        else:
            # Mark as tampered
            self.metadata["files"][filename]["verified"] = False
            self.metadata["files"][filename]["tampered_detected"] = datetime.now().isoformat()
            self.save_metadata()
            print(f"✗ TAMPERED: {filename}")
            print(f"  Expected: {stored_hash}")
            print(f"  Got:      {current_hash}")
            return False
    
    def hash_all_logs(self):
        """Hash all files in the logs directory"""
        if not os.path.exists(self.logs_dir):
            print(f"Logs directory not found: {self.logs_dir}")
            return
        
        file_count = 0
        for filename in os.listdir(self.logs_dir):
            filepath = os.path.join(self.logs_dir, filename)
            
            # Skip directories and metadata file itself
            if os.path.isdir(filepath) or filename == "evidence_metadata.json":
                continue
            
            # Determine description based on filename
            description = self.get_file_description(filename)
            
            if self.register_file(filepath, description):
                file_count += 1
        
        print(f"\nTotal files hashed: {file_count}")
        return file_count
    
    def verify_all_logs(self):
        """Verify all registered files"""
        results = {"verified": [], "tampered": [], "missing": []}
        
        for filename in self.metadata["files"]:
            filepath = os.path.join(self.logs_dir, filename)
            
            if not os.path.exists(filepath):
                results["missing"].append(filename)
                print(f"⚠ MISSING: {filename}")
                continue
            
            if self.verify_file(filepath):
                results["verified"].append(filename)
            else:
                results["tampered"].append(filename)
        
        # Print summary
        print("\n=== VERIFICATION SUMMARY ===")
        print(f"✓ Verified:  {len(results['verified'])}")
        print(f"✗ Tampered:  {len(results['tampered'])}")
        print(f"⚠ Missing:   {len(results['missing'])}")
        
        return results
    
    def get_file_description(self, filename):
        """Get description based on filename"""
        descriptions = {
            "android_logcat.txt": "Android system logcat",
            "sms_logs.txt": "SMS messages",
            "call_logs.txt": "Call history",
            "location_data.txt": "GPS location data",
            "unified_timeline.json": "Unified forensic timeline",
            "privacy_profile.json": "Privacy profiler data",
            "pii_leaks.json": "PII leak detection results",
            "network_activity.json": "Network intelligence data",
            "social_graph.json": "Social link graph data",
            "power_forensics.json": "Power usage forensics",
            "intent_hunter.json": "Intent and URL recovery",
            "beacon_map.json": "WiFi/Bluetooth beacons",
            "clipboard_forensics.json": "Clipboard recovery data",
            "app_sessions.json": "App usage sessions"
        }
        return descriptions.get(filename, "Forensic evidence file")
    
    def generate_integrity_report(self):
        """Generate a comprehensive integrity report"""
        report = {
            "report_generated": datetime.now().isoformat(),
            "total_files": len(self.metadata["files"]),
            "files": []
        }
        
        for filename, data in self.metadata["files"].items():
            filepath = os.path.join(self.logs_dir, filename)
            exists = os.path.exists(filepath)
            
            file_info = {
                "filename": filename,
                "description": data.get("description", ""),
                "hash": data["hash"],
                "size_bytes": data["size_bytes"],
                "created": data["created"],
                "exists": exists,
                "verified": data.get("verified", False),
                "last_verification": data.get("last_verification", "Never")
            }
            
            if not data.get("verified", True):
                file_info["tampered_detected"] = data.get("tampered_detected", "Unknown")
            
            report["files"].append(file_info)
        
        return report

def main():
    """Main CLI interface"""
    import sys
    
    hasher = EvidenceHasher()
    
    if len(sys.argv) < 2:
        print("Usage:")
        print("  python evidence_hasher.py hash       - Hash all log files")
        print("  python evidence_hasher.py verify     - Verify all log files")
        print("  python evidence_hasher.py report     - Generate integrity report")
        return
    
    command = sys.argv[1].lower()
    
    if command == "hash":
        hasher.hash_all_logs()
    elif command == "verify":
        hasher.verify_all_logs()
    elif command == "report":
        report = hasher.generate_integrity_report()
        print(json.dumps(report, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == "__main__":
    main()
