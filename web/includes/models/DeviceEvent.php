<?php
/**
 * DeviceEvent - Forensic Event Model
 * Represents a single timestamped device behavior event
 * 
 * FORENSIC REQUIREMENTS:
 * - All events must be directly observable in logs
 * - Confidence always "High" (no inference)
 * - Timezone-aware timestamps (UTC + local)
 * - Traceable to raw log file:line
 */

class DeviceEvent
{
    // Event identification
    public $id;
    public $event_type;
    public $category;
    
    // Timestamps (MANDATORY REFINEMENT: timezone-aware)
    public $timestamp_utc;
    public $timestamp_local;
    public $timestamp_unix;
    public $timezone_offset;
    
    // Source attribution
    public $source;
    public $raw_reference;
    public $event_nature;  // "LOGGED" or "SNAPSHOT"
    
    // Forensic metadata
    public $confidence;  // Always "High"
    public $metadata;
    
    // Event categories
    const CATEGORY_DEVICE = 'DEVICE';
    const CATEGORY_APP = 'APP';
    const CATEGORY_NETWORK = 'NETWORK';
    const CATEGORY_POWER = 'POWER';
    
    // Event types
    const TYPE_SCREEN_ON = 'SCREEN_ON';
    const TYPE_SCREEN_OFF = 'SCREEN_OFF';
    const TYPE_USER_PRESENT = 'USER_PRESENT';
    const TYPE_USER_LOCKED = 'USER_LOCKED';
    const TYPE_APP_FOREGROUND = 'APP_FOREGROUND';
    const TYPE_APP_BACKGROUND = 'APP_BACKGROUND';
    const TYPE_NETWORK_CONNECTED = 'NETWORK_CONNECTED';
    const TYPE_NETWORK_DISCONNECTED = 'NETWORK_DISCONNECTED';
    const TYPE_WIFI_ON = 'WIFI_ON';
    const TYPE_WIFI_OFF = 'WIFI_OFF';
    const TYPE_AIRPLANE_MODE_ON = 'AIRPLANE_MODE_ON';
    const TYPE_AIRPLANE_MODE_OFF = 'AIRPLANE_MODE_OFF';
    const TYPE_CHARGING_START = 'CHARGING_START';
    const TYPE_CHARGING_STOP = 'CHARGING_STOP';
    const TYPE_WAKE_LOCK_ACQUIRED = 'WAKE_LOCK_ACQUIRED';
    const TYPE_WAKE_LOCK_RELEASED = 'WAKE_LOCK_RELEASED';
    
    public function __construct($data)
    {
        $this->id = $data['id'] ?? uniqid('evt_', true);
        $this->event_type = $data['event_type'];
        $this->category = $data['category'];
        
        // Timestamps with timezone awareness
        $this->timestamp_utc = $data['timestamp_utc'];
        $this->timestamp_local = $data['timestamp_local'] ?? $data['timestamp_utc'];
        $this->timestamp_unix = $data['timestamp_unix'];
        $this->timezone_offset = $data['timezone_offset'] ?? '+00:00';
        
        // Source attribution
        $this->source = $data['source'];
        $this->raw_reference = $data['raw_reference'];
        $this->event_nature = $data['event_nature'] ?? 'LOGGED';
        
        // Confidence always High for logged events
        $this->confidence = 'High';
        
        // Additional metadata
        $this->metadata = $data['metadata'] ?? [];
        
        // Validate required fields
        $this->validate();
    }
    
    private function validate()
    {
        if (empty($this->event_type)) {
            throw new Exception("DeviceEvent: event_type is required");
        }
        
        if (empty($this->category)) {
            throw new Exception("DeviceEvent: category is required");
        }
        
        if (empty($this->timestamp_utc)) {
            throw new Exception("DeviceEvent: timestamp_utc is required");
        }
        
        if (empty($this->source)) {
            throw new Exception("DeviceEvent: source is required");
        }
        
        if (empty($this->raw_reference)) {
            throw new Exception("DeviceEvent: raw_reference is required for traceability");
        }
        
        // MANDATORY REFINEMENT: dumpsys events must be labeled
        if (stripos($this->source, 'dumpsys') !== false && $this->event_nature !== 'SNAPSHOT') {
            error_log("WARNING: Dumpsys event without SNAPSHOT label: " . $this->event_type);
        }
    }
    
    public function toArray()
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'category' => $this->category,
            'timestamp_utc' => $this->timestamp_utc,
            'timestamp_local' => $this->timestamp_local,
            'timestamp_unix' => $this->timestamp_unix,
            'timezone_offset' => $this->timezone_offset,
            'source' => $this->source,
            'raw_reference' => $this->raw_reference,
            'event_nature' => $this->event_nature,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Get human-readable event description
     */
    public function getDescription()
    {
        $desc = str_replace('_', ' ', $this->event_type);
        
        if (!empty($this->metadata['app_name'])) {
            $desc .= ': ' . $this->metadata['app_name'];
        }
        
        if ($this->event_nature === 'SNAPSHOT') {
            $desc .= ' (observed at acquisition)';
        }
        
        return $desc;
    }
}
