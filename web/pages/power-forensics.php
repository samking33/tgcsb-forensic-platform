<?php
$pageTitle = 'Device Habit & Power Forensics - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$powerData = [];
$powerFile = $logsPath . '/power_forensics.json';
if (file_exists($powerFile)) {
    $powerData = json_decode(file_get_contents($powerFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-battery-half me-2 text-warning"></i>Device Habit & Power Forensics</h3>
            <p class="text-muted small">Reconstructing physical user activity through screen and power states</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Timeline Visualization -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header border-0">
                            <h5 class="card-title">Activity Timeline</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="powerTimelineChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header border-0 bg-success text-white">
                            <h5 class="card-title text-white">Usage Stats</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php
                                $counts = array_count_values(array_column($powerData, 'event'));
                                foreach ($counts as $event => $count):
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <?= str_replace('_', ' ', $event) ?>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= $count ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Log -->
            <div class="card shadow-sm border-0">
                <div class="card-header border-0">
                    <h5 class="card-title">Detailed Power Events</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Event Type</th>
                                    <th>Raw Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($powerData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">No power events found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_reverse($powerData) as $event): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <?= date('H:i:s Y-m-d', strtotime($event['timestamp'])) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = 'secondary';
                                                if (strpos($event['event'], 'SCREEN_ON') !== false)
                                                    $badgeClass = 'success';
                                                if (strpos($event['event'], 'SCREEN_OFF') !== false)
                                                    $badgeClass = 'dark';
                                                if (strpos($event['event'], 'PLUGGED') !== false)
                                                    $badgeClass = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $badgeClass ?>">
                                                    <?= $event['event'] ?>
                                                </span>
                                            </td>
                                            <td><code
                                                    class="small text-muted"><?= htmlspecialchars(substr($event['raw'], 0, 100)) ?></code>
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
        const rawData = <?= json_encode($powerData) ?>;

        // Prepare chart data (simple scatter/timeline)
        // Map event types to Y-axis values
        const eventMap = {
            'SCREEN_ON': 3,
            'USER_PRESENT': 3,
            'SCREEN_OFF': 2,
            'PLUGGED_AC': 1,
            'PLUGGED_USB': 1,
            'UNPLUGGED': 0,
            'SHUTDOWN': 0
        };

        const datasets = [{
            label: 'Device State',
            data: rawData.map(e => ({
                x: e.timestamp,
                y: eventMap[e.event] !== undefined ? eventMap[e.event] : 2,
                event: e.event
            })),
            borderColor: '#f59e0b',
            backgroundColor: '#f59e0b',
            showLine: false,
            pointRadius: 5
        }];

        const ctx = document.getElementById('powerTimelineChart').getContext('2d');
        new Chart(ctx, {
            type: 'scatter',
            data: { datasets: datasets },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: { unit: 'hour' },
                        title: { display: true, text: 'Time' }
                    },
                    y: {
                        ticks: {
                            callback: function (value) {
                                if (value === 3) return 'Active (On)';
                                if (value === 2) return 'Standby (Off)';
                                if (value === 1) return 'Charging';
                                if (value === 0) return 'Offline';
                                return '';
                            }
                        },
                        min: -0.5,
                        max: 3.5
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.raw.event + ': ' + context.label;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>