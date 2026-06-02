<?php
$pageTitle = 'Intent & URL Hunter - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$intentData = [];
$intentFile = $logsPath . '/intent_hunter.json';
if (file_exists($intentFile)) {
    $intentData = json_decode(file_get_contents($intentFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-crosshairs me-2 text-danger"></i>Intent & URL Hunter</h3>
            <p class="text-muted small">Recovering user navigation, web history, and deep-link actions</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header border-0 bg-dark text-white">
                    <h5 class="card-title text-white">Captured Intents & Links</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0" id="intentTable">
                            <thead class="bg-secondary text-white">
                                <tr>
                                    <th>Type</th>
                                    <th>Action / Method</th>
                                    <th>Data / URL</th>
                                    <th>Target Component</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($intentData)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">No intents or URLs recovered in current
                                            logs.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($intentData as $item): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $item['type'] === 'INTENT' ? 'primary' : 'info' ?>">
                                                    <?= $item['type'] ?>
                                                </span>
                                            </td>
                                            <td><small class="fw-bold">
                                                    <?= htmlspecialchars(str_replace('android.intent.action.', '', $item['action'])) ?>
                                                </small></td>
                                            <td>
                                                <?php if ($item['data'] !== 'N/A'): ?>
                                                    <a href="<?= htmlspecialchars($item['data']) ?>" target="_blank"
                                                        class="text-break text-decoration-none">
                                                        <?= htmlspecialchars(substr($item['data'], 0, 60)) . (strlen($item['data']) > 60 ? '...' : '') ?>
                                                        <i class="fas fa-external-link-alt small ms-1"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted">
                                                    <?= htmlspecialchars($item['component']) ?>
                                                </small></td>
                                            <td>
                                                <button class="btn btn-xs btn-outline-secondary" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#raw-<?= $item['line'] ?>">
                                                    View Raw
                                                </button>
                                                <div class="collapse mt-2" id="raw-<?= $item['line'] ?>">
                                                    <div class="card card-body bg-light p-2 mb-0">
                                                        <code
                                                            class="small text-dark"><?= htmlspecialchars($item['raw']) ?></code>
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
        // Assuming DataTables is loaded in header/footer
        if ($.fn.DataTable) {
            $('#intentTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 25
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>