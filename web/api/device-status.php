<?php
/**
 * Android Forensic Tool - Device Status API
 * Returns connected device information with timeout protection
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

// Set max execution time for this script
set_time_limit(10);

// Suppress warnings
error_reporting(E_ERROR | E_PARSE);

// Use ADB from PATH
$adbPath = 'adb';

$response = [
    'connected' => false,
    'device' => null,
    'buffer' => null,
    'error' => null,
    'message' => 'Checking device status...'
];

// Quick test if ADB is accessible - use simple exec instead of proc_open
@exec('adb version 2>&1', $testOutput, $testCode);
if ($testCode !== 0 || empty($testOutput)) {
    $response['error'] = 'ADB not accessible';
    $response['message'] = 'Please ensure ADB is in system PATH';
    echo json_encode($response);
    exit;
}

// ADB is accessible, now check for devices using simple exec
@exec('adb devices 2>&1', $deviceOutput, $deviceCode);
if ($deviceCode === 0 && !empty($deviceOutput)) {
    foreach ($deviceOutput as $line) {
        if (strpos($line, 'device') !== false && strpos($line, 'List') === false && strpos($line, 'offline') === false) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[1] === 'device') {
                // ============================================
                // STATE 1: CONNECTION (AUTHORITATIVE)
                // This MUST be set first and CANNOT be overridden
                // ============================================
                $response['connected'] = true;
                $response['message'] = 'Device connected successfully';
                
                // Initialize device with safe defaults
                $response['device'] = [
                    'serial' => $parts[0],
                    'model' => 'Unknown Model',
                    'manufacturer' => 'Unknown',
                    'name' => 'Android Device',
                    'android' => 'Unknown',
                    'image' => 'assets/images/devices/generic-phone.svg',
                    'imageFound' => false
                ];

                // ============================================
                // STATE 2: METADATA (OPTIONAL, NON-FATAL)
                // Failures here MUST NOT affect connection state
                // ============================================
                try {
                    // Try to get manufacturer
                    @exec('adb shell getprop ro.product.manufacturer 2>&1', $mfrOutput);
                    if (!empty($mfrOutput[0]) && trim($mfrOutput[0]) !== '') {
                        $response['device']['manufacturer'] = trim($mfrOutput[0]);
                    }
                } catch (Exception $e) {
                    // Silently fail - connection state unaffected
                }

                try {
                    // Try to get model name
                    @exec('adb shell getprop ro.product.model 2>&1', $modelOutput);
                    if (!empty($modelOutput[0]) && trim($modelOutput[0]) !== '') {
                        $response['device']['model'] = trim($modelOutput[0]);
                    }
                } catch (Exception $e) {
                    // Silently fail - connection state unaffected
                }

                try {
                    // Try to get Android version
                    @exec('adb shell getprop ro.build.version.release 2>&1', $versionOutput);
                    if (!empty($versionOutput[0]) && trim($versionOutput[0]) !== '') {
                        $response['device']['android'] = trim($versionOutput[0]);
                    }
                } catch (Exception $e) {
                    // Silently fail - connection state unaffected
                }

                // ============================================
                // STATE 3: VISUALIZATION (COSMETIC, NON-BLOCKING)
                // Image resolution MUST be silent and never block
                // ============================================
                try {
                    require_once '../includes/device-image-helper.php';
                    $imageData = @resolveDeviceImage(
                        $response['device']['model'],
                        $response['device']['manufacturer']
                    );
                    
                    if ($imageData && isset($imageData['image'])) {
                        $response['device']['image'] = $imageData['image'];
                        $response['device']['imageFound'] = $imageData['found'] ?? false;
                        if (isset($imageData['marketName']) && !empty($imageData['marketName'])) {
                            $response['device']['name'] = $imageData['marketName'];
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail - use default generic icon
                    // Connection state and metadata unaffected
                }

                break;
            }
        }
    }
}

// Skip buffer check to speed up response
if ($response['connected']) {
    $response['buffer'] = [
        'available' => true,
        'duration' => 'Available',
        'oldest' => 'Ready to extract'
    ];
    $response['message'] = 'Device connected successfully';
} else {
    $response['message'] = 'No device connected';
}

echo json_encode($response);


