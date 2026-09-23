/**
 * RePlate Notifications Controller
 * Location: public/js/notifications.js
 */

document.addEventListener('DOMContentLoaded', () => {
    loadNotificationsPage();
    setupNotificationControls();

    // Listen for cross-module notifications updates
    window.addEventListener('replate_notifications_updated', () => {
        loadNotificationsPage();
    });
});

let currentFilter = 'all';
let allNotifications = [];

async function loadNotificationsPage() {
    const container = document.getElementById('notificationsPageContainer') || document.querySelector('.notifications-container');
    if (!container) return;

    try {
        let res = await fetch('/api/notifications');
        if (!res.ok && res.status === 404) {
            res = await fetch('/api/notifications.php');
        }

        if (res.ok) {
            const data = await res.json();
            allNotifications = data.notifications || (Array.isArray(data) ? data : []);
            localStorage.setItem('replate_notifications', JSON.stringify(allNotifications));
        } else {
            allNotifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
        }
    } catch (err) {
        console.error('Failed to load notifications from server, falling back to local storage:', err);
        allNotifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
    }

    renderNotificationsList();
}

function renderNotificationsList() {
    const container = document.getElementById('notificationsPageContainer') || document.querySelector('.notifications-container');
    if (!container) return;

    let filtered = allNotifications;
    if (currentFilter === 'unread') {
        filtered = allNotifications.filter(n => Number(n.is_read || n.read) === 0);
    } else if (currentFilter === 'read') {
        filtered = allNotifications.filter(n => Number(n.is_read || n.read) === 1);
    }

    if (!filtered || filtered.length === 0) {
        container.innerHTML = `
            <div class="empty-notifications">
                <i class="fa-solid fa-bell-slash"></i>
                <p>No notifications found.</p>
            </div>`;
        return;
    }

    const typeIcons = {
        account: 'fa-user-check',
        welcome: 'fa-user-plus',
        security: 'fa-shield-halved',
        profile: 'fa-user-gear',
        donation: 'fa-box-archive',
        request: 'fa-hand-holding-heart',
        pickup: 'fa-truck-ramp-box',
        expired: 'fa-clock-rotate-left',
        approved: 'fa-circle-check',
        nearby: 'fa-location-dot',
        milestone: 'fa-seedling',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    const escapeFn = window.UIEngine ? window.UIEngine.escapeHTML.bind(window.UIEngine) : escapeNotifHTML;

    container.innerHTML = filtered.map((n, idx) => {
        const notifId = String(n.id || idx);
        const isRead = Number(n.is_read || n.read) === 1;
        const type = (n.type || 'info').toLowerCase();
        const icon = typeIcons[type] || 'fa-bell';
        const title = escapeFn(n.title || 'Notification');
        const message = escapeFn(n.message || '');
        const time = formatNotifTime(n.created_at || n.timestamp || n.date_sent);

        return `
            <div class="notification-card ${isRead ? 'read' : 'unread'}" data-id="${notifId}" onclick="markNotifAsRead('${notifId}', ${isRead})">
                <div class="notification-main">
                    <div class="notification-icon ${type}">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                    <div class="notification-content">
                        <h4>${title}</h4>
                        <p>${message}</p>
                    </div>
                </div>
                <div class="notification-meta">
                    <span class="notification-time">${time}</span>
                    <button class="btn-dismiss-notif" onclick="event.stopPropagation(); deleteNotif('${notifId}')" title="Delete notification">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function markNotifAsRead(id, isAlreadyRead) {
    if (isAlreadyRead) return;

    try {
        let res = await fetch('/api/notifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id, notification_id: id })
        });

        if (!res.ok && res.status === 404) {
            await fetch('/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id: id, notification_id: id })
            });
        }
    } catch (e) {
        console.error('Error marking notification as read on server:', e);
    }

    allNotifications = allNotifications.map(n => {
        if (String(n.id) === String(id)) return { ...n, is_read: 1, read: true };
        return n;
    });

    localStorage.setItem('replate_notifications', JSON.stringify(allNotifications));
    window.dispatchEvent(new Event('replate_notifications_updated'));
    renderNotificationsList();
}

async function deleteNotif(id) {
    try {
        let res = await fetch('/api/notifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_one', id: id, notification_id: id })
        });

        if (!res.ok && res.status === 404) {
            await fetch('/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_one', id: id, notification_id: id })
            });
        }
    } catch (e) {
        console.error('Error deleting notification on server:', e);
    }

    allNotifications = allNotifications.filter(n => String(n.id) !== String(id));
    localStorage.setItem('replate_notifications', JSON.stringify(allNotifications));

    window.dispatchEvent(new Event('replate_notifications_updated'));
    renderNotificationsList();
}

function setupNotificationControls() {
    const filterButtons = document.querySelectorAll('.notif-filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            filterButtons.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilter = e.target.dataset.filter || 'all';
            renderNotificationsList();
        });
    });

    const markAllBtn = document.getElementById('markAllNotifsReadBtn') || document.querySelector('.btn-mark-all');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', async () => {
            try {
                let res = await fetch('/api/notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_all_read' })
                });

                if (!res.ok && res.status === 404) {
                    await fetch('/api/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_all_read' })
                    });
                }
            } catch (e) {
                console.error('Error marking all as read on server:', e);
            }

            allNotifications = allNotifications.map(n => ({ ...n, is_read: 1, read: true }));
            localStorage.setItem('replate_notifications', JSON.stringify(allNotifications));
            window.dispatchEvent(new Event('replate_notifications_updated'));
            renderNotificationsList();
        });
    }

    const clearAllBtn = document.getElementById('clearAllNotifsBtn') || document.querySelector('.btn-clear-all');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to clear all notifications?')) return;

            try {
                let res = await fetch('/api/notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_all' })
                });

                if (!res.ok && res.status === 404) {
                    await fetch('/api/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'clear_all' })
                    });
                }
            } catch (e) {
                console.error('Error clearing all notifications on server:', e);
            }

            allNotifications = [];
            localStorage.setItem('replate_notifications', JSON.stringify([]));
            window.dispatchEvent(new Event('replate_notifications_updated'));
            renderNotificationsList();
        });
    }
}

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