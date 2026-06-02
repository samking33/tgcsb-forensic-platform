<?php
/**
 * Export Audit Log API Endpoint
 * Exports audit trail for chain of custody documentation
 */

require_once '../includes/config.php';
require_once '../includes/audit.php';

$auditLogger = getAuditLogger();

try {
    // Get format parameter
    $format = $_GET['format'] ?? 'json';

    // Get all audit logs
    $logs = $auditLogger->readLogs();

    if ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, ['Timestamp', 'Session ID', 'IP Address', 'User Agent', 'Action', 'Details', 'Severity', 'Page']);

        // Data rows
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['timestamp'],
                $log['session_id'],
                $log['ip_address'],
                $log['user_agent'],
                $log['action'],
                $log['details'],
                $log['severity'],
                $log['page']
            ]);
        }

        fclose($output);

    } else {
        // JSON Export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.json"');

        $export_data = [
            'export_date' => date('Y-m-d H:i:s'),
            'total_entries' => count($logs),
            'exported_by' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'logs' => $logs
        ];

        echo json_encode($export_data, JSON_PRETTY_PRINT);
    }

    // Log the export action
    $auditLogger->logExport('AUDIT_LOG', "audit_log_" . date('Y-m-d_H-i-s') . ".$format");

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
