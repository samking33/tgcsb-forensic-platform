"""
parsers.py - Simple log parsers for Android forensic data
"""

import re
from datetime import datetime


def parse_sms_logs(log_content):
    """Parse SMS logs into readable records."""
    sms_records = []
    lines = log_content.strip().split('\n')
    
    for line in lines:
        if not line.strip() or 'Row:' not in line:
            continue
        
        record = {}
        
        # Extract contact/address
        addr_match = re.search(r'address=([^,]+)', line)
        if addr_match:
            record['contact'] = addr_match.group(1).strip()
        else:
            record['contact'] = 'Unknown'
        
        # Extract date
        date_match = re.search(r'date=(\d+)', line)
        if date_match:
            try:
                timestamp = int(date_match.group(1)) / 1000
                dt = datetime.fromtimestamp(timestamp)
                record['date'] = dt.strftime('%Y-%m-%d')
                record['time'] = dt.strftime('%H:%M:%S')
            except:
                record['date'] = 'Unknown'
                record['time'] = 'Unknown'
        
        # Extract type
        type_match = re.search(r'type=(\d+)', line)
        if type_match:
            msg_type = type_match.group(1)
            record['type'] = 'Received' if msg_type == '1' else 'Sent'
        else:
            record['type'] = 'Unknown'
        
        # Extract message body
        # Enhanced regex to handle commas in body
        # Matches everything until a known following field or end of line
        body_match = re.search(r'body=(.+?)(?:,\s*(?:service_center|locked|sub_id|error_code|creator|seen|priority)=|$)', line)
        if body_match:
            record['message'] = body_match.group(1).strip()
        else:
            record['message'] = ''
        
        if record:
            sms_records.append(record)
    
    return sms_records


def parse_call_logs(log_content):
    """Parse call logs into readable records."""
    call_records = []
    lines = log_content.strip().split('\n')
    
    for line in lines:
        if not line.strip() or 'Row:' not in line:
            continue
        
        record = {}
        
        # Extract number/contact
        num_match = re.search(r'number=([^,]+)', line)
        if num_match:
            record['contact'] = num_match.group(1).strip()
        else:
            record['contact'] = 'Unknown'
        
        # Extract date
        date_match = re.search(r'date=(\d+)', line)
        if date_match:
            try:
                timestamp = int(date_match.group(1)) / 1000
                dt = datetime.fromtimestamp(timestamp)
                record['date'] = dt.strftime('%Y-%m-%d')
                record['time'] = dt.strftime('%H:%M:%S')
            except:
                record['date'] = 'Unknown'
                record['time'] = 'Unknown'
        
        # Extract duration
        dur_match = re.search(r'duration=(\d+)', line)
        if dur_match:
            seconds = int(dur_match.group(1))
            mins = seconds // 60
            secs = seconds % 60
            record['duration'] = f"{mins}:{secs:02d}"
        else:
            record['duration'] = '0:00'
        
        # Extract call type
        type_match = re.search(r'type=(\d+)', line)
        if type_match:
            call_type = type_match.group(1)
            if call_type == '1':
                record['type'] = 'Incoming'
            elif call_type == '2':
                record['type'] = 'Outgoing'
            elif call_type == '3':
                record['type'] = 'Missed'
            else:
                record['type'] = 'Unknown'
        else:
            record['type'] = 'Unknown'
        
        if record:
            call_records.append(record)
    
    return call_records


def parse_location_logs(log_content):
    """
    Parse dumpsys location output to extract location records.
    Focuses on 'Last Known Locations' and 'Location Request History'.
    """
    location_records = []
    lines = log_content.strip().split('\n')
    
    # Regex for standard Location[provider ... lat,lon ...] format
    # Example: Location[gps 37.421998,-122.084000 acc=10 et=+2d1h2m3s alt=0.0 vel=0.0 bear=0.0 {Bundle[...]}]
    # Relaxed pattern: Location[provider lat,lon
    loc_pattern = re.compile(r'Location\[(\w+)\s+([-+]?\d+\.\d+),([-+]?\d+\.\d+)')
    
    # Regex for Xiaomi MI LMS logs
    # Example: ... getLastLocation, packageName=..., provider=... permitted: true
    mi_lms_pattern = re.compile(r'(\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3})\s+-\s+=MI LMS=\s+getLastLocation,\s+packageName=([^,]+)(?:,\s+provider=([^,\s]+))?.*?(?:permitted:\s+(true|false))?')

    # Regex for timestamp in some dumpsys outputs
    time_pattern = re.compile(r'(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})')

    current_provider = "Unknown"
    
    for line in lines:
        line = line.strip()
        if not line:
            continue

        # 1. Try standard Location[...] format
        match = loc_pattern.search(line)
        if match:
            provider = match.group(1)
            lat = match.group(2)
            lon = match.group(3)
            
            # Extract accuracy if present
            acc_match = re.search(r'acc=(\d+(?:\.\d+)?)', line)
            accuracy = f"{acc_match.group(1)}m" if acc_match else "Unknown"
            
            # Try to find a timestamp
            time_match = time_pattern.search(line)
            if time_match:
                timestamp = time_match.group(1)
            else:
                t_match = re.search(r'time=(\d+)', line)
                if t_match:
                    try:
                        ts = int(t_match.group(1)) / 1000
                        timestamp = datetime.fromtimestamp(ts).strftime('%Y-%m-%d %H:%M:%S')
                    except:
                        timestamp = "Unknown Time"
                else:
                    # Fallback to 'et' (Elapsed Time)
                    et_match = re.search(r'et=([+\-]?[\w\d]+)', line)
                    if et_match:
                        timestamp = f"Elapsed: {et_match.group(1)}"
                    else:
                        timestamp = "Unknown Time"

            record = {
                'provider': provider,
                'latitude': lat,
                'longitude': lon,
                'accuracy': accuracy,
                'time': timestamp,
                'context': 'Location Fix',
                'raw': line[:100] + "..."
            }
            location_records.append(record)
            continue

        # 2. Try Xiaomi MI LMS format
        mi_match = mi_lms_pattern.search(line)
        if mi_match:
            timestamp_str = mi_match.group(1)
            package = mi_match.group(2).strip()
            provider = mi_match.group(3).strip() if mi_match.group(3) else "Unknown"
            permitted = mi_match.group(4)
            
            context = package
            if permitted:
                context += f" (Permitted: {permitted})"
            
            # Add current year to timestamp if missing
            current_year = datetime.now().year
            try:
                dt = datetime.strptime(f"{current_year}-{timestamp_str}", "%Y-%m-%d %H:%M:%S.%f")
                timestamp = dt.strftime("%Y-%m-%d %H:%M:%S")
            except:
                timestamp = timestamp_str

            record = {
                'provider': provider,
                'latitude': 'Request',
                'longitude': 'Request',
                'accuracy': 'N/A',
                'time': timestamp,
                'context': context,
                'raw': line[:100] + "..."
            }
            location_records.append(record)
            continue
            
        # Handle "passive" or "gps" headers if they appear as sections (simplified)
        if line.endswith(':'):
            current_provider = line.strip(':')

    return location_records
