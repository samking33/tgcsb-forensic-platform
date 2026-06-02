<?php
/**
 * Android Forensic Tool - GSMArena Image Fetcher
 * Fetches device images from GSMArena using model number search
 * NON-BLOCKING, CACHES LOCALLY
 */

header('Content-Type: application/json');
require_once '../includes/config.php';

/**
 * Search GSMArena for device by model number
 * @param string $modelNumber Raw model number (e.g., "22101320I")
 * @return array Result with device page URL or error
 */
function searchGSMArena($modelNumber) {
    $searchUrl = "https://www.gsmarena.com/results.php3?sName=" . urlencode($modelNumber);
    
    // Fetch search results with timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $html = @file_get_contents($searchUrl, false, $context);
    
    if ($html === false) {
        return ['success' => false, 'error' => 'Network timeout'];
    }
    
    // Parse search results - look for device links
    preg_match_all('/<a href="([^"]+\.php)">\s*<img src="([^"]+)" title="([^"]+)"/i', $html, $matches);
    
    if (empty($matches[1])) {
        return ['success' => false, 'error' => 'No results found'];
    }
    
    // Require EXACTLY ONE result
    if (count($matches[1]) > 1) {
        return ['success' => false, 'error' => 'Multiple devices found', 'count' => count($matches[1])];
    }
    
    return [
        'success' => true,
        'deviceUrl' => 'https://www.gsmarena.com/' . $matches[1][0],
        'deviceName' => trim($matches[3][0]),
        'thumbnailUrl' => $matches[2][0]
    ];
}

/**
 * Extract high-resolution device image from GSMArena device page
 */
function extractDeviceImage($deviceUrl) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $html = @file_get_contents($deviceUrl, false, $context);
    
    if ($html === false) {
        return ['success' => false, 'error' => 'Failed to fetch device page'];
    }
    
    // Extract main device image
    if (preg_match('/<div class="specs-photo-main">.*?<img src="([^"]+)"/s', $html, $match)) {
        return [
            'success' => true,
            'imageUrl' => $match[1]
        ];
    }
    
    return ['success' => false, 'error' => 'Image not found on page'];
}

/**
 * Download and cache device image
 */
function cacheDeviceImage($imageUrl, $manufacturer, $model) {
    // Normalize cache filename
    $manufacturer = strtolower(preg_replace('/[^a-z0-9]/', '', $manufacturer));
    $model = strtolower(preg_replace('/[^a-z0-9]/', '', $model));
    $cacheFilename = "{$manufacturer}_{$model}.webp";
    $cachePath = __DIR__ . "/../assets/images/devices/cache/{$cacheFilename}";
    
    // Check if already cached
    if (file_exists($cachePath)) {
        return [
            'success' => true,
            'cached' => true,
            'path' => "assets/images/devices/cache/{$cacheFilename}"
        ];
    }
    
    // Download image
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $imageData = @file_get_contents($imageUrl, false, $context);
    
    if ($imageData === false) {
        return ['success' => false, 'error' => 'Failed to download image'];
    }
    
    // Save to cache
    if (@file_put_contents($cachePath, $imageData) === false) {
        return ['success' => false, 'error' => 'Failed to write cache file'];
    }
    
    return [
        'success' => true,
        'cached' => false,
        'path' => "assets/images/devices/cache/{$cacheFilename}",
        'size' => strlen($imageData)
    ];
}

// Main API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $model = $input['model'] ?? '';
    $manufacturer = $input['manufacturer'] ?? '';
    
    if (empty($model)) {
        echo json_encode(['success' => false, 'error' => 'Model number required']);
        exit;
    }
    
    // Step 1: Search GSMArena
    $searchResult = searchGSMArena($model);
    
    if (!$searchResult['success']) {
        echo json_encode($searchResult);
        exit;
    }
    
    // Step 2: Extract device image URL
    $imageResult = extractDeviceImage($searchResult['deviceUrl']);
    
    if (!$imageResult['success']) {
        echo json_encode($imageResult);
        exit;
    }
    
    // Step 3: Cache image locally
    $cacheResult = cacheDeviceImage(
        $imageResult['imageUrl'],
        $manufacturer,
        $model
    );
    
    if (!$cacheResult['success']) {
        echo json_encode($cacheResult);
        exit;
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'deviceName' => $searchResult['deviceName'],
        'imagePath' => $cacheResult['path'],
        'cached' => $cacheResult['cached'],
        'searchUrl' => "https://www.gsmarena.com/results.php3?sName=" . urlencode($model),
        'deviceUrl' => $searchResult['deviceUrl']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
}
