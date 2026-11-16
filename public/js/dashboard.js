/**
 * RePlate Dashboard Controller (Modular Entry Point)
 * Location: public/js/dashboard.js
 */

document.addEventListener('DOMContentLoaded', () => {
    initUserProfile();
    loadCategories();
    loadDashboardMetrics();
    loadDonationListings();
    loadUserNotifications();
    setupEventListeners();

    window.addEventListener('replate_notifications_updated', () => {
        loadUserNotifications();
    });
});