<?php
/**
 * Android Forensic Tool - Comprehensive Location Extraction API
 * Extracts location data from 6 different sources for maximum coverage
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$response = [
    'success' => false,
    'message' => '',
    'stats' => [
        'total_coordinates' => 0,
        'cell_towers' => 0,
        'wifi_networks' => 0,
        'sources_extracted' => 0
    ],
    'sources' => [],
    'error' => null
];

$logsPath = getLogsPath();

// Ensure logs directory exists
if (!is_dir($logsPath)) {
    mkdir($logsPath, 0755, true);
}

/**
 * Execute ADB command safely with timeout
 */
function execAdb(string $command, int $timeout = 10): array {
    $output = [];
    $returnCode = 0;
    
    // Execute with timeout on Windows
    $fullCommand = "adb $command 2>&1";
    exec($fullCommand, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'lines' => count($output)
    ];
}

/**
 * Count location entries in content
 */
function countLocations(string $content): int {
    return substr_count($content, 'Location[');
}

/**
 * Count cell tower entries in content
 */
function countCellTowers(string $content): int {
    return substr_count($content, 'CellLocation') + 
           substr_count($content, 'mCellInfo') +
           preg_match_all('/\b(mcc|MCC)\s*[=:]\s*\d+/', $content);
}

/**
 * Parse cell tower data from telephony registry
 */
function parseCellTowerData(string $content): array {
    $towers = [];
    
    // Match patterns like: mcc=404 mnc=10 lac=1234 cid=56789
    // Also match: CellLocation[mcc,mnc,lac,cid,psc]
    
    // Pattern 1: Key-value format
    if (preg_match_all('/mcc[=:]\s*(\d+).*?mnc[=:]\s*(\d+).*?lac[=:]\s*(\d+).*?cid[=:]\s*(\d+)/is', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $towers[] = [
                'mcc' => $match[1],
                'mnc' => $match[2],
                'lac' => $match[3],
                'cid' => $match[4],
                'type' => 'gsm'
            ];
        }
    }
    
    // Pattern 2: CellLocation format
    if (preg_match_all('/CellLocation.*?(\d+),\s*(\d+),\s*(\d+),\s*(\d+)/', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $towers[] = [
                'mcc' => $match[1],
                'mnc' => $match[2],
                'lac' => $match[3],
                'cid' => $match[4],
                'type' => 'gsm'
            ];
        }
    }
    
    // Pattern 3: CellIdentityLte format
    if (preg_match_all('/CellIdentityLte.*?mcc\s*=\s*(\d+).*?mnc\s*=\s*(\d+).*?ci\s*=\s*(\d+).*?tac\s*=\s*(\d+)/is', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $towers[] = [
                'mcc' => $match[1],
                'mnc' => $match[2],
                'cid' => $match[3],
                'tac' => $match[4],
                'type' => 'lte'
            ];
        }
    }
    
    // Remove duplicates
    return array_unique($towers, SORT_REGULAR);
}

try {
    $sources = [];
    $allContent = '';
    $totalLocations = 0;
    $totalCellTowers = 0;
    $totalWifi = 0;
    
    // ========================================
    // SOURCE 1: dumpsys location (Last known GPS locations)
    // ========================================
    $result = execAdb('shell dumpsys location');
    if ($result['success'] && !empty($result['output'])) {
        file_put_contents($logsPath . '/location_logs.txt', $result['output']);
        $locCount = countLocations($result['output']);
        $totalLocations += $locCount;
        $sources['dumpsys_location'] = [
            'status' => 'success',
            'locations' => $locCount,
            'lines' => $result['lines']
        ];
        $allContent .= $result['output'] . "\n";
        $response['stats']['sources_extracted']++;
    } else {
        $sources['dumpsys_location'] = ['status' => 'failed', 'error' => 'No output'];
    }
    
    // ========================================
    // SOURCE 2: dumpsys telephony.registry (Cell tower info)
    // ========================================
    $result = execAdb('shell dumpsys telephony.registry');
    if ($result['success'] && !empty($result['output'])) {
        file_put_contents($logsPath . '/cell_tower_data.txt', $result['output']);
        $cellTowers = parseCellTowerData($result['output']);
        $cellCount = count($cellTowers);
        $totalCellTowers += $cellCount;
        
        // Save parsed cell towers as JSON for easy lookup
        file_put_contents($logsPath . '/cell_towers.json', json_encode($cellTowers, JSON_PRETTY_PRINT));
        
        $sources['telephony_registry'] = [
            'status' => 'success',
            'cell_towers' => $cellCount,
            'lines' => $result['lines']
        ];
        $allContent .= $result['output'] . "\n";
        $response['stats']['sources_extracted']++;
    } else {
        $sources['telephony_registry'] = ['status' => 'failed', 'error' => 'No output or permission denied'];
    }
    
    // ========================================
    // SOURCE 3: logcat location history
    // ========================================
    $result = execAdb('logcat -d -v time', 15);
    if ($result['success'] && !empty($result['output'])) {
        // Filter for location-related entries
        $lines = explode("\n", $result['output']);
        $locationLines = array_filter($lines, function($line) {
            $lower = strtolower($line);
            return strpos($lower, 'location[') !== false ||
                   strpos($lower, 'latitude') !== false ||
                   strpos($lower, 'longitude') !== false ||
                   strpos($lower, 'gpslocationprovider') !== false ||
                   strpos($lower, 'networklocationprovider') !== false;
        });
        
        $locationContent = implode("\n", $locationLines);
        file_put_contents($logsPath . '/logcat_location_history.txt', $locationContent);
        $locCount = countLocations($locationContent);
        $totalLocations += $locCount;
        
        $sources['logcat_history'] = [
            'status' => 'success',
            'locations' => $locCount,
            'filtered_lines' => count($locationLines)
        ];
        $allContent .= $locationContent . "\n";
        $response['stats']['sources_extracted']++;
    } else {
        $sources['logcat_history'] = ['status' => 'failed'];
    }
    
    // ========================================
    // SOURCE 4: WiFi networks (for location context)
    // ========================================
    $result = execAdb('shell dumpsys wifi');
    if ($result['success'] && !empty($result['output'])) {
        file_put_contents($logsPath . '/wifi_networks.txt', $result['output']);
        $wifiCount = substr_count($result['output'], 'SSID');
        $totalWifi += $wifiCount;
        
        $sources['wifi_networks'] = [
            'status' => 'success',
            'networks' => $wifiCount,
            'lines' => $result['lines']
        ];
        $response['stats']['sources_extracted']++;
    } else {
        $sources['wifi_networks'] = ['status' => 'failed'];
    }
    
    // ========================================
    // SOURCE 5: Radio buffer (cell tower history)
    // ========================================
    $result = execAdb('logcat -b radio -d -v time', 15);
    if ($result['success'] && !empty($result['output'])) {
        file_put_contents($logsPath . '/radio_buffer.txt', $result['output']);
        $cellCount = countCellTowers($result['output']);
        
        $sources['radio_buffer'] = [
            'status' => 'success',
            'cell_entries' => $cellCount,
            'lines' => $result['lines']
        ];
        $allContent .= $result['output'] . "\n";
        $response['stats']['sources_extracted']++;
    } else {
        $sources['radio_buffer'] = ['status' => 'failed'];
    }
    
    // ========================================
    // SOURCE 6: Network location cache (GMS)
    // ========================================
    $result = execAdb('shell dumpsys activity service com.google.android.gms/.location.reporting.service.ReportingAndroidService');
    if ($result['success'] && !empty($result['output']) && strlen($result['output']) > 100) {
        file_put_contents($logsPath . '/network_location_cache.txt', $result['output']);
        $locCount = countLocations($result['output']);
        $totalLocations += $locCount;
        
        $sources['gms_cache'] = [
            'status' => 'success',
            'locations' => $locCount,
            'lines' => $result['lines']
        ];
        $response['stats']['sources_extracted']++;
    } else {
        $sources['gms_cache'] = ['status' => 'skipped', 'reason' => 'GMS not available or no data'];
    }
    
    // ========================================
    // SOURCE 7: Settings provider cached locations
    // ========================================
    $result = execAdb('shell content query --uri content://settings/secure --projection value --where "name=\'location_providers_allowed\'"');
    if ($result['success'] && !empty($result['output'])) {
        file_put_contents($logsPath . '/settings_location.txt', $result['output']);
        $sources['settings_provider'] = [
            'status' => 'success',
            'lines' => $result['lines']
        ];
        $response['stats']['sources_extracted']++;
    } else {
        $sources['settings_provider'] = ['status' => 'skipped'];
    }
    
    // ========================================
    // SOURCE 8: Google Fused Location Provider cache
    // ========================================
    $result = execAdb('shell dumpsys activity service com.google.android.gms/.location.fused.FusedLocationProviderService');
    if ($result['success'] && !empty($result['output']) && strlen($result['output']) > 100) {
        file_put_contents($logsPath . '/fused_location.txt', $result['output']);
        $locCount = countLocations($result['output']);
        $totalLocations += $locCount;
        
        $sources['fused_location'] = [
            'status' => 'success',
            'locations' => $locCount,
            'lines' => $result['lines']
        ];
        $allContent .= $result['output'] . "\n";
        $response['stats']['sources_extracted']++;
    } else {
        $sources['fused_location'] = ['status' => 'skipped', 'reason' => 'Fused provider not available'];
    }
    
    // ========================================
    // SOURCE 9: Historical location from databases (if accessible)
    // ========================================
    $result = execAdb('shell "cat /data/data/com.google.android.gms/databases/herrevad 2>/dev/null | head -c 10000"');
    if ($result['success'] && !empty($result['output']) && strlen($result['output']) > 100) {
        file_put_contents($logsPath . '/location_history_db.txt', $result['output']);
        $sources['location_history_db'] = [
            'status' => 'success',
            'size' => strlen($result['output'])
        ];
        $response['stats']['sources_extracted']++;
    } else {
        $sources['location_history_db'] = ['status' => 'skipped', 'reason' => 'Requires root access'];
    }
    
    // ========================================
    // SOURCE 10: APK location caches (cached_locations)
    // ========================================
    $result = execAdb('shell "ls -la /data/data/*/cache/*location* 2>/dev/null"');
    if ($result['success'] && !empty($result['output']) && strlen($result['output']) > 10) {
        file_put_contents($logsPath . '/app_location_caches.txt', $result['output']);
        $sources['app_caches'] = [
            'status' => 'success',
            'info' => 'Found app location cache files'
        ];
        $response['stats']['sources_extracted']++;
    } else {
        $sources['app_caches'] = ['status' => 'skipped', 'reason' => 'No accessible app caches'];
    }
    
    // ========================================
    // Compile results
    // ========================================
    $response['success'] = true;
    $response['message'] = "Extracted location data from {$response['stats']['sources_extracted']} sources";
    $response['stats']['total_coordinates'] = $totalLocations;
    $response['stats']['cell_towers'] = $totalCellTowers;
    $response['stats']['wifi_networks'] = $totalWifi;
    $response['sources'] = $sources;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
