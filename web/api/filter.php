<?php
/**
 * Android Forensic Tool - Filter API
 * Filters log data based on criteria
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$response = [
    'success' => false,
    'count' => 0,
    'results' => [],
    'error' => null
];

// Get filter parameters
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$logType = $input['logType'] ?? 'all';
$timeRange = $input['timeRange'] ?? 'all';
$severity = $input['severity'] ?? 'all';
$category = $input['category'] ?? 'all';
$keyword = $input['keyword'] ?? '';
$regex = $input['regex'] ?? '';
$caseSensitive = $input['caseSensitive'] ?? false;

$logsPath = getLogsPath();
$results = [];

try {
    // Determine which files to search
    $files = [];
    switch ($logType) {
        case 'logcat':
            $files[] = $logsPath . '/android_logcat.txt';
            break;
        case 'calls':
            $files[] = $logsPath . '/call_logs.txt';
            break;
        case 'sms':
            $files[] = $logsPath . '/sms_logs.txt';
            break;
        case 'location':
            $files[] = $logsPath . '/location_logs.txt';
            break;
        default:
            $files = glob($logsPath . '/*.txt');
    }

    // Calculate time threshold
    $timeThreshold = 0;
    switch ($timeRange) {
        case '1h':
            $timeThreshold = time() - 3600;
            break;
        case '24h':
            $timeThreshold = time() - 86400;
            break;
        case '7d':
            $timeThreshold = time() - 604800;
            break;
        case '30d':
            $timeThreshold = time() - 2592000;
            break;
    }

    // Severity pattern
    $severityPattern = '';
    switch ($severity) {
        case 'verbose':
            $severityPattern = '/\sV\//';
            break;
        case 'debug':
            $severityPattern = '/\sD\//';
            break;
        case 'info':
            $severityPattern = '/\sI\//';
            break;
        case 'warning':
            $severityPattern = '/\sW\//';
            break;
        case 'error':
            $severityPattern = '/\sE\//';
            break;
        case 'fatal':
            $severityPattern = '/\sF\//';
            break;
    }

    // Debug logging - log once per request, not per line
    file_put_contents(LOGS_PATH . '/debug_filter_request.log', date('Y-m-d H:i:s') . " - Input: " . json_encode($input) . "\n", FILE_APPEND);

    // Process each file
    foreach ($files as $file) {
        if (!file_exists($file))
            continue;

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $fileType = basename($file, '.txt');

        foreach ($lines as $line) {
            // --- STEP 1: PARSE METADATA (Time & Level) ---
            $timestamp = '--';
            $level = 'I'; // Default to Info
            $logTimestamp = 0;

            if ($fileType === 'android_logcat') {
                // Logcat Format: 01-23 13:46:46.045 ... Level/Tag ... or Level Tag
                // Extract Time
                if (preg_match('/^(\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+)/', $line, $match)) {
                    $timestamp = $match[1];
                    $logTimestamp = strtotime(date('Y') . '-' . $timestamp);
                }
                // Extract Severity (Robust: handle "E/Tag" and " E Tag")
                if (preg_match('/(?:^|\s)([VDIWEF])(?:\/|\s)/', $line, $match)) {
                    $level = $match[1];
                }
            } else {
                // SMS/Call Logs: date=1770112968852
                if (preg_match('/date=(\d{10,13})/', $line, $match)) {
                    $ts = $match[1];
                    // Convert ms to seconds if needed
                    if (strlen($ts) > 10) $ts = substr($ts, 0, 10);
                    
                    $logTimestamp = (int)$ts;
                    $timestamp = date('m-d H:i:s', $logTimestamp);
                }
            }

            // --- STEP 2: APPLY FILTERS ---

            // A. Time Range Filter
            if ($timeThreshold > 0) {
                // If we couldn't parse a time on a meaningful line, skip or keep? 
                // Usually skip if strict, but if $logTimestamp is 0 it means parse failed.
                // Let's assume if parse failed we might want to keep it if it's a stack trace following a valid line?
                // For now, strict: if valid timestamp found, filter it.
                if ($logTimestamp > 0 && $logTimestamp < $timeThreshold) {
                    continue;
                }
            }

            // B. Severity Filter
            if (!empty($severityPattern)) {
                // Map Level char to int for comparison? Or just Regex?
                // The frontend sends specific levels. 
                // If User selected 'E', they want 'E' or 'F'.
                // If User selected 'W', they want 'W', 'E', 'F'.
                // Simplest is to strict match the provided severity chars if we use checkboxes, 
                // but here it seems we receive a pattern or single char?
                // The input 'severity' generates '$severityPattern'.
                
                // If it's a regex pattern from input:
                // But wait, $severityPattern might be "/[WEF]/" etc. matches against the LINE.
                // Better to match against our parsed $level for reliability.
                
                // Let's rely on the regex pattern provided by backend setup OR manual check.
                // If checking line for severity pattern:
                if (!preg_match($severityPattern, $line)) {
                    // Try matching against the extracted level just in case logic differs
                    if (strpos($severityPattern, $level) === false) { 
                        continue; 
                    }
                }
            }

            // C. Category Filter
            if ($category !== 'all') {
                global $LOG_TYPES;
                if (isset($LOG_TYPES[ucfirst($category)])) {
                    $pattern = $LOG_TYPES[ucfirst($category)]['pattern'];
                    if (!preg_match($pattern, $line))
                        continue;
                }
            }

            // D. Keyword Filter
            if (!empty($keyword)) {
                if ($caseSensitive) {
                    if (strpos($line, $keyword) === false) continue;
                } else {
                    if (stripos($line, $keyword) === false) continue;
                }
            }

            // E. Regex Filter
            if (!empty($regex)) {
                $flags = $caseSensitive ? '' : 'i';
                if (!preg_match("/$regex/$flags", $line))
                    continue;
            }

            $results[] = [
                'timestamp' => $timestamp,
                'type' => str_replace('_', ' ', ucfirst($fileType)),
                'level' => $level,
                'content' => $line // No truncation - send full line
            ];

            // Limit results
            if (count($results) >= 1000)
                break 2;
        }
    }

    $response['success'] = true;
    $response['count'] = count($results);
    $response['results'] = $results;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
