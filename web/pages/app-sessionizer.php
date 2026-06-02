<?php
$pageTitle = 'App Usage Sessionizer - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logsPath = getLogsPath();
$sessionData = ["sessions" => [], "app_statistics" => [], "summary" => []];
$sessionFile = $logsPath . '/app_sessions.json';
if (file_exists($sessionFile)) {
    $sessionData = json_decode(file_get_contents($sessionFile), true);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-hourglass-half me-2 text-info"></i>App Usage Sessionizer</h3>
            <p class="text-muted small">Forensic screen time analysis with precise app usage durations</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-info">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Unique Apps</span>
                            <span class="info-box-number"><?= $sessionData['summary']['unique_apps'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <div class="info-box-icon bg-success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Sessions</span>
                            <span class="info-box-number"><?= $sessionData['summary']['total_sessions'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box">
                        <div class="info-box-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Screen Time</span>
                            <span class="info-box-number"><?= $sessionData['summary']['total_usage_time_human'] ?? '0s' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Chart -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header border-0">
                    <h5 class="card-title">Top Apps by Usage Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="usageChart" height="120"></canvas>
                </div>
            </div>

            <!-- App Statistics Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-chart-bar me-2"></i>App Usage Statistics
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="statsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>App Package</th>
                                    <th>Total Duration</th>
                                    <th>Sessions</th>
                                    <th>Avg Session</th>
                                    <th>First Use</th>
                                    <th>Last Use</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sessionData['app_statistics'])): ?>
                                    <tr><td colspan="6" class="text-center py-4">No app usage data available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($sessionData['app_statistics'] as $app): ?>
                                    <tr>
                                        <td><strong class="text-primary"><?= htmlspecialchars($app['package']) ?></strong></td>
                                        <td><span class="badge bg-success"><?= $app['total_duration_human'] ?></span></td>
                                        <td><?= $app['session_count'] ?></td>
                                        <td><?= $app['avg_session_duration_human'] ?></td>
                                        <td class="small text-muted"><?= date('H:i:s', strtotime($app['first_use'])) ?></td>
                                        <td class="small text-muted"><?= date('H:i:s', strtotime($app['last_use'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detailed Sessions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-list me-2"></i>Detailed Session Log
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="sessionsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th>App</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sessionData['sessions'])): ?>
                                    <tr><td colspan="4" class="text-center py-4">No sessions recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_reverse($sessionData['sessions']) as $session): ?>
                                    <tr>
                                        <td><code class="small"><?= htmlspecialchars($session['package']) ?></code></td>
                                        <td class="small"><?= date('Y-m-d H:i:s', strtotime($session['start_time'])) ?></td>
                                        <td class="small"><?= date('Y-m-d H:i:s', strtotime($session['end_time'])) ?></td>
                                        <td><span class="badge bg-primary"><?= $session['duration_human'] ?></span></td>
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
document.addEventListener('DOMContentLoaded', function() {
    // Usage Chart
    const appStats = <?= json_encode($sessionData['app_statistics'] ?? []) ?>;
    
    if (appStats.length > 0) {
        const topApps = appStats.slice(0, 10); // Top 10 apps
        
        const ctx = document.getElementById('usageChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topApps.map(app => app.package.split('.').slice(-1)[0]), // Short name
                datasets: [{
                    label: 'Usage Time (seconds)',
                    data: topApps.map(app => app.total_duration),
                    backgroundColor: 'rgba(34, 211, 238, 0.6)',
                    borderColor: 'rgba(34, 211, 238, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value < 60) return value + 's';
                                else if (value < 3600) return Math.floor(value / 60) + 'm';
                                else return Math.floor(value / 3600) + 'h';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const seconds = context.raw;
                                let formatted;
                                if (seconds < 60) formatted = seconds + 's';
                                else if (seconds < 3600) formatted = Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
                                else formatted = Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
                                return 'Usage: ' + formatted;
                            }
                        }
                    }
                }
            }
        });
    }

    // DataTables
    if ($.fn.DataTable) {
        $('#statsTable').DataTable({
            order: [[1, 'desc']],
            pageLength: 25
        });
        $('#sessionsTable').DataTable({
            order: [[1, 'desc']],
            pageLength: 50
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
