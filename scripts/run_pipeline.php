<?php
/**
 * CLI Script to run the full extraction pipeline
 * Handles Enhanced Extraction, ADB Calls, Timeline, Social Graph, etc.
 * Designed to run in the background.
 */

// Basic Config - CLI mode doesn't share web constants easily, defining minimal
define('BASE_PATH', dirname(__DIR__));
define('LOGS_PATH', BASE_PATH . '/logs');

// Helper to update progress
function updateProgress($percent, $status) {
    if (!is_dir(LOGS_PATH)) mkdir(LOGS_PATH, 0755, true);
    file_put_contents(LOGS_PATH . '/extraction_progress.json', json_encode([
        'progress' => $percent,
        'status' => $status
    ]));
}

// Helper to save stats (which the frontend will read when done)
function saveStats($stats) {
    if (!is_dir(LOGS_PATH)) mkdir(LOGS_PATH, 0755, true);
    file_put_contents(LOGS_PATH . '/extraction_stats.json', json_encode($stats));
}

// Parse args (Simple: run everything by default)
$options = getopt("", ["logcat:", "calls:", "sms:", "location:"]);

// Defaults (CLI mode assumes everything if not specified, or checks args)
// Note: passed as strings "true" or "false" from extract.php exec
$extractLogcat = ($options['logcat'] ?? 'true') === 'true';
$extractCalls = ($options['calls'] ?? 'true') === 'true';
$extractSms = ($options['sms'] ?? 'true') === 'true';
$extractLocation = ($options['location'] ?? 'true') === 'true';

// Start
updateProgress(0, "Starting backend pipeline...");
$stats = ['logcat' => 0, 'calls' => 0, 'sms' => 0, 'locations' => 0, 'status' => 'Running'];

// 1. Run Enhanced Extraction (Python) -> 0-40%
// We use direct python execution
$rootPath = BASE_PATH;
$cmd = "cd \"$rootPath\" && python scripts/enhanced_extraction.py 2>&1";
exec($cmd, $output, $ret);

if ($ret !== 0) {
    // Log error but continue
    file_put_contents(LOGS_PATH . '/pipeline_error.txt', implode("\n", $output));
}

// 2. Extract Standard Logs -> 40-55%
updateProgress(45, "Extracting standard logs...");

if ($extractSms) {
    $cmd = "adb shell content query --uri content://sms > \"" . LOGS_PATH . "/sms_logs.txt\" 2>&1";
    exec($cmd);
}

if ($extractCalls) {
    $cmd = "adb shell content query --uri content://call_log/calls > \"" . LOGS_PATH . "/call_logs.txt\" 2>&1";
    exec($cmd);
}

updateProgress(55, "Extracting raw location dumps...");
if ($extractLocation) {
    $cmd = "adb shell dumpsys location > \"" . LOGS_PATH . "/location_logs.txt\" 2>&1";
    exec($cmd);
}

// Update Stats so far
if (file_exists(LOGS_PATH . '/android_logcat.txt')) $stats['logcat'] = count(file(LOGS_PATH . '/android_logcat.txt'));
if (file_exists(LOGS_PATH . '/sms_logs.txt')) $stats['sms'] = substr_count(file_get_contents(LOGS_PATH . '/sms_logs.txt'), 'Row:');
if (file_exists(LOGS_PATH . '/call_logs.txt')) $stats['calls'] = substr_count(file_get_contents(LOGS_PATH . '/call_logs.txt'), 'Row:');

// 3. Post Processing -> 70%
updateProgress(70, "Generating Unified Timeline...");
exec("cd \"$rootPath\" && python analysis/unified_timeline.py 2>&1");

updateProgress(80, "Building Social Graph...");
exec("cd \"$rootPath\" && python analysis/social_graph.py 2>&1");

updateProgress(90, "Analyzing App Sessions...");
exec("cd \"$rootPath\" && python analysis/app_sessionizer.py 2>&1");

updateProgress(95, "Finalizing Dual Space Analysis...");
exec("cd \"$rootPath\" && python analysis/dual_space_analyzer.py 2>&1");

updateProgress(98, "detecting Fake Logs (Ghost Tags)...");
exec("cd \"$rootPath\" && python analysis/fake_log_detector.py 2>&1");

// 4. Complete
$stats['status'] = 'Complete';
// Integrate Analysis Results (Mule Hunter)
if (file_exists($rootPath . '/logs/dual_space_analysis.json')) {
    $muleInfo = json_decode(file_get_contents($rootPath . '/logs/dual_space_analysis.json'), true);
    $stats['mule_risk'] = $muleInfo['mule_assessment']['risk_level'] ?? 'N/A';
    $stats['cloned_banking_apps'] = $muleInfo['banking_clone_count'] ?? 0;
}

saveStats($stats);

// Update progress LAST so frontend only proceeds when stats are ready
updateProgress(100, "Extraction Complete!");

?>
