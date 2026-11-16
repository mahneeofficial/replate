/**
 * RePlate Donations Helpers & Utilities
 * Location: public/js/donations_modules/donations_helpers.js
 */

function getUserRole() {
    try {
        const user = JSON.parse(localStorage.getItem('replate_user') || localStorage.getItem('user') || '{}');
        return (user?.role || user?.user_type || 'recipient').toLowerCase();
    } catch (e) {
        return 'recipient';
    }
}

function parseLocalDate(dateString) {
    if (!dateString) return 'N/A';
    const parts = dateString.split(' ')[0].split('-');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
            return new Date(year, month, day).toLocaleDateString();
        }
    }
    return new Date(dateString).toLocaleDateString();
}

function showToastMessage(msg, type = 'info') {
    if (window.UIEngine && typeof window.UIEngine.showToast === 'function') {
        window.UIEngine.showToast(msg, type);
    } else if (typeof window.showToast === 'function') {
        window.showToast(msg, type);
    } else {
        alert(msg);
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}