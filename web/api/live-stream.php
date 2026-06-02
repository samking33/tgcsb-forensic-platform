<?php
/**
 * Android Forensic Tool - Live Stream API
 * Server-Sent Events for real-time logcat streaming
 */

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Prevent output buffering
if (ob_get_level())
    ob_end_clean();

// Set time limit for long-running connection
set_time_limit(0);

require_once '../includes/config.php';

// Send a message to the client
function sendEvent($data, $event = 'message')
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// -------------------------------------------------------
// Find ADB full path — MUST be absolute for proc_open on
// Windows PHP 8.5+ (string commands are blocked; array
// form requires a fully-resolved binary path).
// -------------------------------------------------------
function findAdb(): string
{
    // Common Windows install locations (ordered by likelihood)
    $candidates = [
        getenv('USERPROFILE') . '\\platform-tools\\adb.exe',
        getenv('LOCALAPPDATA') . '\\Android\\Sdk\\platform-tools\\adb.exe',
        getenv('USERPROFILE') . '\\AppData\\Local\\Android\\Sdk\\platform-tools\\adb.exe',
        'C:\\platform-tools\\adb.exe',
        'C:\\adb\\adb.exe',
        getenv('PROGRAMFILES') . '\\Android\\android-sdk\\platform-tools\\adb.exe',
        getenv('PROGRAMFILES(X86)') . '\\Android\\android-sdk\\platform-tools\\adb.exe',
    ];

    foreach ($candidates as $path) {
        if ($path && file_exists($path)) {
            return $path;
        }
    }

    // Last resort: try 'where adb' (only works if in PATH)
    $where = shell_exec('where adb 2>NUL');
    if ($where) {
        $line = trim(explode("\n", $where)[0]);
        if (file_exists($line)) return $line;
    }

    return ''; // not found
}

$adbPath = findAdb();

if (!$adbPath) {
    sendEvent([
        'status'  => 'error',
        'message' => 'ADB not found. Install Android Platform Tools and ensure adb.exe is in C:\\Users\\YourName\\platform-tools\\'
    ], 'status');
    exit;
}

// -------------------------------------------------------
// Verify ADB works (array form, no quotes needed)
// -------------------------------------------------------
$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$vProc = proc_open([$adbPath, 'version'], $desc, $vPipes);
if (!is_resource($vProc)) {
    sendEvent(['status' => 'error', 'message' => 'ADB binary found but could not execute: ' . $adbPath], 'status');
    exit;
}
proc_close($vProc);

// -------------------------------------------------------
// Detect connected device
// -------------------------------------------------------
$dProc = proc_open([$adbPath, 'devices'], $desc, $dPipes);
$devOutput = [];
if (is_resource($dProc)) {
    stream_set_blocking($dPipes[1], true);
    while (!feof($dPipes[1])) {
        $line = fgets($dPipes[1]);
        if ($line !== false) $devOutput[] = trim($line);
    }
    fclose($dPipes[0]);
    fclose($dPipes[1]);
    fclose($dPipes[2]);
    proc_close($dProc);
}

$connectedDevices = [];
foreach ($devOutput as $devLine) {
    if (preg_match('/^(\S+)\s+(device|recovery|sideload)$/', $devLine, $m)) {
        $connectedDevices[] = $m[1];
    }
}

if (empty($connectedDevices)) {
    sendEvent([
        'status'  => 'error',
        'message' => 'No Android device detected. Connect your device via USB, enable USB Debugging, then try again.'
    ], 'status');
    exit;
}

$serial = $connectedDevices[0];

// Send initial connection message
sendEvent([
    'status'  => 'connected',
    'message' => 'Live monitoring started — device: ' . $serial
], 'status');

// -------------------------------------------------------
// Start ADB logcat — MUST use array form with full path
// on Windows PHP 8.5+ (string proc_open is blocked)
// -------------------------------------------------------
$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open(
    [$adbPath, '-s', $serial, 'logcat', '-v', 'time'],
    $descriptorspec,
    $pipes
);

if (!is_resource($process)) {
    // Read stderr for clue
    $errMsg = isset($pipes[2]) ? @fread($pipes[2], 512) : 'unknown';
    sendEvent([
        'status'  => 'error',
        'message' => 'Failed to start logcat process. ADB: ' . $adbPath . '. Err: ' . $errMsg
    ], 'status');
    exit;
}

// Non-blocking stdout
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$lineBuffer    = '';
$lastHeartbeat = time();

// -------------------------------------------------------
// Main loop — stream logcat to browser via SSE
// -------------------------------------------------------
while (true) {
    // Client disconnected?
    if (connection_aborted()) {
        break;
    }

    // Read from logcat stdout
    $data = fread($pipes[1], 4096);

    if ($data !== false && $data !== '') {
        $lineBuffer .= $data;

        // Process complete lines
        while (($pos = strpos($lineBuffer, "\n")) !== false) {
            $line       = substr($lineBuffer, 0, $pos);
            $lineBuffer = substr($lineBuffer, $pos + 1);
            $line       = trim($line);

            if (!empty($line)) {
                // Parse log level from logcat format: "MM-DD HH:MM:SS.mmm L/Tag(pid): msg"
                $level = 'I';
                if (preg_match('/\s+([VDIWEF])\//', $line, $match)) {
                    $level = $match[1];
                } elseif (preg_match('/^[0-9\-]+ [0-9:.]+ ([VDIWEF])\//', $line, $match)) {
                    $level = $match[1];
                }

                // Parse tag
                $tag = 'System';
                if (preg_match('/[VDIWEF]\/([^(]+)\(/', $line, $match)) {
                    $tag = trim($match[1]);
                }

                sendEvent([
                    'line'      => $line,
                    'level'     => $level,
                    'tag'       => $tag,
                    'timestamp' => date('H:i:s')
                ], 'log');
            }
        }
    }

    // Check if logcat process has died
    $procStatus = proc_get_status($process);
    if (!$procStatus['running']) {
        // Read any leftover stderr
        $errOut = fread($pipes[2], 512);
        sendEvent([
            'status'  => 'error',
            'message' => 'Logcat process ended. ' . ($errOut ? 'Stderr: ' . $errOut : '')
        ], 'status');
        break;
    }

    // Heartbeat every 5 seconds
    if (time() - $lastHeartbeat >= 5) {
        sendEvent(['status' => 'alive', 'time' => date('H:i:s')], 'heartbeat');
        $lastHeartbeat = time();
    }

    usleep(50000); // 50ms — responsive without burning CPU
}

// Cleanup
@fclose($pipes[0]);
@fclose($pipes[1]);
@fclose($pipes[2]);
proc_terminate($process);
proc_close($process);

sendEvent(['status' => 'disconnected'], 'status');
