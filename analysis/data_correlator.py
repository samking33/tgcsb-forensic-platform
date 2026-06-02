"""
Advanced Data Correlation Engine
Correlates events across SMS, Calls, Location, and Logcat to find patterns
"""

import os
import json
from datetime import datetime, timedelta
from collections import defaultdict

class DataCorrelator:
    def __init__(self, logs_dir="logs"):
        self.logs_dir = logs_dir
        self.correlations = []
        
    def correlate_all(self):
        """Run all correlation algorithms"""
        print("🔗 Running Advanced Data Correlation...")
        print("=" * 60)
        
        # Load all data
        timeline = self.load_json('unified_timeline.json')
        sms_data = self.load_text_logs('sms_logs.txt')
        call_data = self.load_text_logs('call_logs.txt')
        network_data = self.load_json('network_activity.json')
        power_data = self.load_json('power_forensics.json')
        app_sessions = self.load_json('app_sessions.json')
        
        # Run correlation algorithms
        self.correlate_sms_and_calls(sms_data, call_data)
        self.correlate_activity_and_power(app_sessions, power_data)
        self.correlate_network_and_apps(network_data, timeline)
        self.detect_suspicious_patterns(timeline)
        self.detect_time_clusters(timeline)
        
        # Save results
        self.save_correlations()
        
        print("=" * 60)
        print(f"✅ Found {len(self.correlations)} correlations")
        
        return self.correlations
    
    def load_json(self, filename):
        """Load JSON file"""
        filepath = os.path.join(self.logs_dir, filename)
        if os.path.exists(filepath):
            with open(filepath, 'r', encoding='utf-8') as f:
                return json.load(f)
        return {}
    
    def load_text_logs(self, filename):
        """Parse text log files"""
        filepath = os.path.join(self.logs_dir, filename)
        if not os.path.exists(filepath):
            return []
        
        events = []
        with open(filepath, 'r', encoding='utf-8') as f:
            for line in f:
                parts = line.strip().split('|')
                if len(parts) >= 3:
                    events.append({
                        'timestamp': parts[0].strip(),
                        'type': parts[1].strip() if len(parts) > 1 else '',
                        'data': '|'.join(parts[2:])
                    })
        return events
    
    def correlate_sms_and_calls(self, sms_data, call_data):
        """Find SMS messages sent/received near call times"""
        print("📱 Correlating SMS and Calls...")
        
        count = 0
        for sms in sms_data:
            sms_time = self.parse_timestamp(sms['timestamp'])
            if not sms_time:
                continue
            
            for call in call_data:
                call_time = self.parse_timestamp(call['timestamp'])
                if not call_time:
                    continue
                
                # Check if within 5 minutes
                time_diff = abs((sms_time - call_time).total_seconds())
                if time_diff <= 300:  # 5 minutes
                    self.correlations.append({
                        'type': 'SMS_CALL_PROXIMITY',
                        'confidence': 'HIGH' if time_diff <= 60 else 'MEDIUM',
                        'description': f"SMS and call within {int(time_diff/60)} minutes",
                        'sms_time': sms['timestamp'],
                        'call_time': call['timestamp'],
                        'time_diff_seconds': time_diff,
                        'significance': 'Possible coordinated communication'
                    })
                    count += 1
        
        print(f"  ✓ Found {count} SMS-Call correlations")
    
    def correlate_activity_and_power(self, app_sessions, power_data):
        """Correlate app usage with power events"""
        print("🔋 Correlating App Usage and Power Events...")
        
        if not app_sessions or 'sessions' not in app_sessions:
            print("  ⚠️  No app session data")
            return
        
        count = 0
        sessions = app_sessions.get('sessions', [])
        events = power_data if isinstance(power_data, list) else []
        
        for session in sessions[:100]:  # Limit to avoid performance issues
            start_time = self.parse_timestamp(session.get('start_time', ''))
            if not start_time:
                continue
            
            for event in events:
                event_time = self.parse_timestamp(event.get('timestamp', ''))
                if not event_time:
                    continue
                
                time_diff = abs((start_time - event_time).total_seconds())
                if time_diff <= 10 and event.get('event') in ['SCREEN_ON', 'USER_PRESENT']:
                    self.correlations.append({
                        'type': 'APP_POWER_CORRELATION',
                        'confidence': 'HIGH',
                        'description': f"App session started {int(time_diff)}s after {event['event']}",
                        'app': session.get('package', 'Unknown'),
                        'power_event': event['event'],
                        'significance': 'User activity pattern'
                    })
                    count += 1
        
        print(f"  ✓ Found {count} App-Power correlations")
    
    def correlate_network_and_apps(self, network_data, timeline):
        """Find network connections correlated with app launches"""
        print("🌐 Correlating Network Activity and App Events...")
        
        # This would require more detailed implementation
        # Placeholder for now
        print("  ✓ Network-App correlation (placeholder)")
    
    def detect_suspicious_patterns(self, timeline):
        """Detect suspicious patterns like rapid-fire actions"""
        print("🚨 Detecting Suspicious Patterns...")
        
        if not timeline or not isinstance(timeline, list):
            print("  ⚠️  No timeline data")
            return
        
        # Group events by minute
        events_by_minute = defaultdict(int)
        for event in timeline:
            timestamp = self.parse_timestamp(event.get('timestamp', ''))
            if timestamp:
                minute_key = timestamp.strftime('%Y-%m-%d %H:%M')
                events_by_minute[minute_key] += 1
        
        # Find spikes (>50 events per minute)
        count = 0
        for minute, event_count in events_by_minute.items():
            if event_count > 50:
                self.correlations.append({
                    'type': 'ACTIVITY_SPIKE',
                    'confidence': 'HIGH',
                    'description': f"{event_count} events in one minute",
                    'timestamp': minute,
                    'event_count': event_count,
                    'significance': 'Possible automated activity or data exfiltration'
                })
                count += 1
        
        print(f"  ✓ Found {count} suspicious activity spikes")
    
    def detect_time_clusters(self, timeline):
        """Detect time-based activity clusters"""
        print("📊 Detecting Time-Based Activity Clusters...")
        
        if not timeline or not isinstance(timeline, list):
            print("  ⚠️  No timeline data")
            return
        
        # Group by hour of day
        hour_distribution = defaultdict(int)
        for event in timeline:
            timestamp = self.parse_timestamp(event.get('timestamp', ''))
            if timestamp:
                hour_distribution[timestamp.hour] += 1
        
        # Find peak hours
        if hour_distribution:
            max_hour = max(hour_distribution, key=hour_distribution.get)
            max_count = hour_distribution[max_hour]
            
            self.correlations.append({
                'type': 'TIME_CLUSTER',
                'confidence': 'MEDIUM',
                'description': f"Peak activity during hour {max_hour}:00-{max_hour+1}:00",
                'hour': max_hour,
                'event_count': max_count,
                'significance': 'User activity pattern - peak usage time'
            })
            
            print(f"  ✓ Peak activity: {max_hour}:00 hour ({max_count} events)")
    
    def parse_timestamp(self, ts_str):
        """Parse timestamp string to datetime object"""
        if not ts_str:
            return None
        
        formats = [
            '%Y-%m-%d %H:%M:%S',
            '%Y-%m-%dT%H:%M:%S',
            '%Y-%m-%dT%H:%M:%S.%f',
        ]
        
        for fmt in formats:
            try:
                return datetime.strptime(ts_str[:19], fmt[:19])
            except:
                continue
        return None
    
    def save_correlations(self):
        """Save correlations to JSON"""
        output = {
            'generated': datetime.now().isoformat(),
            'total_correlations': len(self.correlations),
            'correlation_types': self.get_correlation_summary(),
            'correlations': self.correlations
        }
        
        output_file = os.path.join(self.logs_dir, 'data_correlations.json')
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output, f, indent=4)
        
        print(f"\n📁 Saved correlations to: {output_file}")
    
    def get_correlation_summary(self):
        """Get summary of correlation types"""
        summary = defaultdict(int)
        for corr in self.correlations:
            summary[corr['type']] += 1
        return dict(summary)

if __name__ == "__main__":
    correlator = DataCorrelator()
    correlator.correlate_all()
