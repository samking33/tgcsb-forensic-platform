<?php
/**
 * API to retrieve extraction statistics after completion
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$logsPath = getLogsPath();
$statsFile = $logsPath . '/extraction_stats.json';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (file_exists($statsFile)) {
    echo file_get_contents($statsFile);
} else {
    echo json_encode(['success' => false, 'error' => 'Stats file not found (Extraction might be running or failed)']);
}
?>
