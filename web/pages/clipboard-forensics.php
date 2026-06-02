<?php
$pageTitle = 'Clipboard & Input Reconstruction - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$clipboardData = ["clipboard_events" => [], "ime_events" => [], "summary" => []];
$clipboardFile = $logsPath . '/clipboard_forensics.json';
if (file_exists($clipboardFile)) {
    $clipboardData = json_decode(file_get_contents($clipboardFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-clipboard me-2 text-warning"></i>Clipboard & Input Reconstruction</h3>
            <p class="text-muted small">Recovering transient clipboard data and input method activity</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Android 10+ Limitation Alert -->
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-lock me-2"></i>
                <strong>Android 10+ Restriction:</strong> Android 10 and above restrict clipboard content logging for
                privacy. Only clipboard access events may be visible. Actual clipboard content is blocked by the
                operating system.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <!-- Alert for sensitive data -->
            <?php if (
                ($clipboardData['summary']['sensitive_clipboard_events'] ?? 0) > 0 ||
                ($clipboardData['summary']['sensitive_ime_events'] ?? 0) > 0
            ): ?>
                <div class="alert alert-warning shadow-sm border-0 mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-0 fw-bold">Sensitive Data Detected</h5>
                            <p class="mb-0 small opacity-75">
                                <?= ($clipboardData['summary']['sensitive_clipboard_events'] ?? 0) ?> clipboard and
                                <?= ($clipboardData['summary']['sensitive_ime_events'] ?? 0) ?> input events contain
                                potential passwords, OTPs, or payment information.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-warning">
                            <i class="fas fa-clipboard"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Clipboard Events</span>
                            <span class="info-box-number">
                                <?= $clipboardData['summary']['total_clipboard_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-info">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">IME Events</span>
                            <span class="info-box-number">
                                <?= $clipboardData['summary']['total_ime_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-danger">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Sensitive Clipboard</span>
                            <span class="info-box-number">
                                <?= $clipboardData['summary']['sensitive_clipboard_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-danger">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Sensitive Input</span>
                            <span class="info-box-number">
                                <?= $clipboardData['summary']['sensitive_ime_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clipboard Events -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard me-2"></i>Clipboard Data Recovery
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clipboardTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Content</th>
                                    <th>Source Package</th>
                                    <th>Sensitivity</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clipboardData['clipboard_events'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No clipboard events recovered.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clipboardData['clipboard_events'] as $idx => $event): ?>
                                        <tr class="<?= $event['is_sensitive'] ? 'table-danger' : '' ?>">
                                            <td class="small text-nowrap">
                                                <?= date('Y-m-d H:i:s', strtotime($event['timestamp'])) ?>
                                            </td>
                                            <td>
                                                <code
                                                    class="<?= $event['is_sensitive'] ? 'text-danger fw-bold' : 'text-primary' ?>">
                                                                <?= htmlspecialchars($event['content']) ?>
                                                            </code>
                                            </td>
                                            <td><small class="text-muted">
                                                    <?= htmlspecialchars($event['package']) ?>
                                                </small></td>
                                            <td>
                                                <?php if ($event['is_sensitive']): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i>
                                                        HIGH</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#clip-raw-<?= $idx ?>">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <div class="collapse mt-2" id="clip-raw-<?= $idx ?>">
                                                    <div class="card card-body bg-dark p-2">
                                                        <code
                                                            class="small text-light"><?= htmlspecialchars($event['raw']) ?></code>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- IME Events -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-keyboard me-2"></i>Input Method Activity
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="imeTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Event Type</th>
                                    <th>Content/Length</th>
                                    <th>Sensitivity</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clipboardData['ime_events'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No IME events recovered.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clipboardData['ime_events'] as $idx => $event): ?>
                                        <tr class="<?= $event['is_sensitive'] ? 'table-danger' : '' ?>">
                                            <td class="small text-nowrap">
                                                <?= date('Y-m-d H:i:s', strtotime($event['timestamp'])) ?>
                                            </td>
                                            <td><span class="badge bg-primary">
                                                    <?= $event['event_type'] ?>
                                                </span></td>
                                            <td>
                                                <code class="<?= $event['is_sensitive'] ? 'text-danger fw-bold' : '' ?>">
                                                                <?= htmlspecialchars($event['content']) ?>
                                                            </code>
                                            </td>
                                            <td>
                                                <?php if ($event['is_sensitive']): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i>
                                                        HIGH</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#ime-raw-<?= $idx ?>">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <div class="collapse mt-2" id="ime-raw-<?= $idx ?>">
                                                    <div class="card card-body bg-dark p-2">
                                                        <code
                                                            class="small text-light"><?= htmlspecialchars($event['raw']) ?></code>
                                                    </div>
                                                </div>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if ($.fn.DataTable) {
            $('#clipboardTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
            $('#imeTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>