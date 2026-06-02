<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$logsPath = getLogsPath();
$timelineFile = $logsPath . '/unified_timeline.json';

if (file_exists($timelineFile)) {
    readfile($timelineFile);
} else {
    // Return empty array instead of 404 to avoid frontend crash
    echo json_encode([]);
}
?>