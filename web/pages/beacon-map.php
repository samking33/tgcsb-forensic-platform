<?php
$pageTitle = 'WiFi & Bluetooth Beacon Map - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$beaconData = ["wifi_networks" => [], "bluetooth_devices" => [], "summary" => []];
$beaconFile = $logsPath . '/beacon_map.json';
if (file_exists($beaconFile)) {
    $beaconData = json_decode(file_get_contents($beaconFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-broadcast-tower me-2 text-success"></i>WiFi & Bluetooth Beacon Map</h3>
            <p class="text-muted small">Location inference through network signature analysis</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-info">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">WiFi Networks</span>
                            <span class="info-box-number">
                                <?= $beaconData['summary']['total_wifi_networks'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-primary">
                            <i class="fas fa-bluetooth-b"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">BT Devices</span>
                            <span class="info-box-number">
                                <?= $beaconData['summary']['total_bluetooth_devices'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-success">
                            <i class="fas fa-signal"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">WiFi Events</span>
                            <span class="info-box-number">
                                <?= $beaconData['summary']['total_wifi_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-warning">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">BT Events</span>
                            <span class="info-box-number">
                                <?= $beaconData['summary']['total_bluetooth_events'] ?? 0 ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WiFi Networks Tab -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-wifi me-2"></i>WiFi Network Signatures
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="wifiTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>SSID</th>
                                    <th>Frequency</th>
                                    <th>First Seen</th>
                                    <th>Last Seen</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($beaconData['wifi_networks'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No WiFi networks detected in logs.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($beaconData['wifi_networks'] as $idx => $wifi): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary">
                                                    <?= htmlspecialchars($wifi['ssid']) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $wifi['count'] ?> times
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('Y-m-d H:i:s', strtotime($wifi['first_seen'])) ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('Y-m-d H:i:s', strtotime($wifi['last_seen'])) ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#wifi-ctx-<?= $idx ?>">
                                                    <i class="fas fa-eye"></i> View Context
                                                </button>
                                                <div class="collapse mt-2" id="wifi-ctx-<?= $idx ?>">
                                                    <div class="card card-body bg-dark p-2">
                                                        <?php foreach ($wifi['contexts'] as $ctx): ?>
                                                            <code
                                                                class="small text-light d-block mb-1"><?= htmlspecialchars($ctx) ?></code>
                                                        <?php endforeach; ?>
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

            <!-- Bluetooth Devices Tab -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-bluetooth-b me-2"></i>Bluetooth Device Signatures
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="btTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Device Name</th>
                                    <th>MAC Address</th>
                                    <th>Frequency</th>
                                    <th>First Seen</th>
                                    <th>Last Seen</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($beaconData['bluetooth_devices'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No Bluetooth devices detected in logs.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($beaconData['bluetooth_devices'] as $idx => $bt): ?>
                                        <tr>
                                            <td><strong>
                                                    <?= htmlspecialchars($bt['name']) ?>
                                                </strong></td>
                                            <td><code class="text-info"><?= htmlspecialchars($bt['address']) ?></code></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $bt['count'] ?> times
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('Y-m-d H:i:s', strtotime($bt['first_seen'])) ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('Y-m-d H:i:s', strtotime($bt['last_seen'])) ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#bt-ctx-<?= $idx ?>">
                                                    <i class="fas fa-eye"></i> View Context
                                                </button>
                                                <div class="collapse mt-2" id="bt-ctx-<?= $idx ?>">
                                                    <div class="card card-body bg-dark p-2">
                                                        <?php foreach ($bt['contexts'] as $ctx): ?>
                                                            <code
                                                                class="small text-light d-block mb-1"><?= htmlspecialchars($ctx) ?></code>
                                                        <?php endforeach; ?>
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
            $('#wifiTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
            $('#btTable').DataTable({
                order: [[2, 'desc']],
                pageLength: 25
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>