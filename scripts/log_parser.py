from datetime import datetime, timedelta
import os

def filter_logs(input_file, keyword="", time_range=None, output_file="logs/filtered_logs.txt"):
    try:
        # Ensure that the directory for the output file exists
        os.makedirs(os.path.dirname(output_file), exist_ok=True)
        # Check if the input file exists; if not, create an empty file to avoid errors.
        if not os.path.exists(input_file):
            with open(input_file, "w", encoding="utf-8") as f_temp:
                f_temp.write("")
        with open(input_file, "r", encoding="utf-8") as f:
            lines = f.readlines()

        if time_range:
            now = datetime.now()
            if "1 Hour" in time_range:
                since = now - timedelta(hours=1)
            elif "24 Hours" in time_range:
                since = now - timedelta(days=1)
            elif "7 Days" in time_range:
                since = now - timedelta(days=7)
            else:
                since = None
        else:
            since = None

        filtered = []
        for line in lines:
            # Filter by keyword first
            if keyword.lower() in line.lower():
                if time_range and since:
                    # Assume the timestamp is located at the beginning of the line in "YYYY-MM-DD HH:MM:SS" format.
                    if line[:19].count("-") >= 2 and ":" in line:
                        try:
                            ts = datetime.strptime(line[:19], "%Y-%m-%d %H:%M:%S")
                            if ts >= since:
                                filtered.append(line)
                        except Exception:
                            pass
                    else:
                        filtered.append(line)
                else:
                    filtered.append(line)
        
        with open(output_file, "w", encoding="utf-8") as f:
            f.writelines(filtered)
    except Exception as e:
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(f"❌ Error filtering logs: {str(e)}")
