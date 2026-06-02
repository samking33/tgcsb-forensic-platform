<?php
/**
 * Dumpsys Location Collector
 * Priority 2: Last known locations from dumpsys
 * 
 * NON-ROOT, MEDIUM RELIABILITY
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class DumpsysLocationCollector implements LocationCollectorInterface
{
    private $logsPath;
    
    public function __construct()
    {
        $this->logsPath = getLogsPath();
    }
    
    public function getName()
    {
        return 'Dumpsys Location';
    }
    
    public function canRun()
    {
        $file = $this->logsPath . '/location_dumpsys.txt';
        return file_exists($file);
    }
    
    public function getRetentionEstimate()
    {
        // Dumpsys shows last known locations (typically recent)
        return 'Minutes';
    }
    
    public function collect()
    {
        $points = [];
        
        try {
            $file = $this->logsPath . '/location_dumpsys.txt';
            if (!file_exists($file)) {
                return $points;
            }
            
            $content = file_get_contents($file);
            $points = $this->parseDumpsysContent($content, $file);
            
        } catch (Exception $e) {
            error_log("DumpsysLocationCollector error: " . $e->getMessage());
        }
        
        return $points;
    }
    
    /**
     * Parse dumpsys location output
     */
    private function parseDumpsysContent($content, $sourceFile)
    {
        $points = [];
        $lines = explode("\n", $content);
        
        error_log("DumpsysCollector: Processing " . count($lines) . " lines");
        
        // Pattern: Location[provider lat,lon acc=X time=X]
        $pattern = '/Location\[(\w+)\s+([-+]?\d+\.\d+),([-+]?\d+\.\d+)/';
        
        // Also look for "Last Known Location" sections
        $lastKnownPattern = '/Last.*Location.*:\s*Location\[(\w+)\s+([-+]?\d+\.\d+),([-+]?\d+\.\d+)/i';
        
        // NEW: More flexible pattern for dumpsys output
        $flexiblePattern = '/last\s+location[^:]*:\s*([^\[]+)\[([-+]?\d+\.\d+),\s*([-+]?\d+\.\d+)/i';
        
        $matchedLines = 0;
        $skippedZero = 0;
        
        foreach ($lines as $lineNum => $line) {
            try {
                $matches = [];
                $provider = null;
                $lat = null;
                $lon = null;
                
                // Try all patterns
                if (preg_match($pattern, $line, $matches)) {
                    $provider = strtolower($matches[1]);
                    $lat = (float) $matches[2];
                    $lon = (float) $matches[3];
                    error_log("DumpsysCollector: Pattern 1 matched on line $lineNum");
                } elseif (preg_match($lastKnownPattern, $line, $matches)) {
                    $provider = strtolower($matches[1]);
                    $lat = (float) $matches[2];
                    $lon = (float) $matches[3];
                    error_log("DumpsysCollector: Pattern 2 matched on line $lineNum");
                } elseif (preg_match($flexiblePattern, $line, $matches)) {
                    // Extract provider from captured text before coordinates
                    $providerText = $matches[1];
                    $lat = (float) $matches[2];
                    $lon = (float) $matches[3];
                    
                    // Guess provider from context
                    if (stripos($providerText, 'gps') !== false) {
                        $provider = 'gps';
                    } elseif (stripos($providerText, 'network') !== false) {
                        $provider = 'network';
                    } elseif (stripos($providerText, 'fused') !== false) {
                        $provider = 'fused';
                    } else {
                        $provider = 'passive';
                    }
                    error_log("DumpsysCollector: Pattern 3 (flexible) matched on line $lineNum - provider: $provider");
                }
                
                if ($lat !== null && $lon !== null) {
                    $matchedLines++;
                    
                    // Validate coordinates
                    if ($lat == 0.0 && $lon == 0.0) {
                        $skippedZero++;
                        error_log("DumpsysCollector: Skipped 0,0 coordinate on line $lineNum");
                        continue;
                    }
                    if (abs($lat) > 90 || abs($lon) > 180) {
                        error_log("DumpsysCollector: Invalid coordinates ($lat, $lon) on line $lineNum");
                        continue;
                    }
                    
                    // Extract accuracy
                    $accuracy = null;
                    if (preg_match('/acc=([\d.]+)/', $line, $accMatch)) {
                        $accuracy = (float) $accMatch[1];
                    }
                    
                    // Extract timestamp
                    $timestamp = $this->extractTimestamp($line);
                    
                    // Determine source type
                    $sourceType = $this->mapProviderToSourceType($provider);
                    
                    error_log("DumpsysCollector: Creating point - lat:$lat, lon:$lon, provider:$provider, type:$sourceType");
                    
                    // Create forensic location point
                    $points[] = new ForensicLocationPoint([
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'timestamp' => $timestamp['iso'],
                        'timestamp_unix' => $timestamp['unix'],
                        'retention_estimate' => $this->getRetentionEstimate(),
                        'source_type' => $sourceType,
                        'origin' => 'dumpsys',
                        'provider' => $provider,
                        'raw_reference' => 'location_dumpsys.txt:' . ($lineNum + 1),
                        'precision_meters' => $accuracy,
                        'is_inferred' => false,
                        'metadata' => [
                            'source' => 'Last Known Location',
                            'dumpsys_line' => substr($line, 0, 200)
                        ]
                    ]);
                }
            } catch (Exception $e) {
                error_log("DumpsysCollector: Error on line $lineNum - " . $e->getMessage());
                continue;
            }
        }
        
        error_log("DumpsysCollector: Matched lines: $matchedLines, Skipped zero coords: $skippedZero, Total points created: " . count($points));
        
        return $points;
    }
    
    private function extractTimestamp($line)
    {
        $unix = time();
        
        if (preg_match('/time=(\d+)/', $line, $timeMatch)) {
            $unix = (int) ($timeMatch[1] / 1000);
        } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/', $line, $dateMatch)) {
            $unix = strtotime($dateMatch[0]);
        }
        
        return [
            'unix' => $unix,
            'iso' => date('c', $unix)
        ];
    }
    
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
