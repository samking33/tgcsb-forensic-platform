</div>
<!-- /.app-wrapper -->

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cog me-2"></i>Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Theme</label>
                    <select class="form-select" id="themeSelect">
                        <option value="dark">Dark (Default)</option>
                        <option value="light">Light</option>
                        <option value="auto">System</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Log Refresh Rate</label>
                    <select class="form-select" id="refreshRate">
                        <option value="1000">1 second</option>
                        <option value="2000" selected>2 seconds</option>
                        <option value="5000">5 seconds</option>
                        <option value="10000">10 seconds</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="autoScroll" checked>
                        <label class="form-check-label" for="autoScroll">Auto-scroll logs</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveSettings()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- About Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pb-4">
                <i class="fas fa-fingerprint fa-4x text-primary mb-3"></i>
                <h4><?= APP_NAME ?></h4>
                <p class="text-muted mb-1">Version <?= APP_VERSION ?></p>
                <p class="small text-muted">
                    Professional digital forensics tool for analyzing Android device logs.
                </p>
                <hr>
                <p class="small text-muted mb-0">
                    <i class="fas fa-code me-1"></i> Built with PHP 8+, AdminLTE 4, Bootstrap 5, Chart.js
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 mb-0 text-white" id="loadingText" style="font-size: 1.125rem;">Processing...</p>
    </div>
</div>

<!-- Core JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

<!-- AdminLTE 4 JS -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Leaflet Maps -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Custom Application JS -->
<script src="<?= $basePath ?? '' ?>assets/js/app.js"></script>
<script src="<?= $basePath ?? '' ?>assets/js/app-custom-fixes.js?v=<?= time() ?>"></script>

<?php if (isset($additionalScripts)): ?>
    <?= $additionalScripts ?>
<?php endif; ?>
</body>

</html>