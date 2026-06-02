<?php
/**
 * Android Forensic Tool - Header Component
 * AdminLTE 4 with Bootstrap 5
 */
require_once __DIR__ . '/config.php';

$basePath = getBasePath();
$currentPage = getCurrentPage();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Android Forensic Analysis Tool - Professional digital forensics for Android devices">
    <title><?= $pageTitle ?? APP_NAME ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- AdminLTE 4 CSS (Bootstrap 5 based) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">

    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <!-- Leaflet CSS for Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Custom Forensic Theme - MUST BE LAST TO OVERRIDE DEFAULTS -->
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/custom.css?v=<?= time() ?>">

    <!-- Toggle Component - Standardized Toggle Row Styling -->
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/toggle-component.css?v=<?= time() ?>">

    <!-- Mobile Responsive Design -->
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/responsive.css?v=<?= time() ?>">

    <!-- Viewport meta tag for mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">

        <!-- Top Navbar -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <!-- Sidebar Toggle -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" id="mainSidebarToggle" href="#" role="button" onclick="toggleSidebar(event)">
                            <i class="fas fa-bars"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="<?= $basePath ?? '' ?>index.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="<?= $basePath ?? '' ?>pages/extract-logs.php" class="nav-link">Extract Logs</a>
                    </li>
                </ul>

                <!-- Right Navbar -->
                <ul class="navbar-nav ms-auto">
                    <!-- Device Status -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="deviceStatusBtn" title="Device Status">
                            <i class="fas fa-mobile-alt"></i>
                            <span class="badge bg-success badge-sm ms-1" id="deviceBadge">Connected</span>
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-warning navbar-badge" id="notificationCount">0</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <span class="dropdown-header">Notifications</span>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item" id="noNotifications">
                                <i class="fas fa-info-circle me-2"></i> No new notifications
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                        </div>
                    </li>

                    <!-- Fullscreen Toggle -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </a>
                    </li>

                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="themeToggle" title="Toggle Theme">
                            <i class="fas fa-moon"></i>
                        </a>
                    </li>

                    <!-- User Menu -->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="d-none d-md-inline ms-1">Forensic Analyst</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <li class="user-header text-bg-primary">
                                <i class="fas fa-user-shield fa-3x"></i>
                                <p>
                                    Forensic Analyst
                                    <small><?= APP_NAME ?> v<?= APP_VERSION ?></small>
                                </p>
                            </li>
                            <li class="user-footer">
                                <a href="#" class="btn btn-default btn-flat" data-bs-toggle="modal"
                                    data-bs-target="#settingsModal">Settings</a>
                                <a href="#" class="btn btn-default btn-flat float-end" data-bs-toggle="modal"
                                    data-bs-target="#aboutModal">About</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- /Top Navbar -->