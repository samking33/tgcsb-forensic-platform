<?php
/**
 * Android Forensic Tool - Threat Detection Page
 * Security scanning and threat analysis
 */
$pageTitle = 'Threat Detection - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Threat signatures (simplified version of Python threat_signatures.py)
$THREAT_SIGNATURES = [
    'Suspicious Network' => ['pattern' => '/suspicious|malware|trojan|backdoor/i', 'severity' => 'HIGH'],
    'Data Exfiltration' => ['pattern' => '/exfiltrat|upload.*secret|leak.*data/i', 'severity' => 'CRITICAL'],
    'Root Detection' => ['pattern' => '/su\s+|superuser|magisk|root.*access/i', 'severity' => 'HIGH'],
    'Crypto Mining' => ['pattern' => '/coinhive|cryptonight|miner.*start/i', 'severity' => 'HIGH'],
    'Permission Abuse' => ['pattern' => '/permission.*denied.*bypass|escalat.*privilege/i', 'severity' => 'MEDIUM'],
    'SSL Pinning Bypass' => ['pattern' => '/ssl.*bypass|certificate.*ignore|trust.*all/i', 'severity' => 'HIGH'],
    'Keylogger' => ['pattern' => '/keylog|keystroke.*capture|input.*monitor/i', 'severity' => 'CRITICAL'],
    'SMS Fraud' => ['pattern' => '/premium.*sms|send.*sms.*silent|sms.*intercept/i', 'severity' => 'HIGH']
];
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shield-alt me-2 text-forensic-blue"></i>Threat Detection
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Threats</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Scan Controls -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <button class="btn btn-forensic btn-lg" onclick="startThreatScan()">
                                <i class="fas fa-search-plus me-2"></i>Start Security Scan
                            </button>
                            <button class="btn btn-outline-secondary ms-2" onclick="quickScan()">
                                <i class="fas fa-bolt me-1"></i>Quick Scan
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="text-muted me-3" id="lastScanTime">Last scan: Never</span>
                            <button class="btn btn-outline-info" onclick="exportThreatReport()">
                                <i class="fas fa-file-pdf me-1"></i>Export Report
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-3" id="scanProgress" style="display: none;">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="scanStatus">Scanning...</span>
                            <span id="scanPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                                id="scanProgressBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Threat Summary Cards -->
            <div class="row mb-4" id="threatSummary" style="display: none;">
                <div class="col-lg-3 col-sm-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3 id="criticalCount">0</h3>
                            <p>Critical Threats</p>
                        </div>
                        <div class="icon"><i class="fas fa-skull-crossbones"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 id="highCount">0</h3>
                            <p>High Severity</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 id="mediumCount">0</h3>
                            <p>Medium Severity</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 id="lowCount">0</h3>
                            <p>Low / Info</p>
                        </div>
                        <div class="icon"><i class="fas fa-info-circle"></i></div>
                    </div>
                </div>
            </div>

            <!-- Scan Settings - Horizontal Layout -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cog me-2"></i>Scan Settings
                    </h3>
                </div>
                <div class="card-body">
                    <div class="toggle-container">
                        <!-- Toggle Row 1 (with debug outline) -->
                        <div class="toggle-row toggle-row-debug">
                            <label class="toggle-row-label" for="scanLogcat">
                                <i class="fas fa-file-code toggle-row-label-icon"></i>
                                <span class="toggle-row-label-text">Logcat</span>
                            </label>
                            <div class="toggle-row-switch">
                                <input class="form-check-input" type="checkbox" id="scanLogcat" checked>
                            </div>
                        </div>
                        
                        <!-- Toggle Row 2 -->
                        <div class="toggle-row">
                            <label class="toggle-row-label" for="scanCalls">
                                <i class="fas fa-phone toggle-row-label-icon"></i>
                                <span class="toggle-row-label-text">Call Logs</span>
                            </label>
                            <div class="toggle-row-switch">
                                <input class="form-check-input" type="checkbox" id="scanCalls" checked>
                            </div>
                        </div>
                        
                        <!-- Toggle Row 3 -->
                        <div class="toggle-row">
                            <label class="toggle-row-label" for="scanSms">
                                <i class="fas fa-comment-sms toggle-row-label-icon"></i>
                                <span class="toggle-row-label-text">SMS Logs</span>
                            </label>
                            <div class="toggle-row-switch">
                                <input class="form-check-input" type="checkbox" id="scanSms" checked>
                            </div>
                        </div>
                        
                        <!-- Toggle Row 4 -->
                        <div class="toggle-row">
                            <label class="toggle-row-label" for="deepScan">
                                <i class="fas fa-map-marker-alt toggle-row-label-icon"></i>
                                <span class="toggle-row-label-text">Location</span>
                            </label>
                            <div class="toggle-row-switch">
                                <input class="form-check-input" type="checkbox" id="deepScan">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Threat Signatures & Results - Full Width -->\n
            <div class="row">
                <!-- Threat Signatures - Left Side -->
                <div class="col-lg-3">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-fingerprint me-2"></i>Threat Signatures
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($THREAT_SIGNATURES as $name => $info): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i
                                                class="fas fa-<?= $info['severity'] === 'CRITICAL' ? 'skull' : ($info['severity'] === 'HIGH' ? 'exclamation-triangle' : 'info-circle') ?> me-2 text-<?= $info['severity'] === 'CRITICAL' ? 'danger' : ($info['severity'] === 'HIGH' ? 'warning' : 'info') ?>"></i>
                                            <?= $name ?>
                                        </div>
                                        <span
                                            class="badge bg-<?= $info['severity'] === 'CRITICAL' ? 'danger' : ($info['severity'] === 'HIGH' ? 'warning' : 'info') ?>"><?= $info['severity'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Detected Threats - Right Side (Full Width) -->
                <div class="col-lg-9">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bug me-2"></i>Detected Threats
                            </h3>
                            <div class="card-tools">
                                <button class="btn btn-tool" onclick="clearThreats()">
                                    <i class="fas fa-eraser"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="threatResults">
                            <div class="text-center py-5" id="noThreats">
                                <i class="fas fa-shield-alt fa-4x text-success mb-3"></i>
                                <h5>No Threats Detected</h5>
                                <p class="text-muted">Run a security scan to check for potential threats</p>
                            </div>

                            <div id="threatsList" style="display: none;"></div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="card" id="recommendationsCard" style="display: none;">
                        <div class="card-header bg-info">
                            <h3 class="card-title text-white">
                                <i class="fas fa-lightbulb me-2"></i>Security Recommendations
                            </h3>
                        </div>
                        <div class="card-body" id="recommendations">
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php
$signaturesJson = json_encode($THREAT_SIGNATURES);
?>
<script>
const THREAT_SIGNATURES = <?= $signaturesJson ?>;

function startThreatScan() {
    const progressDiv = document.getElementById('scanProgress');
    const progressBar = document.getElementById('scanProgressBar');
    const statusText = document.getElementById('scanStatus');
    const percentText = document.getElementById('scanPercent');
    
    progressDiv.style.display = 'block';
    document.getElementById('threatSummary').style.display = 'none';
    
    let progress = 0;
    const steps = ['Initializing...', 'Scanning Logcat...', 'Scanning SMS...', 'Scanning Calls...', 'Analyzing patterns...', 'Generating report...'];
    
    const interval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 100) progress = 100;
        
        progressBar.style.width = progress + '%';
        percentText.textContent = Math.round(progress) + '%';
        statusText.textContent = steps[Math.min(Math.floor(progress / 20), steps.length - 1)];
        
        if (progress >= 100) {
            clearInterval(interval);
            completeScan();
        }
    }, 500);
}

function quickScan() {
    showLoading('Running quick scan...');
    setTimeout(() => {
        hideLoading();
        showToast('Quick scan complete: No immediate threats detected', 'success');
    }, 2000);
}

function completeScan() {
    document.getElementById('scanProgress').style.display = 'none';
    document.getElementById('threatSummary').style.display = 'flex';
    document.getElementById('lastScanTime').textContent = 'Last scan: ' + new Date().toLocaleString();
    
    // Simulated results - in production would come from API
    const threats = [
        { type: 'Suspicious Network', severity: 'HIGH', description: 'Detected connection to suspicious IP address', timestamp: '2024-01-15 14:32:01', source: 'Logcat' },
        { type: 'Permission Abuse', severity: 'MEDIUM', description: 'App attempting to access sensitive data without proper permissions', timestamp: '2024-01-15 13:45:22', source: 'Logcat' }
    ];
    
    displayThreats(threats);
}

function displayThreats(threats) {
    const list = document.getElementById('threatsList');
    const noThreats = document.getElementById('noThreats');
    
    // Count by severity
    const counts = { CRITICAL: 0, HIGH: 0, MEDIUM: 0, LOW: 0 };
    threats.forEach(t => counts[t.severity]++);
    
    document.getElementById('criticalCount').textContent = counts.CRITICAL;
    document.getElementById('highCount').textContent = counts.HIGH;
    document.getElementById('mediumCount').textContent = counts.MEDIUM;
    document.getElementById('lowCount').textContent = counts.LOW;
    
    if (threats.length === 0) {
        noThreats.style.display = 'block';
        list.style.display = 'none';
        return;
    }
    
    noThreats.style.display = 'none';
    list.style.display = 'block';
    
    list.innerHTML = threats.map((threat, idx) => `
        <div class="card mb-3 fade-in border-start border-4 border-${getSeverityColor(threat.severity)}" style="animation-delay: ${idx * 0.1}s; background: rgba(15, 23, 42, 0.6);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1 text-${getSeverityColor(threat.severity)}">
                            <i class="fas fa-${getSeverityIcon(threat.severity)} me-2"></i>
                            ${threat.type}
                        </h5>
                        <p class="mb-1">${threat.description}</p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>${threat.timestamp}
                            <span class="ms-2"><i class="fas fa-file me-1"></i>${threat.source}</span>
                        </small>
                    </div>
                    <span class="badge bg-${getSeverityColor(threat.severity)} badge-lg shadow-sm">${threat.severity}</span>
                </div>
            </div>
        </div>
    `).join('');
    
    // Show recommendations
    showRecommendations(threats);
}

function getSeverityIcon(severity) {
    const icons = { CRITICAL: 'skull-crossbones', HIGH: 'exclamation-triangle', MEDIUM: 'exclamation-circle', LOW: 'info-circle' };
    return icons[severity] || 'question';
}

function getSeverityColor(severity) {
    const colors = { CRITICAL: 'danger', HIGH: 'warning', MEDIUM: 'info', LOW: 'secondary' };
    return colors[severity] || 'secondary';
}

function showRecommendations(threats) {
    const card = document.getElementById('recommendationsCard');
    const container = document.getElementById('recommendations');
    
    if (threats.length === 0) {
        card.style.display = 'none';
        return;
    }
    
    card.style.display = 'block';
    
    const recommendations = [
        { icon: 'shield-alt', text: 'Update device security patches to the latest version' },
        { icon: 'user-lock', text: 'Review app permissions and revoke unnecessary access' },
        { icon: 'wifi', text: 'Avoid connecting to untrusted WiFi networks' },
        { icon: 'download', text: 'Only install apps from trusted sources (Google Play Store)' }
    ];
    
    if (threats.some(t => t.severity === 'CRITICAL')) {
        recommendations.unshift({ icon: 'exclamation-triangle', text: 'CRITICAL: Immediately investigate and isolate the device' });
    }
    
    container.innerHTML = recommendations.map(r => `
        <div class="alert alert-info mb-2">
            <i class="fas fa-${r.icon} me-2"></i>${r.text}
        </div>
    `).join('');
}

function clearThreats() {
    document.getElementById('threatsList').innerHTML = '';
    document.getElementById('threatsList').style.display = 'none';
    document.getElementById('noThreats').style.display = 'block';
    document.getElementById('threatSummary').style.display = 'none';
    document.getElementById('recommendationsCard').style.display = 'none';
}

function exportThreatReport() {
    showToast('Generating threat report...', 'info');
    exportFullReport();
}
}
</script>

<?php require_once '../includes/footer.php'; ?>