<?php
/**
 * API to poll extraction progress
 * Reads logs/extraction_progress.json
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$logsPath = getLogsPath();
$progressFile = $logsPath . '/extraction_progress.json';

$action = $_GET['action'] ?? '';

if ($action === 'reset') {
    $initialData = json_encode(['progress' => 0, 'status' => 'Starting...']);
    file_put_contents($progressFile, $initialData);
    echo $initialData;
    exit;
}

if (file_exists($progressFile)) {
    // Prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    readfile($progressFile);
} else {
    echo json_encode(['progress' => 0, 'status' => 'Waiting for extraction...']);
}
?>
