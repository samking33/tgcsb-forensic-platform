"""
threat_signatures.py

Known threat signatures and malware patterns for Android forensic analysis.
Contains IoC (Indicators of Compromise) database for automated threat detection.
"""

import re
import sys

# Fix Windows encoding issues with emoji characters
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except AttributeError:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

# =========================================================================
# MALWARE SIGNATURES
# =========================================================================

KNOWN_MALWARE_PACKAGES = {
    # Common Android malware package patterns
    'com.android.systemupdate': 'Fake system update malware',
    'com.android.battery': 'Fake battery optimizer malware',
    'com.securitydefender': 'Fake security app',
    'com.system.service': 'Suspicious system service',
   
'com.google.provider': 'Fake Google service',
    'com.mobsecure': 'Adware/Spyware',
    'cn.android.setting': 'Chinese malware variant',
    'com.uudevice.update': 'Trojan dropper',
    'com.security.service': 'Fake security service',
    'com.android.systemui.service': 'System UI impersonator',
}

# Suspicious package name patterns (regex)
SUSPICIOUS_PACKAGE_PATTERNS = [
    (re.compile(r'com\.android\.system[^a-z]', re.IGNORECASE), 'System impersonator'),
    (re.compile(r'com\.google\.(play|services|gms)[^a-z]', re.IGNORECASE), 'Google service impersonator'),
    (re.compile(r'\.update(r|service)?$', re.IGNORECASE), 'Fake updater'),
    (re.compile(r'(root|su|superuser|busybox)', re.IGNORECASE), 'Rooting/privilege escalation'),
    (re.compile(r'(hack|crack|cheat|mod)', re.IGNORECASE), 'Suspicious hacking tool'),
    (re.compile(r'cn\.|com\.cn\.|\.cn\.', re.IGNORECASE), 'Chinese origin (suspicious)'),
]

# =========================================================================
# DATA EXFILTRATION INDICATORS
# =========================================================================

DATA_EXFILTRATION_PATTERNS = [
    # Network patterns
    (re.compile(r'POST.*(/upload|/data|/send|/exfil)', re.IGNORECASE), 'Suspicious data upload'),
    (re.compile(r'connect.*:\d{4}\d+', re.IGNORECASE), 'Non-standard port connection'),
    (re.compile(r'socket.*connect.*\b(8080|8888|9999|4444|1337)\b', re.IGNORECASE), 'Common C2 ports'),
    
    # File access patterns
    (re.compile(r'(contacts|sms|call_log|calendar).*query', re.IGNORECASE), 'Personal data access'),
    (re.compile(r'READ_(CONTACTS|SMS|CALL_LOG|CALENDAR)', re.IGNORECASE), 'Sensitive permission usage'),
    (re.compile(r'/sdcard/.*(password|credential|key|token)', re.IGNORECASE), 'Credential file access'),
    
    # Encryption/encoding (potential data preparation)
    (re.compile(r'(AES|DES|RSA|Base64)\.(encrypt|encode)', re.IGNORECASE), 'Data encryption'),
    (re.compile(r'java\.security\.KeyStore', re.IGNORECASE), 'KeyStore access'),
]

# =========================================================================
# PRIVILEGE ESCALATION INDICATORS
# =========================================================================

PRIVILEGE_ESCALATION_PATTERNS = [
    (re.compile(r'su\s', re.IGNORECASE), 'Root access attempt'),
    (re.compile(r'(/system/xbin/su|/system/bin/su)', re.IGNORECASE), 'SU binary access'),
    (re.compile(r'Superuser|SuperSU|Magisk', re.IGNORECASE), 'Root management'),
    (re.compile(r'Runtime\.exec.*sh', re.IGNORECASE), 'Shell command execution'),
    (re.compile(r'ProcessBuilder.*su', re.IGNORECASE), 'SU process spawn'),
    (re.compile(r'/system/(app|priv-app)/.*\.apk.*install', re.IGNORECASE), 'System app modification'),
]

# =========================================================================
# SUSPICIOUS NETWORK ACTIVITY
# =========================================================================

SUSPICIOUS_IPS = [
    # Example known malicious IPs (replace with actual threat intelligence)
    '185.220.101.',  # Tor exit nodes prefix
    '45.142.212.',   # Known C2 infrastructure prefix
    '185.239.226.',  # Suspicious hosting
]

SUSPICIOUS_DOMAINS = [
    'bit.ly',  # URL shorteners (often used in phishing)
    'tinyurl.com',
    'ow.ly',
    't.co',
    '.tk',  # Free TLDs often used in malware
    '.ml',
    '.ga',
    '.cf',
]

NETWORK_THREAT_PATTERNS = [
    (re.compile(r'http://\d+\.\d+\.\d+\.\d+', re.IGNORECASE), 'Direct IP connection (suspicious)'),
    (re.compile(r'\.onion', re.IGNORECASE), 'Tor hidden service'),
    (re.compile(r'tor|proxy|vpn.*connect', re.IGNORECASE), 'Anonymization attempt'),
]

# =========================================================================
# SUSPICIOUS BEHAVIOR PATTERNS
# =========================================================================

SUSPICIOUS_BEHAVIORS = [
    # Hide icon
    (re.compile(r'setComponentEnabled.*COMPONENT_ENABLED_STATE_DISABLED', re.IGNORECASE), 'App hiding icon'),
    
    # Persistence
    (re.compile(r'BOOT_COMPLETED.*receive', re.IGNORECASE), 'Auto-start on boot'),
    (re.compile(r'START_STICKY', re.IGNORECASE), 'Persistent service'),
    
    # Admin rights
    (re.compile(r'DeviceAdminReceiver|DevicePolicyManager', re.IGNORECASE), 'Device admin access'),
    (re.compile(r'DEVICE_ADMIN_ENABLED', re.IGNORECASE), 'Admin activation'),
    
    # SMS interception
    (re.compile(r'SMS_RECEIVED.*abortBroadcast', re.IGNORECASE), 'SMS interception'),
    (re.compile(r'SEND_SMS', re.IGNORECASE), 'SMS sending (potential premium SMS scam)'),
    
    # Overlay attacks
    (re.compile(r'TYPE_APPLICATION_OVERLAY|SYSTEM_ALERT_WINDOW', re.IGNORECASE), 'Screen overlay capability'),
    
    # Accessibility abuse
    (re.compile(r'AccessibilityService.*enabled', re.IGNORECASE), 'Accessibility service abuse'),
]

# =========================================================================
# CRASH/STABILITY INDICATORS
# =========================================================================

CRASH_PATTERNS = [
    (re.compile(r'FATAL EXCEPTION', re.IGNORECASE), 'Fatal crash'),
    (re.compile(r'ANR in ', re.IGNORECASE), 'App not responding'),
    (re.compile(r'java\.lang\.OutOfMemoryError', re.IGNORECASE), 'Out of memory'),
]

# =========================================================================
# THREAT SCORING WEIGHTS
# =========================================================================

THREAT_WEIGHTS = {
    'malware_package': 100,  # Definite malware
    'suspicious_package': 60,
    'data_exfiltration': 80,
    'privilege_escalation': 90,
    'suspicious_network': 70,
    'suspicious_behavior': 50,
    'crash': 20,
}

# =========================================================================
# KNOWN GOOD PACKAGES (Whitelist)
# =========================================================================

WHITELISTED_PACKAGES = {
    'com.google.android.gms',
    'com.google.android.gsf',
    'com.google.android.apps.maps',
    'com.android.vending',
    'com.android.systemui',
    'com.android.settings',
    'com.android.launcher',
    'com.samsung.android.',
    'com.sec.android.',
}


def is_whitelisted(package_name):
    """Check if package is whitelisted."""
    for whitelist in WHITELISTED_PACKAGES:
        if package_name.startswith(whitelist):
            return True
    return False


def get_all_threat_patterns():
    """Get all threat patterns consolidated."""
    return {
        'malware': KNOWN_MALWARE_PACKAGES,
        'suspicious_packages': SUSPICIOUS_PACKAGE_PATTERNS,
        'data_exfil': DATA_EXFILTRATION_PATTERNS,
        'privilege_escalation': PRIVILEGE_ESCALATION_PATTERNS,
        'network_threats': NETWORK_THREAT_PATTERNS,
        'behaviors': SUSPICIOUS_BEHAVIORS,
        'crashes': CRASH_PATTERNS,
    }


if __name__ == '__main__':
    print("Threat Signature Database\n")
    print(f"Known malware packages: {len(KNOWN_MALWARE_PACKAGES)}")
    print(f"Suspicious patterns: {len(SUSPICIOUS_PACKAGE_PATTERNS)}")
    print(f"Data exfil patterns: {len(DATA_EXFILTRATION_PATTERNS)}")
    print(f"Privilege escalation patterns: {len(PRIVILEGE_ESCALATION_PATTERNS)}")
    print(f"Network threat patterns: {len(NETWORK_THREAT_PATTERNS)}")
    print(f"Suspicious behaviors: {len(SUSPICIOUS_BEHAVIORS)}")
    print(f"\n✅ Threat signatures loaded!")
