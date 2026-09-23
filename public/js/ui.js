/**
 * RePlate UI Engine & Global Page Handler
 * Location: public/js/ui.js
 */

const UIEngine = {
    escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    escapeAttr(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    generateStrongPassword(length = 16) {
        const uppers = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lowers = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const symbols = "!@#$%^&*()_+-=[]{}|;:,.<>?";
        const allChars = uppers + lowers + numbers + symbols;

        const charBuf = new Uint32Array(4);
        window.crypto.getRandomValues(charBuf);

        let passwordArray = [
            uppers[charBuf[0] % uppers.length],
            lowers[charBuf[1] % lowers.length],
            numbers[charBuf[2] % numbers.length],
            symbols[charBuf[3] % symbols.length]
        ];

        const randomValues = new Uint32Array(length - 4);
        window.crypto.getRandomValues(randomValues);

        for (let i = 0; i < randomValues.length; i++) {
            passwordArray.push(allChars[randomValues[i] % allChars.length]);
        }

        for (let i = passwordArray.length - 1; i > 0; i--) {
            const randBuf = new Uint32Array(1);
            window.crypto.getRandomValues(randBuf);
            const j = randBuf[0] % (i + 1);
            [passwordArray[i], passwordArray[j]] = [passwordArray[j], passwordArray[i]];
        }

        return passwordArray.join("");
    },

    showToast(message, type = 'success', duration = 4500) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? 'fa-circle-check' :
                     type === 'error' ? 'fa-circle-xmark' :
                     type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info';

        const safeMessage = this.escapeHTML(message);
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${safeMessage}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.4s ease';
            setTimeout(() => toast.remove(), 400);
        }, duration);
    },

    showAlert(message, type = 'error', targetId = 'alertBox') {
        const alertBox = document.getElementById(targetId);
        const friendlyMessage = window.AuthEngine ? window.AuthEngine.parseErrorMessage(message) : message;

        if (alertBox) {
            const icon = type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check';
            const safeMessage = this.escapeHTML(friendlyMessage);

            alertBox.className = `alert-box alert-${type} show`;
            alertBox.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${safeMessage}</span>`;
            alertBox.style.display = 'flex';
        }

        if (targetId === 'alertBox') {
            this.showToast(friendlyMessage, type);
        }
    },

    hideAlert(targetId = 'alertBox') {
        const alertBox = document.getElementById(targetId);
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.innerHTML = '';
            alertBox.className = 'alert-box';
        }
    },

    logout() {
        if (window.AuthEngine && typeof window.AuthEngine.logout === 'function') {
            window.AuthEngine.logout();
        } else {
            localStorage.removeItem('replate_user');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
    },

    initSidebarProfile() {
        let user = null;
        try {
            const rawUser = localStorage.getItem('replate_user') || localStorage.getItem('user');
            user = rawUser ? JSON.parse(rawUser) : null;
        } catch (e) {
            user = null;
        }

        const nameEl = document.getElementById('userNameDisplay');
        const roleEl = document.getElementById('userRoleDisplay');

        if (user && (user.name || user.full_name || user.organization_name || user.org_name || user.email)) {
            const name = user.name || user.full_name || user.organization_name || user.org_name || user.email;
            const role = (user.role || user.user_type || 'recipient').toUpperCase();
            if (nameEl) nameEl.textContent = name;
            if (roleEl) roleEl.textContent = role;
        }

        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.onclick = (e) => {
                e.preventDefault();
                this.logout();
            };
        }
    },

    addNotification(title, message, type = 'info') {
        let notifications = [];
        try {
            notifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
        } catch (e) {
            notifications = [];
        }

        const newNotif = {
            id: 'notif_' + Date.now(),
            type,
            title,
            message,
            created_at: new Date().toISOString(),
            is_read: 0
        };

        notifications.unshift(newNotif);
        localStorage.setItem('replate_notifications', JSON.stringify(notifications));
        window.dispatchEvent(new Event('replate_notifications_updated'));
    }
};

window.UIEngine = UIEngine;

document.addEventListener('DOMContentLoaded', () => {
    UIEngine.initSidebarProfile();
});