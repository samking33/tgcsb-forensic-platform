<?php
/**
 * App Location Collector
 * Priority 5: Opportunistic app-level location data from logcat
 * 
 * NON-ROOT, OPPORTUNISTIC ONLY
 * ALWAYS LOW CONFIDENCE
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class AppLocationCollector implements LocationCollectorInterface
{
    private $logsPath;
    
    public function __construct()
    {
        $this->logsPath = getLogsPath();
    }
    
    public function getName()
    {
        return 'App Location Logs';
    }
    
    public function canRun()
    {
        $file = $this->logsPath . '/app_location.txt';
        return file_exists($file);
    }
    
    public function getRetentionEstimate()
    {
        return 'Hours';
    }
    
    public function collect()
    {
        $points = [];
        
        try {
            $file = $this->logsPath . '/app_location.txt';
            if (!file_exists($file)) {
                return $points;
            }
            
            $content = file_get_contents($file);
            $points = $this->parseAppLocationLogs($content);
            
        } catch (Exception $e) {
            error_log("AppLocationCollector error: " . $e->getMessage());
        }
        
        return $points;
    }
    
    /**
     * Parse app-level location data (OPPORTUNISTIC)
     * MANDATORY: Only accept points with lat + lon + timestamp together
     */
    private function parseAppLocationLogs($content)
    {
        $points = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            try {
                // MANDATORY: Must have lat + lon + timestamp in same line/context
                $lat = null;
                $lon = null;
                $timestamp = null;
                $appPackage = null;
                
                // Extract latitude
                if (preg_match('/lat(?:itude)?[:\s=]+?(\-?\d+\.\d+)/i', $line, $latMatch)) {
                    $lat = (float) $latMatch[1];
                }
                
                // Extract longitude
                if (preg_match('/lon(?:gitude)?[:\s=]+?(\-?\d+\.\d+)/i', $line, $lonMatch)) {
                    $lon = (float) $lonMatch[1];
                }
                
                // MANDATORY: Both coordinates must be present
                if ($lat === null || $lon === null) {
                    continue;
                }
                
                // Validate coordinates
                if ($lat == 0.0 && $lon == 0.0) continue;
                if (abs($lat) > 90 || abs($lon) > 180) continue;
                
                // Extract timestamp
                if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $timeMatch)) {
                    $timestamp = strtotime($timeMatch[1]);
                } else {
                    $timestamp = time(); // Default to now
                }
                
                // Try to extract app package name
                if (preg_match('/([a-z0-9\.]+(?:\.android|\.google|\.maps|\.location)[a-z0-9\.]*)/i', $line, $pkgMatch)) {
                    $appPackage = $pkgMatch[1];
                }
                
                //Create forensic location point
                $points[] = new ForensicLocationPoint([
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'timestamp' => date('c', $timestamp),
                    'timestamp_unix' => $timestamp,
                    'retention_estimate' => $this->getRetentionEstimate(),
                    'source_type' => 'App',
                    'origin' => 'app',
                    'provider' => 'app',
                    'raw_reference' => 'app_location.txt:' . ($lineNum + 1),
                    'precision_meters' => null, // Unknown from app logs
                    'is_inferred' => false, // Direct from app log
                    'metadata' => [
                        'app_package' => $appPackage ?? 'Unknown',
                        'confidence_note' => 'OPPORTUNISTIC - App-level data may be unreliable',
                        'log_snippet' => substr($line, 0, 200)
                    ]
                ]);
                
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $points;
    }
}
