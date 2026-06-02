<?php
$pageTitle = 'Network Intelligence - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$networkData = [];
$networkFile = $logsPath . '/network_activity.json';
if (file_exists($networkFile)) {
    $networkData = json_decode(file_get_contents($networkFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-globe-americas me-2 text-info"></i>Network Intelligence</h3>
            <p class="text-muted small">Semantic analysis of external IP and Domain connections</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header border-0">
                    <h5 class="card-title fw-bold">External Connections</h5>
                    <div class="card-tools">
                        <span class="badge bg-primary">
                            <?= count($networkData) ?> Unique Hosts
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th class="ps-4">Host Type</th>
                                    <th>Address/Domain</th>
                                    <th class="text-center">Hit Count</th>
                                    <th class="pe-4">Last Context Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($networkData)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <i class="fas fa-network-wired fa-3x text-muted mb-2"></i>
                                            <p class="text-muted">No external connections identified in the current log set.
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($networkData as $conn): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span
                                                    class="badge bg-<?= $conn['type'] == 'IP' ? 'info' : 'primary' ?> rounded-pill">
                                                    <?= htmlspecialchars($conn['type']) ?>
                                                </span>
                                            </td>
                                            <td><strong class="text-primary">
                                                    <?= htmlspecialchars($conn['value']) ?>
                                                </strong></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary rounded-pill px-3">
                                                    <?= $conn['hits'] ?>
                                                </span>
                                            </td>
                                            <td class="pe-4">
                                                <code
                                                    class="small text-muted"><?= htmlspecialchars(substr($conn['last_context'], 0, 100)) ?>...</code>
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