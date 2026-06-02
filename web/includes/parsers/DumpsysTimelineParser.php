<?php
/**
 * DumpsysTimelineParser
 * Extracts current state snapshots from dumpsys outputs
 * 
 * MANDATORY REFINEMENT: All dumpsys events are labeled as SNAPSHOT
 * to indicate "State observed at acquisition time"
 */

require_once __DIR__ . '/../models/DeviceEvent.php';

class DumpsysTimelineParser
{
    private $acquisition_timestamp;
    private $timezone_offset;
    
    public function __construct($acquisition_timestamp = null, $timezone_offset = '+00:00')
    {
        $this->acquisition_timestamp = $acquisition_timestamp ?? date('c');
        $this->timezone_offset = $timezone_offset;
    }
    
    /**
     * Parse dumpsys activity file
     */
    public function parseActivity($dumpsys_file)
    {
        if (!file_exists($dumpsys_file)) {
            return [];
        }
        
        $events = [];
        $content = file_get_contents($dumpsys_file);
        
        // Extract currently running (foreground) activities
        if (preg_match_all('/mResumedActivity.*ActivityRecord\{[^\}]+\s+([^\s\/]+)\/([^\s\}]+)/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $package = $match[1];
                $activity = isset($match[2]) ? $match[2] : 'unknown';
                
                $events[] = new DeviceEvent([
                    'event_type' => DeviceEvent::TYPE_APP_FOREGROUND,
                    'category' => DeviceEvent::CATEGORY_APP,
                    'timestamp_utc' => gmdate('Y-m-d H:i:s') . 'Z',
                    'timestamp_local' => date('Y-m-d H:i:s') . $this->timezone_offset,
                    'timestamp_unix' => time(),
                    'timezone_offset' => $this->timezone_offset,
                    'source' => 'dumpsys_activity',
                    'raw_reference' => basename($dumpsys_file) . ':mResumedActivity',
                    'event_nature' => 'SNAPSHOT',  // MANDATORY REFINEMENT
                    'metadata' => [
                        'package_name' => $package,
                        'activity' => $activity,
                        'app_name' => $this->extractAppName($package),
                        'note' => 'State observed at acquisition time'
                    ]
                ]);
            }
        }
        
        return $events;
    }
    
    /**
     * Parse dumpsys power file
     */
    public function parsePower($dumpsys_file)
    {
        if (!file_exists($dumpsys_file)) {
            return [];
        }
        
        $events = [];
        $content = file_get_contents($dumpsys_file);
        
        // Screen state
        if (preg_match('/mWakefulness=(\w+)/i', $content, $match)) {
            $wakefulness = strtolower($match[1]);
            
            if ($wakefulness === 'awake' || $wakefulness === 'on') {
                $events[] = new DeviceEvent([
                    'event_type' => DeviceEvent::TYPE_SCREEN_ON,
                    'category' => DeviceEvent::CATEGORY_DEVICE,
                    'timestamp_utc' => gmdate('Y-m-d H:i:s') . 'Z',
                    'timestamp_local' => date('Y-m-d H:i:s') . $this->timezone_offset,
                    'timestamp_unix' => time(),
                    'timezone_offset' => $this->timezone_offset,
                    'source' => 'dumpsys_power',
                    'raw_reference' => basename($dumpsys_file) . ':mWakefulness',
                    'event_nature' => 'SNAPSHOT',
                    'metadata' => ['wakefulness' => $wakefulness]
                ]);
            }
        }
        
        return $events;
    }
    
    /**
     * Parse dumpsys connectivity file
     */
    public function parseConnectivity($dumpsys_file)
    {
        if (!file_exists($dumpsys_file)) {
            return [];
        }
        
        $events = [];
        $content = file_get_contents($dumpsys_file);
        
        // Active network connection
        if (preg_match('/NetworkAgentInfo.*CONNECTED/i', $content)) {
            $network_type = 'UNKNOWN';
            if (preg_match('/WiFi/i', $content)) {
                $network_type = 'WIFI';
            } elseif (preg_match('/MOBILE/i', $content)) {
                $network_type = 'MOBILE';
            }
            
            $events[] = new DeviceEvent([
                'event_type' => DeviceEvent::TYPE_NETWORK_CONNECTED,
                'category' => DeviceEvent::CATEGORY_NETWORK,
                'timestamp_utc' => gmdate('Y-m-d H:i:s') . 'Z',
                'timestamp_local' => date('Y-m-d H:i:s') . $this->timezone_offset,
                'timestamp_unix' => time(),
                'timezone_offset' => $this->timezone_offset,
                'source' => 'dumpsys_connectivity',
                'raw_reference' => basename($dumpsys_file) . ':NetworkAgentInfo',
                'event_nature' => 'SNAPSHOT',
                'metadata' => ['network_type' => $network_type]
            ]);
        }
        
        return $events;
    }
    
    /**
     * Parse dumpsys wifi file
     */
    public function parseWifi($dumpsys_file)
    {
        if (!file_exists($dumpsys_file)) {
            return [];
        }
        
        $events = [];
        $content = file_get_contents($dumpsys_file);
        
        // WiFi enabled state
        if (preg_match('/Wi-Fi is (enabled|disabled)/i', $content, $match)) {
            $state = strtolower($match[1]);
            
            $event_type = ($state === 'enabled') 
                ? DeviceEvent::TYPE_WIFI_ON 
                : DeviceEvent::TYPE_WIFI_OFF;
            
            $events[] = new DeviceEvent([
                'event_type' => $event_type,
                'category' => DeviceEvent::CATEGORY_NETWORK,
                'timestamp_utc' => gmdate('Y-m-d H:i:s') . 'Z',
                'timestamp_local' => date('Y-m-d H:i:s') . $this->timezone_offset,
                'timestamp_unix' => time(),
                'timezone_offset' => $this->timezone_offset,
                'source' => 'dumpsys_wifi',
                'raw_reference' => basename($dumpsys_file) . ':Wi-Fi status',
                'event_nature' => 'SNAPSHOT',
                'metadata' => ['wifi_enabled' => $state]
            ]);
        }
        
        return $events;
    }
    
    private function extractAppName($package)
    {
        $parts = explode('.', $package);
        return ucfirst(end($parts));
    }
}
