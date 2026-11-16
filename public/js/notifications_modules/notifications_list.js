/**
 * RePlate Notifications Page & List Renderer
 * Location: public/js/notifications_modules/notifications_list.js
 */

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