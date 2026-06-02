<?php
/**
 * Threat Scanner - Stalkerware & Spyware Detector
 */

// Prevent caching to ensure fresh threat data is always displayed
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

$pageTitle = 'Threat Scanner - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Helper to load JSON
function loadThreatData() {
    global $logsPath;
    $possiblePaths = [
        getLogsPath() . '/threat_report.json',
        dirname(dirname(__DIR__)) . '/logs/threat_report.json'
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
    }
    return null;
}

$data = loadThreatData();
$riskLevel = $data['risk_level'] ?? 'UNKNOWN';
$riskScore = $data['risk_score'] ?? 0;
$threats = $data['threats'] ?? [];

// Sort threats by severity: HIGH > MEDIUM > LOW
if (!empty($threats)) {
    usort($threats, function($a, $b) {
        $severityOrder = ['HIGH' => 3, 'MEDIUM' => 2, 'LOW' => 1];
        $aSeverity = $severityOrder[$a['severity'] ?? 'LOW'] ?? 0;
        $bSeverity = $severityOrder[$b['severity'] ?? 'LOW'] ?? 0;
        
        // Sort descending (highest severity first)
        if ($bSeverity !== $aSeverity) {
            return $bSeverity - $aSeverity;
        }
        
        // If same severity, sort alphabetically by type
        return strcmp($a['type'] ?? '', $b['type'] ?? '');
    });
}

// Auto-run if missing
$shouldAutoRun = ($data === null);

// Color Logic
$bgClass = 'success';
if ($riskLevel === 'CRITICAL') $bgClass = 'danger';
if ($riskLevel === 'HIGH') $bgClass = 'warning';
if ($riskLevel === 'MEDIUM') $bgClass = 'info';

?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><i class="fas fa-user-secret me-2 text-<?= $bgClass ?>"></i>Threat Scanner</h3>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-end">
                        <button class="btn btn-primary" onclick="runThreatScan()" id="scanBtn">
                            <i class="fas fa-radar me-1"></i> Run Deep Scan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Alert Banner -->
            <div class="row mb-4">
                <div class="col-12">
                     <div class="card bg-<?= $bgClass ?> text-white shadow">
                        <div class="card-body text-center">
                            <h1 class="display-3 fw-bold mb-0">
                                <?= $riskLevel ?> RISK
                            </h1>
                            <p class="lead">Threat Score: <?= $riskScore ?>/100</p>
                            <?php if(empty($threats)): ?>
                                <p class="mb-0"><i class="fas fa-check-circle"></i> No active spyware behavior detected.</p>
                            <?php else: ?>
                                <p class="mb-0"><i class="fas fa-exclamation-triangle"></i> <?= count($threats) ?> suspicious indicators found.</p>
                            <?php endif; ?>
                        </div>
                     </div>
                </div>
            </div>

            <!-- Threat List -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list-ul me-2"></i>Detected Anomalies</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Severity</th>
                                        <th>Threat Type</th>
                                        <th>Package Name</th>
                                        <th>Detection Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($threats)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                No threats detected. System appears clean.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($threats as $threat): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $threat['severity'] === 'HIGH' ? 'danger' : ($threat['severity'] === 'MEDIUM' ? 'warning' : 'info') ?>">
                                                        <?= $threat['severity'] ?>
                                                    </span>
                                                </td>
                                                <td><strong><?= htmlspecialchars($threat['type']) ?></strong></td>
                                                <td class="font-monospace"><?= htmlspecialchars($threat['package']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($threat['detail']) ?>
                                                    <br>
                                                    <small class="text-muted">Evidence: <?= htmlspecialchars($threat['evidence']) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function runThreatScan() {
    const btn = document.getElementById('scanBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
    
    // Add timestamp to prevent caching
    const cacheBuster = '?t=' + new Date().getTime();
    
    fetch('../api/run_threat_scan.php' + cacheBuster)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Force hard reload to bypass cache
                window.location.href = window.location.href.split('?')[0] + '?refreshed=' + new Date().getTime();
            } else {
                alert('Error: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-radar me-1"></i> Run Deep Scan';
            }
        })
        .catch(e => {
            console.error(e);
            alert('Network Error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-radar me-1"></i> Run Deep Scan';
        });
}

<?php if($shouldAutoRun): ?>
document.addEventListener('DOMContentLoaded', () => {
    runThreatScan();
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
