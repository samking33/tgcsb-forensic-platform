<?php
/**
 * Location Aggregator Service
 * Collects from all sources, deduplicates, scores confidence
 */

// Use absolute paths for includes
$basePath = dirname(dirname(__DIR__));
require_once $basePath . '/includes/collectors/LogcatCollector.php';
require_once $basePath . '/includes/collectors/DumpsysLocationCollector.php';
require_once $basePath . '/includes/collectors/CellTowerCollector.php';
require_once $basePath . '/includes/collectors/WiFiCollector.php';
require_once $basePath . '/includes/collectors/AppLocationCollector.php';
require_once $basePath . '/includes/collectors/RootCollector.php';

class LocationAggregator
{
    private $collectors = [];
    private $auditLog = [];
    private $enableExternalLookup = false;
    
    public function __construct($enableExternalLookup = false)
    {
        error_log("LocationAggregator: Constructor called, external lookup=" . ($enableExternalLookup ? 'enabled' : 'disabled'));
        $this->enableExternalLookup = $enableExternalLookup;
        $this->initializeCollectors();
    }
    
    /**
     * Initialize all collectors in priority order
     */
    private function initializeCollectors()
    {
        error_log("LocationAggregator: Initializing collectors...");
        
        try {
            // Priority order (as approved)
            $this->collectors = [
                new LogcatCollector(),                                    // Priority 1
                new DumpsysLocationCollector(),                           // Priority 2
                new CellTowerCollector($this->enableExternalLookup),      // Priority 3
                new WiFiCollector($this->enableExternalLookup),           // Priority 4
                new AppLocationCollector(),                               // Priority 5
                new RootCollector()                                       // Priority 6 (stub)
            ];
            error_log("LocationAggregator: " . count($this->collectors) . " collectors initialized");
        } catch (Exception $e) {
            error_log("LocationAggregator: Error initializing collectors - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Collect location data from all sources
     * FAIL-OPEN: Failures in one source don't affect others
     */
    public function collectAll()
    {
        error_log("LocationAggregator::collectAll() started");
        
        $allPoints = [];
        $this->auditLog = [];
        
        $this->auditLog[] = [
            'timestamp' => date('c'),
            'action' => 'collection_started',
            'external_lookup_enabled' => $this->enableExternalLookup
        ];
        
        error_log("LocationAggregator: Processing " . count($this->collectors) . " collectors");
        
        foreach ($this->collectors as $collector) {
            $collectorName = $collector->getName();
            error_log("LocationAggregator: Checking collector - {$collectorName}");
            
            try {
                if (!$collector->canRun()) {
                    error_log("LocationAggregator: {$collectorName} - SKIPPED (cannot run)");
                    $this->auditLog[] = [
                        'collector' => $collectorName,
                        'status' => 'skipped',
                        'reason' => 'Data source not available'
                    ];
                    continue;
                }
                
                error_log("LocationAggregator: {$collectorName} - RUNNING");
                $startTime = microtime(true);
                $points = $collector->collect();
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                error_log("LocationAggregator: {$collectorName} - COMPLETED in {$duration}ms with " . count($points) . " points");
                
                $this->auditLog[] = [
                    'collector' => $collectorName,
                    'status' => 'success',
                    'points_collected' => count($points),
                    'duration_ms' => $duration,
                    'retention_estimate' => $collector->getRetentionEstimate()
                ];
                
                $allPoints = array_merge($allPoints, $points);
                
            } catch (Exception $e) {
                // FAIL-OPEN: Log error but continue
                error_log("LocationAggregator: {$collectorName} - ERROR: " . $e->getMessage());
                $this->auditLog[] = [
                    'collector' => $collectorName,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                error_log("LocationAggregator: {$collectorName} failed - " . $e->getMessage());
            }
        }
        
        error_log("LocationAggregator: Total raw points collected: " . count($allPoints));
        
        // Deduplicate
        $uniquePoints = $this->deduplicate($allPoints);
        
        error_log("LocationAggregator: After deduplication: " . count($uniquePoints) . " points");
        
        // Sort by timestamp (oldest first)
        usort($uniquePoints, function($a, $b) {
            return $a->timestamp_unix - $b->timestamp_unix;
        });
        
        $this->auditLog[] = [
            'timestamp' => date('c'),
            'action' => 'collection_completed',
            'total_raw_points' => count($allPoints),
            'unique_points' => count($uniquePoints),
            'duplicates_removed' => count($allPoints) - count($uniquePoints)
        ];
        
        error_log("LocationAggregator::collectAll() completed successfully");
        
        return [
            'points' => $uniquePoints,
            'audit_log' => $this->auditLog
        ];
    }
    
    /**
     * Deduplicate location points
     * Removes exact duplicates within 30 seconds
     */
    private function deduplicate($points)
    {
        $unique = [];
        $seen = [];
        
        foreach ($points as $point) {
            // Create key: lat_lon_time(rounded to 30s)
            $timeRounded = floor($point->timestamp_unix / 30) * 30;
            $latRounded = round($point->latitude, 5); // ~1 meter precision
            $lonRounded = round($point->longitude, 5);
            
            $key = "{$latRounded}_{$lonRounded}_{$timeRounded}";
            
            if (!isset($seen[$key])) {
                $unique[] = $point;
                $seen[$key] = true;
            } else {
                // Duplicate detected - prefer higher confidence source
                $existingIdx = array_search($seen[$key], array_column($unique, 'id'));
                if ($existingIdx !== false) {
                    $existing = $unique[$existingIdx];
                    if ($point->confidence_score > $existing->confidence_score) {
                        $unique[$existingIdx] = $point; // Replace with higher confidence
                    }
                }
            }
        }
        
        return $unique;
    }
    
    /**
     * Get audit log for forensic trail
     */
    public function getAuditLog()
    {
        return $this->auditLog;
    }
}
