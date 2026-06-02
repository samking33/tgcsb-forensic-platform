/**
 * Mind Map Timeline - Hierarchical Event Visualization
 * Shows events as an interactive mind map with collapsible branches
 */

class MindMapTimeline {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.config = {
            colors: {
                'root': '#6c757d',
                'SMS': '#2ecc71',
                'CALL': '#3498db',
                'LOGCAT_APP': '#9b59b6',
                'LOGCAT_NET': '#e74c3c',
                'LOGCAT_SYS': '#f1c40f',
                'VOIP': '#8e44ad'
            },
            nodeRadius: {
                root: 40,
                category: 25,
                event: 8
            },
            ...options
        };

        this.state = {
            data: [],
            allNodes: [],
            filters: new Set(['SMS', 'CALL', 'LOGCAT_APP', 'LOGCAT_NET', 'LOGCAT_SYS', 'VOIP']),
            searchQuery: '',
            flaggedIds: new Set(),
            expandedCategories: new Set(['SMS', 'CALL', 'LOGCAT_APP', 'LOGCAT_NET', 'LOGCAT_SYS', 'VOIP']),
            statistics: {}
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

        // Create SVG
        this.svg = d3.select(`#${this.containerId}`).append('svg')
            .attr('width', '100%')
            .attr('height', '100%');

        this.g = this.svg.append('g');

        // Layers
        this.layers = {
            links: this.g.append('g').attr('class', 'links-layer'),
            nodes: this.g.append('g').attr('class', 'nodes-layer'),
            labels: this.g.append('g').attr('class', 'labels-layer')
        };

        // Setup zoom
        this.zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on('zoom', (e) => {
                this.g.attr('transform', e.transform);
            });

        this.svg.call(this.zoom);

        // Center initial view
        this.svg.call(this.zoom.transform, d3.zoomIdentity.translate(this.width / 2, this.height / 2).scale(0.8));

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
        // Categorize events
        this.state.allNodes = rawData.map((d, i) => {
            let category = d.type;
            if (d.type === 'LOGCAT') {
                const tag = (d.subtype || '').toLowerCase();
                if (tag.includes('wifi') || tag.includes('net')) category = 'LOGCAT_NET';
                else if (tag.includes('activity') || tag.includes('window')) category = 'LOGCAT_APP';
                else category = 'LOGCAT_SYS';
            }

            return {
                id: `event-${i}`,
                timestamp: new Date(d.timestamp),
                category: category,
                original: d
            };
        }).sort((a, b) => a.timestamp - b.timestamp);

        this.state.data = [...this.state.allNodes];
        this.updateStatistics();
        this.elements.loadingOverlay.classList.add('d-none');
    }

    buildHierarchy() {
        // Create root node
        const root = {
            id: 'root',
            name: 'Device Events',
            type: 'root',
            children: []
        };

        // Group events by category
        const categories = {
            'SMS': { id: 'cat-sms', name: 'SMS Messages', type: 'category', children: [] },
            'CALL': { id: 'cat-call', name: 'Phone Calls', type: 'category', children: [] },
            'LOGCAT_APP': { id: 'cat-app', name: 'App Activity', type: 'category', children: [] },
            'LOGCAT_NET': { id: 'cat-net', name: 'Network', type: 'category', children: [] },
            'LOGCAT_SYS': { id: 'cat-sys', name: 'System', type: 'category', children: [] },
            'VOIP': { id: 'cat-voip', name: 'VoIP Calls', type: 'category', children: [] }
        };

        // Filter and add events to categories
        const visibleData = this.state.data.filter(d =>
            this.state.filters.has(d.category) &&
            (!this.state.searchQuery ||
                JSON.stringify(d.original).toLowerCase().includes(this.state.searchQuery))
        );

        visibleData.forEach(event => {
            if (categories[event.category]) {
                categories[event.category].children.push({
                    ...event,
                    name: event.original.content || event.original.message || 'Event',
                    type: 'event'
                });
            }
        });

        // Add non-empty categories to root
        Object.values(categories).forEach(cat => {
            if (cat.children.length > 0 && this.state.expandedCategories.has(cat.id.replace('cat-', '').toUpperCase())) {
                // Limit events per category for performance
                cat.children = cat.children.slice(0, 50);
                root.children.push(cat);
            } else if (cat.children.length > 0) {
                // Collapsed category - just show count
                root.children.push({
                    id: cat.id,
                    name: `${cat.name} (${cat.children.length})`,
                    type: 'category-collapsed',
                    category: cat.id.replace('cat-', '').toUpperCase(),
                    count: cat.children.length
                });
            }
        });

        return root;
    }

    render() {
        const hierarchyData = this.buildHierarchy();

        // Create force simulation
        const simulation = d3.forceSimulation()
            .force('link', d3.forceLink().id(d => d.id).distance(d => {
                if (d.source.type === 'root') return 150;
                if (d.source.type === 'category') return 80;
                return 50;
            }))
            .force('charge', d3.forceManyBody().strength(-300))
            .force('center', d3.forceCenter(0, 0))
            .force('collision', d3.forceCollide().radius(d => {
                if (d.type === 'root') return 50;
                if (d.type === 'category' || d.type === 'category-collapsed') return 35;
                return 15;
            }));

        // Flatten hierarchy into nodes and links
        const nodes = [];
        const links = [];

        const traverse = (node, parent = null) => {
            nodes.push(node);
            if (parent) {
                links.push({ source: parent.id, target: node.id });
            }
            if (node.children) {
                node.children.forEach(child => traverse(child, node));
            }
        };

        traverse(hierarchyData);

        // Draw links
        const link = this.layers.links.selectAll('.link')
            .data(links, d => `${d.source}-${d.target}`);

        link.exit().remove();

        const linkEnter = link.enter().append('line')
            .attr('class', 'link')
            .attr('stroke', '#555')
            .attr('stroke-width', 2)
            .attr('opacity', 0.6);

        const linkAll = linkEnter.merge(link);

        // Draw nodes
        const node = this.layers.nodes.selectAll('.node')
            .data(nodes, d => d.id);

        node.exit().remove();

        const nodeEnter = node.enter().append('circle')
            .attr('class', 'node')
            .attr('cursor', 'pointer')
            .call(d3.drag()
                .on('start', (e, d) => {
                    if (!e.active) simulation.alphaTarget(0.3).restart();
                    d.fx = d.x;
                    d.fy = d.y;
                })
                .on('drag', (e, d) => {
                    d.fx = e.x;
                    d.fy = e.y;
                })
                .on('end', (e, d) => {
                    if (!e.active) simulation.alphaTarget(0);
                    d.fx = null;
                    d.fy = null;
                }));

        const nodeAll = nodeEnter.merge(node);

        nodeAll
            .attr('r', d => {
                if (d.type === 'root') return this.config.nodeRadius.root;
                if (d.type === 'category' || d.type === 'category-collapsed') return this.config.nodeRadius.category;
                return this.config.nodeRadius.event;
            })
            .attr('fill', d => {
                if (d.type === 'root') return this.config.colors.root;
                if (d.type === 'category' || d.type === 'category-collapsed') return this.config.colors[d.category || d.id.replace('cat-', '').toUpperCase()];
                return this.config.colors[d.category];
            })
            .attr('stroke', d => this.state.flaggedIds.has(d.id) ? '#ffff00' : '#fff')
            .attr('stroke-width', d => this.state.flaggedIds.has(d.id) ? 3 : 2)
            .attr('opacity', d => d.type === 'event' ? 0.8 : 1);

        // Add labels
        const label = this.layers.labels.selectAll('.label')
            .data(nodes.filter(d => d.type !== 'event'), d => d.id);

        label.exit().remove();

        const labelEnter = label.enter().append('text')
            .attr('class', 'label')
            .attr('text-anchor', 'middle')
            .attr('dy', d => d.type === 'root' ? 5 : 4)
            .attr('font-size', d => d.type === 'root' ? '14px' : '11px')
            .attr('font-weight', d => d.type === 'root' ? 'bold' : 'normal')
            .attr('fill', '#fff')
            .attr('pointer-events', 'none');

        const labelAll = labelEnter.merge(label);

        labelAll.text(d => {
            if (d.type === 'root') return d.name;
            if (d.type === 'category-collapsed') return d.count;
            return d.name.length > 15 ? d.name.substring(0, 12) + '...' : d.name;
        });

        // Interactivity
        nodeAll.on('click', (e, d) => {
            e.stopPropagation();
            if (d.type === 'category-collapsed') {
                this.state.expandedCategories.add(d.category);
                this.render();
            } else if (d.type === 'event') {
                this.showEventDetails(d);
            }
        })
            .on('mouseover', (e, d) => {
                if (d.type === 'event') {
                    const content = d.original.content || d.original.message || 'No content';
                    this.tooltip.style('opacity', 1)
                        .html(`
                        <div style="margin-bottom: 6px;">
                            <strong style="color: ${this.config.colors[d.category]}">${d.category}</strong>
                        </div>
                        <div style="font-size: 11px; color: #aaa; margin-bottom: 4px;">
                            ${d.timestamp.toLocaleString()}
                        </div>
                        <div style="font-size: 12px;">
                            ${content.substring(0, 150)}${content.length > 150 ? '...' : ''}
                        </div>
                    `)
                        .style('left', (e.pageX + 15) + 'px')
                        .style('top', (e.pageY - 10) + 'px');
                }
            })
            .on('mouseout', () => {
                this.tooltip.style('opacity', 0);
            });

        // Update simulation
        simulation.nodes(nodes);
        simulation.force('link').links(links);

        simulation.on('tick', () => {
            linkAll
                .attr('x1', d => d.source.x)
                .attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x)
                .attr('y2', d => d.target.y);

            nodeAll
                .attr('cx', d => d.x)
                .attr('cy', d => d.y);

            labelAll
                .attr('x', d => d.x)
                .attr('y', d => d.y);
        });

        simulation.alpha(1).restart();
    }

    showEventDetails(node) {
        document.getElementById('panel-time').textContent = node.timestamp.toLocaleString();
        document.getElementById('panel-subtype').textContent = node.original.subtype || node.original.type;
        document.getElementById('panel-content').textContent = node.original.content || node.original.message || 'No content';

        const badge = document.getElementById('panel-type-badge');
        badge.textContent = node.category;
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
            voip: this.state.data.filter(n => n.category === 'VOIP').length,
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
        updateStat('stat-voip', stats.voip);
        updateStat('stat-flagged', stats.flagged);
    }

    setupControls() {
        const filterMap = {
            'sms': 'SMS',
            'call': 'CALL',
            'app': 'LOGCAT_APP',
            'system': 'LOGCAT_SYS',
            'network': 'LOGCAT_NET',
            'voip': 'VOIP'
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

        // Search
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.state.searchQuery = e.target.value.toLowerCase();
                this.applyFilters();
            });
        }

        // Select All
        const selectAll = document.getElementById('select-all-types');
        if (selectAll) {
            selectAll.addEventListener('click', () => {
                Object.values(filterMap).forEach(cat => this.state.filters.add(cat));
                Object.keys(filterMap).forEach(id => {
                    const cb = document.getElementById(`toggle-${id}`);
                    if (cb) cb.checked = true;
                });
                this.applyFilters();
            });
        }

        // Select None
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

        // Reset Filters
        const resetFilters = document.getElementById('reset-filters');
        if (resetFilters) {
            resetFilters.addEventListener('click', () => {
                this.state.filters = new Set(Object.values(filterMap));
                this.state.searchQuery = '';
                Object.keys(filterMap).forEach(id => {
                    const cb = document.getElementById(`toggle-${id}`);
                    if (cb) cb.checked = true;
                });
                if (searchInput) searchInput.value = '';
                this.applyFilters();
            });
        }

        // Export JSON
        const exportJSON = document.getElementById('export-json');
        if (exportJSON) {
            exportJSON.addEventListener('click', () => {
                const visibleNodes = this.state.data.map(n => n.original);
                const blob = new Blob([JSON.stringify(visibleNodes, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `timeline_${Date.now()}.json`;
                a.click();
                URL.revokeObjectURL(url);
            });
        }

        // Export CSV
        const exportCSV = document.getElementById('export-csv');
        if (exportCSV) {
            exportCSV.addEventListener('click', () => {
                const visibleNodes = this.state.data.map(n => n.original);
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
            });
        }

        // Panel close
        const closePanel = document.getElementById('close-panel');
        if (closePanel) {
            closePanel.addEventListener('click', () => {
                this.elements.panel.style.transform = 'translateX(100%)';
            });
        }

        // Reset view
        const resetView = document.getElementById('reset-view');
        if (resetView) {
            resetView.addEventListener('click', () => {
                this.svg.transition().duration(750).call(
                    this.zoom.transform,
                    d3.zoomIdentity.translate(this.width / 2, this.height / 2).scale(0.8)
                );
            });
        }
    }

    handleResize() {
        this.width = this.elements.container.clientWidth;
        this.height = this.elements.container.clientHeight;
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
