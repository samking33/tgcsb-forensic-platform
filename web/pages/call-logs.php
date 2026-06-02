<?php
/**
 * Android Forensic Tool - Call Logs Page
 * Call history analysis with DataTables
 */
$pageTitle = 'Call Logs - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Parse Call logs
function parseCallLogs()
{
    $logsPath = getLogsPath();
    $callFile = $logsPath . '/call_logs.txt';
    $contactsMapFile = $logsPath . '/contacts_map.json';
    $records = [];

    // Load contact mapping if exists
    $contactsMap = [];
    if (file_exists($contactsMapFile)) {
        $contactsMap = json_decode(file_get_contents($contactsMapFile), true) ?? [];
    }

    if (!file_exists($callFile)) {
        return $records;
    }

    $content = file_get_contents($callFile);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        if (strpos($line, 'Row:') === false)
            continue;

        $record = [
            'contact' => 'Unknown',
            'number' => '',
            'date' => '--',
            'time' => '--',
            'duration' => '0:00',
            'type' => 'Unknown',
            'app' => 'Phone' // Default app
        ];

        // Strict Extraction using boundary check (Space or Start)
        $name = 'NULL';
        $number = 'NULL';
        $component = 'NULL';

        if (preg_match('/(?:^|\s)name=([^,]+)/', $line, $match))
            $name = trim($match[1]);
        if (preg_match('/(?:^|\s)number=([^,]+)/', $line, $match))
            $number = trim($match[1]);
        if (preg_match('/(?:^|\s)component_name=([^,]+)/', $line, $match))
            $component = trim($match[1]);

        // === Smart Name Logic ===
        $displayName = $number;
        $appSource = 'Phone';

        // 1. Detect App
        if (stripos($line, 'whatsapp') !== false) {
            $appSource = 'WhatsApp';
        } elseif (stripos($line, 'telegram') !== false) {
            $appSource = 'Telegram';
        }

        // 2. Resolve Name
        // Priority: Log Name > Contacts Map > Number

        // Check Log Name first
        if ($name && $name !== 'NULL' && $name !== '') {
            $displayName = $name;
        }
        // If Log Name is missing, try Contacts Map
        else {
            $mappedName = null;
            $cleanNumber = preg_replace('/[^\d+]/', '', $number);

            if (isset($contactsMap[$number])) {
                $mappedName = $contactsMap[$number];
            } elseif (isset($contactsMap[$cleanNumber])) {
                $mappedName = $contactsMap[$cleanNumber];
            } elseif (strlen($cleanNumber) >= 10) {
                // Try last 10 digits
                $last10 = substr($cleanNumber, -10);
                // Iterate map to find suffix match (slow but effective for small lists)
                foreach ($contactsMap as $k => $v) {
                    if (str_ends_with($k, $last10)) {
                        $mappedName = $v;
                        break;
                    }
                }
            }

            if ($mappedName) {
                $displayName = $mappedName;
            }
        }

        // 3. Deduplication logic
        // If the name is just the number, we treat it as "Unknown" for contact column usually, 
        // but here we want "Use number instead of repeating name".
        // Actually the Request is to show "WhatsApp Call: 12345" if name unknown.

        $record['contact'] = $displayName;
        $record['number'] = $number;
        $record['app'] = $appSource;


        // Extract date
        if (preg_match('/(?:^|\s)date=(\d+)/', $line, $match)) {
            $timestamp = (int) (intval($match[1]) / 1000);
            $record['date'] = date('Y-m-d', $timestamp);
            $record['time'] = date('H:i:s', $timestamp);
            $record['timestamp'] = $timestamp;
        }

        // Extract duration
        if (preg_match('/(?:^|\s)duration=(\d+)/', $line, $match)) {
            $seconds = intval($match[1]);
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            $record['duration'] = sprintf("%d:%02d", $mins, $secs);
            $record['durationSec'] = $seconds;  // Add seconds for total calculation
        }

        // Extract type
        if (preg_match('/(?:^|\s)type=(\d+)/', $line, $match)) {
            $typeCode = $match[1];
            switch ($typeCode) {
                case '1':
                    $record['type'] = 'Incoming';
                    break;
                case '2':
                    $record['type'] = 'Outgoing';
                    break;
                case '3':
                    $record['type'] = 'Missed';
                    break;
                case '4':
                    $record['type'] = 'Voicemail';
                    break;
                case '5':
                    $record['type'] = 'Rejected';
                    break;
                case '6':
                    $record['type'] = 'Blocked';
                    break;
                default:
                    $record['type'] = 'Unknown (' . $typeCode . ')';
            }
        }

        $records[] = $record;
    }
    return $records;
}

// Fetch VoIP calls from Unified Timeline (Logcat analysis)
function getVoipCallsFromTimeline() {
    $timelineFile = dirname(__DIR__, 2) . '/logs/unified_timeline.json';
    if (!file_exists($timelineFile)) return [];

    $data = json_decode(file_get_contents($timelineFile), true);
    $voipCalls = [];

    foreach ($data as $evt) {
        if (($evt['type'] ?? '') === 'VOIP') {
            $metadata = $evt['metadata'] ?? [];
            
            // Only include significant calls (e.g. active) or grouped sessions
            // If we have session groupings, use that to avoid 40 repeating events
            // We'll rely on our new 'call_session_events' metadata to pick just one representative event per session
            // Or if not grouped, we list them all? That might span too many.
            // Best approach: Only show "Call Active" or grouped headers.
            
            // If we have duration calculated, that suggests a session start.
            // Let's look for events with 'call_duration_seconds' which we added to grouped events.
            // To avoid duplicates, we can pick the first event of a session.
            // But 'call_duration_seconds' was added to ALL events in the session.
            // We need a way to distinct sessions. We can group by time proximity here or 
            // check if this event is the "Call Active" subtype.

            if (isset($metadata['call_duration_seconds'])) {
                // To avoid 40 duplicates, we'll store by minute-timestamp as a simple key
                // Or just show them. But user has 40 events for 1 call.
                // Let's filter for specific subtypes that indicate a distinct call action
                $subtype = $evt['subtype'] ?? '';
                if (stripos($subtype, 'Call Active') !== false || stripos($subtype, 'Incoming') !== false || stripos($subtype, 'Outgoing') !== false) {
                    
                     $timestamp = strtotime($evt['timestamp']);
                     
                     $record = [
                        'contact' => 'Unknown (VoIP)', 
                        'number' => 'VoIP',
                        'date' => date('Y-m-d', $timestamp),
                        'time' => date('H:i:s', $timestamp),
                        'duration' => gmdate("i:s", $metadata['call_duration_seconds'] ?? 0),
                        'durationSec' => $metadata['call_duration_seconds'] ?? 0,
                        'type' => 'VoIP Call',
                        'app' => 'VoIP', // Default
                        'timestamp' => $timestamp
                    ];

                    // Refine App Name
                    $content = $evt['content'] ?? '';
                    if (stripos($content, 'whatsapp') !== false || stripos($subtype, 'whatsapp') !== false) $record['app'] = 'WhatsApp';
                    elseif (stripos($content, 'telegram') !== false) $record['app'] = 'Telegram';
                    elseif (stripos($content, 'instagram') !== false) $record['app'] = 'Instagram';
                    elseif (stripos($content, 'messenger') !== false) $record['app'] = 'Messenger';
                    elseif (stripos($content, 'signal') !== false) $record['app'] = 'Signal';

                    // Update Contact Name if possible (from context)
                    $record['contact'] = $record['app'] . ' User';

                    // Deduplication key (same app, same minute)
                    $key = $record['app'] . '_' . date('YmdHi', $timestamp);
                    $voipCalls[$key] = $record; 
                }
            }
        }
    }
    return array_values($voipCalls);
}

// Get frequent callers
function getFrequentCallers($records, $limit = 5)
{
    $counts = [];
    foreach ($records as $r) {
        $contact = $r['contact'];
        if (!isset($counts[$contact])) {
            $counts[$contact] = 0;
        }
        $counts[$contact]++;
    }
    arsort($counts);
    return array_slice($counts, 0, $limit, true);
}

$callRecords = parseCallLogs();
// Merge VoIP Calls
$voipRecords = getVoipCallsFromTimeline();
$callRecords = array_merge($callRecords, $voipRecords);

// Sort by timestamp desc
usort($callRecords, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

$totalCalls = count($callRecords);
$incomingCount = count(array_filter($callRecords, fn($r) => $r['type'] === 'Incoming'));
$outgoingCount = count(array_filter($callRecords, fn($r) => $r['type'] === 'Outgoing'));
$missedCount = count(array_filter($callRecords, fn($r) => $r['type'] === 'Missed'));
$voipCount = count(array_filter($callRecords, fn($r) => $r['type'] === 'VoIP Call'));
$frequentCallers = getFrequentCallers($callRecords);

// Total call duration
$totalDuration = array_sum(array_column($callRecords, 'durationSec'));
$totalHours = floor($totalDuration / 3600);
$totalMins = floor(($totalDuration % 3600) / 60);
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-phone me-2 text-forensic-blue"></i>Call Logs
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Call Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-phone"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Calls</span>
                            <span class="info-box-number"><?= number_format($totalCalls) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-phone-volume"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Incoming</span>
                            <span class="info-box-number"><?= number_format($incomingCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-phone-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Outgoing</span>
                            <span class="info-box-number"><?= number_format($outgoingCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-phone-slash"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Missed</span>
                            <span class="info-box-number"><?= number_format($missedCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon" style="background-color: #8e44ad; color: white;"><i class="fas fa-headset"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">VoIP Calls</span>
                            <span class="info-box-number"><?= number_format($voipCount) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Call Records Table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list me-2"></i>Call Records
                            </h3>
                            <div class="card-tools">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="exportTableData('callTable', 'csv')">
                                        <i class="fas fa-file-csv me-1"></i>CSV
                                    </button>
                                    <button class="btn btn-sm btn-outline-success"
                                        onclick="exportTableData('callTable', 'excel')">
                                        <i class="fas fa-file-excel me-1"></i>Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($totalCalls > 0): ?>
                                <table id="callTable" class="table table-striped table-hover" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Contact</th>
                                            <th><i class="fas fa-calendar me-1"></i>Date</th>
                                            <th><i class="fas fa-clock me-1"></i>Time</th>
                                            <th><i class="fas fa-stopwatch me-1"></i>Duration</th>
                                            <th><i class="fas fa-phone me-1"></i>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($callRecords as $call): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user-circle fa-lg text-secondary me-3"></i>
                                                        <div>
                                                            <div class="fw-bold">
                                                                <?= htmlspecialchars($call['contact']) ?>
                                                                <?php if (isset($call['app']) && $call['app'] !== 'Phone'): 
                                                                    $appIcon = match($call['app']) {
                                                                        'WhatsApp' => 'fab fa-whatsapp',
                                                                        'Telegram' => 'fab fa-telegram',
                                                                        default => 'fas fa-mobile-alt'
                                                                    };
                                                                    $appColor = match($call['app']) {
                                                                        'WhatsApp' => 'success',
                                                                        'Telegram' => 'info',
                                                                        default => 'secondary'
                                                                    };
                                                                ?>
                                                                    <span class="badge bg-<?= $appColor ?> ms-1" style="font-size: 0.7em;">
                                                                        <i class="<?= $appIcon ?> me-1"></i><?= $call['app'] ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <!-- Show number if different from Contact Name -->
                                                            <?php if ($call['number'] !== 'NULL' && $call['number'] !== $call['contact']): ?>
                                                                <div class="small text-muted font-monospace">
                                                                    <?= htmlspecialchars($call['number']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($call['date']) ?></td>
                                                <td><?= htmlspecialchars($call['time']) ?></td>
                                                <td>
                                                    <i class="fas fa-clock me-1 text-muted"></i>
                                                    <?= htmlspecialchars($call['duration']) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeClass = match ($call['type']) {
                                                        'Incoming' => 'bg-success',
                                                        'Outgoing' => 'bg-info',
                                                        'Missed' => 'bg-danger',
                                                        'VoIP Call' => 'bg-purple', // Custom class needed or inline style
                                                        default => 'bg-secondary'
                                                    };
                                                    $typeIcon = match ($call['type']) {
                                                        'Incoming' => 'fa-arrow-down',
                                                        'Outgoing' => 'fa-arrow-up',
                                                        'Missed' => 'fa-phone-slash',
                                                        'VoIP Call' => 'fa-wifi',
                                                        default => 'fa-question'
                                                    };
                                                    $customStyle = ($call['type'] === 'VoIP Call') ? 'style="background-color: #8e44ad;"' : '';
                                                    ?>
                                                    <span class="badge <?= $typeClass ?>" <?= $customStyle ?>>
                                                        <i class="fas <?= $typeIcon ?> me-1"></i><?= $call['type'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-phone-slash fa-4x text-muted mb-3"></i>
                                    <h5>No Call Logs Found</h5>
                                    <p class="text-muted">Extract logs from a device to view call history.</p>
                                    <a href="extract-logs.php" class="btn btn-forensic">
                                        <i class="fas fa-download me-2"></i>Extract Logs
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Stats -->
                <div class="col-lg-4">
                    <!-- Call Duration -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-hourglass-half me-2"></i>Total Call Time
                            </h3>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-forensic-blue mb-0">
                                <?= $totalHours ?>h <?= $totalMins ?>m
                            </h2>
                            <p class="text-muted">Total duration of all calls</p>
                        </div>
                    </div>

                    <!-- Most Frequent Callers -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-fire me-2"></i>Most Frequent
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (!empty($frequentCallers)): ?>
                                    <?php $rank = 1;
                                    foreach ($frequentCallers as $contact => $count): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <span
                                                    class="badge bg-<?= $rank <= 3 ? 'warning' : 'secondary' ?> me-2">#<?= $rank ?></span>
                                                <?= htmlspecialchars($contact) ?>
                                            </div>
                                            <span class="badge bg-primary rounded-pill"><?= $count ?> calls</span>
                                        </li>
                                        <?php $rank++; endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-center text-muted">No data</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Call Type Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie me-2"></i>Call Distribution
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="callDistChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php
$additionalScripts = <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if (document.getElementById('callTable')) {
        initDataTable('callTable', {
            order: [[1, 'desc'], [2, 'desc']]
        });
    }
    
    // Call Distribution Chart
    // Call Distribution Chart
    createChart('callDistChart', 'doughnut', {
        labels: ['Incoming', 'Outgoing', 'Missed', 'VoIP'],
        datasets: [{
            data: [{$incomingCount}, {$outgoingCount}, {$missedCount}, {$voipCount}],
            backgroundColor: [
                chartColors.success,
                chartColors.primary,
                chartColors.danger,
                '#8e44ad' // Purple for VoIP
            ],
            borderWidth: 0
        }]
    }, {
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 10 }
            }
        }
    });
});
</script>
SCRIPT;

require_once '../includes/footer.php';
?>