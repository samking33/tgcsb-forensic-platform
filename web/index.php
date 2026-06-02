<?php
/**
 * Android Forensic Tool - Dashboard
 * Main landing page with KPI stats and overview charts
 */
$pageTitle = 'Dashboard - Android Forensic Tool';
$basePath = '';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Helper for efficient counting
function countMatchesInFile($filepath, $search) {
    if (!file_exists($filepath)) return 0;
    $count = 0;
    $handle = @fopen($filepath, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $count += substr_count($line, $search);
        }
        fclose($handle);
    }
    return $count;
}

// Get stats from log files
function getStats()
{
    $logsPath = getLogsPath();
    $stats = [
        'smsCount' => 0,
        'callCount' => 0,
        'locationCount' => 0,
        'threatCount' => 0,
        'notificationCount' => 0,
        'lastExtraction' => null,
        'deviceInfo' => null,
        'logcatLines' => 0
    ];

    // Count SMS (Optimized)
    $smsFile = $logsPath . '/sms_logs.txt';
    $stats['smsCount'] = countMatchesInFile($smsFile, 'Row:');
    if (file_exists($smsFile)) {
        $stats['lastExtraction'] = date('Y-m-d H:i:s', filemtime($smsFile));
    }

    // Count Calls (Optimized)
    $callFile = $logsPath . '/call_logs.txt';
    $stats['callCount'] = countMatchesInFile($callFile, 'Row:');

    // Count Locations (Optimized)
    $locationFile = $logsPath . '/location_logs.txt';
    $stats['locationCount'] = countMatchesInFile($locationFile, 'Location[');

    // Count Logcat lines (Optimized)
    $logcatFile = $logsPath . '/android_logcat.txt';
    if (file_exists($logcatFile)) {
        $linecount = 0;
        $handle = @fopen($logcatFile, "r");
        if ($handle) {
            while(!feof($handle)){
                fgets($handle);
                $linecount++;
            }
            fclose($handle);
        }
        $stats['logcatLines'] = $linecount;
    }

    // Count Notifications
    $notifFile = $logsPath . '/notification_timeline.json';
    if (file_exists($notifFile)) {
        $content = @file_get_contents($notifFile);
        if ($content) {
            $json = json_decode($content, true);
            if (is_array($json)) {
                $stats['notificationCount'] = count($json);
            }
        }
    }

    // Check Root Status
    $rootFile = $logsPath . '/root_status.json';
    $stats['rootStatus'] = null;
    if (file_exists($rootFile)) {
        $content = @file_get_contents($rootFile);
        if ($content) {
            $stats['rootStatus'] = json_decode($content, true);
        }
    }

    return $stats;
}

$stats = getStats();
// $stats = [
//     'smsCount' => 0,
//     'callCount' => 0,
//     'locationCount' => 0,
//     'threatCount' => 0,
//     'notificationCount' => 0,
//     'lastExtraction' => '-',
//     'deviceInfo' => null,
//     'logcatLines' => 0,
//     'rootStatus' => null
// ];
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tachometer-alt me-2 text-forensic-blue"></i>Dashboard
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Welcome Alert -->
            <?php if ($stats['lastExtraction'] === null): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Welcome!</strong> Connect an Android device and click "Extract Logs" to begin forensic analysis.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- KPI Small Boxes -->
            <div class="row">
                <!-- SMS Count -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-forensic-blue">
                        <div class="inner">
                            <h3 id="totalSms"><?= number_format($stats['smsCount']) ?></h3>
                            <p>SMS Messages</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comment-sms"></i>
                        </div>
                        <a href="pages/sms-messages.php" class="small-box-footer">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Call Count -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-forensic-green">
                        <div class="inner">
                            <h3 id="totalCalls"><?= number_format($stats['callCount']) ?></h3>
                            <p>Call Records</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <a href="pages/call-logs.php" class="small-box-footer">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Location Count -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-forensic-orange">
                        <div class="inner">
                            <h3 id="totalLocations"><?= number_format($stats['locationCount']) ?></h3>
                            <p>Location Points</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <a href="pages/location.php" class="small-box-footer">
                            View on Map <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>


            </div>
            <!-- /.row -->

            <!-- Quick Actions & Device Info Row -->
            <div class="row">
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bolt me-2"></i>Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="pages/extract-logs.php" class="btn btn-forensic btn-lg">
                                    <i class="fas fa-download me-2"></i>Extract Logs
                                </a>

                                <a href="pages/live-monitor.php" class="btn btn-outline-success">
                                    <i class="fas fa-satellite-dish me-2"></i>Live Monitoring
                                </a>
                                <button class="btn btn-outline-primary" onclick="exportFullReport()">
                                    <i class="fas fa-file-pdf me-2"></i>Export Report
                                </button>
                                <button class="btn btn-outline-danger" onclick="clearAllData(event)">
                                    <i class="fas fa-trash-alt me-2"></i>Clear Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Device Info -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-mobile-alt me-2"></i>Device Information
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" onclick="checkDeviceStatus()">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="deviceInfoContainer">
                                <!-- Device Image Container -->
                                <div class="text-center mb-3">
                                    <div id="deviceImageContainer" style="position: relative; display: inline-block;">
                                        <img id="deviceImage" src="assets/images/devices/generic-phone.svg" alt="Device"
                                            style="width: 120px; height: 200px; object-fit: contain; filter: drop-shadow(0 4px 8px rgba(34, 211, 238, 0.3));"
                                            onerror="this.src='assets/images/devices/generic-phone.svg'">
                                    </div>
                                </div>

                                <!-- Device Info -->
                                <div class="text-center">
                                    <h5 class="mb-1" id="deviceModel">Checking...</h5>
                                    <p class="text-muted mb-1" id="deviceAndroid">Android Version: --</p>
                                    <p class="text-muted small mb-0" id="deviceSerial">Serial: --</p>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <span class="text-muted">Status</span>
                                        <h6 id="deviceStatus" class="text-success">
                                            <i class="fas fa-circle me-1"></i>Online
                                        </h6>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-muted">ADB</span>
                                        <h6 id="adbStatus" class="text-success">Connected</h6>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-muted">Mode</span>
                                        <h6 id="usbMode">MTP</h6>
                                    </div>
                                </div>
                                <div class="row text-center mt-3">
                                    <div class="col-12">
                                        <span class="text-muted">Root Access</span>
                                        <?php if (isset($stats['rootStatus']) && $stats['rootStatus']): ?>
                                            <?php if ($stats['rootStatus']['is_rooted']): ?>
                                                <h6 class="text-danger fw-bold">
                                                    <i class="fas fa-unlock me-1"></i>ROOTED (<?= $stats['rootStatus']['confidence'] ?> Confidence)
                                                </h6>
                                            <?php else: ?>
                                                <h6 class="text-success fw-bold">
                                                    <i class="fas fa-lock me-1"></i>NOT ROOTED
                                                </h6>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <h6 class="text-secondary">
                                                <i class="fas fa-question-circle me-1"></i>Unknown
                                            </h6>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Extraction Status -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history me-2"></i>Last Extraction
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($stats['lastExtraction']): ?>
                                <p class="mb-2">
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    <strong><?= $stats['lastExtraction'] ?></strong>
                                </p>
                                <hr>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        SMS: <?= number_format($stats['smsCount']) ?> records
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Calls: <?= number_format($stats['callCount']) ?> records
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Locations: <?= number_format($stats['locationCount']) ?> points
                                    </li>
                                    <li>
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        Logcat: <?= number_format($stats['logcatLines'] ?? 0) ?> lines
                                    </li>
                                </ul>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p class="mb-0">No logs extracted yet</p>
                                    <a href="pages/extract-logs.php" class="btn btn-sm btn-forensic mt-3">
                                        Extract Now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->

            <!-- Charts Row -->
            <div class="row">
                <!-- Call Activity Chart -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line me-2"></i>Call Activity (Last 7 Days)
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="callActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call Type Distribution -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie me-2"></i>Call Type Distribution
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="callTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->

            <!-- SMS Activity & Top Contacts Row -->
            <div class="row">
                <!-- SMS Activity -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar me-2"></i>SMS Activity (Last 7 Days)
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="smsActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Contacts -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users me-2"></i>Most Frequent Contacts
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush" id="topContactsList">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle me-2 text-forensic-blue"></i>
                                        <span class="text-muted">Loading...</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="pages/call-logs.php">View All Contacts <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->

            <!-- Logcat Summary Row -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-file-code me-2"></i>Logcat Summary by Type
                            </h3>
                            <div class="card-tools">
                                <a href="pages/logcat.php" class="btn btn-tool">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($LOG_TYPES as $type => $info): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-<?= $info['color'] ?>">
                                                <i class="<?= $info['icon'] ?>"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text"><?= $type ?></span>
                                                <span class="info-box-number" id="logcat<?= $type ?>Count">0</span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?= $info['color'] ?>" style="width: 0%"
                                                        id="logcat<?= $type ?>Progress"></div>
                                                </div>
                                                <span class="progress-description small text-muted">
                                                    <?= $info['description'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->

        </div>
    </div>
</main>

<?php
$additionalScripts = <<<'SCRIPT'
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Call Activity Chart
    createChart('callActivityChart', 'line', {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Incoming',
            data: [12, 19, 3, 5, 2, 3, 7],
            borderColor: chartColors.success,
            backgroundColor: chartColors.successBg,
            tension: 0.4,
            fill: true
        }, {
            label: 'Outgoing',
            data: [8, 11, 5, 8, 12, 5, 4],
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primaryBg,
            tension: 0.4,
            fill: true
        }]
    });
    
    // Initialize Call Type Distribution Chart
    createChart('callTypeChart', 'doughnut', {
        labels: ['Incoming', 'Outgoing', 'Missed'],
        datasets: [{
            data: [45, 35, 20],
            backgroundColor: [chartColors.success, chartColors.primary, chartColors.danger],
            borderWidth: 0
        }]
    }, {
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    });
    
    // Initialize SMS Activity Chart
    createChart('smsActivityChart', 'bar', {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Received',
            data: [28, 48, 40, 19, 86, 27, 55],
            backgroundColor: chartColors.success,
            borderRadius: 4
        }, {
            label: 'Sent',
            data: [35, 25, 35, 51, 54, 76, 45],
            backgroundColor: chartColors.primary,
            borderRadius: 4
        }]
    });
    
    // Load real stats via AJAX
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            // Update the charts with real data if available
            if (data.callsByDay) {
                // Update call chart
            }
            if (data.topContacts) {
                updateTopContacts(data.topContacts);
            }
        })
        .catch(err => console.log('Stats API not available yet'));
});

function updateTopContacts(contacts) {
    const list = document.getElementById('topContactsList');
    if (!list || !contacts.length) return;
    
    list.innerHTML = '';
    contacts.forEach((contact, index) => {
        list.innerHTML += `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-user-circle me-2 text-forensic-blue"></i>
                    ${contact.number}
                </div>
                <span class="badge bg-primary rounded-pill">${contact.count}</span>
            </li>
        `;
    });
}

function clearAllData(event) {
    // Show confirmation dialog
    if (!confirm('⚠️ WARNING: This will permanently delete all extracted log data.\n\nAre you sure you want to clear all data?')) {
        return;
    }
    
    // Show loading state
    const btn = event.target.closest('button');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing...';
    
    fetch('api/clear-data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        
        if (data.success) {
            // Show success message
            alert('✅ ' + data.message);
            
            // Reset the KPI counts on the page
            document.getElementById('totalSms').textContent = '0';
            document.getElementById('totalCalls').textContent = '0';
            document.getElementById('totalLocations').textContent = '0';
            
            // Reload the page to refresh all stats
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Failed to clear data'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        alert('❌ Error: Failed to connect to server');
        console.error('Clear data error:', err);
    });
}
</script>
SCRIPT;

require_once 'includes/footer.php';
?>