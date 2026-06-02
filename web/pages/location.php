<?php
/**
 * Android Forensic Tool - Forensic Location Page
 * Multi-source location acquisition with conservative visualization
 */
$pageTitle = 'Forensic Location Data - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">📍 Forensic Location Data</h1>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        <button class="btn btn-primary" id="extractLocationBtn">
                            <i class="fas fa-download"></i> Extract Locations
                        </button>
                        <button class="btn btn-secondary" id="refreshMapBtn">
                            <i class="fas fa-sync"></i> Refresh Map
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Statistics Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Location Data Summary</h5>
                </div>
                <div class="card-body">
                    <div id="locationStats">
                        <div class="text-muted">No location data loaded. Click "Extract Locations" to begin.</div>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Source Type Filter -->
                        <div class="col-md-4">
                            <label class="form-label">Source Types</label>
                            <div class="form-check" id="sourceFilters">
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="GPS" id="filterGPS" checked>
                                    <label class="form-check-label" for="filterGPS">
                                        <span style="color: #00AA00;">●</span> GPS
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="Network" id="filterNetwork" checked>
                                    <label class="form-check-label" for="filterNetwork">
                                        <span style="color: #0055FF;">●</span> Network
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="Fused" id="filterFused" checked>
                                    <label class="form-check-label" for="filterFused">
                                        <span style="color: #00CCCC;">●</span> Fused
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="WiFi" id="filterWiFi" checked>
                                    <label class="form-check-label" for="filterWiFi">
                                        <span style="color: #9933FF;">●</span> WiFi
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="Cell" id="filterCell" checked>
                                    <label class="form-check-label" for="filterCell">
                                        <span style="color: #FF8800;">●</span> Cell
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input source-filter" type="checkbox" value="App" id="filterApp" checked>
                                    <label class="form-check-label" for="filterApp">
                                        <span style="color: #FFCC00;">●</span> App
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Confidence Filter -->
                        <div class="col-md-4">
                            <label class="form-label">Minimum Confidence</label>
                            <select class="form-select" id="confidenceFilter">
                                <option value="">All Levels</option>
                                <option value="Low">Low and above</option>
                                <option value="Medium">Medium and above</option>
                                <option value="High">High only</option>
                            </select>
                        </div>

                        <!-- Time Range Filter -->
                        <div class="col-md-4">
                            <label class="form-label">Time Range</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text">From</span>
                                <input type="datetime-local" class="form-control" id="timeFilterStart">
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">To</span>
                                <input type="datetime-local" class="form-control" id="timeFilterEnd">
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <button class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                            <button class="btn btn-secondary" id="resetFiltersBtn">Reset</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Location Map</h5>
                    <small class="text-muted">
                        Marker size indicates confidence level | Dashed outlines indicate inferred data | Paths shown only for high-confidence continuous movement
                    </small>
                </div>
                <div class="card-body">
                    <div id="mapError" style="display: none;"></div>
                    <div id="mapEmpty" style="display: none;"></div>
                    <div id="forensicLocationMap" style="height: 600px; border: 1px solid #ddd;"></div>
                </div>
            </div>

            <!-- Legend -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Legend & Forensic Notes</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Source Types</h6>
                            <ul class="list-unstyled">
                                <li><span style="color: #00AA00; font-size: 20px;">●</span> <strong>GPS</strong> - High reliability, satellite-based</li>
                                <li><span style="color: #0055FF; font-size: 20px;">●</span> <strong>Network</strong> - Medium reliability, cell tower triangulation</li>
                                <li><span style="color: #00CCCC; font-size: 20px;">●</span> <strong>Fused</strong> - High reliability, combined sources</li>
                                <li><span style="color: #9933FF; font-size: 20px;">●</span> <strong>WiFi</strong> - Inferred if enabled, WiFi geolocation</li>
                                <li><span style="color: #FF8800; font-size: 20px;">●</span> <strong>Cell</strong> - Approximate, cell tower location</li>
                                <li><span style="color: #FFCC00; font-size: 20px;">●</span> <strong>App</strong> - Opportunistic, app-level data</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Confidence & Visual Indicators</h6>
                            <ul class="list-unstyled">
                                <li><strong>Large markers</strong> - High confidence</li>
                                <li><strong>Medium markers</strong> - Medium confidence</li>
                                <li><strong>Small markers</strong> - Low confidence</li>
                                <li><strong>Dashed outline</strong> - Externally inferred data (if enabled)</li>
                                <li><strong>Dotted paths</strong> - Movement paths (< 5 min, Medium+ confidence)</li>
                            </ul>
                            <div class="alert alert-warning mt-3">
                                <strong>⚠ Forensic Notice:</strong> All location data shown is extracted from device logs and dumpsys. 
                                External inference (cell/WiFi geolocation) is disabled by default. Retention estimates and confidence 
                                scores are provided for evidentiary context.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Forensic Location Map JS -->
<script src="../assets/js/forensic-location-map.js"></script>

<script>
// Initialize forensic location map
let forensicMap;

document.addEventListener('DOMContentLoaded', function() {
    forensicMap = new ForensicLocationMap('forensicLocationMap');
    
    // Auto-load if data exists
    forensicMap.loadLocations();
    
    // Extract locations button
    document.getElementById('extractLocationBtn').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting...';
        
        fetch('../api/location-acquisition.php?action=extract')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Location extraction complete!\n\nTotal points: ${data.total_points}\n\nSee browser console for audit log.`);
                    console.log('Extraction Audit Log:', data.audit_log);
                    forensicMap.loadLocations();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    if (data.trace) console.error(data.trace);
                }
            })
            .catch(error => {
                alert('Error extracting locations: ' + error.message);
                console.error(error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download"></i> Extract Locations';
            });
    });
    
    // Refresh map button
    document.getElementById('refreshMapBtn').addEventListener('click', function() {
        forensicMap.loadLocations();
    });
    
    // Apply filters button
    document.getElementById('applyFiltersBtn').addEventListener('click', function() {
        applyFilters();
    });
    
    // Reset filters button
    document.getElementById('resetFiltersBtn').addEventListener('click', function() {
        // Reset all checkboxes
        document.querySelectorAll('.source-filter').forEach(cb => cb.checked = true);
        document.getElementById('confidenceFilter').value = '';
        document.getElementById('timeFilterStart').value = '';
        document.getElementById('timeFilterEnd').value = '';
        
        applyFilters();
    });
    
    // Filter change listeners
    document.querySelectorAll('.source-filter').forEach(cb => {
        cb.addEventListener('change', applyFilters);
    });
    
    function applyFilters() {
        const filters = {
            sources: Array.from(document.querySelectorAll('.source-filter:checked')).map(cb => cb.value),
            minConfidence: document.getElementById('confidenceFilter').value || null
        };
        
        // Time filters
        const startTime = document.getElementById('timeFilterStart').valueAsNumber;
        const endTime = document.getElementById('timeFilterEnd').valueAsNumber;
        
        if (startTime) filters.startTime = Math.floor(startTime / 1000);
        if (endTime) filters.endTime = Math.floor(endTime / 1000);
        
        forensicMap.applyFilters(filters);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>