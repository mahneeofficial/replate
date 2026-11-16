/**
 * RePlate Dashboard Global Event Handlers
 * Location: public/js/dashboard_modules/dashboard_events.js
 */

function setupEventListeners() {
    const modal = document.getElementById('donationModal');
    const openBtn = document.getElementById('addDonationBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const form = document.getElementById('createDonationForm');

    if (openBtn && modal) openBtn.onclick = () => modal.style.display = 'flex';
    if (closeBtn && modal) closeBtn.onclick = () => modal.style.display = 'none';
    
    if (modal) {
        window.onclick = (e) => {
            if (e.target === modal) modal.style.display = 'none';
        };
    }

    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');

    if (markAllReadBtn) {
        markAllReadBtn.onclick = async () => {
            try {
                await fetch('/api/notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_all_read' })
                });
            } catch (e) {
                console.error('Error marking all notifications read:', e);
            }

            let notifications = [];
            try {
                notifications = JSON.parse(localStorage.getItem('replate_notifications') || '[]');
            } catch (e) {
                notifications = [];
            }

            notifications = notifications.map(n => ({ ...n, read: true, is_read: 1 }));
            localStorage.setItem('replate_notifications', JSON.stringify(notifications));
            
            loadUserNotifications();
            DashboardUtils.notify('All notifications marked as read', 'info');
        };
    }

    if (clearAllBtn) {
        clearAllBtn.onclick = async () => {
            try {
                await fetch('/api/notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_all' })
                });
            } catch (e) {
                console.error('Error clearing all notifications:', e);
            }

            localStorage.setItem('replate_notifications', JSON.stringify([]));
            loadUserNotifications();
            DashboardUtils.notify('Notifications cleared', 'info');
        };
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const foodName = document.getElementById('food_name')?.value?.trim() || '';
            const quantity = document.getElementById('quantity')?.value || '';

            if (!foodName || !quantity) {
                DashboardUtils.notify('Please fill out the required food name and quantity fields.', 'warning');
                return;
            }

            const payload = {
                food_name: foodName,
                category_id: document.getElementById('category_id')?.value,
                quantity: quantity,
                unit: document.getElementById('unit')?.value,
                expiry_date: document.getElementById('expiry_date')?.value,
                pickup_location: document.getElementById('pickup_location')?.value
            };

            try {
                const res = await fetch('/api/donations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    DashboardUtils.notify('Donation posted successfully!', 'success');
                    if (modal) modal.style.display = 'none';
                    form.reset();
                    loadDashboardMetrics();
                    loadDonationListings();
                    loadUserNotifications();
                } else {
                    DashboardUtils.notify(data.error || 'Failed to post donation.', 'error');
                }
            } catch (err) {
                DashboardUtils.notify('Server error occurred while posting donation', 'error');
            }
        });
    }
}