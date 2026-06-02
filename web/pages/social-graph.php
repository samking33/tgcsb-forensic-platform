<?php
$pageTitle = 'Social Link Graph - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$graphData = ["nodes" => [], "edges" => []];
$graphFile = $logsPath . '/social_graph.json';
if (file_exists($graphFile)) {
    $graphData = json_decode(file_get_contents($graphFile), true);
}
?>

<!-- Vis.js CSS -->
<!-- Vis.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.2/dist/vis-network.min.css" />

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-project-diagram me-2 text-primary"></i>Social Link Analysis</h3>
            <p class="text-muted small">Visualizing interaction patterns and frequency between the device and contacts
            </p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <?php if (!empty($graphData['nodes'])): ?>
                <!-- Filter Controls -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-center">
                            <!-- Interaction Type Filter -->
                            <div class="col-auto">
                                <label class="form-label small fw-bold mb-1">Interaction Type:</label>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="filterType" id="filterBoth" value="both"
                                        checked>
                                    <label class="btn btn-outline-primary" for="filterBoth">Both</label>

                                    <input type="radio" class="btn-check" name="filterType" id="filterSMS" value="sms">
                                    <label class="btn btn-outline-success" for="filterSMS">SMS</label>

                                    <input type="radio" class="btn-check" name="filterType" id="filterCalls" value="call">
                                    <label class="btn btn-outline-info" for="filterCalls">Calls</label>
                                </div>
                            </div>

                            <!-- Frequency Filter -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Min Interactions: <span
                                        id="minInteractionsValue">1</span></label>
                                <input type="range" class="form-range" id="minInteractionsSlider" min="1" max="50"
                                    value="1">
                            </div>

                            <!-- Search -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Search Contact:</label>
                                <input type="text" class="form-control form-control-sm" id="contactSearch"
                                    placeholder="Phone number or name...">
                            </div>

                            <!-- Reset Button -->
                            <div class="col-auto">
                                <label class="form-label small mb-1">&nbsp;</label>
                                <button class="btn btn-sm btn-outline-secondary d-block" id="resetFilters">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>

                            <!-- Export Button -->
                            <div class="col-auto ms-auto">
                                <label class="form-label small mb-1">&nbsp;</label>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-dark" id="exportJSON">
                                        <i class="fas fa-download"></i> JSON
                                    </button>
                                    <button class="btn btn-outline-dark" id="exportCSV">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Panel -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body p-2">
                        <div class="row g-2 text-center small">
                            <div class="col">
                                <div class="text-muted">Total Contacts</div>
                                <div class="fw-bold fs-5" id="statTotalContacts">0</div>
                            </div>
                            <div class="col">
                                <div class="text-muted">Visible Contacts</div>
                                <div class="fw-bold fs-5 text-primary" id="statVisibleContacts">0</div>
                            </div>
                            <div class="col">
                                <div class="text-muted">Total Interactions</div>
                                <div class="fw-bold fs-5" id="statTotalInteractions">0</div>
                            </div>
                            <div class="col">
                                <div class="text-muted">SMS</div>
                                <div class="fw-bold fs-5 text-success" id="statSMS">0</div>
                            </div>
                            <div class="col">
                                <div class="text-muted">Calls</div>
                                <div class="fw-bold fs-5 text-info" id="statCalls">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Graph Card -->
            <div class="card shadow-lg border-0">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">Interaction Network Graph</h5>
                    <div class="card-tools">
                        <button class="btn btn-sm btn-outline-primary" onclick="fitGraph()">
                            <i class="fas fa-compress-arrows-alt"></i> Fit View
                        </button>
                    </div>
                </div>
                <div class="card-body p-0 position-relative" style="height: 700px;">
                    <?php if (empty($graphData['nodes'])): ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100">
                            <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                            <h5>No Interaction Data</h5>
                            <p class="text-muted">No calls or SMS logs available to build the graph.</p>
                        </div>
                    <?php else: ?>
                        <div id="socialNetwork" style="width: 100%; height: 100%;"></div>

                        <!-- Legend -->
                        <div class="position-absolute top-0 end-0 p-3 bg-white shadow-sm m-3 rounded"
                            style="opacity: 0.9; z-index: 10;">
                            <small class="fw-bold d-block mb-2">Legend</small>
                            <div class="d-flex align-items-center mb-1">
                                <span class="d-inline-block rounded-circle bg-danger me-2"
                                    style="width: 10px; height: 10px;"></span>
                                <span class="small">Target Device</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="d-inline-block rounded-circle bg-primary me-2"
                                    style="width: 10px; height: 10px;"></span>
                                <span class="small">Contact</span>
                            </div>
                        </div>

                        <!-- Node Detail Panel -->
                        <div id="nodeDetailPanel"
                            class="position-absolute top-0 start-0 h-100 bg-white border-end shadow-lg p-3"
                            style="width: 350px; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 20; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">Contact Details</h6>
                                <button class="btn btn-sm btn-link text-muted p-0" id="closeDetailPanel">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div id="nodeDetailContent">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-hand-pointer fa-2x mb-2"></i>
                                    <p class="small">Click on a node to view details</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Vis.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.2/dist/vis-network.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rawData = <?= json_encode($graphData) ?>;

        if (typeof vis === 'undefined') {
            console.error('Vis.js library not loaded!');
            document.getElementById('socialNetwork').innerHTML = '<div class="alert alert-danger m-3">Error: Visualization library failed to load. Please check your internet connection.</div>';
            return;
        }

        if (rawData.nodes.length > 0) {
            try {
                // Store original data
                // Community Colors (Palette for crime rings)
                const communityColors = [
                    '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6', '#f43f5e'
                ];

                // Process nodes with forensic metrics
                const allNodesData = rawData.nodes.map(n => {
                    // 1. Kingpin Sizing (Centrality)
                    const sizeMetric = n.centrality ? (n.centrality * 40) + 15 : (Math.min(n.value, 30) + 10);

                    // 2. Crime Ring Coloring (Community)
                    let nodeColor = '#3b82f6';
                    if (n.id === 'DEVICE') {
                        nodeColor = '#ef4444'; // Device always red
                    } else if (n.community !== undefined) {
                        nodeColor = communityColors[n.community % communityColors.length];
                    }

                    // 3. Burner Phone Flagging
                    const shapeProperties = n.is_burner ? { borderDashes: [5, 5] } : {};
                    const borderWidth = n.is_burner ? 4 : 2;
                    const borderColor = n.is_burner ? '#ffffff' : '#222222'; // White border for burner visibility on dark

                    return {
                        id: n.id,
                        label: n.id === 'DEVICE' ? 'TARGET' : n.label,
                        value: sizeMetric, // Vis.js uses 'value' for scaling
                        title: `ID: ${n.id}\nCentrality: ${n.centrality ? n.centrality.toFixed(2) : 'N/A'}\nCommunity: ${n.community}`,
                        color: {
                            background: nodeColor,
                            border: borderColor,
                            highlight: { border: '#22d3ee', background: nodeColor }
                        },
                        borderWidth: borderWidth,
                        shapeProperties: shapeProperties,
                        originalData: n
                    };
                });

                const allEdgesData = rawData.edges.map(e => ({
                    from: e.from,
                    to: e.to,
                    width: Math.min(e.value, 10),
                    title: e.title,
                    value: e.value,
                    color: { color: '#94a3b8', highlight: '#22d3ee' },
                    originalData: e
                }));

                // Create DataSets
                const nodes = new vis.DataSet(allNodesData);
                const edges = new vis.DataSet(allEdgesData);

                const container = document.getElementById('socialNetwork');
                const data = { nodes: nodes, edges: edges };
                const options = {
                    nodes: {
                        scaling: {
                            min: 15,
                            max: 50,
                            customScalingFunction: function (min, max, total, value) {
                                return value;
                            },
                        },
                        font: { size: 14, color: '#ffffff', strokeWidth: 2, strokeColor: '#000000' }
                    },
                    physics: {
                        stabilization: false,
                        barnesHut: {
                            gravitationalConstant: -10000,
                            springConstant: 0.04,
                            springLength: 120
                        }
                    },
                    interaction: {
                        hover: true,
                        tooltipDelay: 200
                    }
                };

                // Initialize Network
                window.network = new vis.Network(container, data, options);

                // Filter State
                const filterState = {
                    type: 'both',
                    minInteractions: 1,
                    searchQuery: ''
                };

                // Apply Filters Function
                function applyFilters() {
                    // 1. Filter edges by type
                    let filteredEdges = allEdgesData.filter(edge => {
                        if (filterState.type === 'both') return true;
                        return edge.title.toLowerCase().includes(filterState.type);
                    });

                    // Get nodes that have edges
                    const connectedNodeIds = new Set();
                    connectedNodeIds.add('DEVICE'); // Always show device
                    filteredEdges.forEach(edge => {
                        connectedNodeIds.add(edge.from);
                        connectedNodeIds.add(edge.to);
                    });

                    // 2. Filter nodes by frequency
                    let filteredNodes = allNodesData.filter(node => {
                        if (node.id === 'DEVICE') return true;
                        if (!connectedNodeIds.has(node.id)) return false;
                        return node.value >= filterState.minInteractions;
                    });

                    // 3. Apply search filter
                    if (filterState.searchQuery) {
                        const query = filterState.searchQuery.toLowerCase();
                        filteredNodes = filteredNodes.filter(node => {
                            if (node.id === 'DEVICE') return true;
                            return node.label.toLowerCase().includes(query) ||
                                node.id.toLowerCase().includes(query);
                        });
                    }

                    // Remove edges that don't have both nodes
                    const filteredNodeIds = new Set(filteredNodes.map(n => n.id));
                    filteredEdges = filteredEdges.filter(edge =>
                        filteredNodeIds.has(edge.from) && filteredNodeIds.has(edge.to)
                    );

                    // Update network
                    nodes.clear();
                    edges.clear();
                    nodes.add(filteredNodes);
                    edges.add(filteredEdges);

                    // Update statistics
                    updateStatistics(filteredNodes, filteredEdges);
                }

                // Update Statistics
                function updateStatistics(filteredNodes, filteredEdges) {
                    const totalContacts = allNodesData.length - 1; // Exclude DEVICE
                    const visibleContacts = filteredNodes.length - 1; // Exclude DEVICE

                    let totalInteractions = 0;
                    let smsCount = 0;
                    let callCount = 0;

                    filteredEdges.forEach(edge => {
                        totalInteractions += edge.value;
                        if (edge.title.toLowerCase().includes('sms')) {
                            smsCount += edge.value;
                        } else if (edge.title.toLowerCase().includes('call')) {
                            callCount += edge.value;
                        }
                    });

                    document.getElementById('statTotalContacts').textContent = totalContacts;
                    document.getElementById('statVisibleContacts').textContent = visibleContacts;
                    document.getElementById('statTotalInteractions').textContent = totalInteractions;
                    document.getElementById('statSMS').textContent = smsCount;
                    document.getElementById('statCalls').textContent = callCount;
                }

                // Event Listeners
                document.querySelectorAll('input[name="filterType"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        filterState.type = e.target.value;
                        applyFilters();
                    });
                });

                document.getElementById('minInteractionsSlider').addEventListener('input', (e) => {
                    filterState.minInteractions = parseInt(e.target.value);
                    document.getElementById('minInteractionsValue').textContent = e.target.value;
                    applyFilters();
                });

                document.getElementById('contactSearch').addEventListener('input', (e) => {
                    filterState.searchQuery = e.target.value;
                    applyFilters();
                });

                document.getElementById('resetFilters').addEventListener('click', () => {
                    filterState.type = 'both';
                    filterState.minInteractions = 1;
                    filterState.searchQuery = '';

                    document.getElementById('filterBoth').checked = true;
                    document.getElementById('minInteractionsSlider').value = 1;
                    document.getElementById('minInteractionsValue').textContent = '1';
                    document.getElementById('contactSearch').value = '';

                    applyFilters();
                });

                // Export Functions
                document.getElementById('exportJSON').addEventListener('click', () => {
                    const exportData = {
                        nodes: nodes.get(),
                        edges: edges.get(),
                        filters: filterState,
                        timestamp: new Date().toISOString()
                    };
                    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `social_graph_${Date.now()}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                });

                document.getElementById('exportCSV').addEventListener('click', () => {
                    const currentEdges = edges.get();
                    let csv = 'From,To,Interactions,Type\n';
                    currentEdges.forEach(edge => {
                        const fromNode = nodes.get(edge.from);
                        const toNode = nodes.get(edge.to);
                        const type = edge.title.includes('SMS') ? 'SMS' : 'Call';
                        csv += `"${fromNode.label}", "${toNode.label}", ${edge.value}, "${type}"\n`;
                    });

                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `social_graph_${Date.now()}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                });

                // Initialize statistics
                applyFilters();

                // Node Click Event - Show Detail Panel
                window.network.on('click', function (params) {
                    if (params.nodes.length > 0) {
                        const nodeId = params.nodes[0];
                        const node = nodes.get(nodeId);
                        showNodeDetails(node, nodeId);
                    }
                });

                // Close Detail Panel
                document.getElementById('closeDetailPanel').addEventListener('click', () => {
                    document.getElementById('nodeDetailPanel').style.transform = 'translateX(-100%)';
                });

                // Show Node Details Function
                function showNodeDetails(node, nodeId) {
                    const panel = document.getElementById('nodeDetailPanel');
                    const content = document.getElementById('nodeDetailContent');

                    // Get all edges connected to this node
                    const connectedEdges = edges.get({
                        filter: edge => edge.from === nodeId || edge.to === nodeId
                    });

                    // Calculate statistics
                    let smsCount = 0;
                    let callCount = 0;
                    let totalInteractions = 0;

                    connectedEdges.forEach(edge => {
                        totalInteractions += edge.value;
                        if (edge.title.toLowerCase().includes('sms')) {
                            smsCount += edge.value;
                        } else if (edge.title.toLowerCase().includes('call')) {
                            callCount += edge.value;
                        }
                    });

                    // Build detail HTML
                    let html = '';

                    // Forensic Insights Logic
                    let insightsHtml = '';
                    const rawNode = node.originalData || {};

                    if (rawNode.is_burner) {
                        insightsHtml += `<div class="alert alert-danger small mb-2"><i class="fas fa-fire me-2"></i><strong>Burner Phone Suspect:</strong><br>High frequency, short duration calls detected.</div>`;
                    }
                    if (rawNode.centrality && rawNode.centrality > 0.0) {
                        insightsHtml += `<div class="alert alert-warning small mb-2"><i class="fas fa-crown me-2"></i><strong>High Centrality (${rawNode.centrality.toFixed(2)}):</strong><br>Acts as a bridge/broker between different groups.</div>`;
                    }
                    if (rawNode.community !== undefined) {
                        insightsHtml += `<div class="alert alert-primary small mb-2"><i class="fas fa-users me-2"></i><strong>Community #${rawNode.community}:</strong><br>Member of a distinct communication cluster.</div>`;
                    }

                    if (nodeId === 'DEVICE') {
                        html = `
                        <div class="text-center mb-3">
                                <div class="bg-danger rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-mobile-alt fa-2x text-white"></i>
                                </div>
                                <h5 class="mt-2 mb-0">${node.label}</h5>
                            </div>
                    <div class="border-top pt-3">
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="small text-muted">Contacts</div>
                                <div class="fw-bold fs-5">${connectedEdges.length}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">SMS</div>
                                <div class="fw-bold fs-5 text-success">${smsCount}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Calls</div>
                                <div class="fw-bold fs-5 text-info">${callCount}</div>
                            </div>
                        </div>
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            This is the target device being analyzed.
                        </div>
                    </div>
                `;
                    } else {
                        html = `
                    <div class="text-center mb-3">
                                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                                <h5 class="mt-2 mb-1">${node.label}</h5>
                                <p class="text-muted small mb-0">${nodeId}</p>
                            </div>

                    <div class="border-top pt-3">
                        ${insightsHtml ? `<h6 class="fw-bold mb-2">Forensic Inisghts</h6>${insightsHtml}<hr>` : ''}
                        
                        <h6 class="fw-bold mb-2">Interaction Summary</h6>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="small text-muted">Total</div>
                                <div class="fw-bold fs-5">${totalInteractions}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">SMS</div>
                                <div class="fw-bold fs-5 text-success">${smsCount}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Calls</div>
                                <div class="fw-bold fs-5 text-info">${callCount}</div>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2 mt-3">Connection Details</h6>
                        <div class="list-group list-group-flush">
                            ${connectedEdges.map(edge => {
                            const type = edge.title.toLowerCase().includes('sms') ? 'SMS' : 'Call';
                            const icon = type === 'SMS' ? 'fa-comment' : 'fa-phone';
                            const color = type === 'SMS' ? 'success' : 'info';
                            return `
                                            <div class="list-group-item px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="fas ${icon} text-${color} me-2"></i>
                                                        <span class="small">${type}</span>
                                                    </div>
                                                    <span class="badge bg-${color}">${edge.value}</span>
                                                </div>
                                            </div>
                                        `;
                        }).join('')}
                        </div>

                        <div class="mt-3 d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="focusOnNode('${nodeId}')">
                                <i class="fas fa-crosshairs me-1"></i> Focus on Node
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="highlightConnections('${nodeId}')">
                                <i class="fas fa-project-diagram me-1"></i> Highlight Connections
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="resetHighlight()">
                                <i class="fas fa-eye me-1"></i> Clear Highlight
                            </button>
                        </div>
                    </div>
                        `;
                    }

                    content.innerHTML = html;
                    panel.style.transform = 'translateX(0)';
                }

                // Focus on Node with Dimming Effect
                window.focusOnNode = function (nodeId) {
                    const connectedEdges = edges.get({
                        filter: edge => edge.from === nodeId || edge.to === nodeId
                    });

                    const connectedNodeIds = new Set([nodeId]);
                    connectedEdges.forEach(edge => {
                        connectedNodeIds.add(edge.from);
                        connectedNodeIds.add(edge.to);
                    });

                    const allNodes = nodes.get();
                    allNodes.forEach(node => {
                        if (connectedNodeIds.has(node.id)) {
                            nodes.update({
                                id: node.id,
                                opacity: 1,
                                color: node.id === 'DEVICE' ? '#ef4444' : (node.id === nodeId ? '#10b981' : '#3b82f6')
                            });
                        } else {
                            nodes.update({
                                id: node.id,
                                opacity: 0.15,
                                color: { background: '#94a3b8', border: '#64748b' }
                            });
                        }
                    });

                    const allEdges = edges.get();
                    const connectedEdgeIds = new Set(connectedEdges.map(e => e.id));

                    allEdges.forEach(edge => {
                        if (connectedEdgeIds.has(edge.id)) {
                            edges.update({
                                id: edge.id,
                                color: { color: '#10b981', highlight: '#22d3ee' },
                                width: Math.min(edge.value, 10)
                            });
                        } else {
                            edges.update({
                                id: edge.id,
                                color: { color: 'rgba(148, 163, 184, 0.15)' },
                                width: 1
                            });
                        }
                    });

                    window.network.focus(nodeId, { scale: 1.5, animation: true });
                };

                // Highlight Connections Function
                window.highlightConnections = function (nodeId) {
                    const connectedEdges = edges.get({
                        filter: edge => edge.from === nodeId || edge.to === nodeId
                    });

                    window.network.selectNodes([nodeId]);
                    window.network.selectEdges(connectedEdges.map(e => e.id));
                };

                // Reset Highlight Function
                window.resetHighlight = function () {
                    const allNodes = nodes.get();
                    allNodes.forEach(node => {
                        nodes.update({
                            id: node.id,
                            opacity: 1,
                            color: node.id === 'DEVICE' ? '#ef4444' : '#3b82f6'
                        });
                    });

                    const allEdges = edges.get();
                    allEdges.forEach(edge => {
                        edges.update({
                            id: edge.id,
                            color: { color: '#94a3b8', highlight: '#22d3ee' },
                            width: Math.min(edge.value, 10)
                        });
                    });

                    window.network.unselectAll();
                    window.network.fit({ animation: true });
                };

            } catch (e) {
                console.error('Error initializing network:', e);
            }
        }
    });

    function fitGraph() {
        if (window.network) {
            window.network.fit({ animation: true });
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>