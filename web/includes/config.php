<?php
/**
 * Android Forensic Tool - Configuration
 * PHP 8+ Configuration File
 */

// Debug mode - SECURITY: Set to false in production to prevent information disclosure
// Only enable during development/troubleshooting. Can be overridden via environment variable.
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true' ? true : false);

// Application Info
define('APP_VERSION', 'v2.1.0');
define('APP_RELEASE_DATE', '2026-01-20');

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('LOGS_PATH', dirname(BASE_PATH) . '/logs');
define('PYTHON_PATH', 'python'); // Adjust if needed

// ========================================
// Cell Tower Geolocation API Configuration
// ========================================
// SETUP REQUIRED: Cell tower geolocation requires external API keys
// 
// OpenCellID API - Get free key at: https://opencellid.org/
// - Sign up for free account
// - Navigate to API section to get your key
// - Paste key below
define('OPENCELLID_API_KEY', getenv('OPENCELLID_API_KEY') ?: ''); // Your OpenCellID API key

// Unwired Labs API - Get free key at: https://unwiredlabs.com/
// - Sign up for free account  
// - Get API key from dashboard
// - Paste key below
define('UNWIREDLABS_API_KEY', getenv('UNWIREDLABS_API_KEY') ?: ''); // Your Unwired Labs API key

// API preference order: 'opencellid', 'unwiredlabs', 'fallback'
// If keys are not configured, system will use fallback mode (limited accuracy)
define('CELL_LOOKUP_PROVIDER', 'opencellid');

// If logs don't exist in parent, check current location
if (!is_dir(LOGS_PATH)) {
    define('LOGS_PATH_ALT', BASE_PATH . '/../logs');
}

// Theme Colors (matching Python config.py)
$THEME = [
    'primary_bg' => '#1e1e2e',
    'secondary_bg' => '#2d2d44',
    'panel_bg' => '#252538',
    'text_primary' => '#cdd6f4',
    'text_secondary' => '#a6adc8',
    'header_bg' => '#181825',
    'accent_blue' => '#89b4fa',
    'accent_lavender' => '#b4befe',
    'success_green' => '#a6e3a1',
    'warning_orange' => '#fab387',
    'error_red' => '#f38ba8',
    'info_cyan' => '#94e2d5'
];

// Log Types (matching Python config.py)
$LOG_TYPES = [
    'Application' => [
        'description' => 'Application-specific logs',
        'pattern' => '/ActivityManager|PackageManager|ApplicationContext/i',
        'color' => 'primary',
        'icon' => 'fas fa-mobile-alt'
    ],
    'System' => [
        'description' => 'System-level logs',
        'pattern' => '/SystemServer|System\.err|SystemClock|SystemProperties/i',
        'color' => 'success',
        'icon' => 'fas fa-cog'
    ],
    'Crash' => [
        'description' => 'Application crashes and exceptions',
        'pattern' => '/FATAL|Exception|ANR|crash|force close|stacktrace/i',
        'color' => 'danger',
        'icon' => 'fas fa-exclamation-triangle'
    ],
    'GC' => [
        'description' => 'Garbage Collection events',
        'pattern' => '/dalvikvm.*GC|art.*GC|GC_|collector/i',
        'color' => 'secondary',
        'icon' => 'fas fa-recycle'
    ],
    'Network' => [
        'description' => 'Network activity logs',
        'pattern' => '/ConnectivityManager|NetworkInfo|WifiManager|HttpURLConnection|socket|wifi|TCP|UDP|DNS/i',
        'color' => 'info',
        'icon' => 'fas fa-wifi'
    ],
    'Broadcast' => [
        'description' => 'Broadcast receivers and events',
        'pattern' => '/BroadcastReceiver|sendBroadcast|onReceive|Intent.*broadcast/i',
        'color' => 'warning',
        'icon' => 'fas fa-broadcast-tower'
    ],
    'Service' => [
        'description' => 'Service lifecycle events',
        'pattern' => '/Service|startService|stopService|bindService|onBind/i',
        'color' => 'primary',
        'icon' => 'fas fa-server'
    ],
    'Device' => [
        'description' => 'Device state and hardware',
        'pattern' => '/PowerManager|BatteryManager|sensor|hardware|camera|location|bluetooth|telephony/i',
        'color' => 'info',
        'icon' => 'fas fa-microchip'
    ]
];

// Severity levels
$SEVERITY_LEVELS = [
    'V' => ['name' => 'Verbose', 'color' => 'secondary', 'icon' => 'fas fa-comment'],
    'D' => ['name' => 'Debug', 'color' => 'info', 'icon' => 'fas fa-bug'],
    'I' => ['name' => 'Info', 'color' => 'primary', 'icon' => 'fas fa-info-circle'],
    'W' => ['name' => 'Warning', 'color' => 'warning', 'icon' => 'fas fa-exclamation-circle'],
    'E' => ['name' => 'Error', 'color' => 'danger', 'icon' => 'fas fa-times-circle'],
    'F' => ['name' => 'Fatal', 'color' => 'dark', 'icon' => 'fas fa-skull']
];

// Time range options
$TIME_RANGES = [
    'Past 1 Hour' => 3600,
    'Past 24 Hours' => 86400,
    'Past 7 Days' => 604800,
    'All Time' => 0
];

// App info
define('APP_NAME', 'Android Forensic Tool');
define('APP_AUTHOR', 'Forensic Analysis Team');

/**
 * Get the correct logs path
 */
function getLogsPath(): string
{
    if (is_dir(LOGS_PATH)) {
        return LOGS_PATH;
    }
    $alt = dirname(dirname(__DIR__)) . '/logs';
    if (is_dir($alt)) {
        return $alt;
    }
    // Create logs directory if it doesn't exist
    @mkdir(dirname(__DIR__) . '/logs', 0755, true);
    return dirname(__DIR__) . '/logs';
}

/**
 * Read log file safely
 */
function readLogFile(string $filename): string
{
    $path = getLogsPath() . '/' . $filename;
    if (file_exists($path)) {
        return file_get_contents($path);
    }
    return '';
}

/**
 * Get current page name for active menu highlighting
 */
function getCurrentPage(): string
{
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page === 'index' ? 'dashboard' : $page;
}
/**
 * Get base path for assets (relative to current script)
 */
function getBasePath()
{
    // Determine if we're in pages/ subdirectory or root
    if (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false) {
        return '../';
    }
    return '';
}
