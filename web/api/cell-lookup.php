<?php
/**
 * Android Forensic Tool - Cell Tower Geolocation Lookup API
 * Converts cell tower identifiers (MCC/MNC/LAC/CID) to approximate GPS coordinates
 * 
 * Uses OpenCellID or falls back to country-level approximation
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';

$response = [
    'success' => false,
    'coordinates' => null,
    'source' => null,
    'error' => null
];

// Cache file for cell tower lookups
$logsPath = getLogsPath();
$cacheFile = $logsPath . '/cell_cache.json';

/**
 * Load cache from file
 */
function loadCache(string $cacheFile): array {
    if (file_exists($cacheFile)) {
        $content = file_get_contents($cacheFile);
        return json_decode($content, true) ?? [];
    }
    return [];
}

/**
 * Save to cache
 */
function saveCache(string $cacheFile, array $cache): void {
    file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
}

/**
 * Get cache key for a cell tower
 */
function getCacheKey(int $mcc, int $mnc, int $lac, int $cid): string {
    return "{$mcc}_{$mnc}_{$lac}_{$cid}";
}

/**
 * India MCC codes and approximate state centers
 * MCC 404 and 405 are India
 */
$INDIA_REGIONS = [
    // Major cities/regions with approximate centers
    'default' => ['lat' => 20.5937, 'lng' => 78.9629, 'name' => 'India'],
    
    // State-level approximations based on common LAC ranges
    // These are fallback approximations when API lookup fails
    'delhi' => ['lat' => 28.6139, 'lng' => 77.2090, 'name' => 'Delhi'],
    'mumbai' => ['lat' => 19.0760, 'lng' => 72.8777, 'name' => 'Mumbai'],
    'bangalore' => ['lat' => 12.9716, 'lng' => 77.5946, 'name' => 'Bangalore'],
    'chennai' => ['lat' => 13.0827, 'lng' => 80.2707, 'name' => 'Chennai'],
    'kolkata' => ['lat' => 22.5726, 'lng' => 88.3639, 'name' => 'Kolkata'],
    'hyderabad' => ['lat' => 17.3850, 'lng' => 78.4867, 'name' => 'Hyderabad'],
    'pune' => ['lat' => 18.5204, 'lng' => 73.8567, 'name' => 'Pune'],
    'ahmedabad' => ['lat' => 23.0225, 'lng' => 72.5714, 'name' => 'Ahmedabad'],
    'jaipur' => ['lat' => 26.9124, 'lng' => 75.7873, 'name' => 'Jaipur'],
    'lucknow' => ['lat' => 26.8467, 'lng' => 80.9462, 'name' => 'Lucknow'],
];

/**
 * Indian carrier MNC codes
 */
$INDIA_CARRIERS = [
    '01' => 'MTNL Delhi',
    '02' => 'Airtel',
    '03' => 'Airtel',
    '04' => 'IDEA',
    '05' => 'Vodafone',
    '10' => 'Airtel',
    '11' => 'Vodafone',
    '12' => 'Idea',
    '14' => 'IDEA',
    '19' => 'Idea',
    '20' => 'Vodafone',
    '21' => 'BSNL',
    '22' => 'Idea',
    '24' => 'IDEA',
    '27' => 'Vodafone',
    '30' => 'Vodafone',
    '31' => 'Airtel',
    '34' => 'BSNL',
    '38' => 'BSNL',
    '40' => 'Airtel',
    '44' => 'IDEA',
    '45' => 'Airtel',
    '49' => 'Airtel',
    '51' => 'MTNL Mumbai',
    '52' => 'Reliance Jio',
    '53' => 'MTNL',
    '54' => 'BSNL',
    '55' => 'BSNL',
    '56' => 'Idea',
    '66' => 'Vodafone',
    '67' => 'Reliance',
    '68' => 'MTNL Delhi',
    '70' => 'Idea',
    '72' => 'BSNL',
    '74' => 'BSNL',
    '76' => 'BSNL',
    '78' => 'Idea',
    '79' => 'BSNL',
    '80' => 'BSNL',
    '81' => 'BSNL',
    '82' => 'Idea',
    '84' => 'Vodafone',
    '85' => 'Reliance Jio',
    '86' => 'Vodafone',
    '87' => 'Idea',
    '88' => 'Vodafone',
    '89' => 'Idea',
    '90' => 'Airtel',
    '91' => 'Airtel',
    '92' => 'Airtel',
    '93' => 'Airtel',
    '94' => 'Airtel',
    '95' => 'Airtel',
    '96' => 'Airtel',
    '97' => 'Airtel',
    '98' => 'Airtel',
    '99' => 'BSNL',
];

/**
 * Attempt to lookup cell tower location using OpenCellID API
 * Requires API key set in environment or config
 */
function lookupOpenCellID(int $mcc, int $mnc, int $lac, int $cid): ?array {
    // Check for API key
    $apiKey = getenv('OPENCELLID_API_KEY') ?: (defined('OPENCELLID_API_KEY') ? OPENCELLID_API_KEY : null);
    
    if (!$apiKey) {
        return null; // No API key configured
    }
    
    $url = "https://opencellid.org/cell/get?key={$apiKey}&mcc={$mcc}&mnc={$mnc}&lac={$lac}&cellid={$cid}&format=json";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['lat']) && isset($data['lon'])) {
            return [
                'lat' => (float)$data['lat'],
                'lng' => (float)$data['lon'],
                'accuracy' => (int)($data['range'] ?? 1000),
                'source' => 'opencellid'
            ];
        }
    }
    
    return null;
}

/**
 * Attempt to lookup cell tower location using Unwired Labs API
 * Requires API key set in config
 */
function lookupUnwiredLabs(int $mcc, int $mnc, int $lac, int $cid): ?array {
    // Check for API key
    $apiKey = defined('UNWIREDLABS_API_KEY') ? UNWIREDLABS_API_KEY : null;
    
    if (!$apiKey) {
        return null; // No API key configured
    }
    
    $url = "https://us1.unwiredlabs.com/v2/process.php";
    
    $postData = json_encode([
        'token' => $apiKey,
        'radio' => 'gsm',
        'mcc' => $mcc,
        'mnc' => $mnc,
        'cells' => [[
            'lac' => $lac,
            'cid' => $cid
        ]],
        'address' => 1
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $postData,
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['lat']) && isset($data['lon']) && ($data['status'] ?? '') === 'ok') {
            return [
                'lat' => (float)$data['lat'],
                'lng' => (float)$data['lon'],
                'accuracy' => (int)($data['accuracy'] ?? 1000),
                'source' => 'unwiredlabs',
                'address' => $data['address'] ?? null
            ];
        }
    }
    
    return null;
}

/**
 * Fallback: Approximate location based on MCC/MNC
 * For India (MCC 404/405), return country center
 */
function approximateLocation(int $mcc, int $mnc, int $lac, int $cid): array {
    global $INDIA_REGIONS, $INDIA_CARRIERS;
    
    // India MCCs
    if ($mcc === 404 || $mcc === 405) {
        $region = $INDIA_REGIONS['default'];
        $carrier = $INDIA_CARRIERS[str_pad($mnc, 2, '0', STR_PAD_LEFT)] ?? 'Unknown';
        
        return [
            'lat' => $region['lat'],
            'lng' => $region['lng'],
            'accuracy' => 50000, // 50km accuracy for country-level
            'source' => 'approximate',
            'region' => $region['name'],
            'carrier' => $carrier
        ];
    }
    
    // Other countries - return null (would need country database)
    return [
        'lat' => 20.5937,
        'lng' => 78.9629,
        'accuracy' => 100000,
        'source' => 'fallback',
        'region' => 'Unknown',
        'carrier' => 'Unknown'
    ];
}

// Handle request
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Also support GET parameters
$mcc = (int)($input['mcc'] ?? $_GET['mcc'] ?? 0);
$mnc = (int)($input['mnc'] ?? $_GET['mnc'] ?? 0);
$lac = (int)($input['lac'] ?? $_GET['lac'] ?? 0);
$cid = (int)($input['cid'] ?? $_GET['cid'] ?? 0);

if ($mcc === 0 || $cid === 0) {
    $response['error'] = 'Missing required parameters: mcc, mnc, lac, cid';
    echo json_encode($response);
    exit;
}

try {
    $cache = loadCache($cacheFile);
    $cacheKey = getCacheKey($mcc, $mnc, $lac, $cid);
    
    // Check cache first
    if (isset($cache[$cacheKey])) {
        $response['success'] = true;
        $response['coordinates'] = $cache[$cacheKey];
        $response['source'] = 'cache';
        echo json_encode($response);
        exit;
    }
    
    // Try OpenCellID lookup first
    $coords = lookupOpenCellID($mcc, $mnc, $lac, $cid);
    
    // Try Unwired Labs if OpenCellID failed
    if (!$coords) {
        $coords = lookupUnwiredLabs($mcc, $mnc, $lac, $cid);
    }
    
    if ($coords) {
        // Cache the result
        $cache[$cacheKey] = $coords;
        saveCache($cacheFile, $cache);
        
        $response['success'] = true;
        $response['coordinates'] = $coords;
        $response['source'] = $coords['source'];
    } else {
        // Fallback to approximation
        $coords = approximateLocation($mcc, $mnc, $lac, $cid);
        
        $response['success'] = true;
        $response['coordinates'] = $coords;
        $response['source'] = 'approximate';
        $response['note'] = 'No API keys configured. Set OPENCELLID_API_KEY or UNWIREDLABS_API_KEY in config.php for accurate lookups.';
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
