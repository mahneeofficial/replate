/**
 * RePlate Notifications Shared State & Helpers
 * Location: public/js/notifications_modules/notifications_state.js
 */

let currentFilter = 'all';
let allNotifications = [];

function formatNotifTime(timeStr) {
    if (!timeStr) return 'Just now';
    const date = new Date(String(timeStr).includes('T') ? timeStr : String(timeStr).replace(' ', 'T'));
    if (isNaN(date.getTime())) return 'Just now';

    const diff = Math.floor((new Date() - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function escapeNotifHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}