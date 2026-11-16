/**
 * RePlate Dashboard Notifications Engine
 * Location: public/js/dashboard_modules/dashboard_notifications.js
 */

async function loadUserNotifications() {
    const notifContainer = document.getElementById('notificationsList');
    if (!notifContainer) return;

    let apiNotifications = null;
    try {
        const res = await fetch('/api/notifications');
        if (res.ok) {
            const data = await res.json();
            apiNotifications = Array.isArray(data) ? data : (data.notifications || []);
        }
    } catch (err) {
        console.error('Failed to fetch backend notifications:', err);
    }

    let localNotifications = [];
    try {
        localNotifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
    } catch (e) {
        localNotifications = [];
    }

    const notifications = (apiNotifications !== null) ? apiNotifications : localNotifications;

    const unreadCount = notifications.filter(n => Number(n.is_read || n.read) === 0).length;
    const badgeEl = document.getElementById('notifBadge') || document.getElementById('unreadNotifCount') || document.querySelector('.notification-badge');
    if (badgeEl) {
        badgeEl.textContent = unreadCount;
        badgeEl.style.display = unreadCount > 0 ? 'inline-block' : 'none';
    }

    if (!notifications || notifications.length === 0) {
        notifContainer.innerHTML = '<p style="color: #94a3b8; font-size: 0.9rem; padding: 12px; text-align: center;">No new notifications.</p>';
        return;
    }

    const typeIcons = {
        request: 'fa-hand-holding-heart',
        donation: 'fa-box',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info',
        account: 'fa-user-check',
        security: 'fa-shield-halved'
    };

    notifContainer.innerHTML = notifications.map((n, index) => {
        const title = n.title || 'Notification';
        const msg = n.message || '';
        const isRead = Number(n.is_read || n.read) === 1;
        const type = (n.type || 'info').toLowerCase();
        const iconClass = typeIcons[type] || 'fa-bell';
        const notifId = n.id ?? `local_${index}`;
        const timeStr = n.created_at || n.timestamp || n.date_sent;
        const formattedTime = DashboardUtils.formatRelativeTime(timeStr);

        return `
            <div class="notification-card ${isRead ? 'read' : 'unread'}" data-id="${DashboardUtils.escapeAttr(notifId)}">
                <div class="notification-main btn-mark-read" data-id="${DashboardUtils.escapeAttr(notifId)}" data-read="${isRead}">
                    <div class="notification-icon">
                        <i class="fa-solid ${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <h4>${DashboardUtils.escape(title)}</h4>
                        <p>${DashboardUtils.escape(msg)}</p>
                    </div>
                </div>
                <div class="notification-meta">
                    <span class="notification-time">${formattedTime}</span>
                    <button class="btn-dismiss-notif" data-id="${DashboardUtils.escapeAttr(notifId)}" title="Dismiss">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    notifContainer.onclick = (e) => {
        const dismissBtn = e.target.closest('.btn-dismiss-notif');
        if (dismissBtn) {
            e.stopPropagation();
            const id = dismissBtn.getAttribute('data-id');
            dismissNotification(id);
            return;
        }

        const markReadEl = e.target.closest('.btn-mark-read');
        if (markReadEl) {
            const id = markReadEl.getAttribute('data-id');
            const isRead = markReadEl.getAttribute('data-read') === 'true';
            markNotificationRead(id, isRead);
        }
    };
}

async function markNotificationRead(id, isAlreadyRead) {
    if (isAlreadyRead) return;

    try {
        await fetch('/api/notifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });
    } catch (e) {
        console.error('Failed to update notification read status on server:', e);
    }

    let notifications = [];
    try {
        notifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
    } catch (e) {
        notifications = [];
    }

    notifications = notifications.map(n => String(n.id) === String(id) ? { ...n, is_read: 1, read: true } : n);
    localStorage.setItem('replate_notifications', JSON.stringify(notifications));

    loadUserNotifications();
}

async function dismissNotification(id) {
    try {
        await fetch('/api/notifications', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
    } catch (e) {
        console.error('Failed to dismiss notification on server:', e);
    }

    let notifications = [];
    try {
        notifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
    } catch (e) {
        notifications = [];
    }

    notifications = notifications.filter(n => String(n.id) !== String(id));
    localStorage.setItem('replate_notifications', JSON.stringify(notifications));

    loadUserNotifications();
}