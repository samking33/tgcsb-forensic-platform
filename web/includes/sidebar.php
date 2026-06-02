<?php
/**
 * Android Forensic Tool - Sidebar Component
 * AdminLTE 4 with Bootstrap 5
 */

$currentPage = $currentPage ?? getCurrentPage();

// Menu items configuration
$menuItems = [
    [
        'id' => 'dashboard',
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => ($basePath ?? '') . 'index.php',
        'badge' => null
    ],
    [
        'id' => 'extract-logs',
        'title' => 'Extract Logs',
        'icon' => 'fas fa-download',
        'url' => ($basePath ?? '') . 'pages/extract-logs.php',
        'badge' => null
    ],
    [
        'id' => 'divider1',
        'type' => 'header',
        'title' => 'LOG ANALYSIS'
    ],
    [
        'id' => 'sms-messages',
        'title' => 'SMS Messages',
        'icon' => 'fas fa-comment-sms',
        'url' => ($basePath ?? '') . 'pages/sms-messages.php',
        'badge' => ['id' => 'smsCount', 'color' => 'info', 'value' => '0']
    ],
    [
        'id' => 'call-logs',
        'title' => 'Call Logs',
        'icon' => 'fas fa-phone',
        'url' => ($basePath ?? '') . 'pages/call-logs.php',
        'badge' => ['id' => 'callCount', 'color' => 'success', 'value' => '0']
    ],
    [
        'id' => 'location',
        'title' => 'Location Data',
        'icon' => 'fas fa-map-marker-alt',
        'url' => ($basePath ?? '') . 'pages/location.php',
        'badge' => ['id' => 'locationCount', 'color' => 'warning', 'value' => '0']
    ],
    [
        'id' => 'timeline',
        'title' => 'Device Timeline',
        'icon' => 'fas fa-clock',
        'url' => ($basePath ?? '') . 'pages/timeline.php',
        'badge' => null
    ],
    [
        'id' => 'logcat',
        'title' => 'Logcat Viewer',
        'icon' => 'fas fa-file-code',
        'url' => ($basePath ?? '') . 'pages/logcat.php',
        'badge' => null
    ],
    [
        'id' => 'divider_forensics',
        'type' => 'header',
        'title' => 'FORENSIC INTELLIGENCE'
    ],
    [
        'id' => 'mule-hunter',
        'title' => 'Mule Hunter',
        'icon' => 'fas fa-piggy-bank',
        'url' => ($basePath ?? '') . 'pages/mule-hunter.php',
        'badge' => ['id' => 'muleRisk', 'color' => 'danger', 'value' => '!']
    ],
    [
        'id' => 'threat-scanner',
        'title' => 'Threat Scanner',
        'icon' => 'fas fa-shield-virus',
        'url' => ($basePath ?? '') . 'pages/threat-scanner.php',
        'badge' => ['id' => 'threatrisk', 'color' => 'warning', 'value' => '!']
    ],
    [
        'id' => 'timeline-advanced',
        'title' => 'Advanced Timeline',
        'icon' => 'fas fa-history',
        'url' => ($basePath ?? '') . 'pages/timeline-advanced.php',
        'badge' => null
    ],
    [
        'id' => 'privacy-profiler',
        'title' => 'Privacy Profiler',
        'icon' => 'fas fa-user-secret',
        'url' => ($basePath ?? '') . 'pages/privacy-profiler.php',
        'badge' => null
    ],
    [
        'id' => 'social-graph',
        'title' => 'Social Link Graph',
        'icon' => 'fas fa-project-diagram',
        'url' => ($basePath ?? '') . 'pages/social-graph.php',
        'badge' => null
    ],
    [
        'id' => 'apk-tracker',
        'title' => 'APK Hunter',
        'icon' => 'fas fa-box-open',
        'url' => ($basePath ?? '') . 'pages/apk-tracker.php',
        'badge' => null
    ],
    // Removed non-functional features on modern Android:
    // - Network Intel (inconsistent data)
    // - PII Detector (privacy restrictions)
    // - Power Forensics (limited logging)
    // - Intent Hunter (filtered logs)
    // - Beacon Map (WiFi/BT logging restricted)
    // - Clipboard Recovery (Android 10+ blocks content)
    // - App Sessionizer (ActivityManager verbosity issues)

    [
        'id' => 'divider2',
        'type' => 'header',
        'title' => 'TOOLS'
    ],

    [
        'id' => 'global-search',
        'title' => 'Global Search',
        'icon' => 'fas fa-search',
        'url' => ($basePath ?? '') . 'pages/global-search.php',
        'badge' => null
    ],
    [
        'id' => 'filter-logs',
        'title' => 'Filter Logs',
        'icon' => 'fas fa-filter',
        'url' => ($basePath ?? '') . 'pages/filter-logs.php',
        'badge' => null
    ],
    [
        'id' => 'graphs',
        'title' => 'Activity Graphs',
        'icon' => 'fas fa-chart-line',
        'url' => ($basePath ?? '') . 'pages/graphs.php',
        'badge' => null
    ],
    [
        'id' => 'live-monitor',
        'title' => 'Live Monitor',
        'icon' => 'fas fa-satellite-dish',
        'url' => ($basePath ?? '') . 'pages/live-monitor.php',
        'badge' => null
    ],
    [
        'id' => 'divider3',
        'type' => 'header',
        'title' => 'EXPORT'
    ],
    [
        'id' => 'export-report',
        'title' => 'Export Report',
        'icon' => 'fas fa-file-export',
        'url' => '#',
        'badge' => null,
        'onclick' => 'exportFullReport()'
    ],
    [
        'id' => 'pdf-report',
        'title' => 'PDF Report',
        'icon' => 'fas fa-file-pdf',
        'url' => ($basePath ?? '') . 'api/generate-pdf-report.php?hashes=1&audit=1',
        'badge' => null,
        'target' => '_blank'
    ],
    [
        'id' => 'legal-disclaimer',
        'title' => 'Legal Disclaimer',
        'icon' => 'fas fa-gavel',
        'url' => ($basePath ?? '') . 'pages/legal-disclaimer.php',
        'badge' => null
    ]
];
?>

<!-- Sidebar -->
<aside class="app-sidebar">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <i class="fas fa-fingerprint"></i>
        <span class="brand-text">
            <strong>Android</strong> Forensic
        </span>
    </div>

    <!-- Sidebar Wrapper -->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav nav-sidebar flex-column">

                <?php foreach ($menuItems as $item): ?>
                    <?php if (isset($item['type']) && $item['type'] === 'header'): ?>
                        <!-- Header -->
                        <li class="nav-header"><?= $item['title'] ?></li>
                    <?php else: ?>
                        <!-- Menu Item -->
                        <li class="nav-item">
                            <a href="<?= $item['url'] ?>" class="nav-link <?= $currentPage === $item['id'] ? 'active' : '' ?>"
                                <?= isset($item['onclick']) ? 'onclick="' . $item['onclick'] . '; return false;"' : '' ?>>
                                <i class="<?= $item['icon'] ?>"></i>
                                <span><?= $item['title'] ?></span>
                                <?php if (isset($item['badge']) && $item['badge']): ?>
                                    <span class="badge bg-<?= $item['badge']['color'] ?>" id="<?= $item['badge']['id'] ?>">
                                        <?= $item['badge']['value'] ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>

            </ul>
        </nav>
    </div>

</aside>
<!-- /Sidebar -->