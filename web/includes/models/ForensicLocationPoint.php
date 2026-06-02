<?php
/**
 * Forensic Location Point - Core Data Model
 * 
 * Represents a single location point with full forensic metadata
 * Enforces defensibility as evidence with clear attribution
 */

class ForensicLocationPoint
{
    // Core Coordinates
    public $id;
    public $latitude;
    public $longitude;
    
    // Temporal Data
    public $timestamp;          // ISO 8601 format
    public $timestamp_unix;     // Unix timestamp
    public $retention_estimate; // "Minutes|Hours|Days|Unknown"
    
    // Source Attribution (MANDATORY)
    public $source_type;        // GPS|Network|WiFi|Cell|Fused|App
    public $origin;             // logcat|dumpsys|wifi|cell|app|root_db
    public $provider;           // gps|network|fused|passive
    public $raw_reference;      // Original log line or file path
    
    // Precision & Confidence
    public $precision_meters;   // Accuracy in meters (null if unknown)
    public $confidence_level;   // High|Medium|Low
    public $confidence_score;   // 0-100
    
    // External Inference (CRITICAL FORENSIC REQUIREMENT)
    public $is_inferred;        // bool - true if externally derived
    public $inference_method;   // null | "OpenCellID" | "Mozilla Location Service" | "WiFi Geolocation"
    public $inference_risk;     // null | "May be inaccurate" | "Approximate only"
    
    // Device Context
    public $device_context;     // Array of device state at time
    
    // Metadata
    public $metadata;           // Additional context
    
    /**
     * Constructor with forensic validation
     */
    public function __construct(array $data)
    {
        // MANDATORY FIELDS
        if (empty($data['latitude']) || empty($data['longitude']) || empty($data['timestamp'])) {
            throw new Exception('ForensicLocationPoint requires lat, lon, and timestamp');
        }
        
        $this->id = $data['id'] ?? uniqid('loc_', true);
        $this->latitude = (float) $data['latitude'];
        $this->longitude = (float) $data['longitude'];
        
        // Timestamps
        $this->timestamp = $data['timestamp'];
        $this->timestamp_unix = $data['timestamp_unix'] ?? strtotime($data['timestamp']);
        
        // Retention estimate (MANDATORY CORRECTION)
        $this->retention_estimate = $data['retention_estimate'] ?? 'Unknown';
        
        // Source attribution
        $this->source_type = $data['source_type'] ?? 'Unknown';
        $this->origin = $data['origin'] ?? 'Unknown';
        $this->provider = $data['provider'] ?? null;
        $this->raw_reference = $data['raw_reference'] ?? null;
        
        // Precision
        $this->precision_meters = $data['precision_meters'] ?? null;
        $this->confidence_level = $data['confidence_level'] ?? 'Low';
        $this->confidence_score = $data['confidence_score'] ?? $this->calculateConfidenceScore();
        
        // EXTERNAL INFERENCE (CRITICAL)
        $this->is_inferred = $data['is_inferred'] ?? false;
        $this->inference_method = $data['inference_method'] ?? null;
        $this->inference_risk = $data['inference_risk'] ?? null;
        
        // Context
        $this->device_context = $data['device_context'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
        
        // Forensic validation
        $this->validateForensicIntegrity();
    }
    
    /**
     * Calculate confidence score based on available data
     */
    private function calculateConfidenceScore()
    {
        $score = 50; // Base score
        
        // Precision boost
        if ($this->precision_meters !== null) {
            if ($this->precision_meters < 50) {
                $score += 30; // High precision
            } elseif ($this->precision_meters < 200) {
                $score += 15; // Medium precision
            }
        }
        
        // Source type boost
        if (in_array($this->source_type, ['GPS', 'Fused'])) {
            $score += 20;
        } elseif ($this->source_type === 'Network') {
            $score += 10;
        }
        
        // Inference penalty (MANDATORY CORRECTION)
        if ($this->is_inferred) {
            $score -= 30; // Significant penalty for inferred data
        }
        
        // Age penalty
        $age_hours = (time() - $this->timestamp_unix) / 3600;
        if ($age_hours > 1) {
            $score -= min(20, $age_hours / 5);
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Validate forensic integrity
     */
    private function validateForensicIntegrity()
    {
        // MANDATORY: Inferred points must have inference metadata
        if ($this->is_inferred) {
            if (empty($this->inference_method) || empty($this->inference_risk)) {
                throw new Exception('Inferred location points MUST include inference_method and inference_risk');
            }
        }
        
        // MANDATORY: All points must have retention estimate
        if (empty($this->retention_estimate)) {
            $this->retention_estimate = 'Unknown';
        }
        
        // Update confidence level from score
        if ($this->confidence_score >= 70) {
            $this->confidence_level = 'High';
        } elseif ($this->confidence_score >= 40) {
            $this->confidence_level = 'Medium';
        } else {
            $this->confidence_level = 'Low';
        }
    }
    
    /**
     * Convert to array for JSON serialization
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timestamp' => $this->timestamp,
            'timestamp_unix' => $this->timestamp_unix,
            'retention_estimate' => $this->retention_estimate,
            'source_type' => $this->source_type,
            'origin' => $this->origin,
            'provider' => $this->provider,
            'raw_reference' => $this->raw_reference,
            'precision_meters' => $this->precision_meters,
            'confidence_level' => $this->confidence_level,
            'confidence_score' => $this->confidence_score,
            'is_inferred' => $this->is_inferred,
            'inference_method' => $this->inference_method,
            'inference_risk' => $this->inference_risk,
            'device_context' => $this->device_context,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Get forensic display string
     */
    public function getForensicSummary()
    {
        $summary = "{$this->source_type} location";
        
        if ($this->precision_meters) {
            $summary .= " (±{$this->precision_meters}m)";
        }
        
        if ($this->is_inferred) {
            $summary .= " [INFERRED via {$this->inference_method}]";
        }
        
        $summary .= " - {$this->confidence_level} confidence";
        $summary .= " - Retention: {$this->retention_estimate}";
        
        return $summary;
    }
}
