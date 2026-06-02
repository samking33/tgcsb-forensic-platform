<?php
/**
 * TimelineAggregator
 * Merges events from all parsers and provides timeline reconstruction
 * 
 * FORENSIC REQUIREMENTS:
 * - Chronological sorting
 * - Deduplication (1-second window)
 * - Audit trail of sources
 * - Retention disclosure
 */

require_once __DIR__ . '/../parsers/LogcatTimelineParser.php';
require_once __DIR__ . '/../parsers/DumpsysTimelineParser.php';

class TimelineAggregator
{
    private $timeline_dir;
    private $audit_log = [];
    
    public function __construct($timeline_dir = null)
    {
        if (!$timeline_dir) {
            $timeline_dir = getLogsPath() . '/timeline';
        }
        $this->timeline_dir = $timeline_dir;
    }
    
    /**
     * Extract and aggregate timeline from latest session
     */
    public function extractTimeline($session_dir = null)
    {
        if (!$session_dir) {
            $session_dir = $this->findLatestSession();
        }
        
        if (!$session_dir) {
            throw new Exception("No timeline session directory found. Please run the timeline extraction script first (scripts/extract_timeline_logs.py) to collect logs from the device.");
        }
        
        if (!is_dir($session_dir)) {
            throw new Exception("Timeline session directory does not exist: $session_dir");
        }
        
        error_log("TimelineAggregator: Processing session: $session_dir");
        
        $this->audit_log = [];
        $all_events = [];
        
        // Load acquisition metadata for timezone
        $metadata = $this->loadAcquisitionMetadata($session_dir);
        $device_timezone = $metadata['device_timezone'] ?? 'UTC';
        $timezone_offset = $metadata['timezone_offset'] ?? '+00:00';
        
        $this->audit_log[] = [
            'timestamp' => date('c'),
            'action' => 'timeline_extraction_started',
            'session_dir' => basename($session_dir),
            'device_timezone' => $device_timezone
        ];
        
        // Parse logcat
        $logcat_file = $session_dir . '/logcat_threadtime.txt';
        if (file_exists($logcat_file)) {
            $parser = new LogcatTimelineParser($device_timezone);
            $logcat_events = $parser->parse($logcat_file, $metadata);
            
            $this->audit_log[] = [
                'parser' => 'LogcatTimelineParser',
                'status' => 'success',
                'events_extracted' => count($logcat_events),
                'source_file' => 'logcat_threadtime.txt'
            ];
            
            $all_events = array_merge($all_events, $logcat_events);
        } else {
            $this->audit_log[] = [
                'parser' => 'LogcatTimelineParser',
                'status' => 'skipped',
                'reason' => 'File not found'
            ];
        }
        
        // Parse dumpsys (all files)
        $dumpsys_parser = new DumpsysTimelineParser(
            $metadata['acquisition_time_utc'] ?? null,
            $timezone_offset
        );
        
        $dumpsys_files = [
            'activity' => 'dumpsys_activity.txt',
            'power' => 'dumpsys_power.txt',
            'connectivity' => 'dumpsys_connectivity.txt',
            'wifi' => 'dumpsys_wifi.txt'
        ];
        
        foreach ($dumpsys_files as $type => $filename) {
            $full_path = $session_dir . '/' . $filename;
            if (file_exists($full_path)) {
                $method = 'parse' . ucfirst($type);
                $dumpsys_events = $dumpsys_parser->$method($full_path);
                
                $this->audit_log[] = [
                    'parser' => "DumpsysTimelineParser($type)",
                    'status' => 'success',
                    'events_extracted' => count($dumpsys_events),
                    'source_file' => $filename
                ];
                
                $all_events = array_merge($all_events, $dumpsys_events);
            }
        }
        
        // Sort chronologically
        usort($all_events, function($a, $b) {
            return $a->timestamp_unix - $b->timestamp_unix;
        });
        
        // Deduplicate (1-second window)
        $unique_events = $this->deduplicate($all_events);
        
        $this->audit_log[] = [
            'timestamp' => date('c'),
            'action' => 'timeline_extraction_completed',
            'total_raw_events' => count($all_events),
            'unique_events' => count($unique_events),
            'duplicates_removed' => count($all_events) - count($unique_events)
        ];
        
        return [
            'events' => $unique_events,
            'audit_log' => $this->audit_log,
            'metadata' => $metadata,
            'retention_notice' => 'Timeline events are limited to logcat buffer retention (typically hours). Dumpsys events represent state at acquisition time only.'
        ];
    }
    
    /**
     * Deduplicate events within 1-second window
     */
    private function deduplicate($events)
    {
        $unique = [];
        $seen = [];
        
        foreach ($events as $event) {
            // Create fingerprint: type + unix_timestamp + category
            $fingerprint = $event->event_type . '_' . 
                          $event->timestamp_unix . '_' . 
                          $event->category;
            
            if (!isset($seen[$fingerprint])) {
                $unique[] = $event;
                $seen[$fingerprint] = true;
            }
        }
        
        return $unique;
    }
    
    /**
     * Find latest timeline session directory
     */
    private function findLatestSession()
    {
        if (!is_dir($this->timeline_dir)) {
            return null;
        }
        
        $sessions = array_filter(scandir($this->timeline_dir), function($item) {
            return $item !== '.' && $item !== '..' && 
                   is_dir($this->timeline_dir . '/' . $item);
        });
        
        if (empty($sessions)) {
            return null;
        }
        
        rsort($sessions); // Latest first
        return $this->timeline_dir . '/' . $sessions[0];
    }
    
    /**
     * Load acquisition metadata
     */
    private function loadAcquisitionMetadata($session_dir)
    {
        $metadata_file = $session_dir . '/acquisition_metadata.txt';
        $metadata = [];
        
        if (file_exists($metadata_file)) {
            $lines = file($metadata_file, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                    $key = str_replace(' ', '_', strtolower(trim($m[1])));
                    $metadata[$key] = trim($m[2]);
                }
            }
            
            // Calculate timezone offset if not present
            if (!isset($metadata['timezone_offset']) && isset($metadata['device_timezone'])) {
                try {
                    $tz = new DateTimeZone($metadata['device_timezone']);
                    $offset_seconds = $tz->getOffset(new DateTime('now', $tz));
                    $hours = floor(abs($offset_seconds) / 3600);
                    $minutes = floor((abs($offset_seconds) % 3600) / 60);
                    $sign = $offset_seconds >= 0 ? '+' : '-';
                    $metadata['timezone_offset'] = sprintf("%s%02d:%02d", $sign, $hours, $minutes);
                } catch (Exception $e) {
                    $metadata['timezone_offset'] = '+00:00';
                }
            }
        }
        
        return $metadata;
    }
}
