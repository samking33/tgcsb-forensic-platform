<?php
/**
 * Android Forensic Tool - Mule Hunter
 * Detects Money Mule indicators: Multiple Banking Apps, Cloned Apps, SIM Swaps
 */
$pageTitle = 'Mule Hunter - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Helper to load JSON data
function loadJsonData($filename) {
    global $logsPath;
    
    // Robust Path Logic
    $possiblePaths = [
        // 1. From Config (Best)
        getLogsPath() . '/' . $filename,
        // 2. Relative to this script (web/pages -> ../../logs)
        dirname(dirname(__DIR__)) . '/logs/' . $filename, 
        // 3. Document Root parent
        dirname($_SERVER['DOCUMENT_ROOT']) . '/logs/' . $filename
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (!empty($content)) {
                return json_decode($content, true);
            }
        }
    }
    return null;
}

$appSessionData = loadJsonData('app_sessions.json');
$dualSpaceData = loadJsonData('dual_space_analysis.json');

// Extract Key Metrics
$bankingAppCount = $appSessionData['summary']['unique_banking_apps'] ?? 0;
$muleRiskLevel = $appSessionData['summary']['mule_risk_level'] ?? 'UNKNOWN';
$muleReason = $appSessionData['summary']['mule_detection_reason'] ?? 'No data available';
$bankingAppsList = $appSessionData['summary']['banking_apps_list'] ?? [];

$clonedAppCount = $dualSpaceData['clone_count'] ?? 0;
$clonedBankingCount = $dualSpaceData['banking_clone_count'] ?? 0;
$dualSpaceRisk = $dualSpaceData['mule_assessment']['risk_level'] ?? 'LOW';

// Determine Overall Risk Score
$riskScore = 0;
if ($bankingAppCount > 5) $riskScore += 40;
if ($bankingAppCount > 10) $riskScore += 20;
if ($clonedBankingCount > 0) $riskScore += 40; // Cloned banking app is a huge red flag
if ($clonedAppCount > 3) $riskScore += 10;

// Color Coding
$riskColor = 'success';
$riskText = 'LOW RISK';
if ($riskScore >= 70) {
    $riskColor = 'danger';
    $riskText = 'CRITICAL MULE RISK';
} elseif ($riskScore >= 40) {
    $riskColor = 'warning';
    $riskText = 'HIGH RISK';
} elseif ($riskScore >= 20) {
    $riskColor = 'info';
    $riskText = 'MEDIUM RISK';
}

// Check if we need to auto-run scan (if data is missing)
$shouldAutoScan = ($appSessionData === null || $dualSpaceData === null);
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-piggy-bank me-2 text-forensic-red"></i>Mule Hunter Intelligence
                    </h3>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-end">
                        <button class="btn btn-danger shadow-sm" onclick="startMuleScan()" id="scanBtn">
                            <i class="fas fa-crosshairs me-2"></i>Run Active Mule Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Risk Assessment Banner -->
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-<?= $riskColor === 'danger' ? 'danger' : ($riskColor === 'warning' ? 'warning' : 'success') ?> text-center">
                        <h1 class="display-4 fw-bold mb-0">
                            <i class="fas fa-shield-alt me-3"></i><?= $riskText ?>
                        </h1>
                        <p class="lead mt-2 mb-0">
                            Risk Score: <strong><?= $riskScore ?>/100</strong>
                            <?php if ($muleReason !== 'No data available'): ?>
                                <br><small><?= $muleReason ?></small>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Key Indicators Row -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-<?= $bankingAppCount > 5 ? 'danger' : 'success' ?>">
                        <div class="inner">
                            <h3><?= $bankingAppCount ?></h3>
                            <p>Installed Banking Apps</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-university"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-<?= $clonedBankingCount > 0 ? 'danger' : 'success' ?>">
                        <div class="inner">
                            <h3><?= $clonedBankingCount ?></h3>
                            <p>Cloned Banking Apps</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clone"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-<?= $clonedAppCount > 3 ? 'warning' : 'success' ?>">
                        <div class="inner">
                            <h3><?= $clonedAppCount ?></h3>
                            <p>Total Cloned Apps</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-copy"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>Check</h3>
                            <p>SIM Swap Status</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-sim-card"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Banking Apps List -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-forensic-gradient text-white">
                            <h3 class="card-title">
                                <i class="fas fa-wallet me-2"></i>Financial Ecosystem
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Money mules often install multiple payment apps to layer funds across accounts.
                                Normal users typically have 1-3.
                            </p>
                            <?php if (!empty($bankingAppsList)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Risk</th>
                                                <th>App Package</th>
                                                <th>Category</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bankingAppsList as $app): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-warning text-dark">Watchlist</span>
                                                    </td>
                                                    <td class="font-monospace"><?= htmlspecialchars($app) ?></td>
                                                    <td>Banking / UPI</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                    <h5>No High-Risk Banking Apps Detected</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Heuristic Detections (New Row inside Column) -->
                    <div class="card mt-4 border-warning">
                         <div class="card-header bg-warning text-dark">
                             <h3 class="card-title"><i class="fas fa-search-dollar me-2"></i>Suspected Financial (Heuristics)</h3>
                         </div>
                         <div class="card-body">
                             <p class="text-muted small">Apps matching keywords (pay, wallet, crypto) but not in database.</p>
                             <?php 
                             $heuristicApps = $appSessionData['summary']['heuristic_financial_apps'] ?? [];
                             if (!empty($heuristicApps)): 
                             ?>
                                 <ul class="list-group list-group-flush">
                                 <?php foreach ($heuristicApps as $sapp): ?>
                                     <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                         <code class="text-dark"><?= htmlspecialchars($sapp) ?></code>
                                         <span class="badge bg-secondary">Suspect</span>
                                     </li>
                                 <?php endforeach; ?>
                                 </ul>
                             <?php else: ?>
                                 <p class="text-success mb-0"><i class="fas fa-check-circle me-2"></i>No heuristic matches.</p>
                             <?php endif; ?>
                         </div>
                    </div>
                </div>

                <!-- Dual Space / Cloned Apps -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <h3 class="card-title">
                                <i class="fas fa-user-secret me-2"></i>Cloned App Detection
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Cloned apps (in Dual Space / Parallel Space) allow running two instances of the same bank account.
                                This is a strong indicator of mule activity.
                            </p>
                            
                            <?php if (!empty($dualSpaceData['cloned_apps'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Device has active Dual Space profiles (User 999 / 10).</strong>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Cloned Application</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dualSpaceData['cloned_apps'] as $clone): ?>
                                                <?php 
                                                    $isBanking = in_array($clone, $bankingAppsList); 
                                                    $rowClass = $isBanking ? 'table-danger' : '';
                                                ?>
                                                <tr class="<?= $rowClass ?>">
                                                    <td class="font-monospace">
                                                        <?= htmlspecialchars($clone) ?>
                                                        <?php if ($isBanking): ?>
                                                            <span class="badge bg-danger ms-2">BANKING APP</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-dark">Dual Instance</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                    <h5>No Cloned Apps Detected</h5>
                                    <p>Standard device configuration.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIM & Security Alerts -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bell me-2"></i>Security & Pattern Alerts
                            </h3>
                        </div>
                        <div class="card-body">
                            <ul>
                                <?php if ($riskScore > 20): ?>
                                    <li class="text-danger mb-2">
                                        <strong>High Volume of Banking Apps:</strong> Device contains <?= $bankingAppCount ?> financial applications. This exceeds the normal threshold (5).
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($clonedBankingCount > 0): ?>
                                    <li class="text-danger mb-2">
                                        <strong>Cloned Banking App Detected:</strong> User is running multiple instances of a bank app. This allows managing multiple mule accounts on one device.
                                    </li>
                                <?php endif; ?>

                                <?php if ($riskScore < 20): ?>
                                    <li class="text-success">
                                        No significant mule indicators detected. Device appears to be used for personal purposes.
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
function startMuleScan(isAuto = false) {
    if (!isAuto && !confirm("Run active Mule Scan? This will extract fresh package data from the device and re-analyze.")) return;

    const btn = document.getElementById('scanBtn');
    // Store original text
    const originalText = btn ? btn.innerHTML : '';
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scanning...';
    }
    
    fetch('../api/run_mule_scan.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if(!isAuto) alert(data.message);
                else console.log(data.message);
                
                // Reload
                setTimeout(() => location.reload(), 15000); 
            } else {
                alert('Error: ' + data.error);
                if(btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        })
        .catch(err => {
            console.error(err);
            if(!isAuto) alert('Network Error');
            if(btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
}

// Auto-run if data is missing
document.addEventListener('DOMContentLoaded', function() {
    const shouldAutoScan = <?= $shouldAutoScan ? 'true' : 'false' ?>;
    if (shouldAutoScan) {
        console.log("⚠️ No Mule Data found. Auto-starting active scan...");
        
        // Show a toast or notification to user
        const btn = document.getElementById('scanBtn');
        if(btn) {
            btn.innerHTML = '<i class="fas fa-magic me-2"></i>Auto-Analyzing...';
            btn.disabled = true;
        }
        
        startMuleScan(true); // Call with true to skip confirmation
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>
