<?php
/**
 * Timeline Acquisition API (Refactored)
 * Bridges unified_timeline.py data to the legacy TimelineViewer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$action = $_GET['action'] ?? 'get_events';

try {
    switch ($action) {
        case 'extract':
            extractTimeline();
            break;

        case 'get_events':
            getEvents();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function extractTimeline()
{
    // 1. Run the Master Pipeline script
    $baseDir = dirname(__DIR__, 2); // Go up from web/api to root
    $scriptPath = $baseDir . '/scripts/process_all.py';

    // Ensure we run from the base directory so relative paths in Python work
    // Windows command chaining
    $cmd = "cd /d " . escapeshellarg($baseDir) . " && python " . escapeshellarg($scriptPath);

    // Execute
    $output = shell_exec($cmd . " 2>&1");

    // Check if json file exists
    $jsonFile = $baseDir . '/logs/unified_timeline.json';

    if (file_exists($jsonFile)) {
        // Read to count events
        $data = json_decode(file_get_contents($jsonFile), true);
        echo json_encode([
            'success' => true,
            'total_events' => count($data),
            'audit_log' => ['Extraction successful', $output],
            'retention_notice' => 'Timeline extracted successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Extraction failed. Python script did not generate output.',
            'debug_info' => [
                'cwd' => getcwd(),
                'base_dir' => $baseDir,
                'cmd' => $cmd,
                'output' => $output
            ]
        ]);
    }
}

function getEvents()
{
    $baseDir = dirname(__DIR__, 2);
    $jsonFile = $baseDir . '/logs/unified_timeline.json';

    if (!file_exists($jsonFile)) {
        echo json_encode([
            'success' => true,
            'events' => [],
            'retention_notice' => 'No data found. Please click Extract.'
        ]);
        return;
    }

    $rawData = json_decode(file_get_contents($jsonFile), true);

    // Transform Data to match TimelineViewer.js expectations
    // Input: { timestamp, type, subtype, content, severity }
    // Output: { category, event_type, timestamp_utc, timestamp_local, timestamp_unix, source, confidence, raw_reference }

    $transformedEvents = [];

    // Load Fake Log Report
    $fakeReportFile = $baseDir . '/logs/fake_log_report.json';
    $fakeReport = [];
    if (file_exists($fakeReportFile)) {
        $fakeJson = json_decode(file_get_contents($fakeReportFile), true);
        $fakeReport = $fakeJson['verification'] ?? [];
    }

    foreach ($rawData as $idx => $ev) {
        // Map Category
        $cat = 'DEVICE'; // Default
        
        // Specific checks first
        if ($ev['type'] === 'FINANCIAL') {
            $cat = 'FINANCIAL';
        } elseif ($ev['type'] === 'NOTIFICATION') {
            $cat = 'NOTIFICATION';
        } elseif ($ev['type'] === 'VOIP') {
            $cat = 'VOIP';
        } elseif ($ev['type'] === 'GHOST') {
            $cat = 'GHOST';
        } elseif ($ev['type'] === 'SECURITY') {
            $cat = 'SECURITY';
        } elseif ($ev['type'] === 'SMS' || $ev['type'] === 'CALL') {
            $cat = 'APP'; 
        } elseif (strpos($ev['type'], 'LOGCAT_POWER') !== false) {
            $cat = 'POWER';
        } elseif (strpos($ev['type'], 'LOGCAT_NET') !== false) {
            $cat = 'NETWORK';
        } elseif (strpos($ev['type'], 'LOGCAT_APP') !== false) {
            $cat = 'APP';
        } elseif (strpos($ev['type'], 'LOGCAT') !== false) {
            // All other LOGCAT_ events (DEVICE, RADIO, SYS) fall to DEVICE
            $cat = 'DEVICE';
        }

        // Timestamps
        $ts = $ev['timestamp']; // ISO8601
        $unix = strtotime($ts);
        
        // Verification / Ghost Detection
        $confidence = 'High';
        $verificationStatus = null;
        $verificationProof = null;
        
        if ($ev['type'] === 'CALL' || $ev['type'] === 'SMS') {
            $typePrefix = strtolower($ev['type']); // 'call' or 'sms'
            
            // Default: All SMS/Call logs from device extraction are verified (source-confirmed)
            $verificationStatus = 'verified';
            $verificationProof = 'Extracted from device logs (Source: ' . $ev['type'] . ')';
            $confidence = 'Verified (Source)';
            
            // Check if explicitly flagged as fake in verification report
            if (!empty($fakeReport)) {
                foreach ($fakeReport as $fid => $finfo) {
                    if (strpos($fid, $typePrefix) !== 0) continue;
                    
                    // Extract timestamp from ID: "call_170689..."
                    $parts = explode('_', $fid);
                    if (count($parts) < 2) continue;
                    
                    $reportMs = floatval($parts[1]);
                    $reportSec = $reportMs / 1000;
                    
                    // Check if timestamps match within 2 seconds
                    if (abs($reportSec - $unix) < 2.0) {
                        // Only override if explicitly marked as fake
                        if ($finfo['status'] === 'fake') {
                            $verificationStatus = 'fake';
                            $verificationProof = $finfo['proof'] ?? 'Flagged as suspicious';
                            $confidence = 'FAKE / GHOST';
                            $cat = 'GHOST'; // Promote to Ghost Category for visibility
                        }
                        break; // Stop after finding match
                    }
                }
            }
        }
        
        $transformedEvents[] = [
            'id' => $idx,
            'category' => $cat,
            'event_type' => $ev['subtype'] ?? $ev['type'],
            'timestamp_utc' => $ts,
            'timestamp_local' => $ts, // Assuming already local from script or just reusing
            'timestamp_unix' => $unix,
            'source' => $ev['type'],
            'event_nature' => 'LOG',
            'confidence' => ($cat === 'GHOST') ? 'Inferred' : $confidence,
            'raw_reference' => $ev['content'],
            'metadata' => [
                'content' => $ev['content'],
                'severity' => $ev['severity'],
                'verification' => $verificationStatus,
                'verification_proof' => $verificationProof ?? null
            ]
        ];
    }

    // Calc stats
    $breakdown = [
        'DEVICE' => 0,
        'APP' => 0,
        'NETWORK' => 0,
        'POWER' => 0,
        'NOTIFICATION' => 0,
        'FINANCIAL' => 0,
        'SECURITY' => 0,
        'GHOST' => 0,
        'VOIP' => 0
    ];

    $debug_counts = [];

    foreach ($transformedEvents as $e) {
        $c = $e['category'];
        if (!isset($debug_counts[$c])) {
            $debug_counts[$c] = 0;
        }
        $debug_counts[$c]++;

        if (isset($breakdown[$c])) {
            $breakdown[$c]++;
        }
    }

    echo json_encode([
        'success' => true,
        'events' => $transformedEvents,
        'category_breakdown' => $breakdown,
        'debug_counts' => $debug_counts, // DIAGNOSTIC
        'retention_notice' => 'Data loaded from unified timeline.'
    ]);
}
?>