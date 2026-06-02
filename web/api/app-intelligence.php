<?php
/**
 * App Intelligence API
 * Provides forensic app usage profiling derived from timeline events
 * 
 * Actions:
 * - get_app_sessions: Session timeline for specific app
 * - get_app_stats: Computed metrics for app
 * - get_all_apps: List all apps with sessions
 * - get_time_distribution: Time-of-day usage pattern
 * - get_background_activity: Background activity indicators
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';
require_once '../includes/services/AppIntelligence.php';

$action = $_GET['action'] ?? 'get_all_apps';

try {
    // Load timeline events
    $logsPath = getLogsPath();
    $timelineFile = $logsPath . '/forensic_timeline.json';
    
    if (!file_exists($timelineFile)) {
        echo json_encode([
            'success' => false,
            'error' => 'Timeline data not available. Extract timeline first.'
        ]);
        exit;
    }
    
    $data = json_decode(file_get_contents($timelineFile), true);
    $events_array = $data['events'] ?? [];
    
    // Convert to DeviceEvent objects
    $events = array_map(function($e) {
        return (object) $e;
    }, $events_array);
    
    $intelligence = new AppIntelligence($events);
    
    switch ($action) {
        case 'get_app_sessions':
            getAppSessions($intelligence);
            break;
            
        case 'get_app_stats':
            getAppStats($intelligence);
            break;
            
        case 'get_all_apps':
            getAllApps($intelligence);
            break;
            
        case 'get_time_distribution':
            getTimeDistribution($intelligence);
            break;
            
        case 'get_background_activity':
            getBackgroundActivity($intelligence);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getAppSessions($intelligence)
{
    $package = $_GET['package'] ?? null;
    
    if (!$package) {
        echo json_encode(['success' => false, 'error' => 'Package name required']);
        return;
    }
    
    $all_sessions = $intelligence->detectSessions();
    $app_sessions = array_filter($all_sessions, function($s) use ($package) {
        return $s['package_name'] === $package;
    });
    
    echo json_encode([
        'success' => true,
        'package_name' => $package,
        'total_sessions' => count($app_sessions),
        'sessions' => array_values($app_sessions),
        'note' => 'Sessions computed from ActivityManager foreground/background events only'
    ]);
}

function getAppStats($intelligence)
{
    $package = $_GET['package'] ?? null;
    
    if ($package) {
        $stats = $intelligence->computeAppStats($package);
        
        if (!$stats) {
            echo json_encode([
                'success' => false,
                'error' => 'No session data for this app'
            ]);
            return;
        }
        
        echo json_encode(array_merge(['success' => true], $stats));
    } else {
        $stats = $intelligence->computeAppStats();
        
        echo json_encode([
            'success' => true,
            'total_apps' => count($stats),
            'apps' => $stats,
            'note' => 'All metrics computed from observed timeline events'
        ]);
    }
}

function getAllApps($intelligence)
{
    $apps = $intelligence->getAllApps();
    
    echo json_encode([
        'success' => true,
        'total_apps' => count($apps),
        'apps' => $apps
    ]);
}

function getTimeDistribution($intelligence)
{
    $package = $_GET['package'] ?? null;
    
    if (!$package) {
        echo json_encode(['success' => false, 'error' => 'Package name required']);
        return;
    }
    
    $distribution = $intelligence->getTimeOfDayDistribution($package);
    
    echo json_encode(array_merge(['success' => true], $distribution));
}

function getBackgroundActivity($intelligence)
{
    $package = $_GET['package'] ?? null;
    
    if (!$package) {
        echo json_encode(['success' => false, 'error' => 'Package name required']);
        return;
    }
    
    $activity = $intelligence->getBackgroundActivityIndicators($package);
    
    echo json_encode(array_merge(['success' => true], $activity));
}
