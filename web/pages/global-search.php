<?php
$pageTitle = 'Global Search - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get search query
$query = $_GET['q'] ?? '';
$results = [];

if (!empty($query)) {
    $logsPath = getLogsPath();
    
    // Define all searchable JSON files
    $searchFiles = [
        'unified_timeline.json' => 'Advanced Timeline',
        'privacy_profile.json' => 'Privacy Profiler',
        'pii_leaks.json' => 'PII Leak Detector',
        'network_activity.json' => 'Network Intelligence',
        'social_graph.json' => 'Social Link Graph',
        'power_forensics.json' => 'Power Forensics',
        'intent_hunter.json' => 'Intent & URL Hunter',
        'beacon_map.json' => 'WiFi & Bluetooth Beacon Map',
        'clipboard_forensics.json' => 'Clipboard Recovery',
        'app_sessions.json' => 'App Usage Sessionizer'
    ];
    
    // Search through each file
    foreach ($searchFiles as $filename => $moduleName) {
        $filepath = $logsPath . '/' . $filename;
        
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            
            // Recursive search in JSON data
            $matches = searchInArray($data, $query);
            
            if (!empty($matches)) {
                $results[] = [
                    'module' => $moduleName,
                    'file' => $filename,
                    'matches' => $matches,
                    'count' => count($matches)
                ];
            }
        }
    }
    
    // Also search in raw log files
    $rawFiles = ['android_logcat.txt', 'sms_logs.txt', 'call_logs.txt'];
    foreach ($rawFiles as $filename) {
        $filepath = $logsPath . '/' . $filename;
        
        if (file_exists($filepath)) {
            $lines = file($filepath, FILE_IGNORE_NEW_LINES);
            $matches = [];
            
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, $query) !== false) {
                    $matches[] = [
                        'line' => $lineNum + 1,
                        'content' => htmlspecialchars($line),
                        'highlight' => highlightQuery($line, $query)
                    ];
                    
                    if (count($matches) >= 20) break; // Limit to 20 matches per file
                }
            }
            
            if (!empty($matches)) {
                $results[] = [
                    'module' => 'Raw Logs: ' . pathinfo($filename, PATHINFO_FILENAME),
                    'file' => $filename,
                    'matches' => $matches,
                    'count' => count($matches)
                ];
            }
        }
    }
}

function searchInArray($array, $query, $path = '') {
    $matches = [];
    
    foreach ($array as $key => $value) {
        $currentPath = $path ? $path . ' → ' . $key : $key;
        
        if (is_array($value)) {
            $matches = array_merge($matches, searchInArray($value, $query, $currentPath));
        } elseif (is_string($value) && stripos($value, $query) !== false) {
            $matches[] = [
                'path' => $currentPath,
                'value' => $value,
                'highlight' => highlightQuery($value, $query)
            ];
        }
    }
    
    return $matches;
}

function highlightQuery($text, $query) {
    return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark class="bg-warning">$1</mark>', htmlspecialchars($text));
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-search me-2 text-primary"></i>Global Search</h3>
            <p class="text-muted small">Search across all forensic modules and raw logs</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Search Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="input-group input-group-lg">
                            <input type="text" 
                                   class="form-control" 
                                   name="q" 
                                   value="<?= htmlspecialchars($query) ?>" 
                                   placeholder="Search for package names, IPs, phone numbers, text..."
                                   autofocus>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            Tip: Search is case-insensitive and searches across all modules and raw logs.
                        </small>
                    </form>
                </div>
            </div>

            <?php if (!empty($query)): ?>
                <!-- Search Results Summary -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Found <strong><?= count($results) ?></strong> module(s) with matches for "<strong><?= htmlspecialchars($query) ?></strong>"
                </div>

                <?php if (empty($results)): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>No Results Found</h5>
                            <p class="text-muted">Try a different search term or check your spelling.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Results by Module -->
                    <?php foreach ($results as $result): ?>
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title text-white mb-0">
                                    <i class="fas fa-folder me-2"></i><?= $result['module'] ?>
                                    <span class="badge bg-light text-dark ms-2"><?= $result['count'] ?> matches</span>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <?php if (isset($result['matches'][0]['path'])): ?>
                                                    <th>Field Path</th>
                                                    <th>Matched Content</th>
                                                <?php else: ?>
                                                    <th width="80">Line</th>
                                                    <th>Content</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($result['matches'], 0, 20) as $match): ?>
                                                <tr>
                                                    <?php if (isset($match['path'])): ?>
                                                        <td><code class="small"><?= htmlspecialchars($match['path']) ?></code></td>
                                                        <td><?= $match['highlight'] ?></td>
                                                    <?php else: ?>
                                                        <td><code><?= $match['line'] ?></code></td>
                                                        <td class="small"><code><?= $match['highlight'] ?></code></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($result['count'] > 20): ?>
                                    <div class="card-footer text-muted small">
                                        Showing first 20 of <?= $result['count'] ?> matches
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>
