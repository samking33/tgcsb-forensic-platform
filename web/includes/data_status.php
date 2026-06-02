<?php
/**
 * Data Availability Helper
 * Shows status indicators for forensic features
 */

function getDataAvailability()
{
    $logsPath = getLogsPath();

    $status = [
        // Core data sources
        'logcat' => file_exists("$logsPath/android_logcat.txt") && filesize("$logsPath/android_logcat.txt") > 100,
        'sms' => file_exists("$logsPath/sms_logs.txt") && filesize("$logsPath/sms_logs.txt") > 100,
        'calls' => file_exists("$logsPath/call_logs.txt") && filesize("$logsPath/call_logs.txt") > 100,
        'contacts' => file_exists("$logsPath/contacts.txt") && filesize("$logsPath/contacts.txt") > 100,

        // Enhanced data sources
        'usage_stats' => file_exists("$logsPath/usage_stats.txt") && filesize("$logsPath/usage_stats.txt") > 100,
        'wifi_dump' => file_exists("$logsPath/wifi_dump.txt") && filesize("$logsPath/wifi_dump.txt") > 100,
        'bluetooth_dump' => file_exists("$logsPath/bluetooth_dump.txt") && filesize("$logsPath/bluetooth_dump.txt") > 100,
        'battery_history' => file_exists("$logsPath/battery_history.txt") && filesize("$logsPath/battery_history.txt") > 100,

        // Analysis outputs
        'timeline' => file_exists("$logsPath/unified_timeline.json") && filesize("$logsPath/unified_timeline.json") > 10,
        'privacy' => file_exists("$logsPath/privacy_profile.json") && filesize("$logsPath/privacy_profile.json") > 10,
        'network' => file_exists("$logsPath/network_intelligence.json") && filesize("$logsPath/network_intelligence.json") > 10,
        'pii' => file_exists("$logsPath/pii_leaks.json") && filesize("$logsPath/pii_leaks.json") > 10,
        'social' => file_exists("$logsPath/social_graph.json") && filesize("$logsPath/social_graph.json") > 10,
        'power' => file_exists("$logsPath/power_forensics.json") && filesize("$logsPath/power_forensics.json") > 10,
        'intents' => file_exists("$logsPath/intent_hunter.json") && filesize("$logsPath/intent_hunter.json") > 10,
        'beacons' => file_exists("$logsPath/beacon_map.json") && filesize("$logsPath/beacon_map.json") > 10,
        'clipboard' => file_exists("$logsPath/clipboard_forensics.json") && filesize("$logsPath/clipboard_forensics.json") > 10,
        'sessions' => file_exists("$logsPath/app_sessions.json") && filesize("$logsPath/app_sessions.json") > 10,
    ];

    return $status;
}

function hasDataContent($jsonFile)
{
    if (!file_exists($jsonFile)) {
        return false;
    }

    $content = file_get_contents($jsonFile);
    $data = json_decode($content, true);

    if (!$data) {
        return false;
    }

    // Check if data is empty
    if (is_array($data)) {
        // For arrays
        if (empty($data)) {
            return false;
        }

        // Check for summary field
        if (isset($data['summary'])) {
            $summary = $data['summary'];
            foreach ($summary as $value) {
                if (is_numeric($value) && $value > 0) {
                    return true;
                }
            }
            return false;
        }

        // Check if any array value has content
        foreach ($data as $value) {
            if (is_array($value) && count($value) > 0) {
                return true;
            }
        }
    }

    return true;
}

function getDataStatusBadge($feature)
{
    $logsPath = getLogsPath();
    $fileMap = [
        'timeline' => 'unified_timeline.json',
        'privacy' => 'privacy_profile.json',
        'network' => 'network_intelligence.json',
        'pii' => 'pii_leaks.json',
        'social' => 'social_graph.json',
        'power' => 'power_forensics.json',
        'intents' => 'intent_hunter.json',
        'beacons' => 'beacon_map.json',
        'clipboard' => 'clipboard_forensics.json',
        'sessions' => 'app_sessions.json',
    ];

    if (!isset($fileMap[$feature])) {
        return '';
    }

    $file = "$logsPath/{$fileMap[$feature]}";

    if (!file_exists($file)) {
        return '<span class="badge bg-secondary ms-2" title="Data file not found">No Data</span>';
    }

    if (hasDataContent($file)) {
        return '<span class="badge bg-success ms-2" title="Has analyzed data">✓ Data Available</span>';
    } else {
        return '<span class="badge bg-warning text-dark ms-2" title="File exists but no data found">⚠ No Results</span>';
    }
}

function getFeatureLimitation($feature)
{
    $limitations = [
        'clipboard' => [
            'icon' => 'fa-lock',
            'title' => 'Android 10+ Restriction',
            'message' => 'Android 10 and above restrict clipboard content logging for privacy. Only clipboard access events may be visible.',
            'type' => 'warning'
        ],
        'beacons' => [
            'icon' => 'fa-info-circle',
            'title' => 'Depends on Network Activity',
            'message' => 'WiFi and Bluetooth beacon data depends on network activity during log capture. May be empty if no WiFi/BT events occurred.',
            'type' => 'info'
        ],
        'sessions' => [
            'icon' => 'fa-info-circle',
            'title' => 'Depends on App Activity',
            'message' => 'App session data depends on ActivityManager logs. Empty results may indicate no app launches during capture period.',
            'type' => 'info'
        ],
        'power' => [
            'icon' => 'fa-info-circle',
            'title' => 'Depends on Power Events',
            'message' => 'Power forensics data depends on screen, charging, and power state changes during log capture.',
            'type' => 'info'
        ],
    ];

    return $limitations[$feature] ?? null;
}

function showLimitationAlert($feature)
{
    $limitation = getFeatureLimitation($feature);

    if (!$limitation) {
        return '';
    }

    $alertClass = $limitation['type'] === 'warning' ? 'alert-warning' : 'alert-info';
    $icon = $limitation['icon'];
    $title = $limitation['title'];
    $message = $limitation['message'];

    return <<<HTML
    <div class="alert {$alertClass} alert-dismissible fade show" role="alert">
        <i class="fas {$icon} me-2"></i>
        <strong>{$title}:</strong> {$message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
HTML;
}
?>