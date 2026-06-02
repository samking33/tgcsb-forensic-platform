<?php
/**
 * Audit Logger - Chain of Custody Tracking
 * Logs all user actions for forensic evidence integrity
 */

class AuditLogger
{
    private $logFile;
    private $sessionId;

    public function __construct($logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__, 2) . '/logs/audit_log.json';
        $this->sessionId = session_id() ?: uniqid('session_', true);

        // Ensure audit log exists
        if (!file_exists($this->logFile)) {
            @mkdir(dirname($this->logFile), 0755, true);
            file_put_contents($this->logFile, json_encode([]));
        }
    }

    /**
     * Log an action
     * @param string $action Action type (e.g., 'LOG_EXTRACT', 'VIEW_PAGE', 'ANALYSIS_RUN')
     * @param string $details Additional details
     * @param string $severity INFO, WARNING, CRITICAL
     */
    public function log($action, $details = '', $severity = 'INFO')
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => $this->sessionId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'action' => $action,
            'details' => $details,
            'severity' => $severity,
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];

        // Read existing logs
        $logs = $this->readLogs();

        // Append new entry
        $logs[] = $entry;

        // Keep last 10000 entries to prevent file bloat
        if (count($logs) > 10000) {
            $logs = array_slice($logs, -10000);
        }

        // Write back
        file_put_contents($this->logFile, json_encode($logs, JSON_PRETTY_PRINT));

        return true;
    }

    /**
     * Log page view
     */
    public function logPageView()
    {
        $page = basename($_SERVER['PHP_SELF'], '.php');
        $this->log('PAGE_VIEW', "Viewed: $page", 'INFO');
    }

    /**
     * Log data extraction
     */
    public function logExtraction($type, $status, $details = '')
    {
        $this->log(
            'DATA_EXTRACTION',
            "Type: $type, Status: $status, Details: $details",
            $status === 'success' ? 'INFO' : 'WARNING'
        );
    }

    /**
     * Log analysis execution
     */
    public function logAnalysis($script, $status, $details = '')
    {
        $this->log(
            'ANALYSIS_RUN',
            "Script: $script, Status: $status, Details: $details",
            $status === 'success' ? 'INFO' : 'WARNING'
        );
    }

    /**
     * Log export/download
     */
    public function logExport($type, $filename)
    {
        $this->log('DATA_EXPORT', "Type: $type, File: $filename", 'INFO');
    }

    /**
     * Log system error
     */
    public function logError($error, $context = '')
    {
        $this->log('SYSTEM_ERROR', "Error: $error, Context: $context", 'CRITICAL');
    }

    /**
     * Read all audit logs
     */
    public function readLogs($limit = null)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logs = json_decode(file_get_contents($this->logFile), true) ?: [];

        if ($limit) {
            return array_slice($logs, -$limit);
        }

        return $logs;
    }

    /**
     * Get logs by action type
     */
    public function getLogsByAction($action, $limit = null)
    {
        $logs = $this->readLogs();
        $filtered = array_filter($logs, function ($log) use ($action) {
            return $log['action'] === $action;
        });

        if ($limit) {
            return array_slice($filtered, -$limit);
        }

        return $filtered;
    }

    /**
     * Get logs by time range
     */
    public function getLogsByTimeRange($startTime, $endTime)
    {
        $logs = $this->readLogs();
        return array_filter($logs, function ($log) use ($startTime, $endTime) {
            $timestamp = strtotime($log['timestamp']);
            return $timestamp >= strtotime($startTime) && $timestamp <= strtotime($endTime);
        });
    }

    /**
     * Generate audit report
     */
    public function generateReport($format = 'array')
    {
        $logs = $this->readLogs();

        $report = [
            'total_entries' => count($logs),
            'time_range' => [
                'first' => $logs[0]['timestamp'] ?? 'N/A',
                'last' => end($logs)['timestamp'] ?? 'N/A'
            ],
            'actions_summary' => [],
            'unique_sessions' => []
        ];

        foreach ($logs as $log) {
            // Count actions
            $action = $log['action'];
            if (!isset($report['actions_summary'][$action])) {
                $report['actions_summary'][$action] = 0;
            }
            $report['actions_summary'][$action]++;

            // Track unique sessions
            if (!in_array($log['session_id'], $report['unique_sessions'])) {
                $report['unique_sessions'][] = $log['session_id'];
            }
        }

        $report['unique_sessions_count'] = count($report['unique_sessions']);
        unset($report['unique_sessions']); // Remove the array, keep only count

        if ($format === 'json') {
            return json_encode($report, JSON_PRETTY_PRINT);
        }

        return $report;
    }
}

// Global instance for easy access
function getAuditLogger()
{
    static $logger = null;
    if ($logger === null) {
        $logger = new AuditLogger();
    }
    return $logger;
}
