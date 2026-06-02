<?php
/**
 * Android Forensic Tool - Live Monitoring Page
 * Real-time logcat streaming with Server-Sent Events
 */
$pageTitle = 'Live Monitor - Android Forensic Tool';
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
                        <i class="fas fa-satellite-dish me-2 text-forensic-blue"></i>Live Monitor
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Live Monitor</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Control Panel -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <button class="btn btn-success btn-lg" id="startMonitorBtn" onclick="startLiveMonitor()">
                                <i class="fas fa-play me-2"></i>Start Monitoring
                            </button>
                            <button class="btn btn-danger btn-lg ms-2" id="stopMonitorBtn" onclick="stopLiveMonitor()"
                                disabled>
                                <i class="fas fa-stop me-2"></i>Stop
                            </button>
                        </div>
                        <div class="col-md-4 text-center">
                            <span id="monitorStatus" class="fs-5">
                                <i class="fas fa-circle text-secondary me-1"></i> Stopped
                            </span>
                            <br>
                            <small class="text-muted" id="logCount">0 lines captured</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-outline-secondary" onclick="clearLiveLog()">
                                <i class="fas fa-eraser me-1"></i>Clear
                            </button>
                            <button class="btn btn-outline-primary" onclick="pauseMonitor()">
                                <i class="fas fa-pause me-1"></i>Pause
                            </button>
                            <button class="btn btn-outline-success" onclick="exportLiveLog()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Log Console -->
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-terminal me-2"></i>Live Log Output
                            </h3>
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="liveSearch" placeholder="Filter logs..."
                                    onkeyup="filterLiveLogs()">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="log-viewer" id="liveLogConsole" style="height: 600px;">
                                <div class="log-entry log-info">
                                    <span class="text-muted">[Ready]</span> Click "Start Monitoring" to begin real-time
                                    log capture...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Sidebar -->
                <div class="col-lg-3">
                    <!-- Log Level Filter -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-filter me-2"></i>Log Levels
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input level-filter" type="checkbox" id="levelV" checked
                                    data-level="V">
                                <label class="form-check-label log-verbose" for="levelV">Verbose</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input level-filter" type="checkbox" id="levelD" checked
                                    data-level="D">
                                <label class="form-check-label log-debug" for="levelD">Debug</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input level-filter" type="checkbox" id="levelI" checked
                                    data-level="I">
                                <label class="form-check-label log-info" for="levelI">Info</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input level-filter" type="checkbox" id="levelW" checked
                                    data-level="W">
                                <label class="form-check-label log-warning" for="levelW">Warning</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input level-filter" type="checkbox" id="levelE" checked
                                    data-level="E">
                                <label class="form-check-label log-error" for="levelE">Error</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input level-filter" type="checkbox" id="levelF" checked
                                    data-level="F">
                                <label class="form-check-label log-fatal" for="levelF">Fatal</label>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar me-2"></i>Live Stats
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-secondary">Verbose</span>
                                    <span class="badge bg-secondary" id="statV">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-forensic-blue">Debug</span>
                                    <span class="badge bg-info" id="statD">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-forensic-cyan">Info</span>
                                    <span class="badge bg-primary" id="statI">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-warning">Warning</span>
                                    <span class="badge bg-warning" id="statW">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-danger">Error</span>
                                    <span class="badge bg-danger" id="statE">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-white bg-danger px-1 rounded">Fatal</span>
                                    <span class="badge bg-dark" id="statF">0</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="card mb-3">
                        <div class="card-header bg-danger text-white">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt me-2"></i>Threats Detected
                            </h3>
                        </div>
                        <div class="card-body text-center">
                            <h1 class="display-4 fw-bold text-danger mb-0" id="liveThreatCount">0</h1>
                            <small class="text-muted">Suspicious Events</small>
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-cog me-2"></i>Options
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="autoScrollLive" checked>
                                <label class="form-check-label" for="autoScrollLive">Auto-scroll</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="showTimestamp" checked>
                                <label class="form-check-label" for="showTimestamp">Show Timestamps</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="highlightErrors" checked>
                                <label class="form-check-label" for="highlightErrors">Highlight Errors</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="detectThreats" checked>
                                <label class="form-check-label text-danger" for="detectThreats"><strong>Active Threat
                                        Detector</strong></label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Max Lines</label>
                                <select class="form-select form-select-sm" id="maxLines">
                                    <option value="500">500</option>
                                    <option value="1000" selected>1000</option>
                                    <option value="2000">2000</option>
                                    <option value="5000">5000</option>
                                </select>
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
let isPaused = false;
let logLines = [];
let stats = { V: 0, D: 0, I: 0, W: 0, E: 0, F: 0 };
let totalLines = 0;
let threatCount = 0;

// Simple Regex Signatures for Live Detection
const THREAT_PATTERNS = [
    { regex: /com\.spyzie|com\.mspy|com\.flexispy/i, name: "Spyware Package" },
    { regex: /sms intercept|otp.*listen/i, name: "SMS Listener" },
    { regex: /screen.*capture|record.*audio|keylog/i, name: "Surveillance Activity" },
    { regex: /accessibility.*service.*enabled/i, name: "Accessibility Abuse" },
    { regex: /bank.*overlay|inject.*view/i, name: "Banking Troj" }
];

function startLiveMonitor() {
    if (ForensicApp.state.isMonitoring) return;
    
    ForensicApp.state.isMonitoring = true;
    document.getElementById('startMonitorBtn').disabled = true;
    document.getElementById('stopMonitorBtn').disabled = false;
    document.getElementById('monitorStatus').innerHTML = '<i class="fas fa-circle text-success pulse me-1"></i> Live';
    
    const logConsole = document.getElementById('liveLogConsole');
    appendLiveLine('🟢 Live monitoring started...', 'success');
    
    // Connect to real SSE backend
    connectToLiveStream();
}

function stopLiveMonitor() {
    ForensicApp.state.isMonitoring = false;
    document.getElementById('startMonitorBtn').disabled = false;
    document.getElementById('stopMonitorBtn').disabled = true;
    document.getElementById('monitorStatus').innerHTML = '<i class="fas fa-circle text-secondary me-1"></i> Stopped';
    
    appendLiveLine('🔴 Live monitoring stopped', 'warning');
    
    if (ForensicApp.state.eventSource) {
        ForensicApp.state.eventSource.close();
        ForensicApp.state.eventSource = null;
    }
}

function pauseMonitor() {
    isPaused = !isPaused;
    const btn = event.target;
    if (isPaused) {
        btn.innerHTML = '<i class="fas fa-play me-1"></i>Resume';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-warning');
        document.getElementById('monitorStatus').innerHTML = '<i class="fas fa-pause text-warning me-1"></i> Paused';
    } else {
        btn.innerHTML = '<i class="fas fa-pause me-1"></i>Pause';
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-outline-primary');
        document.getElementById('monitorStatus').innerHTML = '<i class="fas fa-circle text-success pulse me-1"></i> Live';
    }
}

let scrollTimeout = null;
let lastUptime = 0;
let cachedMaxLines = 1000;
let cachedHighlightErrors = true;
let cachedAutoScroll = true;
let cachedSearchTerm = "";
let cachedDetectThreats = true;

function updateCache() {
    const now = Date.now();
    if (now - lastUptime > 250) {
        cachedMaxLines = parseInt(document.getElementById('maxLines').value) || 1000;
        cachedHighlightErrors = document.getElementById('highlightErrors').checked;
        cachedAutoScroll = document.getElementById('autoScrollLive').checked;
        const searchInput = document.getElementById('liveSearch');
        cachedSearchTerm = (searchInput ? searchInput.value.toLowerCase() : "");
        cachedDetectThreats = document.getElementById('detectThreats').checked;
        lastUptime = now;
    }
}

function checkThreats(lineContent) {
    if (!cachedDetectThreats) return false;

    for (let pattern of THREAT_PATTERNS) {
        if (pattern.regex.test(lineContent)) {
            threatCount++;
            document.getElementById('liveThreatCount').innerText = threatCount;
            // Flash effect
            const card = document.getElementById('liveThreatCount').closest('.card');
            card.classList.add('border-danger');
            setTimeout(() => card.classList.remove('border-danger'), 500);
            return `🚨 THREAT DETECTED: ${pattern.name}`;
        }
    }
    return null;
}

function connectToLiveStream() {
    // Create EventSource connection to live-stream.php
    ForensicApp.state.eventSource = new EventSource('../api/live-stream.php');
    
    // Handle default message events (fallback)
    ForensicApp.state.eventSource.onmessage = function(e) {
        if (isPaused) return;
        console.log('SSE Default Message:', e.data);
        
        try {
            const data = JSON.parse(e.data);
            const line = data.line || e.data;
            const level = data.level || 'I';
            appendLiveLine(line, getLevelClass(level), level);
        } catch(err) {
            // If not JSON, just display raw
            appendLiveLine(e.data, 'info', 'I');
        }
    };
    
    // Handle incoming log events
    ForensicApp.state.eventSource.addEventListener('log', function(e) {
        if (isPaused) return;
        console.log('SSE Log Event:', e.data);
        
        const data = JSON.parse(e.data);
        const line = data.line;
        const level = data.level;
        
        appendLiveLine(line, getLevelClass(level), level);
    });
    
    // Handle status events
    ForensicApp.state.eventSource.addEventListener('status', function(e) {
        const data = JSON.parse(e.data);
        console.log('SSE Status:', data.message);
        appendLiveLine('📡 ' + data.message, 'info');
        
        if (data.status === 'error' || data.status === 'disconnected') {
            stopLiveMonitor();
        }
    });
    
    // Handle heartbeat
    ForensicApp.state.eventSource.addEventListener('heartbeat', function(e) {
        // Keep connection alive
        console.log('SSE Heartbeat received');
    });
    
    // Handle connection open
    ForensicApp.state.eventSource.onopen = function(e) {
        console.log('SSE Connection Opened');
        appendLiveLine('✅ Connected to live stream', 'success');
    };
    
    // Handle errors
    ForensicApp.state.eventSource.onerror = function(e) {
        console.error('SSE Error:', e);
        if (ForensicApp.state.isMonitoring) {
            appendLiveLine('⚠️ Connection lost. Attempting to reconnect...', 'warning');
        }
    };
}

function applyFiltersToLine(line, level) {
    // Check toggle filter safely
    let toggleVisible = true;
    if (level) { 
        const input = document.querySelector(`.level-filter[data-level="${level}"]`);
        if (input && !input.checked) {
            toggleVisible = false;
        }
    }
    
    // Check search term
    let searchVisible = true;
    if (cachedSearchTerm) {
        searchVisible = line.textContent.toLowerCase().includes(cachedSearchTerm);
    }
    
    // Apply final display state
    line.style.display = (toggleVisible && searchVisible) ? '' : 'none';
}

function filterLiveLogs() {
    lastUptime = 0; // force cache update
    updateCache();
    document.querySelectorAll('#liveLogConsole .log-entry').forEach(line => {
        applyFiltersToLine(line, line.dataset.level);
    });
}

const fragmentBuf = document.createDocumentFragment();
let limitsTimeout = null;

function appendLiveLine(text, levelClass = 'info', level = 'I') {
    const consoleElem = document.getElementById('liveLogConsole');
    updateCache();
    
    const line = document.createElement('div');
    line.className = `log-entry log-${levelClass}`;
    line.dataset.level = level;
    
    // THREAT DETECTION HOOK
    const threatMsg = checkThreats(text);
    if (threatMsg) {
        line.innerHTML = `<strong>${threatMsg}</strong><br>` + text;
        line.className += ' bg-danger text-white p-2 mb-1 rounded';
    } else {
        line.textContent = text;
    }

    // Highlight errors
    if (!threatMsg && cachedHighlightErrors && (level === 'E' || level === 'F')) {
        line.style.fontWeight = 'bold';
    }
    
    // Store logic
    logLines.push({ text, level });
    
    // Update stats
    if (stats[level] !== undefined) {
        stats[level]++;
        document.getElementById('stat' + level).textContent = stats[level];
    }
    
    totalLines++;
    if(totalLines % 10 === 0 || totalLines < 10) {
        document.getElementById('logCount').textContent = totalLines + ' lines captured';
    }
    
    // Apply filters immediately
    applyFiltersToLine(line, level);
    
    consoleElem.appendChild(line);
    
    // De-bounce DOM limits to prevent thrashing
    if (!limitsTimeout) {
        limitsTimeout = setTimeout(() => {
            while (consoleElem.children.length > cachedMaxLines) {
                consoleElem.removeChild(consoleElem.firstChild);
            }
            limitsTimeout = null;
        }, 150);
    }
    
    // Auto-scroll logic safely
    if (cachedAutoScroll) {
        if (!scrollTimeout) {
            scrollTimeout = requestAnimationFrame(() => {
                consoleElem.scrollTop = consoleElem.scrollHeight;
                scrollTimeout = null;
            });
        }
    }
}

function getLevelClass(level) {
    const classes = { V: 'verbose', D: 'debug', I: 'info', W: 'warning', E: 'error', F: 'critical' };
    return classes[level] || 'info';
}

function clearLiveLog() {
    document.getElementById('liveLogConsole').innerHTML = '';
    logLines = [];
    stats = { V: 0, D: 0, I: 0, W: 0, E: 0, F: 0 };
    totalLines = 0;
    
    Object.keys(stats).forEach(level => {
        document.getElementById('stat' + level).textContent = '0';
    });
    document.getElementById('logCount').textContent = '0 lines captured';
    
    appendLiveLine('Console cleared', 'info');
}

function exportLiveLog() {
    const console = document.getElementById('liveLogConsole');
    // Export only visible logs or all? Typically we export everything logged
    const text = logLines.map(log => log.text).join('\n');
    
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'live_log_' + new Date().toISOString().slice(0, 10) + '.txt';
    a.click();
    URL.revokeObjectURL(url);
    
    showToast('Log exported successfully', 'success');
}

// Level filter change handler
document.querySelectorAll('.level-filter').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        filterLiveLogs();
    });
});
</script>
SCRIPT;

require_once '../includes/footer.php';
?>