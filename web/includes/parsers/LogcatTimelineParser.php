<?php
/**
 * LogcatTimelineParser
 * Extracts device behavior events from logcat threadtime output
 * 
 * FORENSIC REQUIREMENTS:
 * - Only extract directly logged events
 * - App sessions ONLY from ActivityManager foreground/background
 * - NO inference from onCreate/onResume (MANDATORY REFINEMENT)
 * - Timestamp normalization to UTC
 */

require_once __DIR__ . '/../models/DeviceEvent.php';

class LogcatTimelineParser
{
    private $device_timezone;
    private $timezone_offset;
    
    public function __construct($device_timezone = null)
    {
        $this->device_timezone = $device_timezone ?? 'UTC';
        $this->timezone_offset = $this->calculateTimezoneOffset();
    }
    
    /**
     * Parse logcat threadtime file
     */
    public function parse($logcat_file, $acquisition_metadata = null)
    {
        if (!file_exists($logcat_file)) {
            throw new Exception("Logcat file not found: $logcat_file");
        }
        
        $events = [];
        $lines = file($logcat_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        error_log("LogcatTimelineParser: Processing " . count($lines) . " lines");
        
        foreach ($lines as $line_num => $line) {
            try {
                $event = $this->parseLine($line, $line_num + 1, basename($logcat_file));
                if ($event) {
                    $events[] = $event;
                }
            } catch (Exception $e) {
                // Skip unparseable lines silently
                continue;
            }
        }
        
        error_log("LogcatTimelineParser: Extracted " . count($events) . " events");
        
        return $events;
    }
    
    /**
     * Parse single logcat threadtime line
     * Format: MM-DD HH:MM:SS.mmm PID TID LEVEL TAG: message
     */
    private function parseLine($line, $line_num, $source_file)
    {
        // Threadtime format regex
        if (!preg_match('/^(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})\.(\d{3})\s+(\d+)\s+(\d+)\s+([A-Z])\s+([^:]+):\s*(.*)$/', $line, $matches)) {
            return null;
        }
        
        $month = $matches[1];
        $day = $matches[2];
        $hour = $matches[3];
        $minute = $matches[4];
        $second = $matches[5];
        $millisecond = $matches[6];
        $pid = $matches[7];
        $tid = $matches[8];
        $level = $matches[9];
        $tag = trim($matches[10]);
        $message = trim($matches[11]);
        
        // Determine event type from tag and message
        $event_data = $this->identifyEvent($tag, $message, $line);
        
        if (!$event_data) {
            return null;
        }
        
        // Build timestamps (MANDATORY REFINEMENT: timezone normalization)
        $timestamps = $this->buildTimestamps($month, $day, $hour, $minute, $second, $millisecond);
        
        // Create DeviceEvent
        return new DeviceEvent([
            'event_type' => $event_data['event_type'],
            'category' => $event_data['category'],
            'timestamp_utc' => $timestamps['utc'],
            'timestamp_local' => $timestamps['local'],
            'timestamp_unix' => $timestamps['unix'],
            'timezone_offset' => $this->timezone_offset,
            'source' => 'logcat',
            'raw_reference' => "$source_file:$line_num",
            'event_nature' => 'LOGGED',
            'metadata' => array_merge([
                'tag' => $tag,
                'pid' => $pid,
                'tid' => $tid,
                'level' => $level
            ], $event_data['metadata'] ?? [])
        ]);
    }
    
    /**
     * Identify event type from tag and message
     */
    private function identifyEvent($tag, $message, $full_line)
    {
        // SCREEN EVENTS
        if ($tag === 'PowerManagerService' || $tag === 'PowerManager') {
            if (preg_match('/wakeup|screen.*on|display.*on/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_SCREEN_ON,
                    'category' => DeviceEvent::CATEGORY_DEVICE,
                    'metadata' => ['raw_message' => substr($message, 0, 200)]
                ];
            }
            if (preg_match('/sleep|screen.*off|display.*off/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_SCREEN_OFF,
                    'category' => DeviceEvent::CATEGORY_DEVICE,
                    'metadata' => ['raw_message' => substr($message, 0, 200)]
                ];
            }
        }
        
        // USER PRESENCE
        if ($tag === 'KeyguardService' || $tag === 'Keyguard') {
            if (preg_match('/unlock|user.*present/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_USER_PRESENT,
                    'category' => DeviceEvent::CATEGORY_DEVICE
                ];
            }
            if (preg_match('/lock|device.*locked/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_USER_LOCKED,
                    'category' => DeviceEvent::CATEGORY_DEVICE
                ];
            }
        }
        
        // APP LIFECYCLE (MANDATORY REFINEMENT: ActivityManager ONLY)
        if ($tag === 'ActivityManager' || $tag === 'ActivityTaskManager') {
            // Foreground: "Displayed com.example.app/.MainActivity"
            if (preg_match('/Displayed\s+([^\s\/]+)\/([^\s:]+)/i', $message, $m)) {
                return [
                    'event_type' => DeviceEvent::TYPE_APP_FOREGROUND,
                    'category' => DeviceEvent::CATEGORY_APP,
                    'metadata' => [
                        'package_name' => $m[1],
                        'activity' => $m[2],
                        'app_name' => $this->extractAppName($m[1])
                    ]
                ];
            }
            
            // Background: "Moved to background" or task removed
            if (preg_match('/background.*task|task.*background|app.*background|Removing task/i', $message)) {
                // Try to extract package name
                $package = null;
                if (preg_match('/\b([a-z][a-z0-9_]*(\.[a-z0-9_]+)+)\b/i', $message, $m)) {
                    $package = $m[1];
                }
                
                return [
                    'event_type' => DeviceEvent::TYPE_APP_BACKGROUND,
                    'category' => DeviceEvent::CATEGORY_APP,
                    'metadata' => [
                        'package_name' => $package,
                        'app_name' => $package ? $this->extractAppName($package) : null,
                        'raw_message' => substr($message, 0, 200)
                    ]
                ];
            }
        }
        
        // NETWORK EVENTS
        if ($tag === 'ConnectivityManager' || $tag === 'ConnectivityService') {
            if (preg_match('/connected|connectivity.*active/i', $message)) {
                $network_type = 'UNKNOWN';
                if (preg_match('/wifi/i', $message)) $network_type = 'WIFI';
                elseif (preg_match('/mobile|cellular/i', $message)) $network_type = 'MOBILE';
                
                return [
                    'event_type' => DeviceEvent::TYPE_NETWORK_CONNECTED,
                    'category' => DeviceEvent::CATEGORY_NETWORK,
                    'metadata' => ['network_type' => $network_type]
                ];
            }
            if (preg_match('/disconnect|lost.*connectivity/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_NETWORK_DISCONNECTED,
                    'category' => DeviceEvent::CATEGORY_NETWORK
                ];
            }
        }
        
        // WIFI EVENTS
        if ($tag === 'WifiManager' || $tag === 'WifiService') {
            if (preg_match('/wifi.*enabled|wifi.*on/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_WIFI_ON,
                    'category' => DeviceEvent::CATEGORY_NETWORK
                ];
            }
            if (preg_match('/wifi.*disabled|wifi.*off/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_WIFI_OFF,
                    'category' => DeviceEvent::CATEGORY_NETWORK
                ];
            }
        }
        
        // AIRPLANE MODE
        if (preg_match('/airplane.*mode|radio.*off/i', $message)) {
            if (preg_match('/enabled|on|true/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_AIRPLANE_MODE_ON,
                    'category' => DeviceEvent::CATEGORY_NETWORK
                ];
            }
            if (preg_match('/disabled|off|false/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_AIRPLANE_MODE_OFF,
                    'category' => DeviceEvent::CATEGORY_NETWORK
                ];
            }
        }
        
        // CHARGING EVENTS
        if ($tag === 'BatteryService' || $tag === 'Battery') {
            if (preg_match('/charging.*start|charger.*connect/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_CHARGING_START,
                    'category' => DeviceEvent::CATEGORY_POWER
                ];
            }
            if (preg_match('/charging.*stop|charger.*disconnect/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_CHARGING_STOP,
                    'category' => DeviceEvent::CATEGORY_POWER
                ];
            }
        }
        
        // WAKE LOCKS
        if ($tag === 'PowerManagerService' && preg_match('/wake.*lock/i', $message)) {
            if (preg_match('/acquir/i', $message)) {
                $lock_name = null;
                if (preg_match('/\(([^\)]+)\)/', $message, $m)) {
                    $lock_name = $m[1];
                }
                
                return [
                    'event_type' => DeviceEvent::TYPE_WAKE_LOCK_ACQUIRED,
                    'category' => DeviceEvent::CATEGORY_POWER,
                    'metadata' => ['lock_name' => $lock_name]
                ];
            }
            if (preg_match('/release/i', $message)) {
                return [
                    'event_type' => DeviceEvent::TYPE_WAKE_LOCK_RELEASED,
                    'category' => DeviceEvent::CATEGORY_POWER
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Build timezone-aware timestamps
     */
    private function buildTimestamps($month, $day, $hour, $minute, $second, $millisecond)
    {
        // Logcat doesn't include year - use current year
        $year = date('Y');
        
        // Build local timestamp
        $local_time = sprintf("%04d-%02d-%02d %02d:%02d:%02d.%03d",
            $year, $month, $day, $hour, $minute, $second, $millisecond);
        
        // Convert to Unix timestamp (assumes device timezone)
        $dt = new DateTime($local_time, new DateTimeZone($this->device_timezone));
        
        return [
            'local' => $local_time . $this->timezone_offset,
            'utc' => $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v') . 'Z',
            'unix' => $dt->getTimestamp()
        ];
    }
    
    /**
     * Calculate timezone offset from device timezone
     */
    private function calculateTimezoneOffset()
    {
        try {
            $tz = new DateTimeZone($this->device_timezone);
            $offset_seconds = $tz->getOffset(new DateTime('now', $tz));
            $hours = floor(abs($offset_seconds) / 3600);
            $minutes = floor((abs($offset_seconds) % 3600) / 60);
            $sign = $offset_seconds >= 0 ? '+' : '-';
            return sprintf("%s%02d:%02d", $sign, $hours, $minutes);
        } catch (Exception $e) {
            return '+00:00';
        }
    }
    
    /**
     * Extract friendly app name from package
     */
    private function extractAppName($package)
    {
        // Simple heuristic: last part of package name
        $parts = explode('.', $package);
        return ucfirst(end($parts));
    }
}
