<?php
/**
 * Hash Verification API Endpoint
 * Verifies integrity of all extracted log files
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/audit.php';

$auditLogger = getAuditLogger();

try {
    // Execute Python hash verification script
    $script_path = dirname(__DIR__, 2) . '/analysis/evidence_hasher.py';
    $command = escapeshellcmd("python \"$script_path\" verify");

    exec($command . ' 2>&1', $output, $return_code);

    // Parse output to extract results
    $verified = 0;
    $tampered = 0;
    $missing = 0;

    foreach ($output as $line) {
        if (preg_match('/✓ Verified:\s*(\d+)/', $line, $matches)) {
            $verified = (int) $matches[1];
        }
        if (preg_match('/✗ Tampered:\s*(\d+)/', $line, $matches)) {
            $tampered = (int) $matches[1];
        }
        if (preg_match('/⚠ Missing:\s*(\d+)/', $line, $matches)) {
            $missing = (int) $matches[1];
        }
    }

    $status = $tampered > 0 ? 'tampered' : ($missing > 0 ? 'incomplete' : 'verified');

    // Log the verification
    $auditLogger->log(
        'HASH_VERIFICATION',
        "Verified: $verified, Tampered: $tampered, Missing: $missing",
        $tampered > 0 ? 'CRITICAL' : 'INFO'
    );

    echo json_encode([
        'success' => true,
        'status' => $status,
        'verified' => $verified,
        'tampered' => $tampered,
        'missing' => $missing,
        'timestamp' => date('Y-m-d H:i:s'),
        'details' => implode("\n", $output)
    ]);

} catch (Exception $e) {
    $auditLogger->logError('Hash verification failed', $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
