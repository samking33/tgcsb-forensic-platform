<?php
/**
 * Clear Data API Endpoint
 * Clears all extracted log files from the logs directory
 */

header('Content-Type: application/json');

// Allow from same origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST or DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get the logs path
require_once __DIR__ . '/../includes/config.php';

$logsPath = getLogsPath();

$result = [
    'success' => true,
    'filesDeleted' => 0,
    'errors' => []
];

// List of log files to clear
$logFiles = [
    'sms_logs.txt',
    'call_logs.txt',
    'location_logs.txt',
    'android_logcat.txt',
    'contacts_logs.txt',
    'apps_logs.txt',
    'device_info.txt',
    'battery_logs.txt',
    'network_logs.txt',
    'storage_logs.txt',
    'unified_timeline.json',
    'notification_history.txt',
    'notification_timeline.json',
    'usage_stats.txt',
    'package_dump.txt',
    'dual_space_analysis.json'
];

try {
    // 1. Delete explicit files
    foreach ($logFiles as $file) {
        $filePath = $logsPath . '/' . $file;
        if (file_exists($filePath)) {
            // Suppress warnings with @ in case of locks, check result
            if (@unlink($filePath)) {
                $result['filesDeleted']++;
            } else {
                $result['errors'][] = "Failed to delete (locked?): $file";
            }
        }
    }

    // 2. Clear pattern matches (txt, json)
    if (is_dir($logsPath)) {
        $patterns = ['*.txt', '*.json', '*.log'];
        foreach ($patterns as $pattern) {
            $files = glob($logsPath . '/' . $pattern);
            if ($files) {
                foreach ($files as $file) {
                    $basename = basename($file);
                    // Preserve these files as they contain analysis results
                    if (!in_array($basename, ['README.txt', 'README.json', '.gitkeep', 'root_status.json', 'privacy_profile.json'])) {
                        if (file_exists($file)) {
                            if (@unlink($file)) {
                                $result['filesDeleted']++;
                            }
                        }
                    }
                }
            }
        }
    }

    if (!empty($result['errors'])) {
        // partial success is still success-ish, but let's warn
        // If we deleted nothing and had errors, then false
        if ($result['filesDeleted'] === 0 && count($result['errors']) > 0) {
            $result['success'] = false;
        }
    }

    $result['message'] = $result['filesDeleted'] > 0
        ? "Successfully cleared {$result['filesDeleted']} log file(s)"
        : "No log files found to clear";

} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($result);
?>