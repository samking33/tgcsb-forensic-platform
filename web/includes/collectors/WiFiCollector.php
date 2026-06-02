<?php
/**
 * WiFi Collector
 * Priority 4: WiFi scan results (local only, no external geolocation by default)
 * 
 * NON-ROOT, LOCAL SCANS ONLY
 * EXTERNAL GEOLOCATION: DISABLED BY DEFAULT
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class WiFiCollector implements LocationCollectorInterface
{
    private $logsPath;
    private $externalLookupEnabled = false; // MANDATORY: Default disabled
    
    public function __construct($enableExternalLookup = false)
    {
        $this->logsPath = getLogsPath();
        $this->externalLookupEnabled = $enableExternalLookup;
    }
    
    public function getName()
    {
        return 'WiFi Scans';
    }
    
    public function canRun()
    {
        $file = $this->logsPath . '/wifi_info.txt';
        return file_exists($file);
    }
    
    public function getRetentionEstimate()
    {
        // WiFi dumpsys shows current/recent scans
        return 'Minutes';
    }
    
    public function collect()
    {
        $points = [];
        
        try {
            $file = $this->logsPath . '/wifi_info.txt';
            if (!file_exists($file)) {
                return $points;
            }
            
            $content = file_get_contents($file);
            $wifiScans = $this->parseWiFiScans($content);
            
            // If external lookup is DISABLED (default), log but don't geocode
            if (!$this->externalLookupEnabled) {
                error_log("WiFiCollector: Found " . count($wifiScans) . " WiFi scans (external geolocation disabled)");
                return $points; // Return empty - no lat/lon without lookup
            }
            
            // If enabled, perform geolocation (CLEARLY MARKED AS INFERRED)
            foreach ($wifiScans as $scan) {
                $location = $this->geolocateWiFi($scan);
                if ($location) {
                    $points[] = $location;
                }
            }
            
        } catch (Exception $e) {
            error_log("WiFiCollector error: " . $e->getMessage());
        }
        
        return $points;
    }
    
    /**
     * Parse WiFi scan results from dumpsys
     */
    private function parseWiFiScans($content)
    {
        $scans = [];
        $lines = explode("\n", $content);
        
        $currentScan = [];
        
        foreach ($lines as $line) {
            // Extract BSSID (MAC address)
            if (preg_match('/BSSID:\s*([0-9a-fA-F:]{17})/', $line, $bssidMatch)) {
                if (!empty($currentScan)) {
                    $scans[] = $currentScan;
                }
                $currentScan = ['bssid' => $bssidMatch[1]];
            }
            
            // Extract SSID
            if (isset($currentScan['bssid']) && preg_match('/SSID:\s*(.+)/', $line, $ssidMatch)) {
                $currentScan['ssid'] = trim($ssidMatch[1]);
            }
            
            // Extract signal strength
            if (isset($currentScan['bssid']) && preg_match('/level:\s*(-?\d+)/', $line, $levelMatch)) {
                $currentScan['signal'] = (int) $levelMatch[1];
            }
        }
        
        if (!empty($currentScan)) {
            $scans[] = $currentScan;
        }
        
        // Return top 3 strongest signals only (for geolocation)
        usort($scans, function($a, $b) {
            return ($b['signal'] ?? -100) - ($a['signal'] ?? -100);
        });
        
        return array_slice($scans, 0, 3);
    }
    
    /**
     * Geolocate WiFi networks (INFERRED DATA)
     * MANDATORY: Only called if user explicitly enabled external lookups
     */
    private function geolocateWiFi($scan)
    {
        // STUB: External API call would go here
        // For now, return null (no coordinates without actual API)
        
        // FORENSIC REQUIREMENT: If this were implemented, it MUST return:
        // - is_inferred = true
        // - inference_method = "Mozilla Location Service" or "Google Geolocation API"
        // - inference_risk = "WiFi-based approximation - may be inaccurate"
        
        return null;
        
        /* EXAMPLE IMPLEMENTATION (not active):
        
        try {
            // Query Mozilla Location Service with WiFi BSSIDs
            // ... API call ...
            
            return new ForensicLocationPoint([
                'latitude' => $apiResult['lat'],
                'longitude' => $apiResult['lon'],
                'timestamp' => date('c'),
                'timestamp_unix' => time(),
                'retention_estimate' => 'Minutes',
                'source_type' => 'WiFi',
                'origin' => 'wifi',
                'provider' => 'wifi',
                'raw_reference' => 'WiFi Scan: ' . json_encode($scan),
                'precision_meters' => 200, // Typical WiFi geolocation accuracy
                'is_inferred' => true, // CRITICAL
                'inference_method' => 'Mozilla Location Service',
                'inference_risk' => 'WiFi-based approximation - may be inaccurate',
                'metadata' => [
                    'wifi_bssid' => $scan['bssid'],
                    'wifi_ssid' => $scan['ssid'] ?? 'Hidden',
                    'signal_strength' => $scan['signal'] ?? 'Unknown',
                    'warning' => 'EXTERNALLY INFERRED'
                ]
            ]);
        } catch (Exception $e) {
            return null;
        }
        */
    }
}
