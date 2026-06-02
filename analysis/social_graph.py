
import os
import json
import re

def generate_social_graph(logs_dir="logs", output_file="logs/social_graph.json"):
    nodes = {}
    edges = {}
    
    # Helper to normalize phone numbers
    def normalize_phone(phone):
        """Normalize phone number to consistent format: +91XXXXXXXXXX"""
        # Remove spaces, dashes, parentheses
        cleaned = re.sub(r'[\s\-\(\)]', '', phone)
        
        # Remove leading zeros
        cleaned = cleaned.lstrip('0')
        
        # If it starts with +91, keep it
        if cleaned.startswith('+91'):
            return cleaned
        
        # If it starts with 91 (without +), add +
        if cleaned.startswith('91') and len(cleaned) >= 12:
            return '+' + cleaned
        
        # If it's a 10-digit number, add +91
        if len(cleaned) == 10 and cleaned.isdigit():
            return '+91' + cleaned
        
        # If it starts with + but not +91, keep as is (international)
        if cleaned.startswith('+'):
            return cleaned
        
        # Otherwise, return as is (might be short code)
        return cleaned
    
    # Helper to check if it's a valid phone number (not a short code)
    def is_valid_phone(phone):
        """Check if it's a real phone number, not a short code"""
        normalized = normalize_phone(phone)
        
        # Must start with + and have at least 11 digits total
        if not normalized.startswith('+'):
            # If no +, check if it's at least 10 digits
            if len(normalized) < 10:
                return False
        else:
            # With +, should be at least 12 characters (+91XXXXXXXXXX)
            if len(normalized) < 12:
                return False
        
        # Filter out known short codes (5-6 digit numbers)
        if len(normalized) <= 8 and normalized.isdigit():
            return False
        
        return True
    
    # Load contact names from contacts.json if available
    contact_names = {}
    contacts_path = os.path.join(logs_dir, "contacts.json")
    if os.path.exists(contacts_path):
        try:
            with open(contacts_path, "r", encoding="utf-8") as f:
                contacts_data = json.load(f)
                # Build a mapping of phone numbers to names
                for contact in contacts_data:
                    if "phones" in contact and "name" in contact:
                        for phone in contact["phones"]:
                            # Normalize and store
                            normalized = normalize_phone(phone)
                            contact_names[normalized] = contact["name"]
            print(f"Loaded {len(contact_names)} contact name mappings")
        except Exception as e:
            print(f"Warning: Could not load contacts: {e}")

    # Helper to get contact name
    def get_contact_name(phone):
        """Get contact name for a normalized phone number"""
        normalized = normalize_phone(phone)
        return contact_names.get(normalized, None)

    # Helper to add node
    def add_node(phone, label=None):
        # Normalize the phone number first
        normalized_phone = normalize_phone(phone)
        
        if normalized_phone not in nodes:
            # Try to get contact name if label not provided
            if not label or label == phone or label == normalized_phone:
                contact_name = get_contact_name(normalized_phone)
                if contact_name:
                    label = contact_name
            nodes[normalized_phone] = {"id": normalized_phone, "label": label or normalized_phone, "value": 1, "group": "contact"}
        else:
            nodes[normalized_phone]["value"] += 1 # Size based on interaction count
            # Update label if we found a better name
            if label and label != phone and label != normalized_phone:
                if nodes[normalized_phone]["label"] == normalized_phone or nodes[normalized_phone]["label"] == phone:
                    nodes[normalized_phone]["label"] = label

    # Helper to add edge
    def add_edge(source, target, type):
        # Normalize both source and target
        norm_source = normalize_phone(source) if source != "DEVICE" else source
        norm_target = normalize_phone(target) if target != "DEVICE" else target
        
        key = tuple(sorted((norm_source, norm_target)))
        if key not in edges:
            edges[key] = {"from": norm_source, "to": norm_target, "value": 1, "title": f"1 {type}"}
        else:
            edges[key]["value"] += 1
            count = edges[key]["value"]
            edges[key]["title"] = f"{count} {type}{'s' if count > 1 else ''}"

    # 1. Process SMS
    sms_path = os.path.join(logs_dir, "sms_logs.txt")
    if os.path.exists(sms_path):
        print(f"Processing SMS from {sms_path}")
        with open(sms_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                # Only process lines that start with "Row:"
                if not line.strip().startswith("Row:"):
                    continue
                    
                # Format: Row: N _id=..., address=SENDER, person=CONTACT_ID, ...
                # Extract address field which contains the sender/recipient
                addr_match = re.search(r'\baddress=([^,]+)', line)
                if addr_match:
                    contact = addr_match.group(1).strip()
                    
                    # Filter: keep only valid phone numbers
                    if is_valid_phone(contact):
                        add_node("DEVICE", "This Device")
                        # add_node will automatically look up the contact name
                        add_node(contact)
                        add_edge("DEVICE", contact, "SMS")

    # 2. Process Calls
    call_path = os.path.join(logs_dir, "call_logs.txt")
    if os.path.exists(call_path):
        print(f"Processing Calls from {call_path}")
        with open(call_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                # Only process lines that start with "Row:"
                if not line.strip().startswith("Row:"):
                    continue
                    
                # Format: Row: N _id=..., number=PHONE, name=NAME, ...
                # Extract number field
                number_match = re.search(r'\bnumber=([^,]+)', line)
                if number_match:
                    contact = number_match.group(1).strip()
                    # Get name if available from call log
                    name_match = re.search(r'\bname=([^,]+)', line)
                    label = None
                    if name_match and name_match.group(1) != "NULL":
                        label = name_match.group(1).strip()
                    
                    # Filter: keep only valid phone numbers
                    if is_valid_phone(contact):
                        add_node("DEVICE", "This Device")
                        add_node(contact, label)
                        add_edge("DEVICE", contact, "Call")


    # -------------------------------------------------------------------------
    # FORENSIC GRAPH ALGORITHMS (Custom Implementation without NetworkX)
    # -------------------------------------------------------------------------
    class GraphAnalyzer:
        def __init__(self, nodes, edges):
            self.nodes = nodes # Dict: id -> node_obj
            self.edges = edges # Dict: (u,v) -> edge_obj
            # Build adjacency list
            self.adj = {n: set() for n in nodes}
            for (u, v) in edges:
                self.adj[u].add(v)
                self.adj[v].add(u)

        def calculate_turnover_ratio(self):
            """
            Burner Phone Detection:
            Detects nodes with high frequency but low duration, or unidirectional spam.
            Returns dict: node_id -> is_suspect (bool)
            """
            suspects = {}
            for node_id, node in self.nodes.items():
                if node_id == "DEVICE": continue
                
                # Heuristic 1: High volume, low duration (Simulated for now as duration is in edge attrs but not aggregated well yet)
                # In real extraction, we'd sum durations. 
                # For this demo, we verify 'duration' extracted in process loop.
                # Assuming 'duration' added to node stats in main loop.
                
                total_duration = node.get("total_duration", 0)
                interaction_count = node.get("value", 0)
                
                # Avg duration < 10s and > 5 calls = Suspicious
                if interaction_count > 5 and (total_duration / interaction_count) < 10:
                    suspects[node_id] = True
                else:
                    suspects[node_id] = False
            return suspects

        def calculate_centrality(self):
            """
            Simplified Betweenness Centrality (Kingpin Detection).
            For small graphs, we can use Brandes algorithm or a simplified 'Bridge Score'.
            Here we use Degree Centrality normalized by total nodes as a proxy for Demo,
            plus a 'Bridge Bonus' if node connects to many otherwise unconnected nodes.
            """
            scores = {}
            for node_id in self.nodes:
                neighbors = self.adj[node_id]
                degree = len(neighbors)
                
                # Bridge Score: How many pairs of my neighbors are NOT connected to each other?
                # The more disconnected my neighbors are, the more I am a bridge.
                neighbor_list = list(neighbors)
                possible_pairs = 0
                connected_pairs = 0
                
                if degree > 1:
                    for i in range(len(neighbor_list)):
                        for j in range(i + 1, len(neighbor_list)):
                            possible_pairs += 1
                            u, v = neighbor_list[i], neighbor_list[j]
                            if v in self.adj[u]:
                                connected_pairs += 1
                    
                    # Local Clustering Coefficient = connected / possible
                    # We want the INVERSE (1 - LCC) for "Bridgeness"
                    if possible_pairs > 0:
                        lcc = connected_pairs / possible_pairs
                        bridgeness = 1.0 - lcc
                    else:
                        bridgeness = 0
                else:
                    bridgeness = 0
                
                # Final Score = Degree * (1 + Bridgeness)
                # Kingpins connect disparate groups.
                scores[node_id] = degree * (1 + bridgeness)
                
            # Normalize 0-1
            max_score = max(scores.values()) if scores and max(scores.values()) > 0 else 1
            return {k: v / max_score for k, v in scores.items()}

        def detect_communities(self):
            """
            Simple Label Propagation for Community Detection (Crime Ring Clustering).
            """
            # Initialize each node with unique label
            labels = {n: i for i, n in enumerate(self.nodes)}
            
            # Propagate labels
            changed = True
            iterations = 0
            while changed and iterations < 20:
                changed = False
                iterations += 1
                keys = list(self.nodes.keys())
                for node in keys:
                    if not self.adj[node]: continue
                    
                    # Count neighbor labels
                    neighbor_labels = {}
                    for neighbor in self.adj[node]:
                        lbl = labels[neighbor]
                        neighbor_labels[lbl] = neighbor_labels.get(lbl, 0) + 1
                    
                    # Find max frequency label
                    if not neighbor_labels: continue
                    dominant_label = max(neighbor_labels, key=neighbor_labels.get)
                    
                    if labels[node] != dominant_label:
                        labels[node] = dominant_label
                        changed = True
            
            # Remap arbitrary label IDs to 1..N group IDs
            unique_labels = sorted(list(set(labels.values())))
            label_map = {old: new for new, old in enumerate(unique_labels)}
            return {n: label_map[l] for n, l in labels.items()}

    # 3. Analyze Graph
    print("Performing forensic graph analysis...")
    analyzer = GraphAnalyzer(nodes, edges)
    
    centrality_scores = analyzer.calculate_centrality()
    communities = analyzer.detect_communities()
    suspects = analyzer.calculate_turnover_ratio() # 'Burner' flag (placeholder logic based on duration)

    # 4. Enrich Node Data
    final_nodes = []
    for node_id, node in nodes.items():
        # Add Centrality (Kingpin Score)
        node["centrality"] = centrality_scores.get(node_id, 0)
        
        # Add Community ID (Group Color)
        node["community"] = communities.get(node_id, 0)
        
        # Add Burner Flag
        node["is_burner"] = suspects.get(node_id, False)
        
        final_nodes.append(node)

    # Prepare export format for Vis.js
    graph_data = {
        "nodes": final_nodes,
        "edges": list(edges.values()),
        "meta": {
            "communities_count": len(set(communities.values())),
            "burner_count": sum(suspects.values())
        }
    }

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(graph_data, f, indent=4)
    
    print(f"Generated enhanced social graph: {len(nodes)} nodes, {len(edges)} edges")
    print(f"   - Detected Communities: {len(set(communities.values()))}")
    print(f"   - Potential Burner Phones: {sum(suspects.values())}")

if __name__ == "__main__":
    generate_social_graph()
