<?php
/**
 * Android Forensic Tool - Extract Logs Page
 * Device connection and log extraction controls
 */
$pageTitle = 'Extract Logs - Android Forensic Tool';
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
                        <i class="fas fa-download me-2 text-forensic-blue"></i>Extract Logs
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Extract Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Device Connection Status -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-mobile-alt me-2"></i>Device Connection
                            </h3>
                            <div class="card-tools">
                                <button class="btn btn-tool" onclick="refreshDeviceStatus()">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-4 text-center">
                                    <i class="fas fa-mobile-alt fa-5x text-forensic-blue" id="deviceIcon"></i>
                                </div>
                                <div class="col-8">
                                    <table class="table table-borderless table-sm mb-0">
                                        <tr>
                                            <td class="text-muted">Status:</td>
                                            <td>
                                                <span class="badge bg-success" id="connectionStatus">
                                                    <i class="fas fa-circle me-1"></i>Connected
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Device:</td>
                                            <td id="deviceName">Unknown Device</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Model:</td>
                                            <td id="deviceModel">--</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Android:</td>
                                            <td id="androidVersion">--</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Serial:</td>
                                            <td><code id="deviceSerial">--</code></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buffer Detection -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-database me-2"></i>Log Buffer Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="info-box bg-transparent mb-0">
                                        <span class="info-box-icon bg-forensic-blue">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Buffer Duration</span>
                                            <span class="info-box-number" id="bufferDuration">--</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="info-box bg-transparent mb-0">
                                        <span class="info-box-icon bg-forensic-orange">
                                            <i class="fas fa-calendar-alt"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Oldest Log</span>
                                            <span class="info-box-number small" id="oldestLog">--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                The log buffer capacity varies by device. All available logs within the buffer will be
                                extracted.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Extraction Controls -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-cogs me-2"></i>Extraction Options
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="toggle-container">
                                <!-- Toggle Row: Logcat -->
                                <div class="toggle-row">
                                    <label class="toggle-row-label" for="extractLogcat">
                                        <i class="fas fa-file-code toggle-row-label-icon"></i>
                                        <span class="toggle-row-label-text">Logcat</span>
                                    </label>
                                    <div class="toggle-row-switch">
                                        <input class="form-check-input" type="checkbox" id="extractLogcat" checked>
                                    </div>
                                </div>

                                <!-- Toggle Row: Call Logs -->
                                <div class="toggle-row">
                                    <label class="toggle-row-label" for="extractCalls">
                                        <i class="fas fa-phone toggle-row-label-icon"></i>
                                        <span class="toggle-row-label-text">Call Logs</span>
                                    </label>
                                    <div class="toggle-row-switch">
                                        <input class="form-check-input" type="checkbox" id="extractCalls" checked>
                                    </div>
                                </div>

                                <!-- Toggle Row: SMS -->
                                <div class="toggle-row">
                                    <label class="toggle-row-label" for="extractSms">
                                        <i class="fas fa-comment-sms toggle-row-label-icon"></i>
                                        <span class="toggle-row-label-text">SMS Logs</span>
                                    </label>
                                    <div class="toggle-row-switch">
                                        <input class="form-check-input" type="checkbox" id="extractSms" checked>
                                    </div>
                                </div>

                                <!-- Toggle Row: Location -->
                                <div class="toggle-row">
                                    <label class="toggle-row-label" for="extractLocation">
                                        <i class="fas fa-map-marker-alt toggle-row-label-icon"></i>
                                        <span class="toggle-row-label-text">Location</span>
                                    </label>
                                    <div class="toggle-row-switch">
                                        <input class="form-check-input" type="checkbox" id="extractLocation" checked>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <button class="btn btn-forensic btn-lg" onclick="startExtraction()" id="extractBtn">
                                    <i class="fas fa-download me-2"></i>Start Extraction
                                </button>
                                <button class="btn btn-outline-secondary" onclick="clearOutput()">
                                    <i class="fas fa-eraser me-2"></i>Clear Log
                                </button>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mt-4" id="progressContainer" style="display: none;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Extraction Progress</span>
                                    <span id="progressPercent">0%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-forensic-blue"
                                        role="progressbar" id="extractProgress" style="width: 0%; transition: width 0.5s ease;" aria-valuenow="0"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Output Console -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-terminal me-2"></i>Extraction Output
                            </h3>
                            <div class="card-tools">
                                <button class="btn btn-tool" onclick="copyOutput()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="log-viewer" id="extractOutput">
                                <div class="log-entry log-info">
                                    <span class="text-muted">[Ready]</span> Waiting for extraction command...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help & Tips -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-question-circle me-2"></i>Help & Tips
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-lightbulb me-2"></i>Prerequisites</h6>
                                <ul class="mb-0 ps-3">
                                    <li>USB Debugging enabled on device</li>
                                    <li>ADB installed on this computer</li>
                                    <li>Device connected via USB</li>
                                </ul>
                            </div>

                            <h6>Steps to Enable USB Debugging:</h6>
                            <ol class="small">
                                <li>Go to <strong>Settings > About Phone</strong></li>
                                <li>Tap <strong>Build Number</strong> 7 times</li>
                                <li>Go back to <strong>Settings > Developer Options</strong></li>
                                <li>Enable <strong>USB Debugging</strong></li>
                                <li>Connect device and accept the prompt</li>
                            </ol>

                            <hr>

                            <h6>Data Extracted:</h6>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-check text-success me-2"></i>System logcat (full buffer)</li>
                                <li><i class="fas fa-check text-success me-2"></i>Call history</li>
                                <li><i class="fas fa-check text-success me-2"></i>SMS messages</li>
                                <li><i class="fas fa-check text-success me-2"></i>Location data</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-warning">
                            <h3 class="card-title text-dark">
                                <i class="fas fa-exclamation-triangle me-2"></i>Important Notice
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="small mb-0">
                                This tool is intended for authorized forensic analysis only.
                                Ensure you have proper authorization before extracting data from any device.
                            </p>
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
let extractionInProgress = false;

function refreshDeviceStatus() {
    fetch('../api/device-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.connected) {
                document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle me-1"></i>Connected';
                document.getElementById('connectionStatus').className = 'badge bg-success';
                document.getElementById('deviceName').textContent = data.device?.name || 'Android Device';
                document.getElementById('deviceModel').textContent = data.device?.model || '--';
                document.getElementById('androidVersion').textContent = data.device?.android || '--';
                document.getElementById('deviceSerial').textContent = data.device?.serial || '--';
                document.getElementById('bufferDuration').textContent = data.buffer?.duration || '--';
                document.getElementById('oldestLog').textContent = data.buffer?.oldest || '--';
            } else {
                document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle me-1"></i>Disconnected';
                document.getElementById('connectionStatus').className = 'badge bg-danger';
            }
        })
        .catch(() => {
            document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-circle me-1"></i>Unknown';
            document.getElementById('connectionStatus').className = 'badge bg-secondary';
        });
}

let progressInterval;

function startExtraction() {
    if (extractionInProgress) return;
    
    extractionInProgress = true;
    const btn = document.getElementById('extractBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Extracting...';
    
    document.getElementById('progressContainer').style.display = 'block';
    const output = document.getElementById('extractOutput');
    
    appendLog(output, '🚀 Starting log extraction...', 'info');
    updateExtractProgress(0);
    
    // Reset progress first to avoid stale state (Fix for "Run Twice" bug)
    // Chain the promises correctly
    fetch('../api/progress.php?action=reset')
        .catch(err => console.warn("Reset progress failed, continuing anyway", err))
        .then(() => {
            startProgressPolling();
            
            // Call the extraction API (Now it just starts the process)
            return fetch('../api/extract.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    logcat: document.getElementById('extractLogcat').checked,
                    calls: document.getElementById('extractCalls').checked,
                    sms: document.getElementById('extractSms').checked,
                    location: document.getElementById('extractLocation').checked
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendLog(output, '⏳ ' + data.message, 'info');
                // The process runs in background. Polling will handle the rest.
                // We keep extractionInProgress = true until polling hits 100%
            } else {
                appendLog(output, '❌ ' + (data.error || 'Launcher failed'), 'error');
                showToast('Extraction failed to start', 'danger');
                stopProgressPolling();
                extractionInProgress = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download me-2"></i>Start Extraction';
            }
        })
        .catch(error => {
            appendLog(output, '❌ Network error: ' + error.message, 'error');
            showToast('Network error', 'danger');
            stopProgressPolling();
            extractionInProgress = false;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-2"></i>Start Extraction';
        });
}

function startProgressPolling() {
    stopProgressPolling();
    progressInterval = setInterval(() => {
        fetch('../api/progress.php')
            .then(r => r.json())
            .then(data => {
                const percent = data.progress || 0;
                const status = data.status || 'Processing...';
                
                updateExtractProgress(percent);
                
                // Check if complete
                if (percent >= 100) {
                    stopProgressPolling();
                    // Add small delay to ensure stats file is flushed to disk
                    setTimeout(finishExtraction, 1000);
                }
            })
            .catch(e => console.error("Progress poll failed", e));
    }, 500);
}

function finishExtraction() {
    const btn = document.getElementById('extractBtn');
    const output = document.getElementById('extractOutput');
    
    // Refresh device status to show new log info (e.g. buffer usage)
    if (typeof refreshDeviceStatus === 'function') {
        refreshDeviceStatus();
    }
    
    // Fetch final stats (Cache busted)
    fetch('../api/get_stats.php?t=' + new Date().getTime())
        .then(r => r.json())
        .then(data => {
            appendLog(output, '✅ Extraction Complete!', 'success');
            
            if (data) {
                if (data.sms) appendLog(output, `📊 SMS: ${data.sms} records`, 'info');
                if (data.calls) appendLog(output, `📊 Calls: ${data.calls} records`, 'info');
                if (data.locations) appendLog(output, `📊 Locations: ${data.locations} points`, 'info');
                if (data.logcat) appendLog(output, `📊 Logcat: ${data.logcat} lines`, 'info');
                
                // Mule Hunter Stats (Active Search Results)
                if (data.mule_risk) {
                    let riskColor = 'info';
                    if (data.mule_risk === 'HIGH' || data.mule_risk === 'CRITICAL') riskColor = 'error'; // 'error' maps to red in appendLog usually? No, class is 'log-error' usually. 
                    // Wait, appendLog implementation? 
                    // appendLog(element, message, type)
                    // type 'error' usually adds text-danger.
                    
                    appendLog(output, `🕵️‍♂️ Mule Evaluation: ${data.mule_risk} RISK`, (data.mule_risk === 'LOW') ? 'success' : 'error');
                }
                if (data.cloned_banking_apps && data.cloned_banking_apps > 0) {
                     appendLog(output, `🚨 ALERT: ${data.cloned_banking_apps} Cloned Banking App(s) Detected!`, 'error');
                }
            }
            
            showToast('Logs extracted successfully!', 'success');
            
            // Optional: Provide a link to view logs
            appendLog(output, '👉 <a href="timeline.php" class="text-white" style="text-decoration: underline;">View Device Timeline</a>', 'info');
        })
        .catch(e => {
            appendLog(output, '⚠️ Completed, but could not load stats: ' + e.message, 'warning');
        })
        .finally(() => {
            extractionInProgress = false;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-2"></i>Start Extraction';
        });
}

function stopProgressPolling() {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
}

function updateExtractProgress(percent) {
    const bar = document.getElementById('extractProgress');
    const text = document.getElementById('progressPercent');
    bar.style.width = percent + '%';
    bar.setAttribute('aria-valuenow', percent);
    text.textContent = percent + '%';
}

function clearOutput() {
    const output = document.getElementById('extractOutput');
    output.innerHTML = '<div class="log-entry log-info"><span class="text-muted">[Ready]</span> Waiting for extraction command...</div>';
    document.getElementById('progressContainer').style.display = 'none';
    updateExtractProgress(0);
}

function copyOutput() {
    const output = document.getElementById('extractOutput');
    copyToClipboard(output.innerText);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    refreshDeviceStatus();
});
</script>
SCRIPT;

require_once '../includes/footer.php';
?>