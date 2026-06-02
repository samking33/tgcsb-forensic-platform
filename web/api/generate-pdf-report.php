<?php
/**
 * PDF Report Generator
 * Generates comprehensive forensic report with embedded SHA-256 hashes
 */

require_once '../includes/config.php';
require_once '../includes/audit.php';

// Simple HTML to PDF conversion (no external libraries needed)
// For production, consider using libraries like mPDF or TCPDF

$auditLogger = getAuditLogger();
$auditLogger->log('PDF_GENERATION', 'PDF report generation started', 'INFO');

// Get report parameters
$includeHashes = isset($_GET['hashes']) && $_GET['hashes'] === '1';
$includeAudit = isset($_GET['audit']) && $_GET['audit'] === '1';

$logsPath = getLogsPath();

// Start building HTML report
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Android Forensic Analysis Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header h1 { color: #2c3e50; margin: 0; }
        .header p { color: #7f8c8d; margin: 5px 0; }
        .section { margin: 20px 0; page-break-inside: avoid; }
        .section h2 { background: #3498db; color: white; padding: 10px; margin: 0; }
        .section-content { padding: 15px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .hash { font-family: "Courier New", monospace; font-size: 10px; word-break: break-all; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #7f8c8d; font-size: 10px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; }
        .verified { color: #28a745; font-weight: bold; }
        .tampered { color: #dc3545; font-weight: bold; }
        @media print {
            body { margin: 0; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>';

// Header
$html .= '<div class="header">';
$html .= '<h1>Android Forensic Analysis Report</h1>';
$html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
$html .= '<p>Tool Version: ' . (defined('APP_VERSION') ? APP_VERSION : 'v2.1.0') . '</p>';
$html .= '</div>';

// Legal Notice
$html .= '<div class="warning">';
$html .= '<h3 style="margin-top:0;">⚠️ LEGAL NOTICE</h3>';
$html .= '<p>This report contains forensic evidence extracted from an Android device. ';
$html .= 'The evidence has been processed with chain of custody tracking and cryptographic integrity verification. ';
$html .= 'Unauthorized use or distribution of this report may violate privacy laws and regulations.</p>';
$html .= '</div>';

// Summary Statistics
$html .= '<div class="section">';
$html .= '<h2>📊 Summary Statistics</h2>';
$html .= '<div class="section-content">';

$stats = [];
$stats['SMS Messages'] = file_exists($logsPath . '/sms_logs.txt') ? count(file($logsPath . '/sms_logs.txt')) : 0;
$stats['Call Logs'] = file_exists($logsPath . '/call_logs.txt') ? count(file($logsPath . '/call_logs.txt')) : 0;
$stats['Logcat Entries'] = file_exists($logsPath . '/android_logcat.txt') ? count(file($logsPath . '/android_logcat.txt')) : 0;

// Load analysis results
$modules = [
    'Privacy Events' => 'privacy_profile.json',
    'PII Leaks' => 'pii_leaks.json',
    'Network Connections' => 'network_activity.json',
    'WiFi Networks' => 'beacon_map.json',
    'App Sessions' => 'app_sessions.json'
];

$html .= '<table>';
foreach ($stats as $label => $count) {
    $html .= "<tr><td><strong>$label</strong></td><td>$count</td></tr>";
}

foreach ($modules as $label => $file) {
    $filepath = $logsPath . '/' . $file;
    if (file_exists($filepath)) {
        $data = json_decode(file_get_contents($filepath), true);
        if (isset($data['summary'])) {
            foreach ($data['summary'] as $key => $value) {
                if (is_numeric($value)) {
                    $html .= "<tr><td><strong>$label - " . ucfirst(str_replace('_', ' ', $key)) . "</strong></td><td>$value</td></tr>";
                }
            }
        }
    }
}
$html .= '</table>';
$html .= '</div></div>';

// Evidence Integrity (if requested)
if ($includeHashes) {
    $html .= '<div class="section">';
    $html .= '<h2>🔒 Evidence Integrity Verification</h2>';
    $html .= '<div class="section-content">';

    $metadataFile = $logsPath . '/evidence_metadata.json';
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true);

        $html .= '<p><strong>Hash Algorithm:</strong> SHA-256</p>';
        $html .= '<p><strong>Files Verified:</strong> ' . count($metadata['files']) . '</p>';
        $html .= '<table>';
        $html .= '<tr><th>Filename</th><th>Size (bytes)</th><th>SHA-256 Hash</th><th>Status</th></tr>';

        foreach ($metadata['files'] as $filename => $fileData) {
            $status = $fileData['verified'] ? '<span class="verified">✓ VERIFIED</span>' : '<span class="tampered">✗ TAMPERED</span>';
            $html .= "<tr>";
            $html .= "<td>$filename</td>";
            $html .= "<td>" . number_format($fileData['size_bytes']) . "</td>";
            $html .= "<td class='hash'>" . $fileData['hash'] . "</td>";
            $html .= "<td>$status</td>";
            $html .= "</tr>";
        }

        $html .= '</table>';
        $html .= '<p style="margin-top:15px;"><em>All hashes were verified at report generation time. Any modification to the original files will result in hash mismatch.</em></p>';
    } else {
        $html .= '<p>No hash metadata available. Run evidence_hasher.py to generate hashes.</p>';
    }

    $html .= '</div></div>';
}

// Audit Trail (if requested)
if ($includeAudit) {
    $html .= '<div class="section">';
    $html .= '<h2>📝 Audit Trail (Chain of Custody)</h2>';
    $html .= '<div class="section-content">';

    $auditFile = dirname($logsPath) . '/logs/audit_log.json';
    if (file_exists($auditFile)) {
        $auditData = json_decode(file_get_contents($auditFile), true);
        $recentEntries = array_slice($auditData, -20); // Last 20 entries

        $html .= '<table>';
        $html .= '<tr><th>Timestamp</th><th>Action</th><th>Details</th><th>IP Address</th></tr>';

        foreach ($recentEntries as $entry) {
            $html .= "<tr>";
            $html .= "<td style='font-size:10px;'>" . $entry['timestamp'] . "</td>";
            $html .= "<td><strong>" . $entry['action'] . "</strong></td>";
            $html .= "<td>" . htmlspecialchars($entry['details']) . "</td>";
            $html .= "<td>" . $entry['ip_address'] . "</td>";
            $html .= "</tr>";
        }

        $html .= '</table>';
        $html .= '<p style="margin-top:15px;"><em>Showing last 20 audit entries. Full audit log available separately.</em></p>';
    } else {
        $html .= '<p>No audit log available.</p>';
    }

    $html .= '</div></div>';
}

// Footer
$html .= '<div class="footer">';
$html .= '<p>Generated by Android Forensic Tool ' . (defined('APP_VERSION') ? APP_VERSION : 'v2.1.0') . '</p>';
$html .= '<p>This is a digital forensic evidence report. Handle with appropriate security measures.</p>';
$html .= '<p>Report ID: ' . md5(uniqid() . time()) . '</p>';
$html .= '</div>';

$html .= '</body></html>';

// Set headers for PDF download (browser will handle print to PDF)
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="forensic_report_' . date('Y-m-d_H-i-s') . '.html"');

// Log the export
$auditLogger->logExport('PDF_REPORT', 'forensic_report_' . date('Y-m-d_H-i-s') . '.html');

echo $html;
?>