<?php
/**
 * Cell Tower Collector
 * Priority 3: Cell tower state ONLY (no external lookup by default)
 * 
 * NON-ROOT, APPROXIMATE ONLY
 * EXTERNAL LOOKUP: DISABLED BY DEFAULT (user must enable)
 */

require_once __DIR__ . '/../interfaces/LocationCollectorInterface.php';
require_once __DIR__ . '/../models/ForensicLocationPoint.php';

class CellTowerCollector implements LocationCollectorInterface
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
        return 'Cell Tower State';
    }
    
    public function canRun()
    {
        $file = $this->logsPath . '/cell_info.txt';
        return file_exists($file);
    }
    
    public function getRetentionEstimate()
    {
        // Cell info typically shows current state only
        return 'Minutes';
    }
    
    public function collect()
    {
        $points = [];
        
        try {
            $file = $this->logsPath . '/cell_info.txt';
            if (!file_exists($file)) {
                return $points;
            }
            
            $content = file_get_contents($file);
            $cellTowers = $this->parseCellInfo($content);
            
            // If external lookup is DISABLED (default), return tower info without coordinates
            if (!$this->externalLookupEnabled) {
                // Log that cell towers were found but not geocoded
                error_log("CellTowerCollector: Found " . count($cellTowers) . " cell towers (external lookup disabled)");
                return $points; // Return empty - no lat/lon without lookup
            }
            
            // If enabled, perform lookup (CLEARLY MARKED AS INFERRED)
            foreach ($cellTowers as $tower) {
                $location = $this->lookupCellTower($tower);
                if ($location) {
                    $points[] = $location;
                }
            }
            
        } catch (Exception $e) {
            error_log("CellTowerCollector error: " . $e->getMessage());
        }
        
        return $points;
    }
    
    /**
     * Parse cell tower information from dumpsys
     */
    private function parseCellInfo($content)
    {
        $towers = [];
        $lines = explode("\n", $content);
        
        // Pattern: CellLocation data or similar
        foreach ($lines as $line) {
            // Try to extract MCC, MNC, LAC, CID
            if (preg_match('/CellLocation.*?(\d+).*?(\d+).*?(\d+).*?(\d+)/', $line, $match)) {
                $towers[] = [
                    'mcc' => $match[1],
                    'mnc' => $match[2],
                    'lac' => $match[3],
                    'cid' => $match[4],
                    'raw_line' => $line
                ];
            }
        }
        
        return $towers;
    }
    
    /**
     * Lookup cell tower coordinates (INFERRED DATA)
     * MANDATORY: Only called if user explicitly enabled external lookups
     */
    private function lookupCellTower($tower)
    {
        // STUB: External API call would go here
        // For now, return null (no coordinates without actual API)
        
        // FORENSIC REQUIREMENT: If this were implemented, it MUST return:
        // - is_inferred = true
        // - inference_method = "OpenCellID" or "Mozilla Location Service"
        // - inference_risk = "May be inaccurate - approximate location only"
        
        return null;
        
        /* EXAMPLE IMPLEMENTATION (not active):
        
        try {
            // Query OpenCellID or Mozilla Location Service
            $url = "https://opencellid.org/cell/get?mcc={$tower['mcc']}&mnc={$tower['mnc']}&lac={$tower['lac']}&cellid={$tower['cid']}";
            // ... API call ...
            
            return new ForensicLocationPoint([
                'latitude' => $apiResult['lat'],
                'longitude' => $apiResult['lon'],
                'timestamp' => date('c'),
                'timestamp_unix' => time(),
                'retention_estimate' => 'Minutes',
                'source_type' => 'Cell',
                'origin' => 'cell',
                'provider' => 'cell',
                'raw_reference' => 'Cell Tower: ' . json_encode($tower),
                'precision_meters' => 1000, // Approximate
                'is_inferred' => true, // CRITICAL
                'inference_method' => 'OpenCellID',
                'inference_risk' => 'May be inaccurate - approximate location only',
                'metadata' => [
                    'cell_tower' => $tower,
                    'warning' => 'EXTERNALLY INFERRED'
                ]
            ]);
        } catch (Exception $e) {
            return null;
        }
        */
    }
}
