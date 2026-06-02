<?php
/**
 * Android Forensic Tool - Threat Scanner API
 * Scans logs for security threats
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$response = [
    'success' => true,
    'threats' => [],
    'summary' => [
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ]
];

// Threat signatures
$THREAT_SIGNATURES = [
    [
        'name' => 'Suspicious Network Activity',
        'pattern' => '/suspicious|malware|trojan|backdoor|phishing/i',
        'severity' => 'HIGH',
        'description' => 'Detected potentially malicious network activity'
    ],
    [
        'name' => 'Data Exfiltration',
        'pattern' => '/exfiltrat|upload.*secret|leak.*data|steal.*info/i',
        'severity' => 'CRITICAL',
        'description' => 'Potential data theft activity detected'
    ],
    [
        'name' => 'Root Access Attempt',
        'pattern' => '/\bsu\b|superuser|magisk|root.*access|privilege.*escalat/i',
        'severity' => 'HIGH',
        'description' => 'Root or superuser access attempt detected'
    ],
    [
        'name' => 'Crypto Mining',
        'pattern' => '/coinhive|cryptonight|miner.*start|bitcoin.*mine/i',
        'severity' => 'HIGH',
        'description' => 'Cryptocurrency mining activity detected'
    ],
    [
        'name' => 'Permission Bypass',
        'pattern' => '/permission.*denied.*bypass|escalat.*privilege|bypass.*security/i',
        'severity' => 'MEDIUM',
        'description' => 'Attempt to bypass permission restrictions'
    ],
    [
        'name' => 'SSL/TLS Bypass',
        'pattern' => '/ssl.*bypass|certificate.*ignore|trust.*all.*cert/i',
        'severity' => 'HIGH',
        'description' => 'SSL/TLS certificate validation bypass detected'
    ],
    [
        'name' => 'Keylogger Detection',
        'pattern' => '/keylog|keystroke.*capture|input.*monitor|key.*intercept/i',
        'severity' => 'CRITICAL',
        'description' => 'Potential keylogger activity detected'
    ],
    [
        'name' => 'SMS Fraud',
        'pattern' => '/premium.*sms|send.*sms.*silent|sms.*intercept|subscription.*sms/i',
        'severity' => 'HIGH',
        'description' => 'Suspicious SMS activity detected'
    ],
    [
        'name' => 'Debugging Enabled',
        'pattern' => '/debug.*enabled|debuggable.*true|usb.*debug/i',
        'severity' => 'MEDIUM',
        'description' => 'Debug mode enabled on device'
    ],
    [
        'name' => 'Suspicious Install',
        'pattern' => '/unknown.*source|sideload|apk.*install.*untrusted/i',
        'severity' => 'MEDIUM',
        'description' => 'App installed from unknown source'
    ]
];

$logsPath = getLogsPath();
$filesToScan = [
    $logsPath . '/android_logcat.txt',
    $logsPath . '/sms_logs.txt',
    $logsPath . '/call_logs.txt'
];

try {
    foreach ($filesToScan as $file) {
        if (!file_exists($file))
            continue;

        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        $source = basename($file, '.txt');

        foreach ($lines as $lineNum => $line) {
            foreach ($THREAT_SIGNATURES as $signature) {
                if (preg_match($signature['pattern'], $line)) {
                    // Extract timestamp if available
                    $timestamp = date('Y-m-d H:i:s');
                    if (preg_match('/^(\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $match)) {
                        $timestamp = date('Y-') . $match[1];
                    }

                    $response['threats'][] = [
                        'type' => $signature['name'],
                        'severity' => $signature['severity'],
                        'description' => $signature['description'],
                        'source' => ucfirst(str_replace('_', ' ', $source)),
                        'timestamp' => $timestamp,
                        'line' => $lineNum + 1,
                        'excerpt' => substr($line, 0, 200)
                    ];

                    // Update summary
                    $response['summary'][strtolower($signature['severity'])]++;

                    // Don't match same line multiple times for same signature
                    break;
                }
            }
        }
    }

    // Sort threats by severity
    usort($response['threats'], function ($a, $b) {
        $order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
        return ($order[$a['severity']] ?? 4) - ($order[$b['severity']] ?? 4);
    });

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
