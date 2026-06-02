<?php
// Test threat data loading
require_once __DIR__ . '/web/includes/config.php';

echo "=== Threat Scanner Debug Test ===\n";
echo "getLogsPath(): " . getLogsPath() . "\n";
echo "File path 1: " . getLogsPath() . '/threat_report.json' . "\n";
echo "File path 2: " . dirname(__DIR__) . '/logs/threat_report.json' . "\n\n";

$path1 = getLogsPath() . '/threat_report.json';
$path2 = dirname(__DIR__) . '/logs/threat_report.json';

echo "Path 1 exists: " . (file_exists($path1) ? 'YES' : 'NO') . "\n";
echo "Path 2 exists: " . (file_exists($path2) ? 'YES' : 'NO') . "\n\n";

if (file_exists($path1)) {
    $data = json_decode(file_get_contents($path1), true);
    echo "Data loaded from path 1:\n";
    echo "Risk Level: " . ($data['risk_level'] ?? 'NOT SET') . "\n";
    echo "Risk Score: " . ($data['risk_score'] ?? 'NOT SET') . "\n";
    echo "Threats: " . count($data['threats'] ?? []) . "\n";
}

if (file_exists($path2)) {
    $data = json_decode(file_get_contents($path2), true);
    echo "Data loaded from path 2:\n";
    echo "Risk Level: " . ($data['risk_level'] ?? 'NOT SET') . "\n";
    echo "Risk Score: " . ($data['risk_score'] ?? 'NOT SET') . "\n";
    echo "Threats: " . count($data['threats'] ?? []) . "\n";
}
?>
