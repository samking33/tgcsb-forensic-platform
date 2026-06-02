<?php
/**
 * Android Forensic Tool - Activity Graphs Page
 * Chart.js visualizations for log analysis
 */
$pageTitle = 'Activity Graphs - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get selected time range
$timeRange = isset($_GET['range']) ? $_GET['range'] : '7d';
$minTimestamp = 0;
$now = time();

switch ($timeRange) {
    case '24h':
        $minTimestamp = $now - (24 * 3600);
        break;
    case '7d':
        $minTimestamp = $now - (7 * 24 * 3600);
        break;
    case '30d':
        $minTimestamp = $now - (30 * 24 * 3600);
        break;
    case 'all':
        $minTimestamp = 0;
        break;
}

// Helper to build number->name map
function buildContactMap($logsPath)
{
    $map = [];
    $callFile = $logsPath . '/call_logs.txt';
    if (file_exists($callFile)) {
        $lines = explode("\n", file_get_contents($callFile));
        foreach ($lines as $line) {
            // Use \b to avoid matching 'formatted_number' or 'app_name'
            // Match number and name independently on the line for safety
            if (preg_match('/\bnumber=([^,]+)/', $line, $mNum) && preg_match('/\bname=([^,]+)/', $line, $mName)) {
                $num = trim($mNum[1]);
                $name = trim($mName[1]);
                if ($name !== 'NULL' && $name !== '') {
                    $map[$num] = $name;
                }
            }
        }
    }
    return $map;
}

$logsPath = getLogsPath();
$contactMap = buildContactMap($logsPath);

// Get log data for charts
function getCallStats($minTimestamp, $contactMap)
{
    global $logsPath;
    $callFile = $logsPath . '/call_logs.txt';

    $stats = [
        'byDay' => array_fill(0, 7, 0),
        'byType' => ['Incoming' => 0, 'Outgoing' => 0, 'Missed' => 0],
        'byHour' => array_fill(0, 24, 0),
        'topCallers' => []
    ];

    if (!file_exists($callFile))
        return $stats;

    $content = file_get_contents($callFile);
    $lines = explode("\n", $content);
    $callerCounts = [];

    foreach ($lines as $line) {
        if (strpos($line, 'Row:') === false)
            continue;

        // Get date
        if (preg_match('/date=(\d+)/', $line, $match)) {
            $timestamp = (int) (intval($match[1]) / 1000);

            // Filter by time range
            if ($timestamp < $minTimestamp)
                continue;

            $dayOfWeek = date('w', $timestamp);
            $hour = date('G', $timestamp);
            $stats['byDay'][$dayOfWeek]++;
            $stats['byHour'][$hour]++;
        } else {
            continue;
        }

        // Get type
        if (preg_match('/type=(\d+)/', $line, $match)) {
            switch ($match[1]) {
                case '1':
                    $stats['byType']['Incoming']++;
                    break;
                case '2':
                    $stats['byType']['Outgoing']++;
                    break;
                case '3':
                    $stats['byType']['Missed']++;
                    break;
            }
        }

        // Get caller - STRICT MATCH
        if (preg_match('/\bnumber=([^,]+)/', $line, $match)) {
            $number = trim($match[1]);
            // Extract name directly if present in this line, else valid from map
            $name = isset($contactMap[$number]) && $contactMap[$number] !== 'NULL' ? $contactMap[$number] : $number;

            // Fallback: If line has encoded name locally
            // STRICT MATCH for name here too
            if (preg_match('/\bname=([^,]+)/', $line, $nameMatch)) {
                $rawName = trim($nameMatch[1]);
                if ($rawName !== 'NULL' && $rawName !== '') {
                    $name = $rawName;
                }
            }

            $callerCounts[$name] = ($callerCounts[$name] ?? 0) + 1;
        }
    }

    arsort($callerCounts);
    $stats['topCallers'] = array_slice($callerCounts, 0, 10, true);

    return $stats;
}

function getSmsStats($minTimestamp, $contactMap)
{
    global $logsPath;
    $smsFile = $logsPath . '/sms_logs.txt';

    $stats = [
        'byDay' => array_fill(0, 7, 0),
        'byType' => ['Received' => 0, 'Sent' => 0],
        'byHour' => array_fill(0, 24, 0),
        'topContacts' => []
    ];

    if (!file_exists($smsFile))
        return $stats;

    $content = file_get_contents($smsFile);
    $lines = explode("\n", $content);
    $contactCounts = [];

    foreach ($lines as $line) {
        if (strpos($line, 'Row:') === false)
            continue;

        // Get date
        if (preg_match('/date=(\d+)/', $line, $match)) {
            $timestamp = (int) (intval($match[1]) / 1000);

            // Filter by time range
            if ($timestamp < $minTimestamp)
                continue;

            $dayOfWeek = date('w', $timestamp);
            $hour = date('G', $timestamp);
            $stats['byDay'][$dayOfWeek]++;
            $stats['byHour'][$hour]++;
        } else {
            continue;
        }

        // Get type
        if (preg_match('/type=(\d+)/', $line, $match)) {
            if ($match[1] == '1')
                $stats['byType']['Received']++;
            else
                $stats['byType']['Sent']++;
        }

        // Get contact
        if (preg_match('/address=([^,]+)/', $line, $match)) {
            $contact = trim($match[1]);
            // Try to resolve name from map (built from call logs)
            $displayName = isset($contactMap[$contact]) ? $contactMap[$contact] : $contact;
            $contactCounts[$displayName] = ($contactCounts[$displayName] ?? 0) + 1;
        }
    }

    arsort($contactCounts);
    $stats['topContacts'] = array_slice($contactCounts, 0, 10, true);

    return $stats;
}

$callStats = getCallStats($minTimestamp, $contactMap);
$smsStats = getSmsStats($minTimestamp, $contactMap);
$dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line me-2 text-forensic-blue"></i>Activity Graphs
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Graphs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Time Range Selector -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-calendar me-1"></i>Time Range</label>
                            <select class="form-select" id="timeRangeSelect" onchange="updateCharts()">
                                <option value="24h" <?= $timeRange == '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                                <option value="7d" <?= $timeRange == '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="30d" <?= $timeRange == '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="all" <?= $timeRange == 'all' ? 'selected' : '' ?>>All Time</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-chart-bar me-1"></i>Chart Type</label>
                            <select class="form-select" id="chartTypeSelect" disabled title="Feature coming soon">
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Chart</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button class="btn btn-outline-primary" onclick="exportAllCharts()">
                                    <i class="fas fa-download me-1"></i>Export All Charts
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call Charts Row -->
            <div class="row">
                <!-- Calls by Day -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-phone me-2"></i>Call Activity by Day
                            </h3>
                            <div class="card-tools">
                                <button class="btn btn-tool" onclick="exportChart('callsByDayChart')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="callsByDayChart"></canvas>
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
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="callTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Charts Row -->
            <div class="row">
                <!-- SMS by Day -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-comment-sms me-2"></i>SMS Activity by Day
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="smsByDayChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SMS Type Distribution -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie me-2"></i>SMS Direction
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="smsTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hourly Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock me-2"></i>Activity by Hour of Day
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="hourlyActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Contacts Row -->
            <div class="row">
                <!-- Top Callers -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-fire me-2"></i>Most Frequent Callers
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="topCallersChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top SMS Contacts -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users me-2"></i>Top SMS Contacts
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="topSmsContactsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php
$callByDayJson = json_encode(array_values($callStats['byDay']));
$callByTypeLabelsJson = json_encode(array_keys($callStats['byType']));
$callByTypeValuesJson = json_encode(array_values($callStats['byType']));
$callByHourJson = json_encode(array_values($callStats['byHour']));
// Use full keys (names) for labels, no substring
$topCallersLabelsJson = json_encode(array_keys($callStats['topCallers']));
$topCallersValuesJson = json_encode(array_values($callStats['topCallers']));

$smsByDayJson = json_encode(array_values($smsStats['byDay']));
$smsByTypeLabelsJson = json_encode(array_keys($smsStats['byType']));
$smsByTypeValuesJson = json_encode(array_values($smsStats['byType']));
$smsByHourJson = json_encode(array_values($smsStats['byHour']));
// Use full keys (names/addresses)
$topSmsLabelsJson = json_encode(array_keys($smsStats['topContacts']));
$topSmsValuesJson = json_encode(array_values($smsStats['topContacts']));

$additionalScripts = <<<SCRIPT
<script>
const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');

document.addEventListener('DOMContentLoaded', function() {
    // Calls by Day
    createChart('callsByDayChart', 'bar', {
        labels: dayLabels,
        datasets: [{
            label: 'Calls',
            data: {$callByDayJson},
            backgroundColor: chartColors.primary,
            borderRadius: 4
        }]
    });
    
    // Call Type Distribution
    createChart('callTypeChart', 'doughnut', {
        labels: {$callByTypeLabelsJson},
        datasets: [{
            data: {$callByTypeValuesJson},
            backgroundColor: [chartColors.success, chartColors.primary, chartColors.danger],
            borderWidth: 0
        }]
    }, {
        plugins: { legend: { position: 'bottom' } }
    });
    
    // SMS by Day
    createChart('smsByDayChart', 'bar', {
        labels: dayLabels,
        datasets: [{
            label: 'SMS',
            data: {$smsByDayJson},
            backgroundColor: chartColors.info,
            borderRadius: 4
        }]
    });
    
    // SMS Type Distribution
    createChart('smsTypeChart', 'doughnut', {
        labels: {$smsByTypeLabelsJson},
        datasets: [{
            data: {$smsByTypeValuesJson},
            backgroundColor: [chartColors.success, chartColors.primary],
            borderWidth: 0
        }]
    }, {
        plugins: { legend: { position: 'bottom' } }
    });
    
    // Hourly Activity
    createChart('hourlyActivityChart', 'line', {
        labels: hourLabels,
        datasets: [{
            label: 'Calls',
            data: {$callByHourJson},
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primaryBg,
            tension: 0.4,
            fill: true
        }, {
            label: 'SMS',
            data: {$smsByHourJson},
            borderColor: chartColors.info,
            backgroundColor: chartColors.infoBg,
            tension: 0.4,
            fill: true
        }]
    });
    
    // Top Callers
    createChart('topCallersChart', 'bar', {
        labels: {$topCallersLabelsJson},
        datasets: [{
            label: 'Calls',
            data: {$topCallersValuesJson},
            backgroundColor: chartColors.palette,
            borderRadius: 4
        }]
    }, {
        indexAxis: 'y',
        plugins: { legend: { display: false } }
    });
    
    // Top SMS Contacts
    createChart('topSmsContactsChart', 'bar', {
        labels: {$topSmsLabelsJson},
        datasets: [{
            label: 'Messages',
            data: {$topSmsValuesJson},
            backgroundColor: chartColors.palette,
            borderRadius: 4
        }]
    }, {
        indexAxis: 'y',
        plugins: { legend: { display: false } }
    });
});

function exportChart(chartId) {
    const chart = ForensicApp.state.charts[chartId];
    if (chart) {
        const link = document.createElement('a');
        link.download = chartId + '.png';
        link.href = chart.toBase64Image();
        link.click();
        showToast('Chart exported', 'success');
    }
}

function exportAllCharts() {
    Object.keys(ForensicApp.state.charts).forEach(chartId => {
        exportChart(chartId);
    });
}

function updateCharts() {
    const range = document.getElementById('timeRangeSelect').value;
    window.location.search = '?range=' + range;
}
</script>
SCRIPT;

require_once '../includes/footer.php';
?>