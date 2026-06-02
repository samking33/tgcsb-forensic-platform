/**
 * Android Forensic Tool - Main Application JavaScript
 * Vanilla JS with jQuery for AdminLTE compatibility
 */

// ========================================
// CONFIGURATION
// ========================================
const ForensicApp = {
    config: {
        apiBase: '/api/',
        refreshRate: 2000,
        autoScroll: true,
        soundAlerts: false
    },

    state: {
        isMonitoring: false,
        currentPage: '',
        charts: {},
        tables: {},
        eventSource: null
    }
};

// ========================================
// INITIALIZATION
// ========================================
document.addEventListener('DOMContentLoaded', function () {
    console.log('🔍 Android Forensic Tool initialized');

    // Load saved settings
    loadSettings();

    // Initialize components
    initializeTooltips();
    initializeCounters();

    // Check device status (with 5-second timeout)
    checkDeviceStatus();

    // Set up periodic updates
    setInterval(updateDashboardStats, 30000);
});

// ========================================
// SETTINGS MANAGEMENT
// ========================================
function loadSettings() {
    const settings = localStorage.getItem('forensicSettings');
    if (settings) {
        const parsed = JSON.parse(settings);
        ForensicApp.config = { ...ForensicApp.config, ...parsed };

        // Apply theme
        if (parsed.theme) {
            document.documentElement.setAttribute('data-bs-theme', parsed.theme);
        }
    }
}

function saveSettings() {
    const settings = {
        theme: document.getElementById('themeSelect')?.value || 'dark',
        refreshRate: parseInt(document.getElementById('refreshRate')?.value) || 2000,
        autoScroll: document.getElementById('autoScroll')?.checked ?? true,
        soundAlerts: document.getElementById('soundAlerts')?.checked ?? false
    };

    localStorage.setItem('forensicSettings', JSON.stringify(settings));
    ForensicApp.config = { ...ForensicApp.config, ...settings };

    // Apply theme immediately
    document.documentElement.setAttribute('data-bs-theme', settings.theme);

    showToast('Settings saved successfully', 'success');

    const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
    if (modal) modal.hide();
}

// Theme toggle
document.getElementById('themeToggle')?.addEventListener('click', function () {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    this.innerHTML = newTheme === 'dark' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
});

// ========================================
// DEVICE STATUS
// ========================================
function checkDeviceStatus() {
    // Use AbortController to timeout after 5 seconds
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    fetch(ForensicApp.config.apiBase + 'device-status.php', {
        signal: controller.signal
    })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeoutId);
            updateDeviceIndicator(data.connected, data.device, data.message || data.error);
        })
        .catch((error) => {
            clearTimeout(timeoutId);
            console.log('Device status check timed out or failed:', error.name);
            updateDeviceIndicator(false, null, 'Connection timeout');
        });
}

function updateDeviceIndicator(connected, device, message) {
    const badge = document.getElementById('deviceBadge');
    if (badge) {
        if (connected && device) {
            badge.className = 'badge bg-success badge-sm ms-1';
            badge.textContent = device.model || 'Connected';
            badge.title = `${device.name} - Android ${device.android}`;
        } else if (message) {
            badge.className = 'badge bg-warning badge-sm ms-1';
            badge.textContent = 'Status Unknown';
            badge.title = message;
        } else {
            badge.className = 'badge bg-danger badge-sm ms-1';
            badge.textContent = 'No Device';
            badge.title = 'No device connected';
        }
    }

    // Update dashboard device info card if it exists
    const deviceModel = document.getElementById('deviceModel');
    const deviceAndroid = document.getElementById('deviceAndroid');
    const deviceSerial = document.getElementById('deviceSerial');
    const deviceImage = document.getElementById('deviceImage');
    const deviceStatus = document.getElementById('deviceStatus');

    if (deviceModel && connected && device) {
        // Update device name (prefer market name)
        deviceModel.textContent = device.name || device.model || 'Unknown Device';

        // Update Android version
        if (deviceAndroid) deviceAndroid.textContent = `Android ${device.android || 'Unknown'}`;

        // Update serial number
        if (deviceSerial) deviceSerial.textContent = `Serial: ${device.serial ? device.serial.substring(0, 12) + '...' : '--'}`;

        // Update device image
        if (deviceImage && device.image) {
            deviceImage.src = device.image;

            // If using generic icon and we have model info, try to fetch real image
            if (!device.imageFound && device.model && device.model !== 'Unknown Model') {
                fetchDeviceImageAsync(device.model, device.manufacturer);
            }
        }

        // Update status
        if (deviceStatus) {
            deviceStatus.innerHTML = '<i class="fas fa-circle me-1"></i>Online';
            deviceStatus.className = 'text-success';
        }
    } else if (deviceModel && message) {
        deviceModel.textContent = 'Status Unavailable';
        if (deviceAndroid) deviceAndroid.textContent = message;
        if (deviceSerial) deviceSerial.textContent = 'Serial: --';
        if (deviceImage) deviceImage.src = 'assets/images/devices/generic-phone.svg';
        if (deviceStatus) {
            deviceStatus.innerHTML = '<i class="fas fa-circle me-1"></i>Unknown';
            deviceStatus.className = 'text-warning';
        }
    } else if (deviceModel) {
        deviceModel.textContent = 'No Device';
        if (deviceAndroid) deviceAndroid.textContent = 'Connect a device via ADB';
        if (deviceSerial) deviceSerial.textContent = 'Serial: --';
        if (deviceImage) deviceImage.src = 'assets/images/devices/generic-phone.svg';
        if (deviceStatus) {
            deviceStatus.innerHTML = '<i class="fas fa-circle me-1"></i>Offline';
            deviceStatus.className = 'text-danger';
        }
    }
}

/**
 * Asynchronously fetch device image from GSMArena (Phase 2)
 * This runs in background and updates UI when complete
 */
function fetchDeviceImageAsync(model, manufacturer) {
    // Don't spam requests
    if (window.deviceImageFetchInProgress) return;
    window.deviceImageFetchInProgress = true;

    fetch(ForensicApp.config.apiBase + 'fetch-device-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model: model,
            manufacturer: manufacturer || 'unknown'
        })
    })
        .then(response => response.json())
        .then(data => {
            window.deviceImageFetchInProgress = false;

            if (data.success && data.imagePath) {
                // Update device image with fetched one
                const deviceImage = document.getElementById('deviceImage');
                if (deviceImage) {
                    deviceImage.src = data.imagePath;
                }

                // Update device name if we got market name
                if (data.deviceName) {
                    const deviceModel = document.getElementById('deviceModel');
                    if (deviceModel) {
                        deviceModel.textContent = data.deviceName;
                    }
                }

                console.log('Device image fetched:', data.deviceName, data.imagePath);
            } else {
                console.log('Device image not found on GSMArena:', model);
            }
        })
        .catch(error => {
            window.deviceImageFetchInProgress = false;
            console.log('Device image fetch skipped:', error.message);
        });
}

// ========================================
// TOOLTIPS & POPOVERS
// ========================================
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// ========================================
// SIDEBAR COUNTERS
// ========================================
function initializeCounters() {
    updateDashboardStats();
}

function updateDashboardStats() {
    fetch(ForensicApp.config.apiBase + 'stats.php')
        .then(response => response.json())
        .then(data => {
            // Update sidebar badges
            updateBadge('smsCount', data.smsCount || 0);
            updateBadge('callCount', data.callCount || 0);
            updateBadge('locationCount', data.locationCount || 0);


            // Update dashboard small boxes if on dashboard
            updateSmallBox('totalSms', data.smsCount || 0);
            updateSmallBox('totalCalls', data.callCount || 0);
            updateSmallBox('totalLocations', data.locationCount || 0);

        })
        .catch(error => {
            console.log('Stats update failed:', error);
        });
}

function updateBadge(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = formatNumber(value);
    }
}

function updateSmallBox(id, value) {
    const element = document.getElementById(id);
    if (element) {
        animateCounter(element, parseInt(element.textContent) || 0, value);
    }
}

function animateCounter(element, start, end) {
    const duration = 500;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value = Math.floor(start + (end - start) * easeOutQuart(progress));
        element.textContent = formatNumber(value);

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

function easeOutQuart(x) {
    return 1 - Math.pow(1 - x, 4);
}

function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

// ========================================
// LOG EXTRACTION
// ========================================
function extractLogs() {
    const outputDiv = document.getElementById('extractOutput');
    const progressBar = document.getElementById('extractProgress');
    const progressContainer = document.getElementById('progressContainer');

    if (progressContainer) progressContainer.style.display = 'block';
    if (progressBar) progressBar.style.width = '0%';

    appendLog(outputDiv, '🚀 Starting log extraction...', 'info');

    fetch(ForensicApp.config.apiBase + 'extract.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgress(progressBar, 100);
                appendLog(outputDiv, '✅ ' + data.message, 'success');

                // Update stats after extraction
                setTimeout(updateDashboardStats, 1000);

                showToast('Logs extracted successfully!', 'success');
            } else {
                appendLog(outputDiv, '❌ ' + data.error, 'error');
                showToast('Extraction failed: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            appendLog(outputDiv, '❌ Network error: ' + error.message, 'error');
            showToast('Network error during extraction', 'danger');
        });
}

function appendLog(container, message, type = 'info') {
    if (!container) return;

    const timestamp = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    line.className = `log-entry log-${type}`;
    line.innerHTML = `<span class="text-muted">[${timestamp}]</span> ${message}`;
    container.appendChild(line);

    if (ForensicApp.config.autoScroll) {
        container.scrollTop = container.scrollHeight;
    }
}

function updateProgress(bar, percent) {
    if (bar) {
        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);
    }
}

// ========================================
// DATA TABLES
// ========================================
function initDataTable(tableId, options = {}) {
    const defaultOptions = {
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
            '<"row"<"col-sm-12"tr>>' +
            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'csv',
                className: 'btn btn-sm btn-outline-primary',
                text: '<i class="fas fa-file-csv me-1"></i> CSV'
            },
            {
                extend: 'excel',
                className: 'btn btn-sm btn-outline-success',
                text: '<i class="fas fa-file-excel me-1"></i> Excel'
            },
            {
                extend: 'print',
                className: 'btn btn-sm btn-outline-secondary',
                text: '<i class="fas fa-print me-1"></i> Print'
            }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                last: '<i class="fas fa-angle-double-right"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                previous: '<i class="fas fa-angle-left"></i>'
            }
        },
        drawCallback: function () {
            // Add fade-in animation to new rows
            $(this).find('tbody tr').addClass('fade-in');
        }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    if ($.fn.DataTable.isDataTable('#' + tableId)) {
        $('#' + tableId).DataTable().destroy();
    }

    ForensicApp.state.tables[tableId] = $('#' + tableId).DataTable(mergedOptions);

    // Add buttons container
    ForensicApp.state.tables[tableId].buttons().container()
        .appendTo($('#' + tableId + '_wrapper .col-md-6:eq(0)'));

    return ForensicApp.state.tables[tableId];
}

// ========================================
// CHARTS (Chart.js)
// ========================================
function createChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    // Destroy existing chart if any
    if (ForensicApp.state.charts[canvasId]) {
        ForensicApp.state.charts[canvasId].destroy();
    }

    const ctx = canvas.getContext('2d');

    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: '#cdd6f4',
                    font: { family: 'Inter' }
                }
            },
            tooltip: {
                backgroundColor: '#252538',
                titleColor: '#cdd6f4',
                bodyColor: '#a6adc8',
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12
            }
        },
        scales: type === 'doughnut' || type === 'pie' ? {} : {
            x: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#a6adc8' }
            },
            y: {
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#a6adc8' }
            }
        }
    };

    const mergedOptions = mergeDeep(defaultOptions, options);

    ForensicApp.state.charts[canvasId] = new Chart(ctx, {
        type: type,
        data: data,
        options: mergedOptions
    });

    return ForensicApp.state.charts[canvasId];
}

// Color palettes for charts
const chartColors = {
    primary: '#22d3ee',      // Electric Cyan
    success: '#00e676',      // Forensic Green
    warning: '#f59e0b',      // Amber
    danger: '#ef4444',       // Alert Red
    info: '#38bdf8',         // Soft Cyan
    purple: '#8b5cf6',       // Violet Pulse

    primaryBg: 'rgba(34, 211, 238, 0.2)',
    successBg: 'rgba(0, 230, 118, 0.2)',
    warningBg: 'rgba(245, 158, 11, 0.2)',
    dangerBg: 'rgba(239, 68, 68, 0.2)',
    infoBg: 'rgba(56, 189, 248, 0.2)',
    purpleBg: 'rgba(139, 92, 246, 0.2)',

    palette: [
        'rgba(34, 211, 238, 0.8)', // Cyan
        'rgba(139, 92, 246, 0.8)', // Violet
        'rgba(0, 230, 118, 0.8)',  // Green
        'rgba(245, 158, 11, 0.8)', // Amber
        'rgba(239, 68, 68, 0.8)',  // Red
        'rgba(56, 189, 248, 0.8)', // Blue
        'rgba(168, 85, 247, 0.8)', // Purple
        'rgba(236, 72, 153, 0.8)'  // Pink
    ]
};

// ========================================
// LIVE MONITORING
// ========================================
function startLiveMonitor() {
    if (ForensicApp.state.isMonitoring) return;

    const logConsole = document.getElementById('liveLogConsole');
    const startBtn = document.getElementById('startMonitorBtn');
    const stopBtn = document.getElementById('stopMonitorBtn');
    const statusIndicator = document.getElementById('monitorStatus');

    ForensicApp.state.isMonitoring = true;

    if (startBtn) startBtn.disabled = true;
    if (stopBtn) stopBtn.disabled = false;
    if (statusIndicator) {
        statusIndicator.innerHTML = '<i class="fas fa-circle text-success pulse me-1"></i> Live';
    }

    appendLog(logConsole, '🟢 Live monitoring started...', 'success');

    // Use Server-Sent Events for real-time updates
    ForensicApp.state.eventSource = new EventSource(ForensicApp.config.apiBase + 'live-stream.php');

    ForensicApp.state.eventSource.onmessage = function (event) {
        const data = JSON.parse(event.data);
        appendLog(logConsole, data.line, data.level);
    };

    ForensicApp.state.eventSource.onerror = function () {
        appendLog(logConsole, '⚠️ Connection lost, reconnecting...', 'warning');
    };
}

function stopLiveMonitor() {
    if (!ForensicApp.state.isMonitoring) return;

    const logConsole = document.getElementById('liveLogConsole');
    const startBtn = document.getElementById('startMonitorBtn');
    const stopBtn = document.getElementById('stopMonitorBtn');
    const statusIndicator = document.getElementById('monitorStatus');

    ForensicApp.state.isMonitoring = false;

    if (ForensicApp.state.eventSource) {
        ForensicApp.state.eventSource.close();
        ForensicApp.state.eventSource = null;
    }

    if (startBtn) startBtn.disabled = false;
    if (stopBtn) stopBtn.disabled = true;
    if (statusIndicator) {
        statusIndicator.innerHTML = '<i class="fas fa-circle text-secondary me-1"></i> Stopped';
    }

    appendLog(logConsole, '🔴 Live monitoring stopped', 'warning');
}

// ========================================
// TOAST NOTIFICATIONS
// ========================================
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: 'fa-check-circle',
        danger: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas ${icons[type] || icons.info} text-${type} me-2"></i>
                <strong class="me-auto">Notification</strong>
                <small class="text-muted">just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">${message}</div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: duration });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', function () {
        this.remove();
    });
}

// ========================================
// LOADING OVERLAY
// ========================================
function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const text = document.getElementById('loadingText');

    if (overlay) {
        overlay.style.display = 'flex';
        if (text) text.textContent = message;
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// ========================================
// EXPORT FUNCTIONS
// ========================================
function exportFullReport() {
    showLoading('Generating forensic report...');

    fetch(ForensicApp.config.apiBase + 'export-report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();

            if (data.success) {
                showToast('Report generated successfully!', 'success');

                // Open the report in a new tab
                if (data.downloadUrl) {
                    window.open(data.downloadUrl, '_blank');
                }

                // Also show a download link
                if (data.filename) {
                    const message = `Report generated: ${data.filename}`;
                    showToast(message, 'success', 6000);
                }
            } else {
                showToast('Failed to generate report: ' + (data.error || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            showToast('Failed to generate report: ' + error.message, 'danger');
        });
}

function exportTableData(tableId, format = 'csv') {
    const table = ForensicApp.state.tables[tableId];
    if (table) {
        table.button(`.buttons-${format}`).trigger();
    }
}

// ========================================
// FILTERING
// ========================================
function applyFilters() {
    const logType = document.getElementById('filterLogType')?.value || 'all';
    const timeRange = document.getElementById('filterTimeRange')?.value || 'all';
    const severity = document.getElementById('filterSeverity')?.value || 'all';
    const keyword = document.getElementById('filterKeyword')?.value || '';

    showLoading('Applying filters...');

    fetch(ForensicApp.config.apiBase + 'filter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ logType, timeRange, severity, keyword })
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();

            const outputContainer = document.getElementById('filterResults');
            if (outputContainer && data.results) {
                displayFilterResults(data.results);
            }

            showToast(`Found ${data.count} matching records`, 'success');
        })
        .catch(error => {
            hideLoading();
            showToast('Filter error: ' + error.message, 'danger');
        });
}

function displayFilterResults(results) {
    const table = ForensicApp.state.tables['filterTable'];
    if (table) {
        table.clear();
        table.rows.add(results);
        table.draw();
    }
}


// ========================================
// MAP FUNCTIONS (Leaflet)
// ========================================
let locationMap = null;
let locationMarkers = [];

function initLocationMap(containerId = 'locationMap') {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Initialize Leaflet map
    locationMap = L.map(containerId).setView([20.5937, 78.9629], 5); // Default to India

    // Add tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(locationMap);

    return locationMap;
}

function addLocationMarker(lat, lng, info = {}) {
    if (!locationMap) return;

    const marker = L.marker([lat, lng]).addTo(locationMap);

    if (info.popup) {
        marker.bindPopup(`
            <div class="location-popup">
                <strong>${info.title || 'Location'}</strong><br>
                <small>${info.time || ''}</small><br>
                <span>Accuracy: ${info.accuracy || 'Unknown'}</span><br>
                <span>Provider: ${info.provider || 'Unknown'}</span>
            </div>
        `);
    }

    locationMarkers.push(marker);
    return marker;
}

function clearLocationMarkers() {
    locationMarkers.forEach(marker => {
        locationMap.removeLayer(marker);
    });
    locationMarkers = [];
}

function fitMapToMarkers() {
    if (locationMarkers.length > 0) {
        const group = L.featureGroup(locationMarkers);
        locationMap.fitBounds(group.getBounds().pad(0.1));
    }
}

// ========================================
// UTILITY FUNCTIONS
// ========================================
function mergeDeep(target, source) {
    const isObject = (obj) => obj && typeof obj === 'object';

    if (!isObject(target) || !isObject(source)) {
        return source;
    }

    Object.keys(source).forEach(key => {
        const targetValue = target[key];
        const sourceValue = source[key];

        if (Array.isArray(targetValue) && Array.isArray(sourceValue)) {
            target[key] = targetValue.concat(sourceValue);
        } else if (isObject(targetValue) && isObject(sourceValue)) {
            target[key] = mergeDeep(Object.assign({}, targetValue), sourceValue);
        } else {
            target[key] = sourceValue;
        }
    });

    return target;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(timeString) {
    return timeString;
}

function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    }).catch(() => {
        showToast('Failed to copy', 'danger');
    });
}

// ========================================
// KEYBOARD SHORTCUTS
// ========================================
document.addEventListener('keydown', function (e) {
    // Ctrl+Shift+E: Extract logs
    if (e.ctrlKey && e.shiftKey && e.key === 'E') {
        e.preventDefault();
        extractLogs();
    }


    // Escape: Close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) instance.hide();
        });
    }
});

// ========================================
// EXPORT GLOBALS
// ========================================
window.ForensicApp = ForensicApp;
window.extractLogs = extractLogs;
window.startLiveMonitor = startLiveMonitor;
window.stopLiveMonitor = stopLiveMonitor;
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.initDataTable = initDataTable;
window.createChart = createChart;
window.chartColors = chartColors;
window.applyFilters = applyFilters;

window.exportFullReport = exportFullReport;
window.saveSettings = saveSettings;
window.initLocationMap = initLocationMap;
window.addLocationMarker = addLocationMarker;
window.clearLocationMarkers = clearLocationMarkers;
window.fitMapToMarkers = fitMapToMarkers;
window.fitMapToMarkers = fitMapToMarkers;

// ========================================
// UI FIXES (Sidebar)
// ========================================
document.addEventListener('DOMContentLoaded', function () {
    // Manually handle sidebar toggle if AdminLTE fails
    const toggleBtn = document.querySelector('[data-lte-toggle="sidebar"]');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const body = document.body;
            if (window.innerWidth >= 992) {
                // Desktop: Toggle collapse
                body.classList.toggle('sidebar-collapse');
            } else {
                // Mobile: Toggle open
                body.classList.toggle('sidebar-open');
            }
        });
    }
});
