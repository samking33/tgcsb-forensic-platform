<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$scriptPath = dirname(dirname(__DIR__)) . '/scripts/run_mule_scan.py';

// Windows: start /B python script.py
$logPath = dirname(dirname(__DIR__)) . '/logs/mule_debug.log';
$cmd = "start /B python \"$scriptPath\" > \"$logPath\" 2>&1";

pclose(popen($cmd, "r"));

echo json_encode([
    'success' => true,
    'message' => 'Mule Scan started in background. Refresh page in 30 seconds.'
]);
?>
