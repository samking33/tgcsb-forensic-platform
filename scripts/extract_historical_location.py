"""
Enhanced Location Extraction - Gets historical data even when location is OFF
Extracts from: logcat history, cell towers, WiFi networks, radio buffer
"""
import subprocess
import os
import sys

# Fix Windows encoding issues with emoji characters
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except AttributeError:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

def extract_historical_location():
    """
    Extract location data from multiple sources even when location services are disabled.
    """
    print("🔍 Extracting historical location data from all sources...")
    print("   (This works even if location services are currently OFF)")
    print("")
    
    os.makedirs("logs", exist_ok=True)
    
    all_location_data = []
    
    # Source 1: dumpsys location (last known locations)
    print("📍 Source 1: dumpsys location...")
    try:
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "location"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            with open("logs/location_logs.txt", "w", encoding="utf-8") as f:
                f.write(result.stdout)
            all_location_data.append(("dumpsys location", result.stdout))
            print("   ✅ Extracted")
        else:
            print(f"   ⚠️ Failed: {result.stderr}")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    # Source 2: logcat for historical GPS fixes
    print("📍 Source 2: logcat history (GPS fixes)...")
    try:
        result = subprocess.run(
            ["adb", "logcat", "-d", "-v", "time"],
            capture_output=True,
            text=True,
            timeout=15
        )
        if result.returncode == 0 and result.stdout:
            # Filter for location-related entries
            location_lines = [line for line in result.stdout.split('\n') 
                            if any(keyword in line.lower() for keyword in 
                                  ['location[', 'latitude', 'longitude', 'gpslocationprovider', 'networklocationprovider'])]
            
            with open("logs/logcat_location_history.txt", "w", encoding="utf-8") as f:
                f.write('\n'.join(location_lines))
            all_location_data.append(("logcat location", '\n'.join(location_lines)))
            print(f"   ✅ Found {len(location_lines)} location entries")
        else:
            print(f"   ⚠️ Failed")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    # Source 3: Cell tower data (works even without GPS)
    print("📡 Source 3: Cell tower information...")
    try:
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "telephony.registry"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            with open("logs/cell_tower_data.txt", "w", encoding="utf-8") as f:
                f.write(result.stdout)
            all_location_data.append(("cell towers", result.stdout))
            print("   ✅ Extracted")
        else:
            print(f"   ⚠️ Failed")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    # Source 4: WiFi networks (can be used for location)
    print("📶 Source 4: WiFi network data...")
    try:
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "wifi"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0 and result.stdout:
            with open("logs/wifi_networks.txt", "w", encoding="utf-8") as f:
                f.write(result.stdout)
            all_location_data.append(("wifi", result.stdout))
            print("   ✅ Extracted")
        else:
            print(f"   ⚠️ Failed")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    # Source 5: Radio buffer (cell tower history)
    print("📻 Source 5: Radio buffer (cell history)...")
    try:
        result = subprocess.run(
            ["adb", "logcat", "-b", "radio", "-d", "-v", "time"],
            capture_output=True,
            text=True,
            timeout=15
        )
        if result.returncode == 0:
            with open("logs/radio_buffer.txt", "w", encoding="utf-8") as f:
                f.write(result.stdout)
            all_location_data.append(("radio buffer", result.stdout))
            print("   ✅ Extracted")
        else:
            print(f"   ⚠️ Failed")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    # Source 6: Network location provider cache
    print("🌐 Source 6: Network location cache...")
    try:
        result = subprocess.run(
            ["adb", "shell", "dumpsys", "activity", "service", "com.google.android.gms/.location.reporting.service.ReportingAndroidService"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            with open("logs/network_location_cache.txt", "w", encoding="utf-8") as f:
                f.write(result.stdout)
            all_location_data.append(("network cache", result.stdout))
            print("   ✅ Extracted")
        else:
            print("   ⚠️ Not available (Google Play Services)")
    except Exception as e:
        print(f"   ⚠️ Error: {e}")
    
    print("")
    print("=" * 60)
    print("📊 Extraction Summary:")
    print("=" * 60)
    
    # Analyze what we found
    total_gps = 0
    total_cells = 0
    total_wifi = 0
    
    for source_name, content in all_location_data:
        gps_count = content.count("Location[")
        cell_count = content.count("CellLocation") + content.count("mCellInfo")
        wifi_count = content.count("SSID")
        
        if gps_count > 0 or cell_count > 0 or wifi_count > 0:
            print(f"\n{source_name}:")
            if gps_count > 0:
                print(f"  📍 GPS fixes: {gps_count}")
                total_gps += gps_count
            if cell_count > 0:
                print(f"  📡 Cell towers: {cell_count}")
                total_cells += cell_count
            if wifi_count > 0:
                print(f"  📶 WiFi networks: {wifi_count}")
                total_wifi += wifi_count
    
    print("")
    print("=" * 60)
    print(f"Total GPS locations found: {total_gps}")
    print(f"Total cell tower data: {total_cells}")
    print(f"Total WiFi networks: {total_wifi}")
    print("=" * 60)
    
    if total_gps == 0 and total_cells == 0 and total_wifi == 0:
        print("")
        print("⚠️  No location data found in any source.")
        print("")
        print("This could mean:")
        print("  • Device is brand new with no location history")
        print("  • Location history has been cleared")
        print("  • Device has never used location services")
        print("  • Privacy settings prevent data storage")
    else:
        print("")
        print("✅ Location data extracted successfully!")
        print(f"   Check the logs/ folder for detailed data")
    
    return total_gps, total_cells, total_wifi

if __name__ == "__main__":
    extract_historical_location()
