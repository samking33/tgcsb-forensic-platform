/**
 * TimelineViewer - Forensic Event Timeline UI
 * 
 * FORENSIC UI RULES:
 * - Each DOM entry = one logged event
 * - No visual compression of time gaps
 * - UTC timestamp always shown
 * - Raw log references must be accessible
 * - SNAPSHOT events clearly labeled
 */

class TimelineViewer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.emptyState = document.getElementById('emptyState');
        this.statsRow = document.getElementById('statsRow');
        this.retentionNotice = document.getElementById('retentionNotice');
        this.retentionText = document.getElementById('retentionText');

        this.allEvents = [];
        this.filteredEvents = [];
        this.sortDescending = true; // Newest first by default
    }

    /**
     * Load timeline from API
     */
    async loadTimeline() {
        try {
            const response = await fetch('../api/timeline-acquisition.php?action=get_events');
            const data = await response.json();

            if (!data.success) {
                this.showError(data.error || 'Failed to load timeline');
                return;
            }

            this.allEvents = data.events || [];
            // Sort by timestamp based on current sort direction
            this.sortEvents();
            this.filteredEvents = this.allEvents;

            // Show retention notice (MANDATORY)
            if (data.retention_notice) {
                this.retentionText.textContent = data.retention_notice;
                this.retentionNotice.style.display = 'block';
            }

            this.displayStats(data.category_breakdown || {});
            this.renderTimeline();

        } catch (error) {
            this.showError('Error loading timeline: ' + error.message);
        }
    }

    /**
     * Display statistics
     */
    displayStats(breakdown) {
        if (this.filteredEvents.length === 0) {
            this.statsRow.style.display = 'none';
            return;
        }

        document.getElementById('totalEvents').textContent = this.filteredEvents.length;
        document.getElementById('deviceCount').textContent = breakdown.DEVICE || 0;
        document.getElementById('appCount').textContent = breakdown.APP || 0;
        document.getElementById('networkCount').textContent = breakdown.NETWORK || 0;
        document.getElementById('powerCount').textContent = breakdown.POWER || 0;
        document.getElementById('notifCount').textContent = breakdown.NOTIFICATION || 0;
        document.getElementById('financeCount').textContent = breakdown.FINANCIAL || 0;
        document.getElementById('securityCount').textContent = breakdown.SECURITY || 0;
        document.getElementById('ghostCount').textContent = breakdown.GHOST || 0;
        document.getElementById('voipCount').textContent = breakdown.VOIP || 0;

        this.statsRow.style.display = 'block';
    }

    /**
     * Render timeline events
     * CRITICAL: Each event = one DOM entry (no compression)
     */
    renderTimeline() {
        this.container.innerHTML = '';

        if (this.filteredEvents.length === 0) {
            this.emptyState.style.display = 'block';
            this.statsRow.style.display = 'none';
            return;
        }

        this.emptyState.style.display = 'none';

        // Render each event (FORENSIC: no grouping or compression)
        this.filteredEvents.forEach(event => {
            this.container.appendChild(this.createEventElement(event));
        });
    }

    /**
     * Helper: Format Timestamp for Display
     * Converts ISO8601 to "Jan 01, 2023 14:30:00"
     */
    formatDisplayTime(isoString) {
        if (!isoString) return '';
        try {
            // Check for potential epoch if not string
            const date = new Date(isoString);
            if (isNaN(date.getTime())) return isoString;

            return date.toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false // 24-hour format preference for forensics
            });
        } catch (e) {
            return isoString;
        }
    }

    /**
     * Create event DOM element
     */
    createEventElement(event) {
        const div = document.createElement('div');
        div.className = `timeline-event category-${event.category}`;
        div.dataset.eventId = event.id;

        // Header
        const header = document.createElement('div');
        header.className = 'event-header';

        const eventType = document.createElement('div');
        eventType.className = 'event-type';
        // Add Icon based on category
        const iconClass = this.getCategoryIcon(event.category);

        // Custom Header for VoIP
        let headerText = this.formatEventType(event.event_type);
        if (event.category === 'VOIP') {
            headerText = 'VoIP Call';
        }

        eventType.innerHTML = `<i class="${iconClass}" style="margin-right: 8px; opacity: 0.8;"></i>${headerText}`;

        // UTC timestamp (ALWAYS VISIBLE - MANDATORY)
        const timestamp = document.createElement('div');
        timestamp.className = 'event-timestamp';
        // UPDATED: Use formatDisplayTime
        timestamp.textContent = this.formatDisplayTime(event.timestamp_utc);
        timestamp.title = `Raw: ${event.timestamp_utc}`;

        header.appendChild(eventType);
        header.appendChild(timestamp);

        // Metadata badges
        const meta = document.createElement('div');
        meta.className = 'event-meta';

        // Category badge
        const categoryBadge = document.createElement('span');
        categoryBadge.className = 'event-badge badge-category';
        categoryBadge.textContent = event.category;
        meta.appendChild(categoryBadge);

        // Source badge
        const sourceBadge = document.createElement('span');
        sourceBadge.className = 'event-badge badge-source';
        sourceBadge.textContent = event.source;

        // Special styling for VoIP Source
        if (event.source === 'VOIP') {
            sourceBadge.style.backgroundColor = '#8e44ad';
            sourceBadge.style.color = 'white';
            sourceBadge.innerHTML = '<i class="fas fa-headset" style="margin-right:4px;"></i>VoIP Call';
        }

        meta.appendChild(sourceBadge);

        // SNAPSHOT label (MANDATORY for dumpsys events)
        if (event.event_nature === 'SNAPSHOT') {
            const snapshotBadge = document.createElement('span');
            snapshotBadge.className = 'event-badge badge-snapshot';
            snapshotBadge.textContent = '⚠ SNAPSHOT';
            snapshotBadge.title = 'State observed at acquisition time';
            meta.appendChild(snapshotBadge);
        }

        // Confidence badge
        const confidenceBadge = document.createElement('span');
        confidenceBadge.className = 'event-badge badge-confidence';
        confidenceBadge.textContent = `Confidence: ${event.confidence}`;
        meta.appendChild(confidenceBadge);

        // Details panel (collapsed by default)
        const details = this.createDetailsPanel(event);

        div.appendChild(header);
        div.appendChild(meta);
        div.appendChild(details);

        // Click to expand details
        div.addEventListener('click', () => {
            details.classList.toggle('visible');
        });

        return div;
    }

    /**
     * Create event details panel
     */
    createDetailsPanel(event) {
        const details = document.createElement('div');
        details.className = 'event-details';

        // --- VERIFICATION BANNER ---
        if (event.metadata && event.metadata.verification) {
            const status = event.metadata.verification; // 'verified', 'fake', 'unverified'
            const proof = event.metadata.verification_proof;

            const banner = document.createElement('div');
            banner.style.padding = '12px';
            banner.style.marginBottom = '12px';
            banner.style.borderRadius = '6px';
            banner.style.fontWeight = 'bold';
            banner.style.display = 'flex';
            banner.style.alignItems = 'center';
            banner.style.justifyContent = 'space-between';

            if (status === 'verified') {
                banner.style.background = 'rgba(46, 204, 113, 0.2)';
                banner.style.border = '1px solid #2ecc71';
                banner.style.color = '#2ecc71';
                banner.innerHTML = `
                    <div><i class="fas fa-check-circle me-2"></i> VERIFIED LEGITIMATE</div>
                    <div style="font-size: 0.8em; font-weight: normal; opacity: 0.8;">Confirmed by Source Analysis</div>
                `;
            } else if (status === 'fake') {
                banner.style.background = 'rgba(231, 76, 60, 0.2)';
                banner.style.border = '1px solid #e74c3c';
                banner.style.color = '#e74c3c';
                banner.innerHTML = `
                    <div><i class="fas fa-exclamation-triangle me-2"></i> POTENTIALLY FAKE / GHOST LOG</div>
                    <div style="font-size: 0.8em; font-weight: normal; opacity: 0.8;">Metadata Mismatch Detected</div>
                `;
            } else {
                banner.style.background = 'rgba(149, 165, 166, 0.2)';
                banner.style.border = '1px solid #95a5a6';
                banner.style.color = '#95a5a6';
                banner.innerHTML = `
                    <div><i class="fas fa-question-circle me-2"></i> UNVERIFIED</div>
                    <div style="font-size: 0.8em; font-weight: normal; opacity: 0.8;">No External Corroboration</div>
                `;
            }

            details.appendChild(banner);

            // Show Proof if available
            if (proof) {
                this.addDetailRow(details, 'Verification Proof', proof, 'text-warning');
            }
        }

        // Raw reference (MANDATORY - forensic traceability)
        this.addDetailRow(details, 'Raw Reference', event.raw_reference, 'raw-reference');

        // Timestamps
        this.addDetailRow(details, 'UTC Timestamp', event.timestamp_utc);
        this.addDetailRow(details, 'Local Timestamp', event.timestamp_local);
        this.addDetailRow(details, 'Unix Timestamp', event.timestamp_unix);
        this.addDetailRow(details, 'Timezone Offset', event.timezone_offset);

        // Event metadata
        if (event.metadata) {
            Object.entries(event.metadata).forEach(([key, value]) => {
                // Skip verification keys as we handled them above
                if (key === 'verification' || key === 'verification_proof') return;

                if (value !== null && value !== undefined) {
                    this.addDetailRow(details, this.formatMetadataKey(key), value);
                }
            });
        }

        return details;
    }

    /**
     * Add detail row
     */
    addDetailRow(container, label, value, className = '') {
        const row = document.createElement('div');
        row.className = 'detail-row';

        const labelEl = document.createElement('div');
        labelEl.className = 'detail-label';
        labelEl.textContent = label + ':';

        const valueEl = document.createElement('div');
        valueEl.className = 'detail-value' + (className ? ' ' + className : '');
        valueEl.textContent = typeof value === 'object' ? JSON.stringify(value) : value;

        row.appendChild(labelEl);
        row.appendChild(valueEl);
        container.appendChild(row);
    }

    /**
     * Apply filters
     */
    applyFilters() {
        // 1. Get Selected Categories
        const selectedCategories = Array.from(document.querySelectorAll('.category-filter:checked'))
            .map(cb => cb.value);

        // 2. Get Time Range
        const startTime = document.getElementById('timeStart').value;
        const endTime = document.getElementById('timeEnd').value;
        const startUnix = startTime ? Math.floor(new Date(startTime).getTime() / 1000) : 0;
        const endUnix = endTime ? Math.floor(new Date(endTime).getTime() / 1000) : Number.MAX_SAFE_INTEGER;

        // Debug logging
        if (startTime || endTime) {
            console.log('=== TIME FILTER DEBUG ===');
            console.log('Selected categories:', selectedCategories);
            console.log('Time inputs:', { startTime, endTime });
            console.log('Unix range:', { startUnix, endUnix });
            console.log('Start ISO:', startTime ? new Date(startTime).toISOString() : 'none');
            console.log('End ISO:', endTime ? new Date(endTime).toISOString() : 'none');
        }

        // 3. Filter Data
        const filtered = this.allEvents.filter(event => {
            // Category Check
            if (!selectedCategories.includes(event.category)) {
                return false;
            }

            // Time Check
            if (event.timestamp_unix < startUnix || event.timestamp_unix > endUnix) {
                // Debug: Log first few rejected VOIP events
                if (event.category === 'VOIP' && (startTime || endTime)) {
                    console.log('VOIP rejected by time:', {
                        event_time: event.timestamp_utc,
                        event_unix: event.timestamp_unix,
                        range: { startUnix, endUnix },
                        tooEarly: event.timestamp_unix < startUnix,
                        tooLate: event.timestamp_unix > endUnix
                    });
                }
                return false;
            }

            return true;
        });

        if (startTime || endTime) {
            console.log('Total filtered:', filtered.length);
            console.log('VOIP in filtered:', filtered.filter(e => e.category === 'VOIP').length);
        }

        this.filteredEvents = filtered;
        // Sort filtered events by timestamp based on current sort direction
        this.sortEvents();

        // 4. Update Stats (Calculate breakdown of VISIBLE events)
        const breakdown = {};
        this.filteredEvents.forEach(e => {
            breakdown[e.category] = (breakdown[e.category] || 0) + 1;
        });
        this.displayStats(breakdown);

        // 5. Render
        this.renderTimeline();
    }

    /**
     * Format event type for display
     */
    formatEventType(type) {
        return type.replace(/_/g, ' ');
    }

    /**
     * Format metadata key for display
     */
    formatMetadataKey(key) {
        return key.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.emptyState.innerHTML = `
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px; display: block; color: #dc2626;"></i>
            <p style="color: #dc2626;">${message}</p>
        `;
        this.emptyState.style.display = 'block';
        this.statsRow.style.display = 'none';
    }

    /**
     * Get icon class for category
     */
    getCategoryIcon(category) {
        switch (category) {
            case 'DEVICE': return 'fas fa-mobile-alt';
            case 'APP': return 'fas fa-th';
            case 'NETWORK': return 'fas fa-wifi';
            case 'POWER': return 'fas fa-battery-three-quarters';
            case 'NOTIFICATION': return 'fas fa-bell';
            case 'FINANCIAL': return 'fas fa-money-bill-wave';
            case 'SECURITY': return 'fas fa-exclamation-triangle';
            case 'GHOST': return 'fas fa-ghost';
            case 'VOIP': return 'fas fa-phone';
            default: return 'fas fa-circle';
        }
    }

    /**
     * Sort events by timestamp based on current direction
     */
    sortEvents() {
        if (this.sortDescending) {
            // Newest first (descending) - use event ID as tiebreaker
            this.allEvents.sort((a, b) => {
                if (b.timestamp_unix !== a.timestamp_unix) {
                    return b.timestamp_unix - a.timestamp_unix;
                }
                // If timestamps are equal, sort by event ID (descending)
                return b.id - a.id;
            });
            this.filteredEvents.sort((a, b) => {
                if (b.timestamp_unix !== a.timestamp_unix) {
                    return b.timestamp_unix - a.timestamp_unix;
                }
                return b.id - a.id;
            });
        } else {
            // Oldest first (ascending) - use event ID as tiebreaker
            this.allEvents.sort((a, b) => {
                if (a.timestamp_unix !== b.timestamp_unix) {
                    return a.timestamp_unix - b.timestamp_unix;
                }
                // If timestamps are equal, sort by event ID (ascending)
                return a.id - b.id;
            });
            this.filteredEvents.sort((a, b) => {
                if (a.timestamp_unix !== b.timestamp_unix) {
                    return a.timestamp_unix - b.timestamp_unix;
                }
                return a.id - b.id;
            });
        }
    }

    /**
     * Toggle sort direction
     */
    toggleSortDirection() {
        this.sortDescending = !this.sortDescending;
        this.sortEvents();
        this.renderTimeline();
        return this.sortDescending ? 'Newest First' : 'Oldest First';
    }
}
