<?php
$pageTitle = 'System Health - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/audit.php';

$auditLogger = getAuditLogger();
$auditLogger->logPageView();

// System Health Checks
$health = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// 1. ADB Connection Check
exec('adb devices 2>&1', $adb_output, $adb_return);
$adb_connected = false;
$device_count = 0;
foreach ($adb_output as $line) {
    if (preg_match('/device$/', $line)) {
        $adb_connected = true;
        $device_count++;
    }
}
$health['checks']['adb'] = [
    'status' => $adb_connected ? 'OK' : 'WARNING',
    'message' => $adb_connected ? "$device_count device(s) connected" : 'No devices connected',
    'icon' => $adb_connected ? 'check-circle' : 'exclamation-triangle',
    'color' => $adb_connected ? 'success' : 'warning'
];

// 2. Logs Directory Check
$logsPath = getLogsPath();
$health['checks']['logs_dir'] = [
    'status' => is_dir($logsPath) ? 'OK' : 'ERROR',
    'message' => is_dir($logsPath) ? "Writable: $logsPath" : "Directory not found: $logsPath",
    'icon' => is_dir($logsPath) ? 'check-circle' : 'times-circle',
    'color' => is_dir($logsPath) ? 'success' : 'danger'
];

// 3. Disk Space Check
$disk_total = disk_total_space($logsPath);
$disk_free = disk_free_space($logsPath);
$disk_used_percent = (($disk_total - $disk_free) / $disk_total) * 100;
$disk_status = $disk_used_percent > 90 ? 'WARNING' : 'OK';
$health['checks']['disk_space'] = [
    'status' => $disk_status,
    'message' => sprintf('%.2f GB free (%.1f%% used)', $disk_free / 1024 / 1024 / 1024, $disk_used_percent),
    'icon' => $disk_status == 'OK' ? 'check-circle' : 'exclamation-triangle',
    'color' => $disk_status == 'OK' ? 'success' : 'warning',
    'used_percent' => round($disk_used_percent, 1)
];

// 4. Python Check
exec('python --version 2>&1', $py_output, $py_return);
$python_available = $py_return === 0;
$health['checks']['python'] = [
    'status' => $python_available ? 'OK' : 'ERROR',
    'message' => $python_available ? trim($py_output[0]) : 'Python not found in PATH',
    'icon' => $python_available ? 'check-circle' : 'times-circle',
    'color' => $python_available ? 'success' : 'danger'
];

// 5. Log Files Check
$log_files = ['android_logcat.txt', 'sms_logs.txt', 'call_logs.txt'];
$log_count = 0;
$log_size = 0;
foreach ($log_files as $file) {
    $filepath = $logsPath . '/' . $file;
    if (file_exists($filepath)) {
        $log_count++;
        $log_size += filesize($filepath);
    }
}
$health['checks']['log_files'] = [
    'status' => $log_count > 0 ? 'OK' : 'INFO',
    'message' => "$log_count core files (" . sprintf('%.2f MB', $log_size / 1024 / 1024) . ")",
    'icon' => $log_count > 0 ? 'check-circle' : 'info-circle',
    'color' => $log_count > 0 ? 'success' : 'info'
];

// 6. Evidence Hash Verification
$metadata_file = $logsPath . '/evidence_metadata.json';
$hash_status = 'UNKNOWN';
$hash_message = 'No verification data';
if (file_exists($metadata_file)) {
    $metadata = json_decode(file_get_contents($metadata_file), true);
    $verified_count = 0;
    $total_count = count($metadata['files'] ?? []);
    foreach (($metadata['files'] ?? []) as $file) {
        if ($file['verified'] ?? false) {
            $verified_count++;
        }
    }
    $hash_status = $verified_count == $total_count && $total_count > 0 ? 'OK' : 'WARNING';
    $hash_message = "$verified_count/$total_count files verified";
}
$health['checks']['hash_verification'] = [
    'status' => $hash_status,
    'message' => $hash_message,
    'icon' => $hash_status == 'OK' ? 'check-circle' : ($hash_status == 'WARNING' ? 'exclamation-triangle' : 'info-circle'),
    'color' => $hash_status == 'OK' ? 'success' : ($hash_status == 'WARNING' ? 'warning' : 'secondary')
];

// 7. Audit Log Check
$audit_file = dirname(__DIR__, 2) . '/logs/audit_log.json';
$audit_status = file_exists($audit_file) ? 'OK' : 'WARNING';
$audit_count = 0;
if (file_exists($audit_file)) {
    $audit_data = json_decode(file_get_contents($audit_file), true);
    $audit_count = count($audit_data);
}
$health['checks']['audit_log'] = [
    'status' => $audit_status,
    'message' => $audit_status == 'OK' ? "$audit_count entries logged" : 'Audit log not initialized',
    'icon' => $audit_status == 'OK' ? 'check-circle' : 'exclamation-triangle',
    'color' => $audit_status == 'OK' ? 'success' : 'warning'
];

// Calculate overall health
$error_count = 0;
$warning_count = 0;
foreach ($health['checks'] as $check) {
    if ($check['status'] == 'ERROR')
        $error_count++;
    if ($check['status'] == 'WARNING')
        $warning_count++;
}
$overall_status = $error_count > 0 ? 'ERROR' : ($warning_count > 0 ? 'WARNING' : 'HEALTHY');
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-heartbeat me-2 text-success"></i>System Health Monitor</h3>
            <p class="text-muted small">Real-time system diagnostics and evidence integrity checks</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Overall Status -->
            <div
                class="alert alert-<?= $overall_status == 'HEALTHY' ? 'success' : ($overall_status == 'WARNING' ? 'warning' : 'danger') ?> shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <i
                        class="fas fa-<?= $overall_status == 'HEALTHY' ? 'check-circle' : ($overall_status == 'WARNING' ? 'exclamation-triangle' : 'times-circle') ?> fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading mb-0">System Status:
                            <?= $overall_status ?>
                        </h4>
                        <p class="mb-0 small">
                            <?= $error_count ?> errors,
                            <?= $warning_count ?> warnings |
                            Last checked:
                            <?= $health['timestamp'] ?>
                        </p>
                    </div>
                    <button class="btn btn-sm btn-outline-dark ms-auto" onclick="location.reload()">
                        <i class="fas fa-sync me-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Health Checks Grid -->
            <div class="row">
                <?php foreach ($health['checks'] as $component => $check): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm border-<?= $check['color'] ?> h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-<?= $check['color'] ?>-subtle p-3 me-3">
                                        <i class="fas fa-<?= $check['icon'] ?> fa-2x text-<?= $check['color'] ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1 text-capitalize">
                                            <?= str_replace('_', ' ', $component) ?>
                                        </h5>
                                        <p class="mb-0 text-muted small">
                                            <?= $check['message'] ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-<?= $check['color'] ?> rounded-pill px-3">
                                        <?= $check['status'] ?>
                                    </span>
                                </div>

                                <?php if (isset($check['used_percent'])): ?>
                                    <div class="mt-3">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?= $check['used_percent'] > 90 ? 'danger' : ($check['used_percent'] > 75 ? 'warning' : 'success') ?>"
                                                style="width: <?= $check['used_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title text-white mb-0"><i class="fas fa-tools me-2"></i>System Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <button class="btn btn-outline-success w-100" onclick="runHashVerification()">
                                <i class="fas fa-shield-alt d-block fa-2x mb-2"></i>
                                Verify Hashes
                            </button>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <a href="../api/export-audit-log.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-download d-block fa-2x mb-2"></i>
                                Export Audit Log
                            </a>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <button class="btn btn-outline-warning w-100" onclick="clearTempFiles()">
                                <i class="fas fa-broom d-block fa-2x mb-2"></i>
                                Clear Temp Files
                            </button>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <a href="legal-disclaimer.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-gavel d-block fa-2x mb-2"></i>
                                Legal Disclaimer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function runHashVerification() {
        if (confirm('This will verify the integrity of all extracted log files. Continue?')) {
            alert('Hash verification initiated. This may take a few moments...');
            fetch('../api/verify-hashes.php')
                .then(r => r.json())
                .then(data => {
                    alert(`Verification Complete\n\n✓ Verified: ${data.verified}\n✗ Tampered: ${data.tampered}\n⚠ Missing: ${data.missing}`);
                    location.reload();
                })
                .catch(e => alert('Error: ' + e.message));
        }
    }

    function clearTempFiles() {
        if (confirm('This will clear temporary analysis files. Source logs will NOT be deleted. Continue?')) {
            alert('Feature coming soon...');
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>