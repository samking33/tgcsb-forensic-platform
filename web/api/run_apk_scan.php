<?php
header('Content-Type: application/json');

$baseDir = dirname(dirname(__DIR__));
$scriptPath = $baseDir . '/analysis/apk_tracker.py';

// Check if intent_hunter needs to run first
$intentHunterJson = $baseDir . '/logs/intent_hunter.json';
if (!file_exists($intentHunterJson)) {
    $intentHunterScript = $baseDir . '/analysis/intent_hunter.py';
    exec("python \"$intentHunterScript\" 2>&1", $output1, $returnCode1);
}

$command = "python \"$scriptPath\" 2>&1";
$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo json_encode(['success' => true, 'message' => 'APK scan complete', 'output' => $output]);
} else {
    echo json_encode(['success' => false, 'error' => 'Script failed', 'details' => $output]);
}
?>
