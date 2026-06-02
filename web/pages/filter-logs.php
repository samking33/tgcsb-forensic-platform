<?php
/**
 * Android Forensic Tool - Filter Logs Page
 * Advanced log filtering interface
 */
$pageTitle = 'Filter Logs - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-filter me-2 text-forensic-blue"></i>Filter Logs
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Filter Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Filter Controls Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sliders-h me-2"></i>Filter Options
                    </h3>
                </div>
                <div class="card-body">
                    <form id="filterForm">
                        <div class="row">
                            <!-- Log Type -->
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="filterLogType">
                                        <i class="fas fa-file me-1"></i>Log Type
                                    </label>
                                    <select class="form-select" id="filterLogType" name="logType">
                                        <option value="all">All Logs</option>
                                        <option value="logcat">Logcat</option>
                                        <option value="calls">Call Logs</option>
                                        <option value="sms">SMS Messages</option>
                                        <option value="location">Location Data</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Time Range -->
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="filterTimeRange">
                                        <i class="fas fa-clock me-1"></i>Time Range
                                    </label>
                                    <select class="form-select" id="filterTimeRange" name="timeRange">
                                        <option value="all">All Time</option>
                                        <option value="1h">Past 1 Hour</option>
                                        <option value="24h" selected>Past 24 Hours</option>
                                        <option value="7d">Past 7 Days</option>
                                        <option value="30d">Past 30 Days</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Severity -->
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="filterSeverity">
                                        <i class="fas fa-exclamation-circle me-1"></i>Severity
                                    </label>
                                    <select class="form-select" id="filterSeverity" name="severity">
                                        <option value="all">All Levels</option>
                                        <option value="verbose">Verbose</option>
                                        <option value="debug">Debug</option>
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="error">Error</option>
                                        <option value="fatal">Fatal</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="filterCategory">
                                        <i class="fas fa-folder me-1"></i>Category
                                    </label>
                                    <select class="form-select" id="filterCategory" name="category">
                                        <option value="all">All Categories</option>
                                        <?php foreach ($LOG_TYPES as $type => $info): ?>
                                            <option value="<?= strtolower($type) ?>"><?= $type ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Keyword Search -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="filterKeyword">
                                        <i class="fas fa-search me-1"></i>Keyword Search
                                    </label>
                                    <input type="text" class="form-control" id="filterKeyword" name="keyword"
                                        placeholder="Enter keywords to search...">
                                </div>
                            </div>

                            <!-- Regex Pattern -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="filterRegex">
                                        <i class="fas fa-code me-1"></i>Regex Pattern (Advanced)
                                    </label>
                                    <input type="text" class="form-control" id="filterRegex" name="regex"
                                        placeholder="e.g., Error.*timeout">
                                </div>
                            </div>

                            <!-- Options -->
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="caseSensitive">
                                        <label class="form-check-label" for="caseSensitive">
                                            Case Sensitive
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Date Range (hidden by default) -->
                        <div class="row" id="customDateRange" style="display: none;">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="dateFrom">From Date</label>
                                    <input type="datetime-local" class="form-control" id="dateFrom">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="dateTo">To Date</label>
                                    <input type="datetime-local" class="form-control" id="dateTo">
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-forensic me-2" onclick="applyFilters()">
                                    <i class="fas fa-filter me-1"></i>Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="resetFilters()">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="saveFiltersPreset()">
                                    <i class="fas fa-save me-1"></i>Save Preset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Stats -->
            <div class="row mb-4" id="resultStats" style="display: none;">
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box bg-transparent">
                        <span class="info-box-icon bg-info"><i class="fas fa-search"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Results Found</span>
                            <span class="info-box-number" id="resultCount">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box bg-transparent">
                        <span class="info-box-icon bg-success"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Processing Time</span>
                            <span class="info-box-number" id="processTime">0 ms</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list me-2"></i>Filtered Results
                    </h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportFilteredLogs('csv')">
                                <i class="fas fa-file-csv me-1"></i>CSV
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportFilteredLogs('excel')">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="exportFilteredLogs('json')">
                                <i class="fas fa-file-code me-1"></i>JSON
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="filterResults">
                        <div class="text-center py-5" id="noResults">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h5>Apply Filters to See Results</h5>
                            <p class="text-muted">Configure the filter options above and click "Apply Filters"</p>
                        </div>

                        <div class="table-responsive" style="max-height: 600px; overflow-x: auto; overflow-y: auto;">
                            <table id="filterTable" class="table table-striped table-hover"
                                style="width: auto; display: none; white-space: nowrap;">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-clock me-1"></i>Timestamp</th>
                                        <th><i class="fas fa-tag me-1"></i>Type</th>
                                        <th><i class="fas fa-exclamation-circle me-1"></i>Level</th>
                                        <th><i class="fas fa-comment me-1"></i>Content</th>
                                    </tr>
                                </thead>
                                <tbody id="filterTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <style>
                    /* Custom scrollbar styling for filter results table */
                    .table-responsive::-webkit-scrollbar {
                        width: 12px;
                        height: 12px;
                    }

                    .table-responsive::-webkit-scrollbar-track {
                        background: var(--bg-darker);
                        border-radius: 6px;
                    }

                    .table-responsive::-webkit-scrollbar-thumb {
                        background: var(--border-soft);
                        border-radius: 6px;
                        border: 2px solid var(--bg-panel);
                    }

                    .table-responsive::-webkit-scrollbar-thumb:hover {
                        background: var(--accent-primary);
                    }

                    .table-responsive::-webkit-scrollbar-corner {
                        background: var(--bg-darker);
                    }
                </style>
            </div>

            <!-- Saved Presets -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bookmark me-2"></i>Saved Filter Presets
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row" id="presetsContainer">
                        <div class="col-md-3">
                            <div class="card bg-secondary-subtle">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                    <h6>Errors Only</h6>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="loadPreset('errors')">Load</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary-subtle">
                                <div class="card-body text-center">
                                    <i class="fas fa-wifi fa-2x text-info mb-2"></i>
                                    <h6>Network Activity</h6>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="loadPreset('network')">Load</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary-subtle">
                                <div class="card-body text-center">
                                    <i class="fas fa-bug fa-2x text-warning mb-2"></i>
                                    <h6>Crashes & ANRs</h6>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="loadPreset('crashes')">Load</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary-subtle">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x text-success mb-2"></i>
                                    <h6>Last Hour</h6>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="loadPreset('lasthour')">Load</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php
$additionalScripts = <<<'SCRIPT'
<script>
// Show/hide custom date range
document.getElementById('filterTimeRange').addEventListener('change', function() {
    document.getElementById('customDateRange').style.display = 
        this.value === 'custom' ? 'flex' : 'none';
});

let currentController = null;

function applyFilters() {
    showLoading('Filtering logs...');
    
    // Abort previous request if running
    if (currentController) {
        currentController.abort();
    }
    currentController = new AbortController();
    const signal = currentController.signal;
    
    // Clear previous results immediately
    document.getElementById('filterTableBody').innerHTML = '';
    document.getElementById('resultStats').style.display = 'none';
    
    const startTime = performance.now();
    
    const filters = {
        logType: document.getElementById('filterLogType').value,
        timeRange: document.getElementById('filterTimeRange').value,
        severity: document.getElementById('filterSeverity').value,
        category: document.getElementById('filterCategory').value,
        keyword: document.getElementById('filterKeyword').value,
        regex: document.getElementById('filterRegex').value,
        caseSensitive: document.getElementById('caseSensitive').checked,
        dateFrom: document.getElementById('dateFrom').value,
        dateTo: document.getElementById('dateTo').value
    };
    
    // Add cache busting timestamp
    fetch('../api/filter.php?t=' + new Date().getTime(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(filters),
        signal: signal
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        currentController = null;
        
        const endTime = performance.now();
        
        if (data.success) {
            displayResults(data.results, filters.keyword);
            
            // Show stats
            document.getElementById('resultStats').style.display = 'flex';
            document.getElementById('resultCount').textContent = data.count;
            document.getElementById('processTime').textContent = Math.round(endTime - startTime) + ' ms';
            
            showToast(`Found ${data.count} matching records`, 'success');
        } else {
            showToast('Filter error: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        if (error.name === 'AbortError') {
            console.log('Fetch aborted');
            return;
        }
        hideLoading();
        currentController = null;
        showToast('Network error: ' + error.message, 'danger');
    });
}

function displayResults(results, searchKeyword) {
    const table = document.getElementById('filterTable');
    const tbody = document.getElementById('filterTableBody');
    const noResults = document.getElementById('noResults');
    
    if (results.length === 0) {
        table.style.display = 'none';
        noResults.innerHTML = `
            <i class="fas fa-search fa-4x text-muted mb-3"></i>
            <h5>No Results Found</h5>
            <p class="text-muted">Try adjusting your filter criteria</p>
        `;
        noResults.style.display = 'block';
        return;
    }
    
    noResults.style.display = 'none';
    table.style.display = 'table';
    
    // Use the keyword that was used for the search, not the current DOM value
    const keyword = searchKeyword || document.getElementById('filterKeyword').value;
    
    // DESTROY DataTable FIRST (Critical fix)
    // We must destroy the old instance before modifying the DOM, 
    // otherwise destroy() might revert our changes or cause conflicts.
    if ($.fn.DataTable.isDataTable('#filterTable')) {
        $('#filterTable').DataTable().destroy();
    }
    
    tbody.innerHTML = results.map(row => `
        <tr>
            <td style="white-space: nowrap;"><small>${row.timestamp || '--'}</small></td>
            <td style="white-space: nowrap;"><span class="badge bg-info">${row.type || 'Log'}</span></td>
            <td style="white-space: nowrap;"><span class="badge bg-${getLevelColor(row.level)}">${row.level || 'I'}</span></td>
            <td style="word-break: break-word; max-width: 800px;">${highlightKeyword(escapeHtml(row.content || ''), keyword)}</td>
        </tr>
    `).join('');
    
    // Initialize new DataTable
    initDataTable('filterTable');
}

function getLevelColor(level) {
    const colors = { V: 'secondary', D: 'info', I: 'primary', W: 'warning', E: 'danger', F: 'dark' };
    return colors[level] || 'secondary';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function highlightKeyword(text, keyword) {
    if (!keyword || keyword.trim() === '') return text;
    
    // Case-insensitive highlighting
    const regex = new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark class="bg-warning text-dark">$1</mark>');
}

function resetFilters() {
    document.getElementById('filterForm').reset();
    document.getElementById('customDateRange').style.display = 'none';
    document.getElementById('resultStats').style.display = 'none';
    document.getElementById('filterTable').style.display = 'none';
    document.getElementById('noResults').style.display = 'block';
    document.getElementById('noResults').innerHTML = `
        <i class="fas fa-search fa-4x text-muted mb-3"></i>
        <h5>Apply Filters to See Results</h5>
        <p class="text-muted">Configure the filter options above and click "Apply Filters"</p>
    `;
}

function saveFiltersPreset() {
    const name = prompt('Enter a name for this preset:');
    if (!name) return;
    
    const filters = {
        name: name,
        logType: document.getElementById('filterLogType').value,
        timeRange: document.getElementById('filterTimeRange').value,
        severity: document.getElementById('filterSeverity').value,
        category: document.getElementById('filterCategory').value,
        keyword: document.getElementById('filterKeyword').value
    };
    
    // Save to localStorage
    let presets = JSON.parse(localStorage.getItem('filterPresets') || '[]');
    presets.push(filters);
    localStorage.setItem('filterPresets', JSON.stringify(presets));
    
    showToast('Preset saved successfully', 'success');
}

function loadPreset(preset) {
    const presets = {
        errors: { severity: 'error', category: 'all' },
        network: { category: 'network', severity: 'all' },
        crashes: { category: 'crash', severity: 'all' },
        lasthour: { timeRange: '1h', category: 'all' }
    };
    
    if (presets[preset]) {
        if (presets[preset].severity) document.getElementById('filterSeverity').value = presets[preset].severity;
        if (presets[preset].category) document.getElementById('filterCategory').value = presets[preset].category;
        if (presets[preset].timeRange) document.getElementById('filterTimeRange').value = presets[preset].timeRange;
        
        applyFilters();
    }
}

function exportFilteredLogs(format) {
    showToast('Exporting as ' + format.toUpperCase() + '...', 'info');
    exportTableData('filterTable', format);
}
</script>
SCRIPT;

require_once '../includes/footer.php';
?>