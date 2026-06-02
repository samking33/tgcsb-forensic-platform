<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Root Status - Android Forensics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="mb-4">
                    <i class="fas fa-shield-alt text-danger me-2"></i>Device Root Status
                </h2>

                <?php
                $logPath = "../../logs/root_status.json";
                $fileExists = file_exists($logPath);
                $fileContent = $fileExists ? file_get_contents($logPath) : false;
                
                if ($fileExists && $fileContent) {
                    $rootData = json_decode($fileContent, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $rootData = null;
                        $jsonError = json_last_error_msg();
                    }
                } else {
                    $rootData = null;
                }
                ?>

                <?php if (!$rootData): ?>
                    <!-- No Data Available -->
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">No Root Check Performed Yet</h5>
                            <p class="mb-0">Connect a device via ADB and run log extraction to check root status.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Root Status Dashboard -->
                    
                    <!-- Main Status Card -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card shadow-lg border-0 <?= $rootData['is_rooted'] ? 'border-start border-danger border-5' : 'border-start border-success border-5' ?>">
                                <div class="card-body p-4">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            <i class="fas fa-<?= $rootData['is_rooted'] ? 'unlock' : 'lock' ?> fa-5x <?= $rootData['is_rooted'] ? 'text-danger' : 'text-success' ?>"></i>
                                        </div>
                                        <div class="col-md-10">
                                            <h3 class="fw-bold mb-3">
                                                Device is <?= $rootData['is_rooted'] ? '<span class="text-danger">ROOTED</span>' : '<span class="text-success">NOT ROOTED</span>' ?>
                                            </h3>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <p class="text-muted mb-1 small">Android Version</p>
                                                    <h5 class="fw-bold"><?= htmlspecialchars($rootData['android_version']) ?></h5>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="text-muted mb-1 small">Confidence</p>
                                                    <h5 class="fw-bold"><?= htmlspecialchars($rootData['confidence']) ?></h5>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="text-muted mb-1 small">Detections</p>
                                                    <h5 class="fw-bold"><?= $rootData['detection_count'] ?>/<?= $rootData['total_checks'] ?></h5>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="text-muted mb-1 small">Last Checked</p>
                                                    <h5 class="fw-bold small"><?= date('M d, H:i', strtotime($rootData['timestamp'])) ?></h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Root Indicators Summary -->
                    <?php if (!empty($rootData['summary'])): ?>
                        <div class="alert alert-warning" role="alert">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>Root Indicators Detected
                            </h5>
                            <ul class="mb-0">
                                <?php foreach ($rootData['summary'] as $indicator): ?>
                                    <li><?= htmlspecialchars($indicator) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Detection Results -->
                    <div class="row">
                        <?php
                        $checkDetails = [
                            'su_binary' => ['name' => 'SU Binary Check', 'icon' => 'terminal', 'color' => 'danger'],
                            'root_apps' => ['name' => 'Root Management Apps', 'icon' => 'mobile-alt', 'color' => 'warning'],
                            'build_properties' => ['name' => 'Build Properties', 'icon' => 'cog', 'color' => 'info'],
                            'selinux' => ['name' => 'SELinux Status', 'icon' => 'shield-alt', 'color' => 'primary'],
                            'busybox' => ['name' => 'BusyBox Detection', 'icon' => 'toolbox', 'color' => 'secondary'],
                            'dangerous_dirs' => ['name' => 'System Directories', 'icon' => 'folder-open', 'color' => 'dark'],
                            'test_keys' => ['name' => 'Build Tags', 'icon' => 'key', 'color' => 'success']
                        ];

                        foreach ($checkDetails as $checkKey => $meta):
                            $check = $rootData['checks'][$checkKey] ?? ['detected' => false, 'method' => 'Unknown'];
                            $detected = $check['detected'];
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-header bg-<?= $meta['color'] ?>-subtle border-0">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="fas fa-<?= $meta['icon'] ?> me-2 text-<?= $meta['color'] ?>"></i>
                                            <?= $meta['name'] ?>
                                            <span class="float-end badge <?= $detected ? 'bg-danger' : 'bg-success' ?>">
                                                <?= $detected ? 'DETECTED' : 'Not Found' ?>
                                            </span>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($checkKey === 'su_binary' && isset($check['locations']) && !empty($check['locations'])): ?>
                                            <p class="text-muted mb-2 small">SU Binary Locations:</p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($check['locations'] as $location): ?>
                                                    <li><code class="small"><?= htmlspecialchars($location) ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        
                                        <?php elseif ($checkKey === 'root_apps' && isset($check['apps']) && !empty($check['apps'])): ?>
                                            <p class="text-muted mb-2 small">Detected Apps:</p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($check['apps'] as $app): ?>
                                                    <li><span class="badge bg-danger"><?= htmlspecialchars($app) ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        
                                        <?php elseif ($checkKey === 'build_properties' && isset($check['findings']) && !empty($check['findings'])): ?>
                                            <p class="text-muted mb-2 small">Suspicious Properties:</p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($check['findings'] as $finding): ?>
                                                    <li><code class="small text-danger"><?= htmlspecialchars($finding) ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        
                                        <?php elseif ($checkKey === 'selinux' && isset($check['status'])): ?>
                                            <p class="mb-1"><strong>Status:</strong> 
                                                <span class="badge <?= $detected ? 'bg-warning' : 'bg-success' ?>">
                                                    <?= htmlspecialchars($check['status']) ?>
                                                </span>
                                            </p>
                                            <?php if ($detected): ?>
                                                <p class="small text-muted mb-0">SELinux is in Permissive mode, typical of rooted devices.</p>
                                            <?php endif; ?>
                                        
                                        <?php elseif ($checkKey === 'busybox' && isset($check['path']) && $check['path']): ?>
                                            <p class="mb-1"><strong>Path:</strong></p>
                                            <code class="small"><?= htmlspecialchars($check['path']) ?></code>
                                        
                                        <?php elseif ($checkKey === 'dangerous_dirs' && isset($check['directories']) && !empty($check['directories'])): ?>
                                            <p class="text-muted mb-2 small">Writable Directories:</p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($check['directories'] as $dir): ?>
                                                    <li><code class="small"><?= htmlspecialchars($dir) ?></code></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        
                                        <?php elseif ($checkKey === 'test_keys' && isset($check['tags'])): ?>
                                            <p class="mb-1"><strong>Build Tags:</strong> 
                                                <code class="small <?= $detected ? 'text-danger' : '' ?>"><?= htmlspecialchars($check['tags']) ?></code>
                                            </p>
                                            <?php if ($detected): ?>
                                                <p class="small text-muted mb-0">Device signed with test-keys instead of release-keys.</p>
                                            <?php endif; ?>
                                        
                                        <?php else: ?>
                                            <p class="text-muted mb-0 small">
                                                <i class="fas fa-check-circle text-success me-1"></i> No indicators found
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Forensic Notes -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary-subtle border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>Forensic Analysis Notes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($rootData['is_rooted']): ?>
                                <div class="alert alert-danger mb-3">
                                    <h6 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i>Root Detection Impact</h6>
                                    <ul class="mb-0 small">
                                        <li>Root access may allow tampering with logs and forensic evidence</li>
                                        <li>System-level data may have been modified or deleted</li>
                                        <li>Additional verification of extracted data is recommended</li>
                                        <li>Consider cross-referencing with server-side logs where available</li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <h6 class="fw-bold">Detection Methods Used:</h6>
                            <ul class="small">
                                <li><strong>SU Binary Check:</strong> Searches for su binaries in common system locations</li>
                                <li><strong>Root Management Apps:</strong> Detects Magisk, SuperSU, KingRoot, KingoRoot</li>
                                <li><strong>Build Properties:</strong> Checks ro.debuggable, ro.secure, ro.build.tags</li>
                                <li><strong>SELinux Status:</strong> Verifies enforcement mode (Enforcing vs Permissive)</li>
                                <li><strong>BusyBox:</strong> Checks for BusyBox installation</li>
                                <li><strong>System Directories:</strong> Tests write access to protected directories</li>
                                <li><strong>Build Tags:</strong> Verifies release-keys vs test-keys signing</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
