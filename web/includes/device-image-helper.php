<?php
/**
 * Android Forensic Tool - Device Image Helper
 * Resolves device model to image using offline fallback database
 */

/**
 * Normalize device model string for lookup
 */
function normalizeDeviceModel($model) {
    $model = strtolower(trim($model));
    $model = preg_replace('/[^a-z0-9-]/', '', $model); // Remove special chars except hyphen
    return $model;
}

/**
 * Normalize manufacturer name
 */
function normalizeManufacturer($manufacturer) {
    $mfr = strtolower(trim($manufacturer));
    // Common manufacturer aliases
    $aliases = [
        'google' => 'google',
        'samsung' => 'samsung',
        'xiaomi' => 'xiaomi',
        'oneplus' => 'oneplus',
        'oppo' => 'oppo',
        'vivo' => 'vivo',
        'sony' => 'sony',
        'motorola' => 'motorola',
        'moto' => 'motorola',
        'lg' => 'lg',
        'huawei' => 'huawei'
    ];
    return $aliases[$mfr] ?? $mfr;
}

/**
 * Resolve device image from model and manufacturer
 * 
 * @param string $model Device model (e.g., "SM-G998B", "Pixel 7 Pro")
 * @param string $manufacturer Device manufacturer (e.g., "samsung", "google")
 * @return array ['image' => path, 'marketName' => name, 'found' => bool]
 */
function resolveDeviceImage($model, $manufacturer = '') {
    // Default response
    $response = [
        'image' => 'assets/images/devices/generic-phone.svg',
        'marketName' => $model,
        'found' => false,
        'source' => 'generic'
    ];

    if (empty($model)) {
        return $response;
    }

    // Normalize inputs
    $normalizedModel = normalizeDeviceModel($model);
    $normalizedMfr = normalizeManufacturer($manufacturer);

    // 1. Check local cache first
    $basePath = __DIR__ . '/../assets/images/devices/';
    $cacheKey = "{$normalizedMfr}_{$normalizedModel}";
    
    $extensions = ['webp', 'png', 'jpg'];
    foreach ($extensions as $ext) {
        $cachePath = $basePath . "cache/{$cacheKey}.{$ext}";
        if (file_exists($cachePath)) {
            $response['image'] = "assets/images/devices/cache/{$cacheKey}.{$ext}";
            $response['found'] = true;
            $response['source'] = 'cache';
            return $response;
        }
    }

    // 2. Check fallback database
    $dbPath = $basePath . 'device-database.json';
    if (file_exists($dbPath)) {
        $dbContent = file_get_contents($dbPath);
        $database = json_decode($dbContent, true);

        if ($database && isset($database['devices'])) {
            // Try exact match first
            foreach ($database['devices'] as $device) {
                $deviceModel = normalizeDeviceModel($device['model']);
                $deviceMfr = normalizeManufacturer($device['manufacturer']);

                if ($deviceModel === $normalizedModel && $deviceMfr === $normalizedMfr) {
                    $imagePath = "assets/images/devices/fallback/" . $device['image'];
                    if (file_exists($basePath . "fallback/" . $device['image'])) {
                        $response['image'] = $imagePath;
                        $response['marketName'] = $device['marketName'];
                        $response['found'] = true;
                        $response['source'] = 'fallback';
                        return $response;
                    }
                }

                // Check aliases
                if (isset($device['aliases']) && is_array($device['aliases'])) {
                    foreach ($device['aliases'] as $alias) {
                        if (normalizeDeviceModel($alias) === $normalizedModel) {
                            $imagePath = "assets/images/devices/fallback/" . $device['image'];
                            if (file_exists($basePath . "fallback/" . $device['image'])) {
                                $response['image'] = $imagePath;
                                $response['marketName'] = $device['marketName'];
                                $response['found'] = true;
                                $response['source'] = 'fallback-alias';
                                return $response;
                            }
                        }
                    }
                }
            }

            // Check global aliases
            if (isset($database['aliases'])) {
                $aliasKey = normalizeDeviceModel($model);
                if (isset($database['aliases'][$aliasKey])) {
                    $deviceId = $database['aliases'][$aliasKey];
                    foreach ($database['devices'] as $device) {
                        if ($device['id'] === $deviceId) {
                            $imagePath = "assets/images/devices/fallback/" . $device['image'];
                            if (file_exists($basePath . "fallback/" . $device['image'])) {
                                $response['image'] = $imagePath;
                                $response['marketName'] = $device['marketName'];
                                $response['found'] = true;
                                $response['source'] = 'global-alias';
                                return $response;
                            }
                        }
                    }
                }
            }
        }
    }

    // 3. Final fallback - generic icon (NO GSMArena fetch here to prevent blocking)
    return $response;
}

/**
 * GSMArena fetch is intentionally REMOVED from synchronous device-status flow
 * to prevent blocking and deadlocks. Image fetching should be done client-side
 * or via separate async endpoint.
 */
