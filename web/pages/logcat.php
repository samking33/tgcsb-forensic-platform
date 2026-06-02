<?php
/**
 * Android Forensic Tool - Logcat Viewer Page
 * Categorized logcat logs with tabs
 */
$pageTitle = 'Logcat Viewer - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get logcat content
function getLogcatContent() {
    $logsPath = getLogsPath();
    $logcatFile = $logsPath . '/android_logcat.txt';
    
    if (file_exists($logcatFile)) {
        return file_get_contents($logcatFile);
    }
    return '';
}

// Categorize logs by type
function categorizeLogcat($content) {
    global $LOG_TYPES;
    
    $categories = [];
    foreach ($LOG_TYPES as $type => $info) {
        $categories[$type] = [
            'lines' => [],
            'count' => 0,
            'info' => $info
        ];
    }
    $categories['Other'] = [
        'lines' => [],
        'count' => 0,
        'info' => ['color' => 'secondary', 'icon' => 'fas fa-file-alt', 'description' => 'Uncategorized logs']
    ];
    
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $matched = false;
        foreach ($LOG_TYPES as $type => $info) {
            if (preg_match($info['pattern'], $line)) {
                $categories[$type]['lines'][] = $line;
                $categories[$type]['count']++;
                $matched = true;
                break;
            }
        }
        
        if (!$matched) {
            $categories['Other']['lines'][] = $line;
            $categories['Other']['count']++;
        }
    }
    
    return $categories;
}

// Get log level from line
function getLogLevel($line) {
    if (preg_match('/^\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+\s+\d+\s+\d+\s+([VDIWEF])/', $line, $match)) {
        return $match[1];
    }
    return 'I'; // Default to Info
}

$logcatContent = getLogcatContent();
$categories = categorizeLogcat($logcatContent);
$totalLines = array_sum(array_column($categories, 'count'));
?>

<!-- Main Content Wrapper -->
<main class="app-main">
    <!-- Content Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-code me-2 text-forensic-blue"></i>Logcat Viewer
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Logcat</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-file-code"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Lines</span>
                            <span class="info-box-number"><?= number_format($totalLines) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Crashes</span>
                            <span class="info-box-number"><?= number_format($categories['Crash']['count']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-mobile-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Application</span>
                            <span class="info-box-number"><?= number_format($categories['Application']['count']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-wifi"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Network</span>
                            <span class="info-box-number"><?= number_format($categories['Network']['count']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Controls -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="logSearch" 
                                       placeholder="Search in logs..." onkeyup="filterLogs()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="levelFilter" onchange="filterLogs()">
                                <option value="">All Levels</option>
                                <option value="V">Verbose</option>
                                <option value="D">Debug</option>
                                <option value="I">Info</option>
                                <option value="W">Warning</option>
                                <option value="E">Error</option>
                                <option value="F">Fatal</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="btn-group" role="group">
                                <input type="checkbox" class="btn-check" id="autoScroll" checked>
                                <label class="btn btn-outline-secondary" for="autoScroll">
                                    <i class="fas fa-scroll me-1"></i>Auto-scroll
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-outline-primary" onclick="copyCurrentTab()">
                                <i class="fas fa-copy me-1"></i>Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Logcat Tabs -->
            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs" id="logcatTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-all" type="button">
                                <i class="fas fa-list me-1"></i>All
                                <span class="badge bg-secondary ms-1"><?= number_format($totalLines) ?></span>
                            </button>
                        </li>
                        <?php foreach ($categories as $type => $data): ?>
                        <?php if ($data['count'] > 0): ?>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-<?= strtolower($type) ?>" type="button">
                                <i class="<?= $data['info']['icon'] ?> me-1"></i><?= $type ?>
                                <span class="badge bg-<?= $data['info']['color'] ?> ms-1"><?= number_format($data['count']) ?></span>
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="logcatTabContent">
                        <!-- All Logs Tab -->
                        <div class="tab-pane fade show active" id="tab-all">
                            <div class="log-viewer" id="logConsoleAll" style="height: 500px;">
                                <?php 
                                $allLines = explode("\n", $logcatContent);
                                foreach (array_slice($allLines, 0, 1000) as $line): 
                                    if (empty(trim($line))) continue;
                                    $level = getLogLevel($line);
                                    $levelClass = match($level) {
                                        'V' => 'log-verbose',
                                        'D' => 'log-debug',
                                        'I' => 'log-info',
                                        'W' => 'log-warning',
                                        'E' => 'log-error',
                                        'F' => 'log-critical',
                                        default => 'log-info'
                                    };
                                ?>
                                <div class="log-entry <?= $levelClass ?>" data-level="<?= $level ?>"><?= htmlspecialchars($line) ?></div>
                                <?php endforeach; ?>
                                <?php if (count($allLines) > 1000): ?>
                                <div class="log-entry text-warning">--- Showing first 1000 lines. Use search to filter. ---</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Category Tabs -->
                        <?php foreach ($categories as $type => $data): ?>
                        <?php if ($data['count'] > 0): ?>
                        <div class="tab-pane fade" id="tab-<?= strtolower($type) ?>">
                            <div class="log-viewer" id="logConsole<?= $type ?>" style="height: 500px;">
                                <?php foreach (array_slice($data['lines'], 0, 500) as $line): 
                                    $level = getLogLevel($line);
                                    $levelClass = match($level) {
                                        'V' => 'log-verbose',
                                        'D' => 'log-debug',
                                        'I' => 'log-info',
                                        'W' => 'log-warning',
                                        'E' => 'log-error',
                                        'F' => 'log-critical',
                                        default => 'log-info'
                                    };
                                ?>
                                <div class="log-entry <?= $levelClass ?>" data-level="<?= $level ?>"><?= htmlspecialchars($line) ?></div>
                                <?php endforeach; ?>
                                <?php if ($data['count'] > 500): ?>
                                <div class="log-entry text-warning">--- Showing first 500 lines of this category ---</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-palette me-2"></i>Log Level Colors
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <span class="log-verbose">■</span> Verbose (V)
                        </div>
                        <div class="col-md-2">
                            <span class="log-debug">■</span> Debug (D)
                        </div>
                        <div class="col-md-2">
                            <span class="log-info">■</span> Info (I)
                        </div>
                        <div class="col-md-2">
                            <span class="log-warning">■</span> Warning (W)
                        </div>
                        <div class="col-md-2">
                            <span class="log-error">■</span> Error (E)
                        </div>
                        <div class="col-md-2">
                            <span class="log-fatal">■</span> Fatal (F)
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</main>

<?php 
$additionalScripts = <<<'SCRIPT'
<script>
function filterLogs() {
    const searchTerm = document.getElementById('logSearch').value.toLowerCase();
    const levelFilter = document.getElementById('levelFilter').value;
    
    document.querySelectorAll('.log-viewer .log-entry').forEach(line => {
        const text = line.textContent.toLowerCase();
        const level = line.dataset.level || '';
        
        const matchesSearch = !searchTerm || text.includes(searchTerm);
        const matchesLevel = !levelFilter || level === levelFilter;
        
        line.style.display = (matchesSearch && matchesLevel) ? '' : 'none';
    });
}

function copyCurrentTab() {
    const activePane = document.querySelector('.tab-pane.active .log-console');
    if (activePane) {
        copyToClipboard(activePane.innerText);
    }
}

// Debounce search
document.getElementById('logSearch').addEventListener('input', debounce(filterLogs, 300));
</script>
SCRIPT;

require_once '../includes/footer.php';
?>
