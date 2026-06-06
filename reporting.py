import os
import re
import hashlib
from datetime import datetime
from collections import Counter
from jinja2 import Environment, FileSystemLoader, select_autoescape
import sys

# Fix Windows encoding issues with emoji characters
if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except AttributeError:
        import io
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

# Make weasyprint optional (requires GTK on Windows which can be difficult to install)
try:
    from weasyprint import HTML
    WEASYPRINT_AVAILABLE = True
except (ImportError, OSError) as e:
    WEASYPRINT_AVAILABLE = False
    print(f"⚠️  WeasyPrint not available: {e}")
    print("   PDF export will be disabled. HTML export will still work.")

def _load_lines(path):
    if not os.path.exists(path):
        return []
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as f:
            return f.readlines()
    except Exception:
        return []



def _parse_system_properties():
    """Parses the system_properties.txt file into a dictionary."""
    props = {}
    path = "logs/system_properties.txt"
    if not os.path.exists(path):
        return props
    
    try:
        with open(path, 'r', encoding='utf-8', errors='replace') as f:
            for line in f:
                # Format: [key]: [value]
                # Regex matches [anything]: [anything]
                match = re.match(r'^\[(.*?)]: \[(.*?)\]$', line.strip())
                if match:
                    props[match.group(1)] = match.group(2)
    except Exception as e:
        print(f"Warning: Error parsing system properties: {e}")
        
    return props

def _get_kernel_version_safe():
    """Attempts to get kernel version from first 200 lines of logcat without loading full file."""
    path = "logs/android_logcat.txt"
    if not os.path.exists(path):
        return "Unknown"
        
    try:
        with open(path, 'r', encoding='utf-8', errors='replace') as f:
            for i, line in enumerate(f):
                if i > 200: # detailed kernel info usually at top
                    break
                # Linux version 4.19.157-perf+ (build_user@build_host) ...
                if "Linux version" in line:
                    match = re.search(r'Linux\s+version\s+([^\s]+)', line)
                    if match:
                        return match.group(1)
    except Exception:
        pass
    return "Unknown"

def _summarize_calls():
    lines = _load_lines("logs/call_logs.txt")
    # Only count lines that start with "Row:"
    call_lines = [line for line in lines if line.strip().startswith("Row:")]
    
    incoming = sum(1 for line in call_lines if re.search(r'\btype=1\b', line))
    outgoing = sum(1 for line in call_lines if re.search(r'\btype=2\b', line))
    missed = sum(1 for line in call_lines if re.search(r'\btype=3\b', line))
    
    # Extract number and name pairs
    number_name_pairs = []
    for line in call_lines:
        # Extract number field
        number_match = re.search(r'\bnumber=([+\d]+)', line)
        # Extract name field
        name_match = re.search(r'\bname=([^,]+)', line)
        
        if number_match:
            number = number_match.group(1)
            name = name_match.group(1).strip() if name_match else "NULL"
            # Clean up name - remove empty strings or NULL values
            if not name or name == "NULL" or name == "":
                name = None
            number_name_pairs.append((number, name))
    
    # Count occurrences and keep track of names
    number_counts = {}
    for number, name in number_name_pairs:
        if number not in number_counts:
            number_counts[number] = {"count": 0, "name": name}
        number_counts[number]["count"] += 1
        # Update name if we find a non-null one
        if name and not number_counts[number]["name"]:
            number_counts[number]["name"] = name
    
    # Sort by count and get top 5
    top_callers = sorted(number_counts.items(), key=lambda x: x[1]["count"], reverse=True)[:5]
    # Format as list of tuples: (number, count, name)
    top_callers_formatted = [(num, data["count"], data["name"] or "NULL") for num, data in top_callers]
    
    return {
        "total": len(call_lines),
        "incoming": incoming,
        "outgoing": outgoing,
        "missed": missed,
        "top_callers": top_callers_formatted,
    }

def _summarize_sms():
    lines = _load_lines("logs/sms_logs.txt")
    # Only count lines that start with "Row:"
    sms_lines = [line for line in lines if line.strip().startswith("Row:")]
    
    # SMS types: 1=received, 2=sent
    incoming = sum(1 for line in sms_lines if re.search(r'\btype=1\b', line))
    outgoing = sum(1 for line in sms_lines if re.search(r'\btype=2\b', line))
    
    # Extract address (phone number) and person (contact name) pairs
    address_name_pairs = []
    for line in sms_lines:
        # Extract address field (phone number)
        address_match = re.search(r'\baddress=([+\d]+)', line)
        # Extract person field (contact ID or name - we'll try to get the name)
        # First try to get contact name from the line
        name_match = re.search(r'\bperson=([^,]+)', line)
        
        if address_match:
            address = address_match.group(1)
            # Person field is usually a contact ID, but let's check if there's a readable name
            name = name_match.group(1).strip() if name_match else None
            # Clean up name - if it's a number (contact ID) or NULL, set to None
            if name and (name.isdigit() or name == "NULL" or name == "null" or not name):
                name = None
            address_name_pairs.append((address, name))
    
    # Count occurrences and keep track of names
    address_counts = {}
    for address, name in address_name_pairs:
        if address not in address_counts:
            address_counts[address] = {"count": 0, "name": name}
        address_counts[address]["count"] += 1
        # Update name if we find a non-null one
        if name and not address_counts[address]["name"]:
            address_counts[address]["name"] = name
    
    # Sort by count and get top 5
    top_senders = sorted(address_counts.items(), key=lambda x: x[1]["count"], reverse=True)[:5]
    # Format as list of tuples: (address, count, name)
    top_senders_formatted = [(addr, data["count"], data["name"] or "NULL") for addr, data in top_senders]
    
    return {
        "total": len(sms_lines),
        "incoming": incoming,
        "outgoing": outgoing,
        "top_senders": top_senders_formatted,
    }

def _summarize_logcat():
    path = "logs/android_logcat.txt"
    if not os.path.exists(path):
        return {
            "total": 0,
            "recent_sample": [],
            "recent_label": "None",
        }

    total_lines = 0
    today_sample = []
    fallback_sample = []
    today_str = datetime.now().strftime("%m-%d")
    
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                total_lines += 1
                line = line.strip()
                if not line: 
                    continue
                
                # Capture first 50 lines as fallback
                if len(fallback_sample) < 50:
                    fallback_sample.append(line)
                
                # Capture first 50 lines of today
                if len(today_sample) < 50 and line.startswith(today_str):
                    today_sample.append(line)
        
    except Exception as e:
        print(f"Error reading logcat: {e}")
        return {"total": 0, "recent_sample": ["Error reading log"], "recent_label": "Error"}

    if today_sample:
        return {
            "total": total_lines,
            "recent_sample": today_sample,
            "recent_label": "Today",
        }
    else:
        return {
            "total": total_lines,
            "recent_sample": fallback_sample,
            "recent_label": "Recent (No Today Logs)",
        }

def _calculate_file_hashes():
    """
    Calculate SHA-256 hashes for all evidence files.
    Required for Section 65B Certificate (Indian Evidence Act, 1872)
    """
    evidence_files = [
        "logs/android_logcat.txt",
        "logs/call_logs.txt",
        "logs/sms_logs.txt",
        "logs/unified_timeline.json",
        "logs/app_sessions.json",
        "logs/location_logs.txt",
        "logs/installed_apps.txt",
    ]
    
    hashes = []
    for filepath in evidence_files:
        if os.path.exists(filepath):
            sha256 = hashlib.sha256()
            file_size = 0
            
            try:
                with open(filepath, 'rb') as f:
                    while True:
                        chunk = f.read(8192)  # Read in 8KB chunks
                        if not chunk:
                            break
                        sha256.update(chunk)
                        file_size += len(chunk)
                
                hashes.append({
                    "filename": os.path.basename(filepath),
                    "path": filepath,
                    "hash": sha256.hexdigest(),
                    "size": file_size,
                    "size_human": _format_file_size(file_size)
                })
            except Exception as e:
                print(f"Warning: Could not hash {filepath}: {e}")
    
    return hashes

def _format_file_size(size_bytes):
    """Convert bytes to human-readable format"""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if size_bytes < 1024.0:
            return f"{size_bytes:.2f} {unit}"
        size_bytes /= 1024.0
    return f"{size_bytes:.2f} TB"

def _collect_context():
    now = datetime.now()
    device_info = {
        "model": "Unknown",
        "android_version": "Unknown",
        "kernel": "Unknown",
    }
    
    # PRIORITY 1: Read from device_info.txt if available (most reliable)
    device_info_path = "logs/device_info.txt"
    if os.path.exists(device_info_path):
        try:
            with open(device_info_path, 'r', encoding='utf-8', errors='replace') as f:
                content = f.read()
                
                # Extract model
                model_match = re.search(r'Model:\s*(.+)', content)
                if model_match:
                    device_info["model"] = model_match.group(1).strip()
                
                # Extract Android version
                android_match = re.search(r'Android Version:\s*(.+)', content)
                if android_match:
                    device_info["android_version"] = android_match.group(1).strip()
                
                # Extract kernel
                kernel_match = re.search(r'Kernel:\s*(.+)', content)
                if kernel_match:
                    device_info["kernel"] = kernel_match.group(1).strip()
                    
                print(f"✓ Device info loaded from device_info.txt")
        except Exception as e:
            print(f"Warning: Could not parse device_info.txt: {e}")
    
    # FALLBACK: Try extracting from logcat if device_info.txt failed
    if device_info["model"] == "Unknown" or device_info["android_version"] == "Unknown":
        raw_log = "".join(_load_lines("logs/android_logcat.txt"))
        
        # Try multiple patterns for device model
        if device_info["model"] == "Unknown":
            model_patterns = [
                r'ro\.product\.model[=:]\s*([^\s,\]]+)',
                r'model=([^,\s\]]+)',
                r'Build/([A-Z0-9]+)',
                r'Device:\s*([^\s,]+)'
            ]
            for pattern in model_patterns:
                model_match = re.search(pattern, raw_log, re.IGNORECASE)
                if model_match:
                    device_info["model"] = model_match.group(1)
                    break
        
        # Try multiple patterns for Android version
        if device_info["android_version"] == "Unknown":
            version_patterns = [
                r'ro\.build\.version\.release[=:]\s*(\d+(?:\.\d+)?)',
                r'Android\s+(\d+(?:\.\d+)?)',
                r'SDK:\s*(\d+)',
                r'API\s+level\s+(\d+)'
            ]
            for pattern in version_patterns:
                version_match = re.search(pattern, raw_log, re.IGNORECASE)
                if version_match:
                    ver = version_match.group(1)
                    try:
                        # Convert SDK level to Android version if needed
                        if ver.isdigit() and int(ver) > 20:
                            sdk_to_android = {
                                '34': '14', '33': '13', '32': '12L', '31': '12',
                                '30': '11', '29': '10', '28': '9', '27': '8.1',
                                '26': '8.0', '25': '7.1', '24': '7.0'
                            }
                            device_info["android_version"] = sdk_to_android.get(ver, ver)
                        else:
                            device_info["android_version"] = ver
                        break
                    except ValueError:
                        device_info["android_version"] = ver
                        break
        
        # Try multiple patterns for kernel version
        if device_info["kernel"] == "Unknown":
            kernel_patterns = [
                r'Linux\s+version\s+([^\s]+)',
                r'Kernel:\s*([^\s,]+)',
                r'ro\.kernel\.version[=:]\s*([^\s,\]]+)'
            ]
            for pattern in kernel_patterns:
                kernel_match = re.search(pattern, raw_log, re.IGNORECASE)
                if kernel_match:
                    device_info["kernel"] = kernel_match.group(1)
                    break

    # Load Section 65B data from JSON if available
    section_65b_data = None
    section_65b_file = "logs/section_65b_data.json"
    if os.path.exists(section_65b_file):
        try:
            import json
            with open(section_65b_file, 'r', encoding='utf-8') as f:
                section_65b_data = json.load(f)
        except Exception as e:
            print(f"Warning: Could not load Section 65B data: {e}")
    
    # If Section 65B data not available, create basic version
    if not section_65b_data:
        section_65b_data = {
            "acquisition_time": now.strftime("%Y-%m-%d %H:%M:%S"),
            "acquisition_date": now.strftime("%d/%m/%Y"),
            "acquisition_time_only": now.strftime("%H:%M:%S"),
            "evidence_hashes": _calculate_file_hashes(),
            "device_identifiers": device_info,
            "examiner": "Digital Forensic Analyst",
            "case_number": "123456",
            "total_evidence_files": len(_calculate_file_hashes()),
        }
    
    # Ensure evidence_hashes exists even if loaded from JSON
    if "evidence_hashes" not in section_65b_data or not section_65b_data["evidence_hashes"]:
        print("ℹ️  Calculating evidence hashes (missing in source data)...")
        section_65b_data["evidence_hashes"] = _calculate_file_hashes()
        section_65b_data["total_evidence_files"] = len(section_65b_data["evidence_hashes"])

    return {
        "generated_at": now.strftime("%Y-%m-%d %H:%M:%S"),
        "case_number": section_65b_data.get("case_number", "123456"),
        "examiner": section_65b_data.get("examiner", "Digital Forensic Analyst"),
        "device": device_info,
        "calls": _summarize_calls(),
        "sms": _summarize_sms(),
        "logcat": _summarize_logcat(),
        "chain_of_custody": [
            "Evidence acquired from the Android device using approved ADB tools.",
            "Logs extracted include Android Logcat, Call Logs, and SMS Logs.",
            "Files verified with cryptographic hashes immediately after acquisition.",
            "Analysis steps documented to preserve chain-of-custody integrity.",
        ],
        "methodology": [
            "ADB-based acquisition of logcat, call, and SMS datasets.",
            "Filtering by time range, keyword, severity, and subtype for targeted review.",
            "Visualization of temporal activity and frequency distributions.",
            "Threat triage and report packaging for court-ready evidence.",
        ],
        # TGCSB Section 65B Certificate (Indian Evidence Act, 1872)
        "section_65b": section_65b_data
    }

def _render_html(context):
    base_dir = os.path.dirname(os.path.abspath(__file__))
    env = Environment(
        loader=FileSystemLoader(os.path.join(base_dir, "templates")),
        autoescape=select_autoescape(["html", "xml"])
    )
    template = env.get_template("report_template.html")
    return template.render(**context)

def export_full_report():
    try:
        os.makedirs("logs/exports", exist_ok=True)
        context = _collect_context()
        html = _render_html(context)
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Always save HTML first
        html_filepath = f"logs/exports/forensic_report_{timestamp}.html"
        with open(html_filepath, 'w', encoding='utf-8') as f:
            f.write(html)
        print(f"✅ HTML report saved: {html_filepath}")
        
        # Try PDF if WeasyPrint is available
        if WEASYPRINT_AVAILABLE:
            try:
                pdf_filepath = f"logs/exports/forensic_report_{timestamp}.pdf"
                HTML(string=html, base_url=os.path.abspath(".")).write_pdf(pdf_filepath)
                print(f"✅ PDF report saved: {pdf_filepath}")
                print(f"✅ PDF report saved: {pdf_filepath}")
            except Exception as pdf_error:
                print(f"⚠️ PDF generation failed: {pdf_error}")
                print(f"   HTML report is still available: {html_filepath}")
        else:
            print(f"✅ HTML report saved: {html_filepath}")
            print("   PDF export unavailable. Install WeasyPrint for PDF support.")
        
        # Open the HTML file in default browser
        import webbrowser
        webbrowser.open(os.path.abspath(html_filepath))
        
    except Exception as e:
        import traceback
        error_details = traceback.format_exc()
        print(f"❌ Report generation failed: {e}")
        print(error_details)
        raise
