<?php
header('Content-Type: application/json');

$baseDir = dirname(dirname(__DIR__));
$scriptPath = $baseDir . '/analysis/threat_detector.py';
$outputFile = $baseDir . '/logs/threat_report.json';

// Delete old report to ensure we get fresh results
if (file_exists($outputFile)) {
    unlink($outputFile);
}

$command = "python \"$scriptPath\" 2>&1";
$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

// Check if the report file was created (better indicator of success than return code)
if (file_exists($outputFile)) {
    echo json_encode(['success' => true, 'message' => 'Scan complete', 'output' => $output]);
} else {
    echo json_encode(['success' => false, 'error' => 'Script failed to generate report', 'details' => $output, 'return_code' => $returnCode]);
}
?>
