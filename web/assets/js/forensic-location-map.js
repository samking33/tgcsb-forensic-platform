/**
 * Forensic Location Map - Visualization Layer
 * VISUALIZATION ONLY - Does not modify acquisition logic
 */

class ForensicLocationMap {
    constructor(mapElementId) {
        this.mapElement = document.getElementById(mapElementId);
        this.map = null;
        this.markers = [];
        this.paths = [];
        this.allLocations = [];
        this.filteredLocations = [];

        // Source type colors (forensically distinct)
        this.sourceColors = {
            'GPS': '#00AA00',        // Green - high reliability
            'Network': '#0055FF',    // Blue - medium reliability
            'Fused': '#00CCCC',      // Cyan - high reliability
            'WiFi': '#9933FF',       // Purple - inferred
            'Cell': '#FF8800',       // Orange - approximate
            'App': '#FFCC00'         // Yellow - opportunistic
        };

        // Confidence sizes (visual hierarchy)
        this.confidenceSizes = {
            'High': 10,
            'Medium': 7,
            'Low': 4
        };

        this.init();
    }

    init() {
        // Initialize Leaflet map
        this.map = L.map(this.mapElement).setView([0, 0], 2);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
    }

    /**
     * Load locations from API
     */
    async loadLocations(filters = {}) {
        try {
            const params = new URLSearchParams(filters);
            const response = await fetch(`../api/location-acquisition.php?action=get_locations&${params}`);
            const data = await response.json();

            if (!data.success) {
                this.showError(data.error || 'Failed to load location data');
                return;
            }

            this.allLocations = data.locations || [];
            this.filteredLocations = this.allLocations;

            this.displayStats(data);
            this.renderLocations();

        } catch (error) {
            this.showError('Error loading locations: ' + error.message);
        }
    }

    /**
     * Display statistics
     */
    displayStats(data) {
        const statsEl = document.getElementById('locationStats');
        if (!statsEl) return;

        const sourceBreakdown = Object.entries(data.source_breakdown || {})
            .map(([source, count]) => `${source}: ${count}`)
            .join(' | ');

        const confidenceBreakdown = Object.entries(data.confidence_breakdown || {})
            .map(([level, count]) => `${level}: ${count}`)
            .join(' | ');

        statsEl.innerHTML = `
            <div class="stats-grid">
                <div><strong>Total Points:</strong> ${data.total_points}</div>
                <div><strong>Extracted:</strong> ${data.extracted_at}</div>
                <div><strong>Time Range:</strong> ${data.time_range.earliest || 'N/A'} → ${data.time_range.latest || 'N/A'}</div>
                <div><strong>Sources:</strong> ${sourceBreakdown}</div>
                <div><strong>Confidence:</strong> ${confidenceBreakdown}</div>
                ${data.external_lookup_was_enabled ? '<div class="text-warning"><strong>⚠ External Inference Enabled</strong></div>' : ''}
            </div>
        `;
    }

    /**
     * Render all location points on map
     */
    renderLocations() {
        // Clear existing markers and paths
        this.clearMap();

        if (this.filteredLocations.length === 0) {
            this.showEmpty();
            return;
        }

        // Add markers
        this.filteredLocations.forEach(loc => {
            this.addMarker(loc);
        });

        // Draw paths (conservative rules)
        this.drawPaths();

        // Fit map to bounds
        if (this.markers.length > 0) {
            const group = L.featureGroup(this.markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    /**
     * Add marker for a single location point
     */
    addMarker(loc) {
        const color = this.sourceColors[loc.source_type] || '#666666';
        const size = this.confidenceSizes[loc.confidence_level] || 5;

        // Visual distinction for inferred data
        const fillOpacity = loc.is_inferred ? 0.4 : 0.8;
        const weight = loc.is_inferred ? 2 : 1;
        const dashArray = loc.is_inferred ? '5, 5' : null;

        const marker = L.circleMarker([loc.latitude, loc.longitude], {
            radius: size,
            fillColor: color,
            color: color,
            weight: weight,
            opacity: 1,
            fillOpacity: fillOpacity,
            dashArray: dashArray
        }).addTo(this.map);

        // Popup with forensic metadata
        const popupContent = this.createPopupContent(loc);
        marker.bindPopup(popupContent);

        this.markers.push(marker);
    }

    /**
     * Create popup content with full forensic metadata
     */
    createPopupContent(loc) {
        let html = `
            <div class="forensic-location-popup">
                <h6>${loc.source_type} Location</h6>
                <table class="table table-sm">
                    <tr><td><strong>Coordinates:</strong></td><td>${loc.latitude}, ${loc.longitude}</td></tr>
                    <tr><td><strong>Timestamp:</strong></td><td>${loc.timestamp}</td></tr>
                    <tr><td><strong>Confidence:</strong></td><td><span class="badge bg-${this.getConfidenceBadgeClass(loc.confidence_level)}">${loc.confidence_level}</span> (${loc.confidence_score}/100)</td></tr>
                    <tr><td><strong>Precision:</strong></td><td>${loc.precision_meters ? '±' + loc.precision_meters + 'm' : 'Unknown'}</td></tr>
                    <tr><td><strong>Retention:</strong></td><td>${loc.retention_estimate}</td></tr>
                    <tr><td><strong>Origin:</strong></td><td>${loc.origin}</td></tr>
                    <tr><td><strong>Provider:</strong></td><td>${loc.provider || 'N/A'}</td></tr>
                    <tr><td><strong>Reference:</strong></td><td>${loc.raw_reference}</td></tr>
        `;

        // CRITICAL: Show inference metadata if present
        if (loc.is_inferred) {
            html += `
                    <tr class="table-warning"><td><strong>⚠ INFERRED:</strong></td><td>${loc.inference_method}</td></tr>
                    <tr class="table-warning"><td><strong>Risk:</strong></td><td>${loc.inference_risk}</td></tr>
            `;
        }

        // Additional metadata
        if (loc.metadata && Object.keys(loc.metadata).length > 0) {
            html += `<tr><td colspan="2"><hr><strong>Metadata:</strong></td></tr>`;
            for (const [key, value] of Object.entries(loc.metadata)) {
                html += `<tr><td>${key}:</td><td>${value}</td></tr>`;
            }
        }

        html += `
                </table>
            </div>
        `;

        return html;
    }

    /**
     * Draw movement paths (CONSERVATIVE RULES)
     */
    drawPaths() {
        // Sort by timestamp
        const sorted = [...this.filteredLocations].sort((a, b) => a.timestamp_unix - b.timestamp_unix);

        if (sorted.length < 2) return;

        let pathCoords = [];
        let lastPoint = null;

        for (const loc of sorted) {
            if (lastPoint) {
                const timeDiff = (loc.timestamp_unix - lastPoint.timestamp_unix) / 60; // minutes

                // RULE: Only draw if <5 minutes apart AND confidence >= Medium
                const canDraw = timeDiff < 5 &&
                    ['High', 'Medium'].includes(loc.confidence_level) &&
                    ['High', 'Medium'].includes(lastPoint.confidence_level) &&
                    !loc.is_inferred && !lastPoint.is_inferred; // NEVER across inferred

                if (canDraw) {
                    if (pathCoords.length === 0) {
                        pathCoords.push([lastPoint.latitude, lastPoint.longitude]);
                    }
                    pathCoords.push([loc.latitude, loc.longitude]);
                } else {
                    // Break in path - render current segment
                    if (pathCoords.length >= 2) {
                        this.addPathSegment(pathCoords);
                    }
                    pathCoords = [];
                }
            }
            lastPoint = loc;
        }

        // Render final segment
        if (pathCoords.length >= 2) {
            this.addPathSegment(pathCoords);
        }
    }

    /**
     * Add a path segment to the map
     */
    addPathSegment(coords) {
        const path = L.polyline(coords, {
            color: '#3388ff',
            weight: 2,
            opacity: 0.5,
            dashArray: '5, 10'
        }).addTo(this.map);

        this.paths.push(path);
    }

    /**
     * Apply client-side filters
     */
    applyFilters(filters) {
        this.filteredLocations = this.allLocations.filter(loc => {
            // Time filter
            if (filters.startTime && loc.timestamp_unix < filters.startTime) return false;
            if (filters.endTime && loc.timestamp_unix > filters.endTime) return false;

            // Source filter
            if (filters.sources && filters.sources.length > 0) {
                if (!filters.sources.includes(loc.source_type)) return false;
            }

            // Confidence filter
            if (filters.minConfidence) {
                const levels = ['Low', 'Medium', 'High'];
                const locIndex = levels.indexOf(loc.confidence_level);
                const minIndex = levels.indexOf(filters.minConfidence);
                if (locIndex < minIndex) return false;
            }

            return true;
        });

        this.renderLocations();
    }

    /**
     * Clear all markers and paths
     */
    clearMap() {
        this.markers.forEach(m => m.remove());
        this.paths.forEach(p => p.remove());
        this.markers = [];
        this.paths = [];
    }

    /**
     * Show error message
     */
    showError(message) {
        const container = document.getElementById('mapError');
        if (container) {
            container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
            container.style.display = 'block';
        }
    }

    /**
     * Show empty state
     */
    showEmpty() {
        const container = document.getElementById('mapEmpty');
        if (container) {
            container.innerHTML = `<div class="alert alert-info">No points match selected criteria</div>`;
            container.style.display = 'block';
        }
    }

    /**
     * Get Bootstrap badge class for confidence level
     */
    getConfidenceBadgeClass(level) {
        return {
            'High': 'success',
            'Medium': 'warning',
            'Low': 'secondary'
        }[level] || 'secondary';
    }
}
