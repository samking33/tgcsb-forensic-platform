<?php
/**
 * Device Behavior Timeline - Forensic Event Viewer
 * 
 * FORENSIC UI RULES:
 * - Each entry = one logged event
 * - UTC timestamp always visible
 * - SNAPSHOT events labeled
 * - Raw log references clickable
 * - No inference or smoothing
 */

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
    .timeline-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .retention-notice {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .timeline-filters {
        background: #1e1e1e;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .filter-group {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-label {
        font-weight: 600;
        color: #a0a0a0;
        margin-right: 10px;
    }

    .category-filters label {
        margin-right: 15px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 6px;
        transition: all 0.08s ease;
        user-select: none;
    }

    .category-filters label:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .category-filters label:active {
        background-color: rgba(255, 255, 255, 0.1);
        transform: scale(0.98);
    }

    .category-filters input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin: 0;
        accent-color: #00d4ff;
    }

    .timeline-events {
        position: relative;
        padding-left: 40px;
        max-height: 800px;
        overflow-x: auto;
        overflow-y: auto;
    }

    /* Custom Scrollbar Styling */
    .timeline-events::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }

    .timeline-events::-webkit-scrollbar-track {
        background: #1e1e1e;
        border-radius: 6px;
    }

    .timeline-events::-webkit-scrollbar-thumb {
        background: #444;
        border-radius: 6px;
        border: 2px solid #1e1e1e;
    }

    .timeline-events::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    .timeline-events::-webkit-scrollbar-corner {
        background: #1e1e1e;
    }

    .timeline-line {
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #444;
    }

    .timeline-event {
        position: relative;
        margin-bottom: 20px;
        background: #1e1e1e;
        border-radius: 8px;
        padding: 16px;
        border-left: 4px solid #666;
        cursor: pointer;
        transition: all 0.2s;
    }

    .timeline-event:hover {
        background: #252525;
        transform: translateX(4px);
    }

    .timeline-event.category-DEVICE {
        border-left-color: #2196F3;
    }

    .timeline-event.category-APP {
        border-left-color: #4CAF50;
    }

    .timeline-event.category-NETWORK {
        border-left-color: #9C27B0;
    }

    .timeline-event.category-POWER {
        border-left-color: #FF9800;
    }

    .timeline-event.category-NOTIFICATION {
        border-left-color: #03A9F4;
    }

    .timeline-event.category-FINANCIAL {
        border-left-color: #009688;
    }

    .timeline-event.category-SECURITY {
        border-left-color: #F44336;
        background: #2a1515;
        /* Slight reddish tint for security alerts */
    }

    .timeline-event.category-GHOST {
        border-left-color: #9E9E9E;
        background: url('data:image/svg+xml;utf8,<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><g fill="%232e2e2e" fill-rule="evenodd"><path d="M0 40L40 0H20L0 20M40 40V20L20 40"/></g></svg>') 0 0/10px 10px;
        border-left-style: dashed;
        opacity: 0.8;
    }

    .timeline-event.category-VOIP {
        border-left-color: #8e44ad;
    }

    .timeline-event::before {
        content: '';
        position: absolute;
        left: -46px;
        top: 24px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #666;
        border: 3px solid #0a0a0a;
    }

    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .event-type {
        font-size: 16px;
        font-weight: 600;
        color: #fff;
    }

    .event-timestamp {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        color: #a0a0a0;
    }

    .event-meta {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .event-badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-category {
        background: #333;
        color: #aaa;
    }

    .badge-source {
        background: #1a4d2e;
        color: #4ade80;
    }

    .badge-snapshot {
        background: #854d0e;
        color: #fbbf24;
    }

    .badge-confidence {
        background: #1e3a8a;
        color: #93c5fd;
    }

    .event-details {
        display: none;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #333;
    }

    .event-details.visible {
        display: block;
    }

    .detail-row {
        display: flex;
        padding: 6px 0;
        font-size: 13px;
    }

    .detail-label {
        width: 150px;
        color: #a0a0a0;
        font-weight: 600;
    }

    .detail-value {
        flex: 1;
        color: #fff;
        font-family: 'Courier New', monospace;
    }

    .raw-reference {
        color: #60a5fa;
        cursor: pointer;
        text-decoration: underline;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #a0a0a0;
    }

    /* Scroll to Top Button */
    #scrollToTopBtn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 99;
        font-size: 18px;
        border: none;
        outline: none;
        background-color: #007bff;
        color: white;
        cursor: pointer;
        padding: 15px;
        border-radius: 50%;
        display: none;
        /* Hidden by default */
        transition: opacity 0.3s, transform 0.3s;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    /* Optimized Filter Visibility Classes */
    .timeline-container.hide-DEVICE .category-DEVICE,
    .timeline-container.hide-APP .category-APP,
    .timeline-container.hide-NETWORK .category-NETWORK,
    .timeline-container.hide-POWER .category-POWER,
    .timeline-container.hide-NOTIFICATION .category-NOTIFICATION,
    .timeline-container.hide-FINANCIAL .category-FINANCIAL,
    .timeline-container.hide-SECURITY .category-SECURITY,
    .timeline-container.hide-GHOST .category-GHOST,
    .timeline-container.hide-VOIP .category-VOIP {
        display: none !important;
    }

    #scrollToTopBtn:hover {
        background-color: #0056b3;
        transform: translateY(-3px);
    }
</style>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-clock me-2"></i>Device Behavior Timeline</h3>
            <p class="text-muted small mb-0">Forensic reconstruction of device events from logcat and dumpsys sources
            </p>
        </div>
    </div>

    <div class="app-content">
        <div class="timeline-container">

            <!-- RETENTION NOTICE (MANDATORY) -->
            <div class="retention-notice" id="retentionNotice" style="display: none;">
                <strong><i class="fas fa-exclamation-triangle"></i> Retention Limitation:</strong>
                <span id="retentionText"></span>
            </div>

            <!-- Extraction Controls -->
            <div style="margin-bottom: 20px;">
                <button id="extractTimelineBtn" class="btn btn-primary">
                    <i class="fas fa-history"></i> Extract Timeline
                </button>
                <button id="refreshBtn" class="btn btn-secondary" style="margin-left: 10px;">
                    <i class="fas fa-sync"></i> Refresh
                </button>
                <button id="toggleSortBtn" class="btn btn-outline-secondary" style="margin-left: 10px;" title="Toggle sort order">
                    <i class="fas fa-sort-amount-down"></i> <span id="sortLabel">Newest First</span>
                </button>
            </div>

            <!-- Filters -->
            <div class="timeline-filters">
                <div class="filter-group">
                    <span class="filter-label">Categories: 
                        <span style="font-weight: normal; font-size: 0.85em; margin-left: 5px;">
                            <a href="#" id="selectAllCats" style="text-decoration: none;">All</a> / 
                            <a href="#" id="selectNoneCats" style="text-decoration: none;">None</a>
                        </span>
                    </span>
                    <div class="category-filters">
                        <label>
                            <input type="checkbox" class="category-filter" value="DEVICE" checked>
                            <i class="fas fa-mobile-alt" style="color: #2196F3;"></i> Device
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="APP" checked>
                            <i class="fas fa-th" style="color: #4CAF50;"></i> App
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="NETWORK" checked>
                            <i class="fas fa-wifi" style="color: #9C27B0;"></i> Network
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="POWER" checked>
                            <i class="fas fa-battery-three-quarters" style="color: #FF9800;"></i> Power
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="NOTIFICATION" checked>
                            <i class="fas fa-bell" style="color: #03A9F4;"></i> Notification
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="FINANCIAL" checked>
                            <i class="fas fa-money-bill-wave" style="color: #009688;"></i> Financial
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="SECURITY" checked>
                            <i class="fas fa-exclamation-triangle" style="color: #F44336;"></i> Security
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="GHOST" checked>
                            <i class="fas fa-ghost" style="color: #9E9E9E;"></i> Ghost
                        </label>
                        <label>
                            <input type="checkbox" class="category-filter" value="VOIP" checked>
                            <i class="fas fa-phone" style="color: #8e44ad;"></i> VoIP
                        </label>
                    </div>
                </div>

                <div class="filter-group" style="margin-top: 15px;">
                    <span class="filter-label">Time Range:</span>
                    <input type="datetime-local" id="timeStart" class="form-control" style="width: 200px;">
                    <span style="color: #a0a0a0;">to</span>
                    <input type="datetime-local" id="timeEnd" class="form-control" style="width: 200px;">
                    <button id="applyFiltersBtn" class="btn btn-sm btn-success">Apply</button>
                    <button id="resetFiltersBtn" class="btn btn-sm btn-secondary">Reset</button>
                </div>
            </div>

            <!-- Stats Summary -->
            <div id="statsRow" style="display: none; margin-bottom: 20px;">
                <div style="background: #1e1e1e; border-radius: 8px; padding: 16px;">
                    <strong>Total Events:</strong> <span id="totalEvents">0</span> |
                    <strong>Device:</strong> <span id="deviceCount">0</span> |
                    <strong>App:</strong> <span id="appCount">0</span> |
                    <strong>Network:</strong> <span id="networkCount">0</span> |
                    <strong>Power:</strong> <span id="powerCount">0</span> |
                    <strong>Notif:</strong> <span id="notifCount">0</span> |
                    <strong>Finance:</strong> <span id="financeCount">0</span> |
                    <strong>Security:</strong> <span id="securityCount">0</span> |
                    <strong>Ghost:</strong> <span id="ghostCount">0</span> |
                    <strong>VoIP:</strong> <span id="voipCount">0</span>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline-events">
                <div class="timeline-line"></div>
                <div id="timelineContainer"></div>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No timeline data available</p>
                <p style="font-size: 14px;">Click "Extract Timeline" to begin</p>
            </div>
        </div>
    </div>
</main>

<!-- Scroll To Top Button -->
<button id="scrollToTopBtn" title="Go to top">
    <i class="fas fa-arrow-up"></i>
</button>

<script src="../assets/js/timeline-viewer.js"></script>

<script>
    // Scroll Button Logic
    const scrollBtn = document.getElementById("scrollToTopBtn");

    window.onscroll = function () {
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            scrollBtn.style.display = "block";
        } else {
            scrollBtn.style.display = "none";
        }
    };

    scrollBtn.addEventListener("click", function () {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

    // Initialize timeline viewer
    let timelineViewer;

    document.addEventListener('DOMContentLoaded', function () {
        timelineViewer = new TimelineViewer('timelineContainer');

        // Auto-load if data exists
        timelineViewer.loadTimeline();

        // Extract timeline button
        document.getElementById('extractTimelineBtn').addEventListener('click', function () {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting...';

            fetch('../api/timeline-acquisition.php?action=extract')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Timeline extraction complete!\n\nTotal events: ${data.total_events}\n\nCheck console for audit log.`);
                        console.log('Extraction Audit Log:', data.audit_log);
                        timelineViewer.loadTimeline();
                    } else {
                        // Check if instructions are provided
                        if (data.instructions) {
                            const msg = data.error + '\n\n' + data.instructions.join('\n');
                            alert(msg);
                            console.error('Technical error:', data.technical_error);
                        } else {
                            alert('Error: ' + (data.error || 'Unknown error'));
                            if (data.trace) console.error(data.trace);
                        }
                    }
                })
                .catch(error => {
                    alert('Error extracting timeline: ' + error.message);
                    console.error(error);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-history"></i> Extract Timeline';
                });
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', function () {
            timelineViewer.loadTimeline();
        });

        // Sort toggle button
        document.getElementById('toggleSortBtn').addEventListener('click', function () {
            const newMode = timelineViewer.toggleSortDirection();
            const icon = this.querySelector('i');
            const label = document.getElementById('sortLabel');
            
            if (newMode === 'Newest First') {
                icon.className = 'fas fa-sort-amount-down';
                label.textContent = 'Newest First';
            } else {
                icon.className = 'fas fa-sort-amount-up';
                label.textContent = 'Oldest First';
            }
        });

        // Filter listeners - instant response on click
        document.querySelectorAll('.category-filter').forEach(cb => {
            cb.addEventListener('click', function () {
                // Use setTimeout to allow checkbox state to update first
                setTimeout(() => timelineViewer.applyFilters(), 0);
            });
        });

        document.getElementById('applyFiltersBtn').addEventListener('click', function () {
            timelineViewer.applyFilters();
        });

        document.getElementById('resetFiltersBtn').addEventListener('click', function () {
            document.querySelectorAll('.category-filter').forEach(cb => cb.checked = true);
            document.getElementById('timeStart').value = '';
            document.getElementById('timeEnd').value = '';
            timelineViewer.applyFilters();
        });

        // Filter Helpers
        document.getElementById('selectAllCats').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.category-filter').forEach(cb => cb.checked = true);
            timelineViewer.applyFilters();
        });

        document.getElementById('selectNoneCats').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.category-filter').forEach(cb => cb.checked = false);
            timelineViewer.applyFilters();
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>