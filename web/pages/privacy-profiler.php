<?php
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'App Privacy Profiler - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$privacyFile = $logsPath . '/privacy_profile.json';

// Debug: Check if file exists
$fileExists = file_exists($privacyFile);
$fileContent = $fileExists ? file_get_contents($privacyFile) : null;

if ($fileExists && $fileContent) {
    $privacyData = json_decode($fileContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $privacyData = [
            "location" => [], "camera" => [], "microphone" => [], "contacts" => [], 
            "biometrics" => [], "clipboard" => [], "storage" => [], "phone_state" => [],
            "sms" => [], "calendar" => [], "sensors" => [], "body_sensors" => [],
            "summary" => []
        ];
        $jsonError = json_last_error_msg();
    } else {
        // Build summary if it doesn't exist by counting array lengths
        if (!isset($privacyData['summary']) || empty($privacyData['summary'])) {
            $privacyData['summary'] = [
                'location' => count($privacyData['location'] ?? []),
                'camera' => count($privacyData['camera'] ?? []),
                'microphone' => count($privacyData['microphone'] ?? []),
                'contacts' => count($privacyData['contacts'] ?? []),
                'biometrics' => count($privacyData['biometrics'] ?? []),
                'clipboard' => count($privacyData['clipboard'] ?? []),
                'storage' => count($privacyData['storage'] ?? []),
                'phone_state' => count($privacyData['phone_state'] ?? []),
                'sms' => count($privacyData['sms'] ?? []),
                'calendar' => count($privacyData['calendar'] ?? []),
                'sensors' => count($privacyData['sensors'] ?? []),
                'body_sensors' => count($privacyData['body_sensors'] ?? []),
            ];
        }
    }
} else {
    $privacyData = [
        "location" => [], "camera" => [], "microphone" => [], "contacts" => [], 
        "biometrics" => [], "clipboard" => [], "storage" => [], "phone_state" => [],
        "sms" => [], "calendar" => [], "sensors" => [], "body_sensors" => [],
        "summary" => []
    ];
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-user-secret me-2 text-danger"></i>App Privacy Profiler</h3>
            <p class="text-muted small">Tracking sensor and data permission usage across applications</p>

            <?php if (DEBUG_MODE && isset($fileExists)): ?>
                <div class="alert alert-info alert-dismissible fade show mt-2" role="alert">
                    <strong>Debug Info:</strong>
                    File: <?= $privacyFile ?><br>
                    Exists: <?= $fileExists ? 'Yes' : 'No' ?><br>
                    <?php if ($fileExists): ?>
                        Size: <?= filesize($privacyFile) ?> bytes<br>
                        Summary counts: Location=<?= $privacyData['summary']['location'] ?? 0 ?>,
                        Camera=<?= $privacyData['summary']['camera'] ?? 0 ?>,
                        Microphone=<?= $privacyData['summary']['microphone'] ?? 0 ?>,
                        Biometrics=<?= $privacyData['summary']['biometrics'] ?? 0 ?>
                    <?php endif; ?>
                    <?php if (isset($jsonError)): ?>
                        <br><span class="text-danger">JSON Error: <?= $jsonError ?></span>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Metrics Row -->
            <div class="row mb-4">
                <?php
                $cards = [
                    'location' => ['icon' => 'map-marker-alt', 'color' => 'primary', 'label' => 'Location'],
                    'camera' => ['icon' => 'camera', 'color' => 'danger', 'label' => 'Camera'],
                    'microphone' => ['icon' => 'microphone', 'color' => 'warning', 'label' => 'Microphone'],
                    'biometrics' => ['icon' => 'fingerprint', 'color' => 'success', 'label' => 'Biometrics'],
                    'contacts' => ['icon' => 'address-book', 'color' => 'info', 'label' => 'Contacts'],
                    'clipboard' => ['icon' => 'clipboard', 'color' => 'secondary', 'label' => 'Clipboard'],
                    'storage' => ['icon' => 'hdd', 'color' => 'dark', 'label' => 'Storage'],
                    'phone_state' => ['icon' => 'phone', 'color' => 'primary', 'label' => 'Phone State'],
                    'sms' => ['icon' => 'sms', 'color' => 'success', 'label' => 'SMS/MMS'],
                    'calendar' => ['icon' => 'calendar', 'color' => 'info', 'label' => 'Calendar'],
                    'sensors' => ['icon' => 'satellite-dish', 'color' => 'warning', 'label' => 'Sensors'],
                    'body_sensors' => ['icon' => 'heartbeat', 'color' => 'danger', 'label' => 'Body Sensors']
                ];
                foreach ($cards as $key => $meta):
                    ?>
                    <div class="col-md-3 col-lg-2 mb-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-2 d-flex align-items-center">
                                <div
                                    class="rounded-circle bg-<?= $meta['color'] ?>-subtle text-<?= $meta['color'] ?> p-2 me-2">
                                    <i class="fas fa-<?= $meta['icon'] ?> fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-0 small"><?= $meta['label'] ?></h6>
                                    <h5 class="mb-0 fw-bold"><?= $privacyData['summary'][$key] ?? 0 ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row">
                <?php foreach (['location', 'camera', 'microphone', 'contacts', 'biometrics', 'clipboard', 'storage', 'phone_state', 'sms', 'calendar', 'sensors', 'body_sensors'] as $key): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-header border-0 bg-transparent">
                                <h5 class="card-title fw-bold text-uppercase small text-muted">
                                    <?= str_replace('_', ' ', $key) ?> Access Details
                                </h5>
                            </div>
                            <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                                <?php if (empty($privacyData[$key])): ?>
                                    <div class="text-center p-5">
                                        <i class="fas fa-shield-alt fa-3x text-success mb-2"></i>
                                        <p class="text-muted mb-0">No suspicious access detected</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($privacyData[$key] as $entry): ?>
                                            <div class="list-group-item bg-transparent border-bottom border-light-subtle py-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="badge bg-info-subtle text-info fw-bold">
                                                        <i class="fas fa-cube me-1"></i> <?= htmlspecialchars($entry['package']) ?>
                                                    </span>
                                                </div>
                                                <code class="small text-secondary"><?= htmlspecialchars($entry['content']) ?></code>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>