<?php
/**
 * Android Forensic Tool - Stats API
 * Returns dashboard statistics as JSON
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$logsPath = getLogsPath();

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'smsCount' => 0,
    'callCount' => 0,
    'locationCount' => 0,
    'threatCount' => 0,
    'logcatLines' => 0,
    'topContacts' => [],
    'callsByDay' => [],
    'smsByDay' => []
];

// Count SMS
$smsFile = $logsPath . '/sms_logs.txt';
if (file_exists($smsFile)) {
    $content = file_get_contents($smsFile);
    $response['smsCount'] = substr_count($content, 'Row:');
}

// Count Calls
$callFile = $logsPath . '/call_logs.txt';
if (file_exists($callFile)) {
    $content = file_get_contents($callFile);
    $response['callCount'] = substr_count($content, 'Row:');

    // Extract top contacts
    $contacts = [];
    preg_match_all('/number=([^,]+)/', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $number) {
            $num = trim($number);
            $contacts[$num] = ($contacts[$num] ?? 0) + 1;
        }
        arsort($contacts);

        $response['topContacts'] = array_map(function ($num, $count) {
            return ['number' => $num, 'count' => $count];
        }, array_keys(array_slice($contacts, 0, 5)), array_slice($contacts, 0, 5));
    }
}

// Count Locations (from forensic extraction)
$forensicFile = $logsPath . '/forensic_locations.json';
if (file_exists($forensicFile)) {
    $forensicData = json_decode(file_get_contents($forensicFile), true);
    $response['locationCount'] = isset($forensicData['points']) ? count($forensicData['points']) : 0;
} else {
    // Fallback: trigger auto-extraction on first stats call
    $response['locationCount'] = 0;
}

// ==========================================================
// MULE HUNTER STATS INTEGRATION (Fix for Zero State)
// ==========================================================
$response['muleStats'] = [
    'riskLevel' => 'LOW',
    'totalBankingApps' => 0,
    'clonedBankingApps' => 0,
    'indicators' => []
];

// Read Dual Space Analysis
$dualSpaceFile = $logsPath . '/dual_space_analysis.json';
if (file_exists($dualSpaceFile)) {
    $dualData = json_decode(file_get_contents($dualSpaceFile), true);
    if ($dualData && isset($dualData['mule_assessment'])) {
        $response['muleStats']['riskLevel'] = $dualData['mule_assessment']['risk_level'];
        $response['muleStats']['clonedBankingApps'] = $dualData['banking_clone_count'] ?? 0;
        // Merge indicators
        if (!empty($dualData['mule_assessment']['indicators'])) {
             $response['muleStats']['indicators'] = array_merge(
                 $response['muleStats']['indicators'], 
                 $dualData['mule_assessment']['indicators']
             );
        }
    }
}

// Read App Session Analysis (Installed Banking Apps)
$appSessionFile = $logsPath . '/app_sessions.json';
if (file_exists($appSessionFile)) {
    $sessionData = json_decode(file_get_contents($appSessionFile), true);
    if ($sessionData && isset($sessionData['summary'])) {
        $response['muleStats']['totalBankingApps'] = $sessionData['summary']['unique_banking_apps'] ?? 0;
        
        // Upgrade Risk if Sessionizer says HIGH and current is lower
        $sessionRisk = $sessionData['summary']['mule_risk_level'] ?? 'LOW';
        if ($sessionRisk === 'HIGH' || ($sessionRisk === 'MEDIUM' && $response['muleStats']['riskLevel'] === 'LOW')) {
              // Only upgrade if Dual Space didn't already set it to CRITICAL
              if ($response['muleStats']['riskLevel'] !== 'CRITICAL') {
                  $response['muleStats']['riskLevel'] = $sessionRisk;
              }
        }
    }
}

// Count Logcat lines
$logcatFile = $logsPath . '/android_logcat.txt';
if (file_exists($logcatFile)) {
    $response['logcatLines'] = count(file($logcatFile));
}

echo json_encode($response);
