"""
threat_scanner.py

Real-time threat scanning module for Android forensic analysis.
Scans logs against threat signature database and generates security alerts.
"""

from threat_signatures import *
import re
from datetime import datetime
from collections import defaultdict


class ThreatScanner:
    """Main threat scanner class."""
    
    def __init__(self):
        self.threats_found = []
        self.risk_score = 0
        self.scan_stats = defaultdict(int)
    
    def scan_logs(self, log_file_path):
        """
        Scan a log file for threats.
        
        Args:
            log_file_path: Path to the log file
        
        Returns:
            dict with scan results
        """
        self.threats_found = []
        self.risk_score = 0
        self.scan_stats = defaultdict(int)
        
        try:
            with open(log_file_path, 'r', encoding='utf-8', errors='replace') as f:
                lines = f.readlines()
        except FileNotFoundError:
            return {'error': f'File not found: {log_file_path}'}
        
        total_lines = len(lines)
        
        for line_num, line in enumerate(lines, 1):
            # Scan for each threat category
            self._scan_malware_packages(line, line_num)
            self._scan_data_exfiltration(line, line_num)
            self._scan_privilege_escalation(line, line_num)
            self._scan_network_threats(line, line_num)
            self._scan_suspicious_behaviors(line, line_num)
            self._scan_crashes(line, line_num)
        
        # Calculate overall risk score
        self._calculate_risk_score()
        
        return {
            'threats': self.threats_found,
            'risk_score': self.risk_score,
            'threat_count': len(self.threats_found),
            'lines_scanned': total_lines,
            'stats': dict(self.scan_stats)
        }
    
    def _scan_malware_packages(self, line, line_num):
        """Scan for known malware packages."""
        for package, description in KNOWN_MALWARE_PACKAGES.items():
            if package in line:
                # Check if whitelisted
                if not is_whitelisted(package):
                    self.threats_found.append({
                        'line': line_num,
                        'type': 'MALWARE',
                        'severity': 'CRITICAL',
                        'package': package,
                        'description': description,
                        'evidence': line.strip(),
'weight': THREAT_WEIGHTS['malware_package']
                    })
                    self.scan_stats['malware'] += 1
        
        # Scan for suspicious patterns
        for pattern, description in SUSPICIOUS_PACKAGE_PATTERNS:
            if pattern.search(line):
                # Extract package name
                package_match = re.search(r'(com\.[a-z0-9.]+)', line, re.IGNORECASE)
                package = package_match.group(1) if package_match else 'Unknown'
                
                if not is_whitelisted(package):
                    self.threats_found.append({
                        'line': line_num,
                        'type': 'SUSPICIOUS_PACKAGE',
                        'severity': 'HIGH',
                        'package': package,
                        'description': description,
                        'evidence': line.strip(),
                        'weight': THREAT_WEIGHTS['suspicious_package']
                    })
                    self.scan_stats['suspicious_packages'] += 1
    
    def _scan_data_exfiltration(self, line, line_num):
        """Scan for data exfiltration indicators."""
        for pattern, description in DATA_EXFILTRATION_PATTERNS:
            if pattern.search(line):
                self.threats_found.append({
                    'line': line_num,
                    'type': 'DATA_EXFILTRATION',
                    'severity': 'CRITICAL',
                    'description': description,
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['data_exfiltration']
                })
                self.scan_stats['data_exfiltration'] += 1
    
    def _scan_privilege_escalation(self, line, line_num):
        """Scan for privilege escalation attempts."""
        for pattern, description in PRIVILEGE_ESCALATION_PATTERNS:
            if pattern.search(line):
                self.threats_found.append({
                    'line': line_num,
                    'type': 'PRIVILEGE_ESCALATION',
                    'severity': 'CRITICAL',
                    'description': description,
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['privilege_escalation']
                })
                self.scan_stats['privilege_escalation'] += 1
    
    def _scan_network_threats(self, line, line_num):
        """Scan for suspicious network activity."""
        # Check for suspicious IPs
        for ip_prefix in SUSPICIOUS_IPS:
            if ip_prefix in line:
                self.threats_found.append({
                    'line': line_num,
                    'type': 'SUSPICIOUS_NETWORK',
                    'severity': 'HIGH',
                    'description': f'Connection to suspicious IP: {ip_prefix}',
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['suspicious_network']
                })
                self.scan_stats['suspicious_network'] += 1
        
        # Check for suspicious domains
        for domain in SUSPICIOUS_DOMAINS:
            if domain in line.lower():
                self.threats_found.append({
                    'line': line_num,
                    'type': 'SUSPICIOUS_NETWORK',
                    'severity': 'MEDIUM',
                    'description': f'Connection to suspicious domain: {domain}',
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['suspicious_network'] // 2
                })
                self.scan_stats['suspicious_network'] += 1
        
        # Check for network threat patterns
        for pattern, description in NETWORK_THREAT_PATTERNS:
            if pattern.search(line):
                self.threats_found.append({
                    'line': line_num,
                    'type': 'SUSPICIOUS_NETWORK',
                    'severity': 'HIGH',
                    'description': description,
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['suspicious_network']
                })
                self.scan_stats['suspicious_network'] += 1
    
    def _scan_suspicious_behaviors(self, line, line_num):
        """Scan for suspicious app behaviors."""
        for pattern, description in SUSPICIOUS_BEHAVIORS:
            if pattern.search(line):
                self.threats_found.append({
                    'line': line_num,
                    'type': 'SUSPICIOUS_BEHAVIOR',
                    'severity': 'MEDIUM',
                    'description': description,
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['suspicious_behavior']
                })
                self.scan_stats['suspicious_behavior'] += 1
    
    def _scan_crashes(self, line, line_num):
        """Scan for crashes (may indicate malware or instability)."""
        for pattern, description in CRASH_PATTERNS:
            if pattern.search(line):
                self.threats_found.append({
                    'line': line_num,
                    'type': 'CRASH',
                    'severity': 'LOW',
                    'description': description,
                    'evidence': line.strip(),
                    'weight': THREAT_WEIGHTS['crash']
                })
                self.scan_stats['crashes'] += 1
    
    def _calculate_risk_score(self):
        """Calculate overall risk score (0-100)."""
        if not self.threats_found:
            self.risk_score = 0
            return
        
        # Sum weighted scores
        total_weight = sum(threat['weight'] for threat in self.threats_found)
        
        # Normalize to 0-100 scale (cap at 100)
        self.risk_score = min(100, total_weight)
    
    def get_risk_level(self):
        """Get risk level category."""
        if self.risk_score >= 80:
            return "CRITICAL", "🔴"
        elif self.risk_score >= 60:
            return "HIGH", "🟠"
        elif self.risk_score >= 40:
            return "MEDIUM", "🟡"
        elif self.risk_score >= 20:
            return "LOW", "🟢"
        else:
            return "MINIMAL", "✅"
    
    def generate_report(self):
        """Generate a threat report string."""
        risk_level, icon = self.get_risk_level()
        
        report = []
        report.append("=" * 60)
        report.append("ANDROID FORENSIC TOOL - THREAT SCAN REPORT")
        report.append("=" * 60)
        report.append(f"Scan Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        report.append(f"Risk Level: {icon} {risk_level} (Score: {self.risk_score}/100)")
        report.append(f"Threats Found: {len(self.threats_found)}")
        report.append("")
        
        if not self.threats_found:
            report.append("✅ No threats detected!")
            return "\n".join(report)
        
        report.append("THREAT SUMMARY BY CATEGORY:")
        report.append("-" * 60)
        for category, count in sorted(self.scan_stats.items(), key=lambda x: x[1], reverse=True):
            report.append(f"  {category.replace('_', ' ').title()}: {count}")
        
        report.append("")
        report.append("DETAILED THREATS:")
        report.append("-" * 60)
        
        # Group by severity
        critical = [t for t in self.threats_found if t['severity'] == 'CRITICAL']
        high = [t for t in self.threats_found if t['severity'] == 'HIGH']
        medium = [t for t in self.threats_found if t['severity'] == 'MEDIUM']
        low = [t for t in self.threats_found if t['severity'] == 'LOW']
        
        for severity_name, threats in [('CRITICAL', critical), ('HIGH', high), ('MEDIUM', medium), ('LOW', low)]:
            if threats:
                report.append(f"\n{severity_name} THREATS ({len(threats)}):")
                for i, threat in enumerate(threats[:10], 1):  # Show top 10 per category
                    report.append(f"\n  [{i}] Line {threat['line']}")
                    report.append(f"      Type: {threat['type']}")
                    report.append(f"      {threat['description']}")
                    report.append(f"      Evidence: {threat['evidence'][:100]}...")
                
                if len(threats) > 10:
                    report.append(f"\n      ... and {len(threats) - 10} more")
        
        report.append("")
        report.append("=" * 60)
        report.append("RECOMMENDATIONS:")
        report.append("-" * 60)
        
        if critical or high:
            report.append("  🔴 IMMEDIATE ACTION REQUIRED:")
            report.append("    • Disconnect device from network")
            report.append("    • Backup important data if not compromised")
            report.append("    • Run full antivirus scan")
            report.append("    • Consider factory reset if malware confirmed")
        
       if medium:
            report.append("  🟡 INVESTIGATE FURTHER:")
            report.append("    • Review suspicious applications")
            report.append("    • Check app permissions")
            report.append("    • Monitor network activity")
        
        return "\n".join(report)


def quick_scan(log_file):
    """Quick scan helper function."""
    scanner = ThreatScanner()
    results = scanner.scan_logs(log_file)
    print(scanner.generate_report())
    return results


if __name__ == '__main__':
    print("Threat Scanner Module Test\n")
    
    import os
    test_log = 'logs/android_logcat.txt'
    
    if os.path.exists(test_log):
        print(f"Scanning {test_log}...\n")
        results = quick_scan(test_log)
    else:
        print(f"Test log file not found: {test_log}")
        print("Create some logs first by running the main tool.")
