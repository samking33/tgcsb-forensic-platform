<?php
$pageTitle = 'PII Leak Detector - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$leakData = [];
$leakFile = $logsPath . '/pii_leaks.json';
if (file_exists($leakFile)) {
    $leakData = json_decode(file_get_contents($leakFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-biohazard me-2 text-warning"></i>PII Leak Detector</h3>
            <p class="text-muted small">Heuristic scanner for personally identifiable information in system logs</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-0 fw-bold">Critical Information Found</h5>
                        <p class="mb-0 small opacity-75">Applications and system services may be leaking credentials or tracking data to the global logcat buffer.</p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header border-0">
                    <h5 class="card-title fw-bold">Detected Vulnerabilities</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th class="ps-4">Leak Type</th>
                                    <th>Extracted Value</th>
                                    <th>Line #</th>
                                    <th class="pe-4">Original Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leakData)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                                <h5>Log Sanitization Analysis Passed</h5>
                                                <p class="text-muted">No high-risk PII patterns were identified in the current logcat dump.</p>
                                            </td>
                                        </tr>
                                <?php else: ?>
                                        <?php foreach ($leakData as $leak): ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <span class="badge bg-danger rounded-pill px-3">
                                                            <?= htmlspecialchars($leak['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><code class="text-danger fw-bold"><?= htmlspecialchars($leak['value']) ?></code></td>
                                                    <td><span class="text-muted"><?= $leak['line'] ?></span></td>
                                                    <td class="pe-4">
                                                        <small class="text-muted"><code><?= htmlspecialchars($leak['content']) ?></code></small>
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
</main>

<?php require_once '../includes/footer.php'; ?>
