"""
Contact Parser - Extract and map phone numbers to contact names
"""
import re
import json
import os

def parse_contacts(contacts_file="logs/contacts.txt", output_file="logs/contacts_map.json"):
    """
    Parse the raw contacts output and create a phone number -> name mapping.
    
    The contacts data from content://com.android.contacts/data contains:
    - display_name
    - data1 (phone number or email)
    - mimetype (what type of data)
    """
    
    if not os.path.exists(contacts_file):
        print("⚠️ No contacts file found")
        return {}
    
    with open(contacts_file, 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()
    
    contacts_map = {}
    
    # Parse the content query output format
    # Format: Row: 0 _id=1, display_name=John Doe, data1=1234567890, mimetype=vnd.android.cursor.item/phone_v2
    
    lines = content.split('\n')
    
    for line in lines:
        if 'Row:' not in line:
            continue
        
        # Extract display_name
        name_match = re.search(r'display_name=([^,]+)', line)
        # Extract phone number (data1 when mimetype is phone)
        data_match = re.search(r'data1=([^,]+)', line)
        # Check if it's a phone number entry
        is_phone = 'phone' in line.lower()
        
        if name_match and data_match and is_phone:
            name = name_match.group(1).strip()
            phone = data_match.group(1).strip()
            
            # Clean up phone number (remove spaces, dashes, etc.)
            phone_clean = re.sub(r'[^\d+]', '', phone)
            
            # Skip if name is NULL or empty
            if name and name.upper() != 'NULL' and phone_clean:
                # Store multiple formats for better matching
                contacts_map[phone] = name
                contacts_map[phone_clean] = name
                
                # Also store without country code if present
                if phone_clean.startswith('+'):
                    phone_without_plus = phone_clean[1:]
                    contacts_map[phone_without_plus] = name
                
                # Store last 10 digits for matching
                if len(phone_clean) >= 10:
                    last_10 = phone_clean[-10:]
                    contacts_map[last_10] = name
    
    # Save to JSON for web interface
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(contacts_map, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Parsed {len(set(contacts_map.values()))} unique contacts")
    return contacts_map

if __name__ == "__main__":
    parse_contacts()
