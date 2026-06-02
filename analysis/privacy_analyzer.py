
import os
import json
import re

def analyze_privacy(logs_dir="logs", output_file="logs/privacy_profile.json"):
    """
    Enhanced Privacy Analyzer - Comprehensive Permission Detection
    
    Detects ALL privacy-sensitive permission usage from Android logcat including:
    - Location (GPS, Network, Fused)
    - Camera (Camera1, Camera2, ImageCapture)
    - Microphone (AudioRecord, MediaRecorder, VoiceInteraction)
    - Contacts (ContactsProvider, CallLog)
    - Biometrics (Fingerprint, Face, Iris)
    - Clipboard (ClipboardService, ClipData)
    - Storage (MediaStore, ExternalStorage, SAF)
    - Phone State (TelephonyManager, CallManager)
    - SMS/MMS (SmsManager, MmsManager)
    - Calendar (CalendarProvider)
    - Sensors (Accelerometer, Gyroscope, etc.)
    - Body Sensors (Heart Rate, Step Counter)
    """
    logcat_path = os.path.join(logs_dir, "android_logcat.txt")
    if not os.path.exists(logcat_path):
        print(f"⚠️  Logcat file not found: {logcat_path}")
        return

    profile = {
        "location": [],
        "camera": [],
        "microphone": [],
        "contacts": [],
        "biometrics": [],
        "clipboard": [],
        "storage": [],
        "phone_state": [],
        "sms": [],
        "calendar": [],
        "sensors": [],
        "body_sensors": [],
        "summary": {}
    }

    # ═══════════════════════════════════════════════════════════════
    # COMPREHENSIVE DETECTION PATTERNS
    # ═══════════════════════════════════════════════════════════════
    
    patterns = {
        # ─────────────────────────────────────────────────────────────
        # LOCATION ACCESS (GPS, Network, Fused Location)
        # ─────────────────────────────────────────────────────────────
        "location": r'(?:'
                    r'LocationManager|'
                    r'gps|GPS|'
                    r'LocationService|'
                    r'fused|FusedLocation|'
                    r'GnssLocationProv|GNSS|'
                    r'getLastLocation|'
                    r'requestLocationUpdates|'
                    r'removeLocationUpdates|'
                    r'addGpsStatusListener|'
                    r'GpsLocationProvider|'
                    r'NetworkLocationProvider|'
                    r'PassiveLocationProvider|'
                    r'GeofenceManager|'
                    r'Geocoder|'
                    r'com\.android\.location|'
                    r'ACCESS_FINE_LOCATION|'
                    r'ACCESS_COARSE_LOCATION|'
                    r'ACCESS_BACKGROUND_LOCATION'
                    r')',
        
        # ─────────────────────────────────────────────────────────────
        # CAMERA ACCESS (Camera1, Camera2, ImageCapture, QR Scanner)
        # ─────────────────────────────────────────────────────────────
        "camera": r'(?:'
                  r'CameraService|'
                  r'CameraDevice|'
                  r'Camera2|'
                  r'CameraManager|'
                  r'android\.hardware\.camera|'
                  r'openCamera|'
                  r'Camera\.open|'
                  r'createCaptureSession|'
                  r'takePicture|'
                  r'startPreview|'
                  r'ImageCapture|'
                  r'CameraX|'
                  r'QRCodeScanner|'
                  r'BarcodeScan|'
                  r'CameraMetadata|'
                  r'CameraCharacteristics|'
                  r'android\.permission\.CAMERA'
                  r')',
        
        # ─────────────────────────────────────────────────────────────
        # MICROPHONE ACCESS (AudioRecord, MediaRecorder, Voice)
        # ─────────────────────────────────────────────────────────────
        "microphone": r'(?:'
                      r'AudioRecord|'
                      r'AudioSource|'
                      r'Microphone|'
                      r'AudioService.*startRecording|'
                      r'MediaRecorder|'
                      r'startRecording|'
                      r'VoiceInteraction|'
                      r'VoiceRecognition|'
                      r'SpeechRecognizer|'
                      r'AudioCapture|'
                      r'android\.media\.AudioRecord|'
                      r'android\.media\.MediaRecorder|'
                      r'RECORD_AUDIO|'
                      r'MIC_INDICATOR|'
                      r'AudioFlinger.*record'
                      r')',
        
        # ─────────────────────────────────────────────────────────────
        # CONTACTS ACCESS (ContactsProvider, CallLog, PhoneBook)
        # ─────────────────────────────────────────────────────────────
        "contacts": r'(?:'
                    r'ContactsProvider|'
                    r'ContactMetadata|'
                    r'Querying content://com\.android\.contacts|'
                    r'content://contacts|'
                    r'CallLog\.Calls|'
                    r'ContactsContract|'
                    r'PhoneNumberUtils|'
                    r'READ_CONTACTS|'
                    r'WRITE_CONTACTS|'
                    r'READ_CALL_LOG|'
                    r'WRITE_CALL_LOG|'
                    r'ContactsService|'
                    r'ContactAggregator'
                    r')',
        
        # ─────────────────────────────────────────────────────────────
        # BIOMETRICS (Fingerprint, Face, Iris, Under-display FP)
        # ─────────────────────────────────────────────────────────────
        "biometrics": r'(?:'
                      r'BiometricService|'
                      r'FingerprintService|'
                      r'FaceService|'
                      r'auth_biometric|'
                      r'BiometricPrompt|'
                      r'IrisService|'
                      r'AuthenticationCallback|'
                      r'BiometricManager|'
                      r'authenticate\(|'
                      r'USE_FINGERPRINT|'
                      r'USE_BIOMETRIC|'
                      r'FingerprintManager|'
                      r'FaceManager|'
                      r'BiometricAuthenticator|'
                      r'UdfpsController'  # Under-display fingerprint
                      r')',
        
        # ─────────────────────────────────────────────────────────────
        # CLIPBOARD ACCESS (ClipboardService, Copy/Paste)
        # ─────────────────────────────────────────────────────────────
        "clipboard": r'(?:'
                     r'ClipboardService|'
                     r'setPrimaryClip|'
                     r'getPrimaryClip|'
                     r'ClipData|'
                     r'ClipboardManager|'
                     r'addPrimaryClipChangedListener|'
                     r'hasPrimaryClip|'
                     r'clearPrimaryClip'
                     r')',
        
        # ─────────────────────────────────────────────────────────────
        # STORAGE ACCESS (MediaStore, ExternalStorage, Downloads)
        # ─────────────────────────────────────────────────────────────
        "storage": r'(?:'
                   r'MediaStore|'
                   r'ExternalStorage|'
                   r'WRITE_EXTERNAL_STORAGE|'
                   r'READ_EXTERNAL_STORAGE|'
                   r'MANAGE_EXTERNAL_STORAGE|'
                   r'StorageManager|'
                   r'DownloadManager|'
                   r'DocumentsProvider|'
                   r'MediaProvider|'
                   r'SAF|StorageAccessFramework|'
                   r'scoped.*storage'
                   r')',
        
        # ─────────────────────────────────────────────────────────────
        # PHONE STATE (TelephonyManager, IMEI, Phone Number)
        # ─────────────────────────────────────────────────────────────
        "phone_state": r'(?:'
                       r'TelephonyManager|'
                       r'getDeviceId|'
                       r'getLine1Number|'
                       r'getSubscriberId|'
                       r'getSimSerialNumber|'
                       r'READ_PHONE_STATE|'
                       r'READ_PHONE_NUMBERS|'
                       r'PhoneStateListener|'
                       r'CallManager|'
                       r'TelecomManager'
                       r')',
        
        # ─────────────────────────────────────────────────────────────
        # SMS/MMS ACCESS
        # ─────────────────────────────────────────────────────────────
        "sms": r'(?:'
               r'SmsManager|'
               r'MmsManager|'
               r'READ_SMS|'
               r'SEND_SMS|'
               r'RECEIVE_SMS|'
               r'RECEIVE_MMS|'
               r'content://sms|'
               r'content://mms|'
               r'SmsProvider|'
               r'sendTextMessage'
               r')',
        
        # ─────────────────────────────────────────────────────────────
        # CALENDAR ACCESS
        # ─────────────────────────────────────────────────────────────
        "calendar": r'(?:'
                    r'CalendarProvider|'
                    r'content://com\.android\.calendar|'
                    r'READ_CALENDAR|'
                    r'WRITE_CALENDAR|'
                    r'CalendarContract'
                    r')',
        
        # ─────────────────────────────────────────────────────────────
        # SENSORS (Accelerometer, Gyroscope, Proximity, Light)
        # ─────────────────────────────────────────────────────────────
        "sensors": r'(?:'
                   r'SensorService|'
                   r'SensorManager|'
                   r'Accelerometer|'
                   r'Gyroscope|'
                   r'ProximitySensor|'
                   r'LightSensor|'
                   r'MagneticField|'
                   r'Barometer|'
                   r'registerListener.*Sensor|'
                   r'onSensorChanged'
                   r')',
        
        # ─────────────────────────────────────────────────────────────
        # BODY SENSORS (Heart Rate, Step Counter, Health)
        # ─────────────────────────────────────────────────────────────
        "body_sensors": r'(?:'
                        r'HeartRate|'
                        r'StepCounter|'
                        r'BODY_SENSORS|'
                        r'HealthConnect|'
                        r'FitnessService|'
                        r'ActivityRecognition'
                        r')'
    }

    # ═══════════════════════════════════════════════════════════════
    # ENHANCED PACKAGE NAME EXTRACTION
    # ═══════════════════════════════════════════════════════════════
    # Matches:
    # - pkg=com.example.app
    # - packageName=com.example.app
    # - [com.example.app]
    # - package:com.example.app
    # - from com.example.app
    # - called by 1234/com.example.app
    PKG_REGEX = re.compile(
        r'(?:'
        r'pkg=|'
        r'packageName=|'
        r'package:|'
        r'from\s+|'
        r'called by\s+\d+/|'
        r'\[)'
        r'([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)',
        re.IGNORECASE
    )

    # ═══════════════════════════════════════════════════════════════
    # PROCESS LOGCAT LINE BY LINE
    # ═══════════════════════════════════════════════════════════════
    print(f"📖 Reading logcat from: {logcat_path}")
    
    lines_processed = 0
    events_detected = 0
    
    with open(logcat_path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            lines_processed += 1
            
            # Check each privacy category
            for key, pattern in patterns.items():
                if re.search(pattern, line, re.IGNORECASE):
                    # Extract package name
                    pkg_match = PKG_REGEX.search(line)
                    pkg_name = pkg_match.group(1) if pkg_match else "System/Unknown"
                    
                    # Store the event
                    profile[key].append({
                        "package": pkg_name,
                        "content": line.strip()
                    })
                    events_detected += 1

    # ═══════════════════════════════════════════════════════════════
    # GENERATE SUMMARY STATISTICS
    # ═══════════════════════════════════════════════════════════════
    for key in patterns.keys():
        profile["summary"][key] = len(profile[key])

    # ═══════════════════════════════════════════════════════════════
    # SAVE TO JSON FILE
    # ═══════════════════════════════════════════════════════════════
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(profile, f, indent=4)
    
    # ═══════════════════════════════════════════════════════════════
    # DISPLAY SUMMARY
    # ═══════════════════════════════════════════════════════════════
    print(f"\n{'='*60}")
    print(f"  ENHANCED PRIVACY PROFILE GENERATED")
    print(f"{'='*60}")
    print(f"📊 Lines Processed: {lines_processed:,}")
    print(f"🔍 Events Detected: {events_detected:,}")
    print(f"\n📁 Output File: {output_file}")
    print(f"\n📈 Privacy Events Summary:")
    print(f"{'─'*60}")
    
    for key, count in profile["summary"].items():
        icon = "✅" if count > 0 else "⚪"
        category = key.replace('_', ' ').title()
        print(f"  {icon} {category:.<30} {count:>5}")
    
    print(f"{'─'*60}")
    print(f"  TOTAL PRIVACY EVENTS: {sum(profile['summary'].values())}")
    print(f"{'='*60}\n")
    
    print("✅ Enhanced privacy profile generation complete!")

if __name__ == "__main__":
    analyze_privacy()
