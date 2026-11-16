/**
 * RePlate Notifications Actions & Controls
 * Location: public/js/notifications_modules/notifications_actions.js
 */

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