<?php
/**
 * App Intelligence Dashboard - Forensic App Usage Profiling
 * 
 * FORENSIC UI RULES:
 * - All metrics labeled as "Computed from timeline"
 * - Sessions shown with ongoing status if not terminated
 * - Durations ONLY when both start and end exist
 * - No inference or renaming of app identifiers
 */

require_once '../includes/header.php';
?>

<style>
.intelligence-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.computed-notice {
    background: #1e3a8a;
    border: 1px solid #3b82f6;
    border-radius: 4px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #93c5fd;
}

.app-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.app-card {
    background: #1e1e1e;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.app-card:hover {
    border-color: #4CAF50;
    transform: translateY(-2px);
}

.app-header {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.app-icon {
    width: 40px;
    height: 40px;
    background: #4CAF50;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 20px;
}

.app-name {
    flex: 1;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
}

.app-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    font-size: 13px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
}

.stat-label {
    color: #a0a0a0;
}

.stat-value {
    color: #fff;
    font-weight: 600;
}

.session-panel {
    display: none;
    background: #0a0a0a;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.session-panel.visible {
    display: block;
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.session-table {
    width: 100%;
    border-collapse: collapse;
}

.session-table th {
    background: #1e1e1e;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #a0a0a0;
}

.session-table td {
    padding: 12px;
    border-bottom: 1px solid #333;
    font-size: 13px;
}

.session-table tr:hover {
    background: #1a1a1a;
}

.status-ongoing {
    color: #fbbf24;
    font-weight: 600;
}

.status-completed {
    color: #4ade80;
}

.duration-cell {
    font-family: 'Courier New', monospace;
}

.jump-to-timeline {
    color: #60a5fa;
    cursor: pointer;
    text-decoration: underline;
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #a0a0a0;
}
</style>

<div class="content-wrapper">
    <div class="intelligence-container">
        <h1><i class="fas fa-brain"></i> App Intelligence & Usage Profiling</h1>
        <p style="color: #a0a0a0; margin-bottom: 20px;">
            App behavior analysis derived from timeline events
        </p>

        <!-- COMPUTED METRICS NOTICE (MANDATORY) -->
        <div class="computed-notice">
            <strong><i class="fas fa-info-circle"></i> Computed Metrics:</strong>
            All statistics are computed from observed ActivityManager foreground/background events only.
            No inference or estimation is applied.
        </div>

        <!-- Controls -->
        <div style="margin-bottom: 20px;">
            <button id="refreshAppsBtn" class="btn btn-primary">
                <i class="fas fa-sync"></i> Refresh Apps
            </button>
        </div>

        <!-- App Grid -->
        <div id="appGrid" class="app-grid"></div>

        <!-- Session Detail Panel -->
        <div id="sessionPanel" class="session-panel">
            <div class="session-header">
                <h3 id="sessionAppName"></h3>
                <button id="closeSessionBtn" class="btn btn-sm btn-secondary">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>

            <div style="margin-bottom: 20px; font-size: 14px; color: #a0a0a0;">
                <strong>Package:</strong> <code id="sessionPackage" style="color: #60a5fa;"></code>
            </div>

            <h4 style="margin-bottom: 12px;">Sessions</h4>
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Start Time (Local)</th>
                        <th>End Time (Local)</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sessionTableBody"></tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="empty-state">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
            <p>No app usage data available</p>
            <p style="font-size: 14px;">Extract timeline first to analyze app behavior</p>
        </div>
    </div>
</div>

<script>
class AppIntelligenceViewer {
    constructor() {
        this.appGrid = document.getElementById('appGrid');
        this.emptyState = document.getElementById('emptyState');
        this.sessionPanel = document.getElementById('sessionPanel');
        
        this.allApps = [];
        this.currentPackage = null;
        this.iconMap = null;
        
        // Load icon map
        this.loadIconMap();
    }
    
    async loadIconMap() {
        try {
            const response = await fetch('../assets/app-icons/app_icon_map.json');
            this.iconMap = await response.json();
        } catch (error) {
            console.warn('App icon map not loaded, using fallback icons');
            this.iconMap =  {mappings: {}};
        }
    }
    
    async loadApps() {
        try {
            const response = await fetch('../api/app-intelligence.php?action=get_app_stats');
            const data = await response.json();
            
            if (!data.success) {
                this.showError(data.error || 'Failed to load apps');
                return;
            }
            
            this.allApps = data.apps || [];
            this.renderApps();
            
        } catch (error) {
            this.showError('Error loading apps: ' + error.message);
        }
    }
    
    renderApps() {
        this.appGrid.innerHTML = '';
        
        if (this.allApps.length === 0) {
            this.emptyState.style.display = 'block';
            return;
        }
        
        this.emptyState.style.display = 'none';
        
        this.allApps.forEach(app => {
            this.appGrid.appendChild(this.createAppCard(app));
        });
    }
    
    createAppCard(app) {
        const card = document.createElement('div');
        card.className = 'app-card';
        
        // Header
        const header = document.createElement('div');
        header.className = 'app-header';
        
        const icon = document.createElement('div');
        icon.className = 'app-icon';
        
        // FORENSIC: Load icon from map if available, fallback to letter
        const iconData = this.iconMap?.mappings?.[app.package_name];
        
        if (iconData && iconData.icon) {
            // Use app icon image
            const img = document.createElement('img');
            img.src = `../assets/app-icons/known/${iconData.icon}`;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.borderRadius = '8px';
            img.title = `${iconData.name}\n${app.package_name}`; // Package name tooltip
            img.onerror = () => {
                // Fallback to letter if image fails
                icon.textContent = app.app_name.charAt(0).toUpperCase();
            };
            icon.appendChild(img);
            
            // Use mapped color
            if (iconData.color) {
                icon.style.background = iconData.color;
            }
        } else {
            // Fallback: letter icon
            icon.textContent = app.app_name.charAt(0).toUpperCase();
            icon.title = app.package_name; // Package name tooltip
        }
        
        const name = document.createElement('div');
        name.className = 'app-name';
        name.textContent = app.app_name;
        
        header.appendChild(icon);
        header.appendChild(name);
        
        // Stats
        const stats = document.createElement('div');
        stats.className = 'app-stats';
        
        this.addStat(stats, 'Total Sessions', app.total_sessions);
        this.addStat(stats, 'Completed', app.completed_sessions);
        
        // FORENSIC: Only show duration if sessions were completed
        if (app.completed_sessions > 0 && app.average_duration_seconds !== null) {
            const avgDuration = this.formatDuration(app.average_duration_seconds);
            this.addStat(stats, 'Avg Duration', avgDuration);
        } else {
            this.addStat(stats, 'Avg Duration', 'N/A');
        }
        
        // Ongoing sessions
        if (app.ongoing_sessions > 0) {
            const ongoingRow = document.createElement('div');
            ongoingRow.style.gridColumn = '1 / -1';
            ongoingRow.style.color = '#fbbf24';
            ongoingRow.style.fontSize = '12px';
            ongoingRow.style.marginTop = '8px';
            ongoingRow.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${app.ongoing_sessions} session(s) ongoing (not terminated)`;
            stats.appendChild(ongoingRow);
        }
        
        card.appendChild(header);
        card.appendChild(stats);
        
        // Click to view sessions
        card.addEventListener('click', () => {
            this.viewSessions(app.package_name, app.app_name);
        });
        
        return card;
    }
    
    addStat(container, label, value) {
        const item = document.createElement('div');
        item.className = 'stat-item';
        
        const labelEl = document.createElement('span');
        labelEl.className = 'stat-label';
        labelEl.textContent = label + ':';
        
        const valueEl = document.createElement('span');
        valueEl.className = 'stat-value';
        valueEl.textContent = value;
        
        item.appendChild(labelEl);
        item.appendChild(valueEl);
        container.appendChild(item);
    }
    
    async viewSessions(packageName, appName) {
        this.currentPackage = packageName;
        
        try {
            const response = await fetch(`../api/app-intelligence.php?action=get_app_sessions&package=${encodeURIComponent(packageName)}`);
            const data = await response.json();
            
            if (!data.success) {
                alert('Error loading sessions: ' + (data.error || 'Unknown error'));
                return;
            }
            
            document.getElementById('sessionAppName').textContent = appName;
            document.getElementById('sessionPackage').textContent = packageName;
            
            this.renderSessions(data.sessions || []);
            this.sessionPanel.classList.add('visible');
            
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    renderSessions(sessions) {
        const tbody = document.getElementById('sessionTableBody');
        tbody.innerHTML = '';
        
        sessions.forEach(session => {
            const row = tbody.insertRow();
            
            // Start time
            const startCell = row.insertCell();
            startCell.textContent = session.start_time_local || 'N/A';
            
            // End time
            const endCell = row.insertCell();
            if (session.status === 'ongoing') {
                endCell.innerHTML = '<span class="status-ongoing">Ongoing</span>';
            } else {
                endCell.textContent = session.end_time_local || 'N/A';
            }
            
            // Duration (FORENSIC: only if completed)
            const durationCell = row.insertCell();
            durationCell.className = 'duration-cell';
            if (session.status === 'completed' && session.duration_seconds !== null) {
                durationCell.textContent = this.formatDuration(session.duration_seconds);
            } else {
                durationCell.innerHTML = '<span class="status-ongoing">N/A (ongoing)</span>';
            }
            
            // Status
            const statusCell = row.insertCell();
            if (session.status === 'ongoing') {
                statusCell.innerHTML = '<span class="status-ongoing">Ongoing</span>';
                if (session.status_note) {
                    statusCell.title = session.status_note;
                }
            } else {
                statusCell.innerHTML = '<span class="status-completed">Completed</span>';
            }
            
            // Actions
            const actionsCell = row.insertCell();
            actionsCell.innerHTML = '<span class="jump-to-timeline">View in Timeline</span>';
            actionsCell.addEventListener('click', () => {
                window.location.href = 'timeline.php';
            });
        });
    }
    
    formatDuration(seconds) {
        if (seconds < 60) {
            return `${seconds}s`;
        } else if (seconds < 3600) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}m ${secs}s`;
        } else {
            const hours = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            return `${hours}h ${mins}m`;
        }
    }
    
    showError(message) {
        this.emptyState.innerHTML = `
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px; display: block; color: #dc2626;"></i>
            <p style="color: #dc2626;">${message}</p>
        `;
        this.emptyState.style.display = 'block';
    }
}

// Initialize
let appViewer;

document.addEventListener('DOMContentLoaded', function() {
    appViewer = new AppIntelligenceViewer();
    appViewer.loadApps();
    
    document.getElementById('refreshAppsBtn').addEventListener('click', () => {
        appViewer.loadApps();
    });
    
    document.getElementById('closeSessionBtn').addEventListener('click', () => {
        document.getElementById('sessionPanel').classList.remove('visible');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
