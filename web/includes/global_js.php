<?php
/**
 * Global JavaScript for loading states and performance enhancements
 */
?>
<script>
    // Loading Indicator Functions
    function showLoading(message = 'Loading...') {
        const loadingHtml = `
        <div id="globalLoadingOverlay" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        ">
            <div style="
                background: white;
                padding: 30px 40px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.3);
                text-align: center;
            ">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-0">${message}</h5>
            </div>
        </div>
    `;

        // Remove existing overlay if any
        const existing = document.getElementById('globalLoadingOverlay');
        if (existing) existing.remove();

        // Add new overlay
        document.body.insertAdjacentHTML('beforeend', loadingHtml);
    }

    function hideLoading() {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    }

    // Progress Bar Handler
    function showProgressBar(current, total, label = '') {
        const progress = Math.round((current / total) * 100);

        const progressHtml = `
        <div id="globalProgressBar" style="
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 400px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 9999;
        ">
            <h6 class="mb-2">${label || 'Processing...'}</h6>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: ${progress}%"
                     aria-valuenow="${progress}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    ${progress}%
                </div>
            </div>
            <small class="text-muted mt-2 d-block">${current} of ${total} complete</small>
        </div>
    `;

        const existing = document.getElementById('globalProgressBar');
        if (existing) {
            existing.remove();
        }

        document.body.insertAdjacentHTML('beforeend', progressHtml);

        // Auto-hide when complete
        if (current >= total) {
            setTimeout(() => {
                const bar = document.getElementById('globalProgressBar');
                if (bar) bar.remove();
            }, 1500);
        }
    }

    // Toast Notifications
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#17a2b8'
        };

        const icons = {
            'success': 'fa-check-circle',
            'error': 'fa-times-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };

        const toastHtml = `
        <div class="toast-notification" style="
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            border-left: 4px solid ${colors[type]};
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        ">
            <div class="d-flex align-items-center">
                <i class="fas ${icons[type]} fa-2x me-3" style="color: ${colors[type]}"></i>
                <div>${message}</div>
            </div>
        </div>
    `;

        const toast = document.createElement('div');
        toast.innerHTML = toastHtml;
        document.body.appendChild(toast.firstElementChild);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            const toastEl = document.querySelector('.toast-notification');
            if (toastEl) {
                toastEl.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toastEl.remove(), 300);
            }
        }, 3000);
    }

    // Animations
    const style = document.createElement('style');
    style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
    document.head.appendChild(style);

    // Performance: Lazy load tables
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize DataTables with performance options for large datasets
        const tableOptions = {
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            deferRender: true,  // Only create HTML for visible rows
            processing: true,    // Show processing indicator
            language: {
                processing: '<i class="fas fa-spinner fa-spin fa-2x"></i>'
            }
        };

        // Apply to all tables with class 'lazy-table'
        document.querySelectorAll('.lazy-table').forEach(table => {
            if ($.fn.DataTable && !$.fn.DataTable.isDataTable(table)) {
                $(table).DataTable(tableOptions);
            }
        });

        // Lazy load images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(img => {
                imageObserver.observe(img);
            });
        }
    });

    // AJAX Helper with Loading State
    async function fetchWithLoading(url, message = 'Loading...') {
        showLoading(message);
        try {
            const response = await fetch(url);
            const data = await response.json();
            hideLoading();
            return data;
        } catch (error) {
            hideLoading();
            showToast('Error: ' + error.message, 'error');
            throw error;
        }
    }

    // File size formatter
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Timestamp formatter
    function formatTimestamp(isoString) {
        const date = new Date(isoString);
        return date.toLocaleString();
    }
</script>