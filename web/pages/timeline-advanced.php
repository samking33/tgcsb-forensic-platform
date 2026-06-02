<?php
$pageTitle = 'Forensic Transit Map - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><i class="fas fa-chart-scatter me-2 text-primary"></i>Advanced Timeline</h3>
                    <p class="text-muted small">Scatter plot visualization of device events over time</p>
                </div>
                <div class="col-sm-6 text-end">
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary active" id="view-map"><i
                                class="fas fa-map me-1"></i> Map View</button>
                        <button class="btn btn-sm btn-outline-secondary" id="view-list"
                            onclick="window.location.href='timeline.php'"><i class="fas fa-list me-1"></i> List
                            View</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filter Controls -->
            <div class="card shadow-sm mb-2">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-center">
                        <!-- Event Type Filters -->
                        <div class="col-auto">
                            <label class="form-label small fw-bold mb-1">Event Types:</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="checkbox" class="btn-check" id="toggle-sms" checked>
                                <label class="btn btn-outline-success" for="toggle-sms">SMS</label>

                                <input type="checkbox" class="btn-check" id="toggle-call" checked>
                                <label class="btn btn-outline-primary" for="toggle-call">Call</label>

                                <input type="checkbox" class="btn-check" id="toggle-app" checked>
                                <label class="btn btn-outline-info" for="toggle-app">App</label>

                                <input type="checkbox" class="btn-check" id="toggle-network" checked>
                                <label class="btn btn-outline-danger" for="toggle-network">Network</label>

                                <input type="checkbox" class="btn-check" id="toggle-system" checked>
                                <label class="btn btn-outline-warning" for="toggle-system">System</label>

                                <input type="checkbox" class="btn-check" id="toggle-voip" checked>
                                <label class="btn btn-outline-purple" for="toggle-voip">VoIP</label>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Search:</label>
                            <input type="text" class="form-control form-control-sm" id="search-input"
                                placeholder="Filter events...">
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-auto">
                            <label class="form-label small mb-1">&nbsp;</label>
                            <div class="btn-group btn-group-sm d-block">
                                <button class="btn btn-outline-secondary" id="select-all-types">All</button>
                                <button class="btn btn-outline-secondary" id="select-none-types">None</button>
                                <button class="btn btn-outline-secondary" id="reset-filters">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>

                        <!-- Export -->
                        <div class="col-auto ms-auto">
                            <label class="form-label small mb-1">&nbsp;</label>
                            <div class="btn-group btn-group-sm d-block">
                                <button class="btn btn-outline-dark" id="export-json">
                                    <i class="fas fa-download"></i> JSON
                                </button>
                                <button class="btn btn-outline-dark" id="export-csv">
                                    <i class="fas fa-file-csv"></i> CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Panel -->
            <div class="card shadow-sm mb-2">
                <div class="card-body p-2">
                    <div class="row g-2 text-center small">
                        <div class="col">
                            <div class="text-muted">Total Events</div>
                            <div class="fw-bold fs-6" id="stat-total">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted">Visible</div>
                            <div class="fw-bold fs-6 text-primary" id="stat-visible">0</div>
                        </div>
                        <div class="col">
                            <div class="text-success">SMS</div>
                            <div class="fw-bold fs-6" id="stat-sms">0</div>
                        </div>
                        <div class="col">
                            <div class="text-primary">Calls</div>
                            <div class="fw-bold fs-6" id="stat-calls">0</div>
                        </div>
                        <div class="col">
                            <div class="text-info">App</div>
                            <div class="fw-bold fs-6" id="stat-app">0</div>
                        </div>
                        <div class="col">
                            <div class="text-danger">Network</div>
                            <div class="fw-bold fs-6" id="stat-network">0</div>
                        </div>
                        <div class="col">
                            <div class="text-warning">System</div>
                            <div class="fw-bold fs-6" id="stat-system">0</div>
                        </div>
                        <div class="col">
                            <div class="text-purple">VoIP</div>
                            <div class="fw-bold fs-6" id="stat-voip">0</div>
                        </div>
                        <div class="col">
                            <div class="text-muted">Flagged</div>
                            <div class="fw-bold fs-6 text-warning" id="stat-flagged">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Range & Zoom Controls -->
            <div class="card shadow-sm mb-3">
                <div class="card-body p-2">
                    <div class="row g-2 align-items-center">
                        <!-- Time Range Filter -->
                        <div class="col-auto">
                            <label class="form-label small fw-bold mb-1">Time Range:</label>
                            <select class="form-select form-select-sm" id="time-range" style="width: 150px;">
                                <option value="all">All Time</option>
                                <option value="1h">Last Hour</option>
                                <option value="6h">Last 6 Hours</option>
                                <option value="24h">Last 24 Hours</option>
                                <option value="7d">Last 7 Days</option>
                            </select>
                        </div>

                        <!-- Zoom Controls -->
                        <div class="col-auto ms-auto">
                            <label class="form-label small mb-1">&nbsp;</label>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-dark" id="zoom-in" title="Zoom In">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button class="btn btn-outline-dark" id="zoom-out" title="Zoom Out">
                                    <i class="fas fa-search-minus"></i>
                                </button>
                                <button class="btn btn-outline-danger" id="reset-view" title="Reset View">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visualization Canvas -->
            <div class="card shadow-lg border-0 bg-dark overflow-hidden position-relative" style="height: 75vh;">
                <!-- Loading Overlay -->
                <div id="loading-overlay"
                    class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center bg-dark"
                    style="z-index: 1000;">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div class="text-light">Constructing Transit Map...</div>
                </div>

                <!-- D3 Container -->
                <div id="viz-container" class="w-100 h-100"></div>

                <!-- Event Detail Panel -->
                <div id="event-panel"
                    class="position-absolute top-0 end-0 h-100 bg-dark border-start border-secondary p-3 shadow-lg"
                    style="width: 350px; transform: translateX(100%); transition: transform 0.3s ease-in-out; z-index: 900; overflow-y: auto;">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 text-light px-2 py-1 rounded" id="panel-type-badge">Event Type</h5>
                        <button class="btn btn-sm btn-link text-muted" id="close-panel"><i
                                class="fas fa-times"></i></button>
                    </div>

                    <div class="text-secondary small mb-1">TIMESTAMP</div>
                    <div class="text-light font-monospace mb-3" id="panel-time">--</div>

                    <div class="text-secondary small mb-1">SOURCE / TAG</div>
                    <div class="text-light mb-3" id="panel-subtype">--</div>

                    <div class="text-secondary small mb-1">CONTENT</div>
                    <div class="bg-black p-2 rounded border border-secondary mb-3">
                        <code class="text-info text-break" id="panel-content">--</code>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-sm btn-danger w-100" id="btn-flag"><i class="fas fa-flag me-1"></i>
                            Flag</button>
                        <button class="btn btn-sm btn-outline-light w-100" id="btn-trace"><i
                                class="fas fa-route me-1"></i> Trace</button>
                        <button class="btn btn-sm btn-outline-info w-100" id="btn-export-single"><i
                                class="fas fa-download"></i></button>
                    </div>

                    <hr class="border-secondary">

                    <h6 class="text-muted small text-uppercase">Context</h6>
                    <div id="panel-context-list" class="list-group list-group-flush list-group-dark small">
                        <!-- Populated by JS -->
                        <div class="text-muted fst-italic">Select an event to see context.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="../js/timeline-mindmap.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Mind Map Timeline
        const timeline = new MindMapTimeline('viz-container');
        timeline.loadData('../api/timeline-data.php');

        // Make available globally for debugging
        window.timeline = timeline;
    });
</script>

<?php require_once '../includes/footer.php'; ?>