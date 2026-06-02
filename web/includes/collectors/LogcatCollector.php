<?php
/**
 * Logcat Location Collector
 * Priority 1: GPS, Network, Fused location from logcat
 * 
 * NON-ROOT, HIGH RELIABILITY
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class LogcatCollector implements LocationCollectorInterface
{
    private $logsPath;
    
    public function __construct()
    {
        $this->logsPath = getLogsPath();
    }
    
    public function getName()
    {
        return 'Logcat Location';
    }
    
    public function canRun()
    {
        // Check if logcat location files exist
        $files = [
            $this->logsPath . '/location_logs.txt',
            $this->logsPath . '/logcat_location_history.txt'
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getRetentionEstimate()
    {
        // Logcat typically retains a few hours to days depending on verbosity
        return 'Hours';
    }
    
    public function collect()
    {
        $points = [];
        
        try {
            // Source 1: Main location logs
            $locationFile = $this->logsPath . '/location_logs.txt';
            if (file_exists($locationFile)) {
                $content = file_get_contents($locationFile);
                $points = array_merge($points, $this->parseLogcatContent($content, $locationFile));
            }
            
            // Source 2: Location history
            $historyFile = $this->logsPath . '/logcat_location_history.txt';
            if (file_exists($historyFile)) {
                $content = file_get_contents($historyFile);
                $points = array_merge($points, $this->parseLogcatContent($content, $historyFile));
            }
            
        } catch (Exception $e) {
            error_log("LogcatCollector error: " . $e->getMessage());
        }
        
        return $points;
    }
    
    /**
     * Parse logcat content for location fixes
     */
    private function parseLogcatContent($content, $sourceFile)
    {
        $points = [];
        $lines = explode("\n", $content);
        
        // Pattern: Location[provider lat,lon acc=X time=X]
        $pattern = '/Location\[(\w+)\s+([-+]?\d+\.\d+),([-+]?\d+\.\d+)/';
        
        foreach ($lines as $lineNum => $line) {
            try {
                if (preg_match($pattern, $line, $match)) {
                    $provider = strtolower($match[1]);
                    $lat = (float) $match[2];
                    $lon = (float) $match[3];
                    
                    // Skip invalid coordinates
                    if ($lat == 0.0 && $lon == 0.0) continue;
                    if (abs($lat) > 90 || abs($lon) > 180) continue;
                    
                    // Extract accuracy
                    $accuracy = null;
                    if (preg_match('/acc=([\d.]+)/', $line, $accMatch)) {
                        $accuracy = (float) $accMatch[1];
                    }
                    
                    // Extract timestamp
                    $timestamp = $this->extractTimestamp($line);
                    
                    // Determine source type
                    $sourceType = $this->mapProviderToSourceType($provider);
                    
                    // Create forensic location point
                    $points[] = new ForensicLocationPoint([
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'timestamp' => $timestamp['iso'],
                        'timestamp_unix' => $timestamp['unix'],
                        'retention_estimate' => $this->getRetentionEstimate(),
                        'source_type' => $sourceType,
                        'origin' => 'logcat',
                        'provider' => $provider,
                        'raw_reference' => basename($sourceFile) . ':' . ($lineNum + 1),
                        'precision_meters' => $accuracy,
                        'is_inferred' => false, // Direct from device
                        'metadata' => [
                            'logcat_line' => substr($line, 0, 200)
                        ]
                    ]);
                }
            } catch (Exception $e) {
                // Skip malformed lines silently
                continue;
            }
        }
        
        return $points;
    }
    
    /**
     * Extract timestamp from logcat line
     */
    private function extractTimestamp($line)
    {
        $unix = time(); // Default to now
        
        // Try to extract millisecond timestamp
        if (preg_match('/time=(\d+)/', $line, $timeMatch)) {
            $unix = (int) ($timeMatch[1] / 1000);
        }
        // Try elapsed time (less reliable)
        elseif (preg_match('/et=([+\-]?\w+)/', $line, $etMatch)) {
            // Elapsed time is relative, use current time
            $unix = time();
        }
        
        return [
            'unix' => $unix,
            'iso' => date('c', $unix)
        ];
    }
    
    /**
     * Map provider string to source type
     */
    private function mapProviderToSourceType($provider)
    {
        $map = [
            'gps' => 'GPS',
            'network' => 'Network',
            'fused' => 'Fused',
            'passive' => 'Network'
        ];
        
        return $map[$provider] ?? 'Network';
    }
}
