<?php
/**
 * Forensic Location Acquisition API
 * 
 * Actions:
 * - extract: Collect location data from device
 * - get_locations: Retrieve collected location data with filtering
 * - audit: Get forensic audit trail
 * 
 * CRITICAL: This API must ONLY output JSON, never HTML
 */

// ============================================
// API BOUNDARY INTEGRITY - FORCE JSON ONLY
// ============================================

// 1. INCREASE PHP RESOURCE LIMITS
ini_set('memory_limit', '1024M');
set_time_limit(0); // No time limit

// 2. Disable ALL error display (errors go to log file only)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// 3. Force error logging to file
$logDir = dirname(dirname(__DIR__)) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/location_api_errors.log');
ini_set('log_errors', '1');

// 4. Start output buffering to catch ANY stray output
ob_start();

// 5. Set JSON content type IMMEDIATELY
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// 6. Log execution start
error_log("===== LOCATION API CALLED =====");
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
error_log("Action: " . ($_GET['action'] ?? 'default'));

// 7. Wrap EVERYTHING in try/catch(Throwable) for guaranteed JSON response
try {
    require_once '../includes/config.php';
    require_once '../includes/services/LocationAggregator.php';
    
    // Get action
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_locations';
    
    // Check for external lookup setting (default: disabled)
    $enableExternalLookup = isset($_GET['enable_external_lookup']) && $_GET['enable_external_lookup'] === 'true';
    
    // FORENSIC AUDIT: Log all API calls to file (not stdout)
    error_log("ForensicLocationAPI: Action={$action}, ExternalLookup=" . ($enableExternalLookup ? 'enabled' : 'disabled'));

switch ($action) {
    case 'extract':
        extractLocations($enableExternalLookup);
        break;
    
    case 'get_locations':
        getLocations();
        break;
    
    case 'audit':
        getAuditTrail();
        break;
    
    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

} catch (Throwable $e) {
    // CRITICAL: Catch ALL errors/exceptions and return JSON
    error_log("FATAL API ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any buffered output
    ob_end_clean();
    
    // Return error as JSON
    echo json_encode([
        'success' => false,
        'error' => 'API error occurred',
        'message' => $e->getMessage(),
        'debug' => DEBUG_MODE ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
}

/**
 * Extract location data from all sources
 */
function extractLocations($enableExternalLookup)
{
    error_log("=== LOCATION EXTRACTION STARTED ===");
    error_log("External lookup: " . ($enableExternalLookup ? 'enabled' : 'disabled'));
    
    try {
        error_log("Creating LocationAggregator instance...");
        $aggregator = new LocationAggregator($enableExternalLookup);
        
        error_log("Calling collectAll()...");
        $result = $aggregator->collectAll();
        
        error_log("Collection complete. Points collected: " . count($result['points']));
        error_log("Audit log entries: " . count($result['audit_log']));
        
        // Save to JSON file for persistence
        $logsPath = getLogsPath();
        error_log("Logs path: " . $logsPath);
        
        $outputFile = $logsPath . '/forensic_locations.json';
        error_log("Output file: " . $outputFile);
        
        $data = [
            'extracted_at' => date('c'),
            'external_lookup_enabled' => $enableExternalLookup,
            'points' => array_map(function($point) {
                return $point->toArray();
            }, $result['points']),
            'audit_log' => $result['audit_log']
        ];
        
        $jsonWritten = file_put_contents($outputFile, json_encode($data, JSON_PRETTY_PRINT));
        error_log("Bytes written to file: " . $jsonWritten);
        
        error_log("=== LOCATION EXTRACTION COMPLETED SUCCESSFULLY ===");
        
        // Clean buffer before JSON output
        ob_end_clean();
        
        echo json_encode([
            'success' => true,
            'total_points' => count($result['points']),
            'external_lookup_enabled' => $enableExternalLookup,
            'audit_log' => $result['audit_log'],
            'saved_to' => basename($outputFile)
        ]);
        
    } catch (Exception $e) {
        error_log("=== LOCATION EXTRACTION FAILED ===");
        error_log("Error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        // Clean buffer before JSON output
        ob_end_clean();
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
        ]);
    }
}

/**
 * Get location data with filtering
 */
function getLocations()
{
    try {
        $logsPath = getLogsPath();
        $inputFile = $logsPath . '/forensic_locations.json';
        
        if (!file_exists($inputFile)) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'No location data found. Please extract locations first.'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents($inputFile), true);
        $points = $data['points'] ?? [];
        
        // Apply filters
        $startTime = isset($_GET['start_time']) ? (int)$_GET['start_time'] : 0;
        $endTime = isset($_GET['end_time']) ? (int)$_GET['end_time'] : PHP_INT_MAX;
        $sourceFilter = isset($_GET['source_filter']) ? explode(',', $_GET['source_filter']) : null;
        $minConfidence = $_GET['min_confidence'] ?? null;
        
        $filtered = array_filter($points, function($point) use ($startTime, $endTime, $sourceFilter, $minConfidence) {
            // Time filter
            if ($point['timestamp_unix'] < $startTime || $point['timestamp_unix'] > $endTime) {
                return false;
            }
            
            // Source filter
            if ($sourceFilter && !in_array($point['source_type'], $sourceFilter)) {
                return false;
            }
            
            // Confidence filter
            if ($minConfidence) {
                $confidenceMap = ['Low' => 0, 'Medium' => 1, 'High' => 2];
                $pointLevel = $confidenceMap[$point['confidence_level']] ?? 0;
                $minLevel = $confidenceMap[$minConfidence] ?? 0;
                if ($pointLevel < $minLevel) {
                    return false;
                }
            }
            
            return true;
        });
        
        $filtered = array_values($filtered);
        
        // Calculate statistics
        $sourceCounts = [];
        $confidenceCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
        $timeRange = ['earliest' => null, 'latest' => null];
        
        foreach ($filtered as $point) {
            // Source counts
            $sourceCounts[$point['source_type']] = ($sourceCounts[$point['source_type']] ?? 0) + 1;
            
            // Confidence counts
            $confidenceCounts[$point['confidence_level']]++;
            
            // Time range
            if ($timeRange['earliest'] === null || $point['timestamp_unix'] < $timeRange['earliest']) {
                $timeRange['earliest'] = $point['timestamp'];
            }
            if ($timeRange['latest'] === null || $point['timestamp_unix'] > $timeRange['latest']) {
                $timeRange['latest'] = $point['timestamp'];
            }
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'total_points' => count($filtered),
            'external_lookup_was_enabled' => $data['external_lookup_enabled'] ?? false,
            'extracted_at' => $data['extracted_at'] ?? 'Unknown',
            'time_range' => $timeRange,
            'source_breakdown' => $sourceCounts,
            'confidence_breakdown' => $confidenceCounts,
            'locations' => $filtered
        ]);
        
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get forensic audit trail
 */
function getAuditTrail()
{
    try {
        $logsPath = getLogsPath();
        $inputFile = $logsPath . '/forensic_locations.json';
        
        if (!file_exists($inputFile)) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'No audit data available'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents($inputFile), true);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'extracted_at' => $data['extracted_at'] ?? 'Unknown',
            'external_lookup_enabled' => $data['external_lookup_enabled'] ?? false,
            'audit_log' => $data['audit_log'] ?? []
        ]);
        
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
