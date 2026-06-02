<?php
/**
 * Root Collector Stub
 * Priority 6: Root detection only (no parsing in this phase)
 * 
 * DETECTS ROOT, LOGS AVAILABILITY, DOES NOT PARSE
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class RootCollector implements LocationCollectorInterface
{
    private $isRootAvailable = false;
    
    public function __construct()
    {
        $this->isRootAvailable = $this->detectRoot();
    }
    
    public function getName()
    {
        return 'Root Database Access';
    }
    
    public function canRun()
    {
        // Always return false - stub implementation
        return false;
    }
    
    public function getRetentionEstimate()
    {
        return 'Days'; // Root DBs typically retain longer
    }
    
    public function collect()
    {
        // STUB: No actual collection in this phase
        
        if ($this->isRootAvailable) {
            error_log("RootCollector: Root access detected but parsing not implemented (stubbed)");
            error_log("RootCollector: Google Location History DB would be available");
            error_log("RootCollector: Network location cache would be available");
        } else {
            error_log("RootCollector: No root access detected");
        }
        
        // Return empty array - no collection
        return [];
    }
    
    /**
     * Detect if root access is available
     */
    private function detectRoot()
    {
        // Try to check for su binary
        $output = [];
        $returnCode = 0;
        
        @exec('adb shell "which su" 2>&1', $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (stripos($line, '/su') !== false || stripos($line, '/system') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get root status for forensic report
     */
    public function getRootStatus()
    {
        return [
            'available' => $this->isRootAvailable,
            'status' => $this->isRootAvailable ? 'Detected (not utilized)' : 'Not available',
            'note' => 'Root database parsing is stubbed in current implementation'
        ];
    }
}
