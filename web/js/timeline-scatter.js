/**
 * Swimlane Timeline - Forensic Event Visualization
 * Shows events in horizontal lanes by type for easy temporal analysis
 */

class SwimLaneTimeline {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.config = {
            colors: {
                'SMS': '#2ecc71',
                'CALL': '#3498db',
                'LOGCAT_APP': '#9b59b6',
                'LOGCAT_NET': '#e74c3c',
                'LOGCAT_SYS': '#f1c40f'
            },
            lanes: ['SMS', 'CALL', 'LOGCAT_APP', 'LOGCAT_NET', 'LOGCAT_SYS'],
            laneLabels: {
                'SMS': 'SMS Messages',
                'CALL': 'Phone Calls',
                'LOGCAT_APP': 'App Activity',
                'LOGCAT_NET': 'Network',
                'LOGCAT_SYS': 'System'
            },
            eventHeight: 20,
            laneHeight: 60,
            ...options
        };

        this.state = {
            data: [],
            allNodes: [],
            filters: new Set(this.config.lanes),
            searchQuery: '',
            currentTransform: d3.zoomIdentity,
            flaggedIds: new Set(),
            statistics: {
                total: 0,
                visible: 0,
                sms: 0,
                calls: 0,
                app: 0,
                network: 0,
                system: 0,
                flagged: 0
            }
        };

        this.elements = {
            container: document.getElementById(containerId),
            loadingOverlay: document.getElementById('loading-overlay'),
            panel: document.getElementById('event-panel')
        };

        this.init();
    }

    init() {
        this.width = this.elements.container.clientWidth;
        this.height = this.elements.container.clientHeight;
        this.margin = { top: 40, right: 50, bottom: 60, left: 150 };

        // Create SVG
        this.svg = d3.select(`#${this.containerId}`).append('svg')
            .attr('width', '100%')
            .attr('height', '100%');

        // Clip path for events
        this.svg.append('defs').append('clipPath')
            .attr('id', 'clip')
            .append('rect')
            .attr('x', this.margin.left)
            .attr('y', 0)
            .attr('width', this.width - this.margin.left - this.margin.right)
            .attr('height', this.height);

        // Main group
        this.g = this.svg.append('g');

        // Layers
        this.layers = {
            lanes: this.g.append('g').attr('class', 'lanes-layer'),
            events: this.g.append('g').attr('class', 'events-layer').attr('clip-path', 'url(#clip)'),
            axes: this.g.append('g').attr('class', 'axes-layer')
        };

        // Create scales
        this.xScale = d3.scaleTime()
            .range([this.margin.left, this.width - this.margin.right]);

        this.yScale = d3.scaleBand()
            .domain(this.config.lanes)
            .range([this.margin.top, this.height - this.margin.bottom])
            .padding(0.2);

        // Setup zoom (X-axis only)
        this.zoom = d3.zoom()
            .scaleExtent([1, 50])
            .translateExtent([[this.margin.left, 0], [this.width - this.margin.right, this.height]])
            .extent([[this.margin.left, 0], [this.width - this.margin.right, this.height]])
            .on('zoom', (e) => this.handleZoom(e));

        this.svg.call(this.zoom);

        // Tooltip
        this.tooltip = d3.select('body').append('div')
            .attr('class', 'timeline-tooltip')
            .style('position', 'absolute')
            .style('background', 'rgba(0,0,0,0.9)')
            .style('color', '#fff')
            .style('padding', '10px 14px')
            .style('border-radius', '6px')
            .style('font-size', '13px')
            .style('pointer-events', 'none')
            .style('opacity', 0)
            .style('z-index', 10000)
            .style('max-width', '300px');

        this.setupControls();
        window.addEventListener('resize', () => this.handleResize());
    }

    async loadData(url) {
        try {
            const rawData = await d3.json(url);
            if (!rawData || rawData.length === 0) {
                this.showError('No data found. Please extract logs first.');
                return;
            }
            this.processData(rawData);
            this.render();
        } catch (err) {
            console.error(err);
            this.showError(`Error loading data: ${err.message}`);
        }
    }

    processData(rawData) {
        this.state.allNodes = rawData.map((d, i) => {
            let category = d.type;
            if (d.type === 'LOGCAT') {
                const tag = (d.subtype || '').toLowerCase();
                if (tag.includes('wifi') || tag.includes('net')) category = 'LOGCAT_NET';
                else if (tag.includes('activity') || tag.includes('window')) category = 'LOGCAT_APP';
                else category = 'LOGCAT_SYS';
            }

            return {
                id: i,
                timestamp: new Date(d.timestamp),
                category: category,
                original: d
            };
        }).sort((a, b) => a.timestamp - b.timestamp);

        this.state.data = [...this.state.allNodes];

        // Set time extent
        this.timeExtent = d3.extent(this.state.data, d => d.timestamp);
        this.xScale.domain(this.timeExtent);

        this.updateStatistics();
        this.elements.loadingOverlay.classList.add('d-none');
    }

    render() {
        const visibleData = this.state.data.filter(d =>
            this.state.filters.has(d.category) &&
            (!this.state.searchQuery ||
                JSON.stringify(d.original).toLowerCase().includes(this.state.searchQuery))
        );

        this.drawLanes();
        this.drawAxes();
        this.drawEvents(visibleData);
    }

    drawLanes() {
        // Draw lane backgrounds
        const lanes = this.layers.lanes.selectAll('.lane')
            .data(this.config.lanes);

        lanes.exit().remove();

        const enter = lanes.enter().append('g')
            .attr('class', 'lane');

        // Lane background
        enter.append('rect')
            .attr('class', 'lane-bg')
            .attr('x', this.margin.left)
            .attr('width', this.width - this.margin.left - this.margin.right)
            .attr('fill', (d, i) => i % 2 === 0 ? '#1a1a1a' : '#222')
            .attr('stroke', '#333')
            .attr('stroke-width', 1);

        // Lane labels
        enter.append('text')
            .attr('class', 'lane-label')
            .attr('x', this.margin.left - 10)
            .attr('text-anchor', 'end')
            .attr('dy', '0.35em')
            .attr('fill', d => this.config.colors[d])
            .attr('font-size', '14px')
            .attr('font-weight', 'bold')
            .text(d => this.config.laneLabels[d]);

        // Update positions
        const all = enter.merge(lanes);
        all.select('.lane-bg')
            .attr('y', d => this.yScale(d))
            .attr('height', this.yScale.bandwidth());

        all.select('.lane-label')
            .attr('y', d => this.yScale(d) + this.yScale.bandwidth() / 2);
    }

    drawAxes() {
        // X-axis (time)
        const xAxis = d3.axisBottom(this.xScale)
            .ticks(10)
            .tickFormat(d3.timeFormat('%H:%M:%S'));

        this.layers.axes.selectAll('.x-axis').remove();
        this.layers.axes.append('g')
            .attr('class', 'x-axis')
            .attr('transform', `translate(0, ${this.height - this.margin.bottom})`)
            .call(xAxis)
            .selectAll('text')
            .attr('fill', '#999')
            .attr('font-size', '12px');

        this.layers.axes.selectAll('.x-axis path, .x-axis line')
            .attr('stroke', '#666');

        // X-axis label
        this.layers.axes.selectAll('.x-label').remove();
        this.layers.axes.append('text')
            .attr('class', 'x-label')
            .attr('x', (this.margin.left + this.width - this.margin.right) / 2)
            .attr('y', this.height - 10)
            .attr('text-anchor', 'middle')
            .attr('fill', '#999')
            .attr('font-size', '13px')
            .text('Timeline');
    }

    drawEvents(data) {
        const events = this.layers.events.selectAll('.event')
            .data(data, d => d.id);

        events.exit().remove();

        const enter = events.enter().append('rect')
            .attr('class', 'event')
            .attr('rx', 3)
            .attr('ry', 3)
            .attr('cursor', 'pointer')
            .attr('height', this.config.eventHeight);

        const all = enter.merge(events);

        all.attr('x', d => this.xScale(d.timestamp) - 2)
            .attr('y', d => this.yScale(d.category) + (this.yScale.bandwidth() - this.config.eventHeight) / 2)
            .attr('width', 4)
            .attr('fill', d => this.config.colors[d.category])
            .attr('opacity', d => this.state.flaggedIds.has(d.id) ? 1 : 0.8)
            .attr('stroke', d => this.state.flaggedIds.has(d.id) ? '#ffff00' : 'none')
            .attr('stroke-width', 2);

        // Interactivity
        all.on('mouseover', (event, d) => {
            // Highlight
            d3.select(event.currentTarget)
                .attr('opacity', 1)
                .attr('width', 6)
                .attr('x', this.xScale(d.timestamp) - 3);

            // Show tooltip
            const content = d.original.content || d.original.message || d.original.body || 'No content';
            this.tooltip.style('opacity', 1)
                .html(`
                    <div style="margin-bottom: 6px;">
                        <strong style="color: ${this.config.colors[d.category]}">${this.config.laneLabels[d.category]}</strong>
                    </div>
                    <div style="font-size: 11px; color: #aaa; margin-bottom: 4px;">
                        ${d.timestamp.toLocaleString()}
                    </div>
                    <div style="font-size: 12px;">
                        ${content.substring(0, 150)}${content.length > 150 ? '...' : ''}
                    </div>
                `)
                .style('left', (event.pageX + 15) + 'px')
                .style('top', (event.pageY - 10) + 'px');
        })
            .on('mouseout', (event, d) => {
                if (!this.state.flaggedIds.has(d.id)) {
                    d3.select(event.currentTarget)
                        .attr('opacity', 0.8)
                        .attr('width', 4)
                        .attr('x', this.xScale(d.timestamp) - 2);
                }
                this.tooltip.style('opacity', 0);
            })
            .on('click', (event, d) => {
                event.stopPropagation();
                this.showEventDetails(d);
            });
    }

    showEventDetails(node) {
        document.getElementById('panel-time').textContent = node.timestamp.toLocaleString();
        document.getElementById('panel-subtype').textContent = node.original.subtype || node.original.type;
        document.getElementById('panel-content').textContent = node.original.content || node.original.message || 'No content';

        const badge = document.getElementById('panel-type-badge');
        badge.textContent = this.config.laneLabels[node.category];
        badge.style.backgroundColor = this.config.colors[node.category];

        this.elements.panel.style.transform = 'translateX(0)';

        const btnFlag = document.getElementById('btn-flag');
        if (btnFlag) {
            btnFlag.onclick = () => this.toggleFlag(node);
            if (this.state.flaggedIds.has(node.id)) {
                btnFlag.classList.remove('btn-outline-danger');
                btnFlag.classList.add('btn-danger');
                btnFlag.innerHTML = '<i class="fas fa-flag-checkered me-1"></i> Unflag';
            } else {
                btnFlag.classList.remove('btn-danger');
                btnFlag.classList.add('btn-outline-danger');
                btnFlag.innerHTML = '<i class="fas fa-flag me-1"></i> Flag';
            }
        }
    }

    toggleFlag(node) {
        if (this.state.flaggedIds.has(node.id)) {
            this.state.flaggedIds.delete(node.id);
        } else {
            this.state.flaggedIds.add(node.id);
        }
        this.updateStatistics();
        this.render();
    }

    handleZoom(event) {
        this.state.currentTransform = event.transform;
        const newXScale = event.transform.rescaleX(this.xScale);

        // Update events
        this.layers.events.selectAll('.event')
            .attr('x', d => newXScale(d.timestamp) - 2);

        // Update axis
        const xAxis = d3.axisBottom(newXScale)
            .ticks(10)
            .tickFormat(d3.timeFormat('%H:%M:%S'));

        this.layers.axes.select('.x-axis').call(xAxis);
    }

    applyFilters() {
        this.state.data = this.state.allNodes.filter(node => {
            if (!this.state.filters.has(node.category)) return false;
            if (this.state.searchQuery) {
                const searchText = JSON.stringify(node.original).toLowerCase();
                if (!searchText.includes(this.state.searchQuery)) return false;
            }
            return true;
        });

        this.updateStatistics();
        this.render();
    }

    updateStatistics() {
        const stats = {
            total: this.state.allNodes.length,
            visible: this.state.data.length,
            sms: this.state.data.filter(n => n.category === 'SMS').length,
            calls: this.state.data.filter(n => n.category === 'CALL').length,
            app: this.state.data.filter(n => n.category === 'LOGCAT_APP').length,
            network: this.state.data.filter(n => n.category === 'LOGCAT_NET').length,
            system: this.state.data.filter(n => n.category === 'LOGCAT_SYS').length,
            flagged: this.state.flaggedIds.size
        };

        this.state.statistics = stats;

        const updateStat = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        updateStat('stat-total', stats.total);
        updateStat('stat-visible', stats.visible);
        updateStat('stat-sms', stats.sms);
        updateStat('stat-calls', stats.calls);
        updateStat('stat-app', stats.app);
        updateStat('stat-network', stats.network);
        updateStat('stat-system', stats.system);
        updateStat('stat-flagged', stats.flagged);
    }

    exportData(format) {
        const visibleNodes = this.state.data.map(n => n.original);

        if (format === 'json') {
            const blob = new Blob([JSON.stringify(visibleNodes, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `timeline_${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        } else if (format === 'csv') {
            let csv = 'Timestamp,Type,Category,Content\n';
            visibleNodes.forEach(node => {
                const timestamp = new Date(node.timestamp).toISOString();
                const type = node.type || '';
                const category = node.subtype || '';
                const content = (node.message || node.body || '').replace(/"/g, '""');
                csv += `"${timestamp}","${type}","${category}","${content}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `timeline_${Date.now()}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    }

    setupControls() {
        const filterMap = {
            'sms': 'SMS',
            'call': 'CALL',
            'app': 'LOGCAT_APP',
            'system': 'LOGCAT_SYS',
            'network': 'LOGCAT_NET'
        };

        // Event type filters
        Object.entries(filterMap).forEach(([id, category]) => {
            const checkbox = document.getElementById(`toggle-${id}`);
            if (checkbox) {
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        this.state.filters.add(category);
                    } else {
                        this.state.filters.delete(category);
                    }
                    this.applyFilters();
                });
            }
        });

        // Time range filter
        const timeRange = document.getElementById('time-range');
        if (timeRange) {
            timeRange.addEventListener('change', (e) => {
                const range = e.target.value;
                if (range === 'all') {
                    this.xScale.domain(this.timeExtent);
                } else {
                    const now = new Date(this.timeExtent[1]);
                    let startTime;

                    switch (range) {
                        case '1h':
                            startTime = new Date(now.getTime() - 60 * 60 * 1000);
                            break;
                        case '6h':
                            startTime = new Date(now.getTime() - 6 * 60 * 60 * 1000);
                            break;
                        case '24h':
                            startTime = new Date(now.getTime() - 24 * 60 * 60 * 1000);
                            break;
                        case '7d':
                            startTime = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            break;
                        default:
                            startTime = this.timeExtent[0];
                    }

                    this.xScale.domain([startTime, now]);
                }

                this.render();
            });
        }

        // Search
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.state.searchQuery = e.target.value.toLowerCase();
                this.applyFilters();
            });
        }

        const selectAll = document.getElementById('select-all-types');
        if (selectAll) {
            selectAll.addEventListener('click', () => {
                this.config.lanes.forEach(lane => this.state.filters.add(lane));
                Object.keys(filterMap).forEach(id => {
                    const cb = document.getElementById(`toggle-${id}`);
                    if (cb) cb.checked = true;
                });
                this.applyFilters();
            });
        }

        const selectNone = document.getElementById('select-none-types');
        if (selectNone) {
            selectNone.addEventListener('click', () => {
                this.state.filters.clear();
                Object.keys(filterMap).forEach(id => {
                    const cb = document.getElementById(`toggle-${id}`);
                    if (cb) cb.checked = false;
                });
                this.applyFilters();
            });
        }

        const resetFilters = document.getElementById('reset-filters');
        if (resetFilters) {
            resetFilters.addEventListener('click', () => {
                this.state.filters = new Set(this.config.lanes);
                this.state.searchQuery = '';
                Object.keys(filterMap).forEach(id => {
                    const cb = document.getElementById(`toggle-${id}`);
                    if (cb) cb.checked = true;
                });
                if (searchInput) searchInput.value = '';
                this.applyFilters();
            });
        }

        const exportJSON = document.getElementById('export-json');
        if (exportJSON) {
            exportJSON.addEventListener('click', () => this.exportData('json'));
        }

        const exportCSV = document.getElementById('export-csv');
        if (exportCSV) {
            exportCSV.addEventListener('click', () => this.exportData('csv'));
        }

        const closePanel = document.getElementById('close-panel');
        if (closePanel) {
            closePanel.addEventListener('click', () => {
                this.elements.panel.style.transform = 'translateX(100%)';
            });
        }

        const zoomIn = document.getElementById('zoom-in');
        if (zoomIn) {
            zoomIn.addEventListener('click', () => {
                this.svg.transition().duration(300).call(this.zoom.scaleBy, 1.5);
            });
        }

        const zoomOut = document.getElementById('zoom-out');
        if (zoomOut) {
            zoomOut.addEventListener('click', () => {
                this.svg.transition().duration(300).call(this.zoom.scaleBy, 0.67);
            });
        }

        const resetView = document.getElementById('reset-view');
        if (resetView) {
            resetView.addEventListener('click', () => {
                this.svg.transition().duration(750).call(
                    this.zoom.transform,
                    d3.zoomIdentity
                );
            });
        }
    }

    handleResize() {
        this.width = this.elements.container.clientWidth;
        this.height = this.elements.container.clientHeight;
        this.xScale.range([this.margin.left, this.width - this.margin.right]);
        this.yScale.range([this.margin.top, this.height - this.margin.bottom]);

        // Update clip path
        this.svg.select('#clip rect')
            .attr('width', this.width - this.margin.left - this.margin.right);

        this.render();
    }

    showError(message) {
        this.elements.loadingOverlay.classList.remove('d-none');
        this.elements.loadingOverlay.innerHTML = `
            <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <div>${message}</div>
            </div>
        `;
    }
}
