<?php
/**
 * Location Collector Interface
 * All location data collectors must implement this interface
 */

interface LocationCollectorInterface
{
    /**
     * Collect location data from this source
     * 
     * @return array Array of ForensicLocationPoint objects
     * @throws Exception on critical failure (logged, not propagated)
     */
    public function collect();
    
    /**
     * Get collector name for logging
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Check if this collector can run
     * 
     * @return bool
     */
    public function canRun();
    
    /**
     * Get estimated retention for this source
     * 
     * @return string "Minutes|Hours|Days|Unknown"
     */
    public function getRetentionEstimate();
}
