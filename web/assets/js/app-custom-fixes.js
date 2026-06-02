/**
 * Custom Fixes for Android Forensic Tool
 * Overrides and patches for AdminLTE/Bootstrap issues
 */

// Global Sidebar Toggle Function (called by onclick in header if needed)
window.toggleSidebar = function (e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    const body = document.body;
    console.log('Manual sidebar toggle triggered');

    // AdminLTE 4 Behavior
    if (window.innerWidth >= 992) {
        body.classList.toggle('sidebar-collapse');
    } else {
        body.classList.toggle('sidebar-open');
    }
};

// Listener DISABLED to prevent conflict with AdminLTE data-attribute
// document.addEventListener('DOMContentLoaded', function() {
//     const toggleBtn = document.getElementById('mainSidebarToggle');
//     if (toggleBtn) {
//         toggleBtn.addEventListener('click', window.toggleSidebar);
//     }
// });
