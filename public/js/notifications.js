/**
 * RePlate Notifications Controller (Modular Entry Point)
 * Location: public/js/notifications.js
 */

document.addEventListener('DOMContentLoaded', () => {
    loadNotificationsPage();
    setupNotificationControls();

    window.addEventListener('replate_notifications_updated', () => {
        loadNotificationsPage();
    });
});