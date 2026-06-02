<?php
/**
 * AppIntelligence
 * Computes app usage metrics strictly from timeline events
 * 
 * MANDATORY REFINEMENTS:
 * - Sessions ONLY from ActivityManager foreground/background pairs
 * - NEVER auto-close sessions
 * - NEVER infer backgrounding from screen off
 * - Missing background = "Session ongoing (not terminated in logs)"
 * - All metrics labeled as "Computed from timeline"
 */

require_once __DIR__ . '/../models/DeviceEvent.php';

class AppIntelligence
{
    private $events;
    
    public function __construct($timeline_events)
    {
        $this->events = $timeline_events;
    }
    
    /**
     * Detect app sessions from timeline events
     * MANDATORY REFINEMENT: Never auto-close sessions
     */
    public function detectSessions()
    {
        $sessions = [];
        $active_sessions = []; // package => [start_event, start_time]
        
        foreach ($this->events as $event) {
            if ($event->category !== DeviceEvent::CATEGORY_APP) {
                continue;
            }
            
            $package = $event->metadata['package_name'] ?? null;
            if (!$package) {
                continue;
            }
            
            // App went to foreground
            if ($event->event_type === DeviceEvent::TYPE_APP_FOREGROUND) {
                // If there's already an active session for this app, close it first
                if (isset($active_sessions[$package])) {
                    // Previous session ended when new one started
                    $sessions[] = $this->createSession(
                        $active_sessions[$package],
                        $event,
                        'Session ended by new foreground event'
                    );
                }
                
                // Start new session
                $active_sessions[$package] = [
                    'event' => $event,
                    'start_time' => $event->timestamp_unix
                ];
            }
            
            // App went to background
            elseif ($event->event_type === DeviceEvent::TYPE_APP_BACKGROUND) {
                if (isset($active_sessions[$package])) {
                    // Complete session
                    $sessions[] = $this->createSession(
                        $active_sessions[$package],
                        $event,
                        null
                    );
                    
                    unset($active_sessions[$package]);
                }
            }
        }
        
        // MANDATORY REFINEMENT: Don't auto-close remaining sessions
        // Flag them as ongoing
        foreach ($active_sessions as $package => $session_data) {
            $sessions[] = [
                'package_name' => $package,
                'app_name' => $session_data['event']->metadata['app_name'] ?? $package,
                'start_time_utc' => $session_data['event']->timestamp_utc,
                'start_time_local' => $session_data['event']->timestamp_local,
                'start_unix' => $session_data['start_time'],
                'end_time_utc' => null,
                'end_time_local' => null,
                'end_unix' => null,
                'duration_seconds' => null,
                'status' => 'ongoing',
                'status_note' => 'Session ongoing (background event not observed in logs)',
                'start_reference' => $session_data['event']->raw_reference,
                'end_reference' => null
            ];
        }
        
        return $sessions;
    }
    
    /**
     * Create completed session record
     */
    private function createSession($start_data, $end_event, $note = null)
    {
        $start_event = $start_data['event'];
        $duration = $end_event->timestamp_unix - $start_data['start_time'];
        
        return [
            'package_name' => $start_event->metadata['package_name'],
            'app_name' => $start_event->metadata['app_name'] ?? $start_event->metadata['package_name'],
            'start_time_utc' => $start_event->timestamp_utc,
            'start_time_local' => $start_event->timestamp_local,
            'start_unix' => $start_data['start_time'],
            'end_time_utc' => $end_event->timestamp_utc,
            'end_time_local' => $end_event->timestamp_local,
            'end_unix' => $end_event->timestamp_unix,
            'duration_seconds' => $duration,
            'status' => 'completed',
            'status_note' => $note,
            'start_reference' => $start_event->raw_reference,
            'end_reference' => $end_event->raw_reference
        ];
    }
    
    /**
     * Compute app statistics from sessions
     * All metrics labeled as "Computed from timeline"
     */
    public function computeAppStats($package_name = null)
    {
        $sessions = $this->detectSessions();
        
        if ($package_name) {
            $sessions = array_filter($sessions, function($s) use ($package_name) {
                return $s['package_name'] === $package_name;
            });
        }
        
        $apps = [];
        
        // Group sessions by app
        foreach ($sessions as $session) {
            $pkg = $session['package_name'];
            
            if (!isset($apps[$pkg])) {
                $apps[$pkg] = [
                    'package_name' => $pkg,
                    'app_name' => $session['app_name'],
                    'total_sessions' => 0,
                    'completed_sessions' => 0,
                    'ongoing_sessions' => 0,
                    'total_duration_seconds' => 0,
                    'average_duration_seconds' => null,
                    'launch_times' => [],
                    'computed_from' => 'timeline_events',
                    'note' => 'Metrics computed from observed ActivityManager events only'
                ];
            }
            
            $apps[$pkg]['total_sessions']++;
            
            if ($session['status'] === 'completed') {
                $apps[$pkg]['completed_sessions']++;
                $apps[$pkg]['total_duration_seconds'] += $session['duration_seconds'];
                $apps[$pkg]['launch_times'][] = $session['start_time_local'];
            } else {
                $apps[$pkg]['ongoing_sessions']++;
            }
        }
        
        // Calculate averages
        foreach ($apps as &$app) {
            if ($app['completed_sessions'] > 0) {
                $app['average_duration_seconds'] = round(
                    $app['total_duration_seconds'] / $app['completed_sessions']
                );
            }
        }
        
        return $package_name ? ($apps[$package_name] ?? null) : array_values($apps);
    }
    
    /**
     * Get time-of-day distribution
     */
    public function getTimeOfDayDistribution($package_name)
    {
        $sessions = $this->detectSessions();
        $sessions = array_filter($sessions, function($s) use ($package_name) {
            return $s['package_name'] === $package_name;
        });
        
        // Initialize 24-hour bins
        $distribution = array_fill(0, 24, 0);
        
        foreach ($sessions as $session) {
            $hour = (int) date('H', $session['start_unix']);
            $distribution[$hour]++;
        }
        
        return [
            'package_name' => $package_name,
            'hourly_launches' => $distribution,
            'computed_from' => 'timeline_events',
            'note' => 'Launch counts per hour (local time)'
        ];
    }
    
    /**
     * Get background activity indicators
     * Detect wake locks, services, etc. associated with app
     */
    public function getBackgroundActivityIndicators($package_name)
    {
        $indicators = [];
        
        foreach ($this->events as $event) {
            // Check for wake locks
            if ($event->event_type === DeviceEvent::TYPE_WAKE_LOCK_ACQUIRED) {
                $lock_name = $event->metadata['lock_name'] ?? '';
                if (stripos($lock_name, $package_name) !== false) {
                    $indicators[] = [
                        'type' => 'wake_lock',
                        'timestamp' => $event->timestamp_local,
                        'details' => $lock_name,
                        'reference' => $event->raw_reference
                    ];
                }
            }
        }
        
        return [
            'package_name' => $package_name,
            'indicators' => $indicators,
            'indicator_count' => count($indicators),
            'note' => 'Background activity indicators (not conclusions about app behavior)'
        ];
    }
    
    /**
     * Get all apps with sessions
     */
    public function getAllApps()
    {
        $sessions = $this->detectSessions();
        $apps = [];
        
        foreach ($sessions as $session) {
            $pkg = $session['package_name'];
            if (!isset($apps[$pkg])) {
                $apps[$pkg] = $session['app_name'];
            }
        }
        
        return array_map(function($pkg, $name) {
            return ['package_name' => $pkg, 'app_name' => $name];
        }, array_keys($apps), array_values($apps));
    }
}
