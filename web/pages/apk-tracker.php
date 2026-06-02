<?php
/**
 * APK Hunter - Sideload Tracker
 * Tracks APK downloads, installations, and sideloaded apps
 */
$pageTitle = 'APK Hunter - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Helper to load JSON
function loadAPKData() {
    global $logsPath;
    $possiblePaths = [
        getLogsPath() . '/apk_analysis.json',
        dirname(dirname(__DIR__)) . '/logs/apk_analysis.json'
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
    }
    return null;
}

$data = loadAPKData();
$totalEvents = $data['summary']['total_events'] ?? 0;
$sideloads = $data['summary']['sideloads_detected'] ?? 0;
$events = $data['events'] ?? [];

// Auto-run if missing
$shouldAutoRun = ($data === null);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><i class="fas fa-file-archive me-2 text-primary"></i>APK Hunter</h3>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-end">
                        <button class="btn btn-primary" onclick="runAPKScan()" id="scanBtn">
                            <i class="fas fa-search me-1"></i> Run APK Scan
                        </button>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Info Alert -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Module Scope:</strong> This tool tracks the lifecycle of <code>.apk</code> files: from browser download → file execution → package installation.
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-6 col-md-6">
                    <div class="small-box text-bg-primary">
                        <div class="inner">
                            <h3><?= $totalEvents ?></h3>
                            <p>Total APK Events</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="small-box-footer">
                            Downloads, Opens, and Installs detected
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="small-box <?= $sideloads > 0 ? 'text-bg-danger' : 'text-bg-success' ?>">
                        <div class="inner">
                            <h3><?= $sideloads ?></h3>
                            <p>High Risk Sideloads</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="small-box-footer">
                            Installations from unknown sources (not Play Store)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list-ul me-2"></i>APK Movement Timeline</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <?php if (empty($events)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-3">No APK movement events detected in current logs.</p>
                                    <p class="text-muted"><small>This is a positive security indicator - no sideloading activity found.</small></p>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Stage</th>
                                            <th>Timestamp</th>
                                            <th>Details</th>
                                            <th>Source</th>
                                            <th>Risk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $badgeClass = 'secondary';
                                                    $icon = 'fa-circle';
                                                    if ($event['stage'] == 'INSTALLATION') {
                                                        $badgeClass = 'success';
                                                        $icon = 'fa-check-circle';
                                                    }
                                                    if ($event['stage'] == 'DOWNLOAD_ATTEMPT') {
                                                        $badgeClass = 'info';
                                                        $icon = 'fa-download';
                                                    }
                                                    if ($event['stage'] == 'MANUAL_OPEN') {
                                                        $badgeClass = 'warning';
                                                        $icon = 'fa-folder-open';
                                                    }
                                                    if ($event['stage'] == 'SIDELOADED_APP') {
                                                        $badgeClass = 'danger';
                                                        $icon = 'fa-exclamation-triangle';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $badgeClass ?>">
                                                        <i class="fas <?= $icon ?> me-1"></i><?= htmlspecialchars($event['stage']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($event['timestamp']) ?></td>
                                                <td><?= htmlspecialchars($event['details']) ?></td>
                                                <td><?= htmlspecialchars($event['source']) ?></td>
                                                <td>
                                                    <?php if($event['risk'] == 'CRITICAL'): ?>
                                                        <span class="badge bg-danger">CRITICAL</span>
                                                    <?php elseif($event['risk'] == 'HIGH'): ?>
                                                        <span class="badge bg-warning">HIGH</span>
                                                    <?php elseif($event['risk'] == 'MEDIUM'): ?>
                                                        <span class="badge bg-info">MEDIUM</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">LOW</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function runAPKScan() {
    const btn = document.getElementById('scanBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
    
    fetch('../api/run_apk_scan.php')
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search me-1"></i> Run APK Scan';
            }
        })
        .catch(e => {
            console.error(e);
            alert('Network Error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i> Run APK Scan';
        });
}

<?php if($shouldAutoRun): ?>
document.addEventListener('DOMContentLoaded', () => {
    runAPKScan();
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
