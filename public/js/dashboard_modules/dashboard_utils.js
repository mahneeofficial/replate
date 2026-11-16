/**
 * RePlate Dashboard Utilities
 * Location: public/js/dashboard_modules/dashboard_utils.js
 */

const DashboardUtils = {
    escape(str) {
        if (window.UIEngine && typeof window.UIEngine.escapeHTML === 'function') {
            return window.UIEngine.escapeHTML(str);
        }
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    escapeAttr(str) {
        if (window.UIEngine && typeof window.UIEngine.escapeAttr === 'function') {
            return window.UIEngine.escapeAttr(str);
        }
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    notify(message, type = 'info') {
        if (window.UIEngine && typeof window.UIEngine.showToast === 'function') {
            window.UIEngine.showToast(message, type);
        } else if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log(`[Toast - ${type}]: ${message}`);
        }
    },

    addNotification(title, message, type = 'info') {
        if (window.UIEngine && typeof window.UIEngine.addNotification === 'function') {
            window.UIEngine.addNotification(title, message, type);
        } else {
            let notifications = [];
            try {
                notifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
            } catch (e) {
                notifications = [];
            }
            notifications.unshift({ 
                id: 'notif_' + Date.now(), 
                title, 
                message, 
                type, 
                created_at: new Date().toISOString(), 
                is_read: 0 
            });
            localStorage.setItem('replate_notifications', JSON.stringify(notifications));
            window.dispatchEvent(new Event('replate_notifications_updated'));
        }
    },

    getUser() {
        try {
            const rawUser = localStorage.getItem('replate_user') || localStorage.getItem('user');
            return rawUser ? JSON.parse(rawUser) : null;
        } catch (e) {
            console.error('Failed to parse user session:', e);
            return null;
        }
    },

    getUserRole() {
        const user = this.getUser();
        return (user?.role || user?.user_type || 'recipient').toLowerCase().trim();
    },

    formatRelativeTime(timeStr) {
        if (!timeStr) return 'Just now';
        
        const cleanTimeStr = String(timeStr).includes('T') ? timeStr : String(timeStr).replace(' ', 'T');
        const date = new Date(cleanTimeStr);
        
        if (isNaN(date.getTime())) return 'Just now';

        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 30) return 'Just now';
        if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) return `${diffInMinutes}m ago`;

        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return `${diffInHours}h ago`;

        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 7) return `${diffInDays}d ago`;

        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
};