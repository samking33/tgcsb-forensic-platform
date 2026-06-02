<?php
/**
 * Android Forensic Tool - Extract API (Async Launcher)
 * Spawns the extraction pipeline in background to avoid blocking the single-threaded server.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$logcat = ($input['logcat'] ?? true) ? 'true' : 'false';
$calls = ($input['calls'] ?? true) ? 'true' : 'false';
$sms = ($input['sms'] ?? true) ? 'true' : 'false';
$location = ($input['location'] ?? true) ? 'true' : 'false';

$logsPath = getLogsPath();
if (!is_dir($logsPath)) mkdir($logsPath, 0755, true);

// Reset Progress
$progressFile = $logsPath . '/extraction_progress.json';
file_put_contents($progressFile, json_encode(['progress' => 0, 'status' => 'Starting extraction pipeline...']));

// Clear old stats
$statsFile = $logsPath . '/extraction_stats.json';
if (file_exists($statsFile)) unlink($statsFile);

// Build Command
// Windows: start /B php script.php > NUL 2>&1
// Args: --logcat=true ...
$pipelineScript = dirname(dirname(__DIR__)) . '/scripts/run_pipeline.php';
$args = "--logcat=$logcat --calls=$calls --sms=$sms --location=$location";

// Use 'start /B' for background on Windows
// Use the bundled php.exe (not system PATH)
$phpBin = dirname(dirname(__DIR__)) . '\\php\\php.exe';
$cmd = "start /B \"\" \"$phpBin\" \"$pipelineScript\" $args > NUL 2>&1";

pclose(popen($cmd, "r"));

// Return immediately
echo json_encode([
    'success' => true,
    'message' => 'Background extraction started. Monitoring progress...',
    'status' => 'started'
]);
?>
