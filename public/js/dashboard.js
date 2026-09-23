/**
 * RePlate Dashboard Controller
 * Location: public/js/dashboard.js
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

document.addEventListener('DOMContentLoaded', () => {
    initUserProfile();
    loadCategories();
    loadDashboardMetrics();
    loadDonationListings();
    loadUserNotifications();
    setupEventListeners();

    window.addEventListener('replate_notifications_updated', () => {
        loadUserNotifications();
    });
});

// 1. Session & User Profile Loader
async function initUserProfile() {
    const cachedUser = DashboardUtils.getUser();

    if (cachedUser && (cachedUser.name || cachedUser.full_name || cachedUser.organization_name || cachedUser.email)) {
        renderUser(cachedUser);
    }

    try {
        const res = await fetch('/api/users?action=me');
        if (res.ok) {
            const data = await res.json();
            if (data && data.user) {
                const updatedUser = data.user;
                const currentRole = cachedUser ? (cachedUser.role || cachedUser.user_type) : null;
                const newRole = updatedUser.role || updatedUser.user_type;

                localStorage.setItem('replate_user', JSON.stringify(updatedUser));
                renderUser(updatedUser);

                if (currentRole && currentRole.toLowerCase() !== String(newRole).toLowerCase()) {
                    loadDonationListings();
                }
                return;
            }
        }
    } catch (e) {
        console.error('Failed to sync user profile with backend:', e);
    }

    if (!cachedUser) {
        renderFallbackUser();
    }
}

function renderUser(u) {
    const name = u.name || u.full_name || u.organization_name || u.username || 'Member Profile';
    const role = (u.role || u.user_type || 'recipient').toLowerCase().trim();
    
    const nameEl = document.getElementById('userNameDisplay');
    const roleEl = document.getElementById('userRoleDisplay');
    const welcomeEl = document.getElementById('welcomeHeading');
    const postBtn = document.getElementById('addDonationBtn');

    if (nameEl) nameEl.textContent = name;
    if (roleEl) roleEl.textContent = role.toUpperCase();
    if (welcomeEl) welcomeEl.textContent = `Welcome Back, ${name.split(' ')[0]}`;

    if (postBtn) {
        postBtn.style.display = (role === 'donor' || role === 'admin') ? 'inline-flex' : 'none';
    }
}

function renderFallbackUser() {
    const nameEl = document.getElementById('userNameDisplay');
    const roleEl = document.getElementById('userRoleDisplay');
    if (nameEl) nameEl.textContent = 'Guest User';
    if (roleEl) roleEl.textContent = 'RECIPIENT';
}

// 2. Fetch and Populate Food Categories
async function loadCategories() {
    const categorySelect = document.getElementById('category_id');
    if (!categorySelect) return;

    try {
        let res = await fetch('/api/categories.php');
        if (!res.ok) {
            res = await fetch('/api/categories');
        }
        if (res.ok) {
            const data = await res.json();
            const categories = data.categories || (Array.isArray(data) ? data : []);
            
            if (categories.length > 0) {
                categorySelect.innerHTML = '<option value="">Select Category</option>' + 
                    categories.map(c => `<option value="${c.category_id || c.id}">${DashboardUtils.escape(c.name)}</option>`).join('');
            }
        }
    } catch (err) {
        console.error('Failed to load food categories:', err);
    }
}

// 3. Metrics Fetching
async function loadDashboardMetrics() {
    try {
        const res = await fetch('/api/donations?action=metrics');
        if (!res.ok) return;
        const data = await res.json();
        
        if (data && data.success && data.metrics) {
            if (document.getElementById('metricTotal')) document.getElementById('metricTotal').textContent = data.metrics.total_claims ?? data.metrics.total_donations ?? 0;
            if (document.getElementById('metricActive')) document.getElementById('metricActive').textContent = data.metrics.active_requests ?? data.metrics.active_listings ?? 0;
            if (document.getElementById('metricCO2')) document.getElementById('metricCO2').textContent = data.metrics.co2_saved ?? data.metrics.co2_avoided ?? '0 kg';
        }
    } catch (err) {
        console.error('Failed to load dashboard metrics:', err);
    }
}

// 4. Donation Listings
async function loadDonationListings() {
    const container = document.getElementById('donationsListContainer');
    if (!container) return;

    const role = DashboardUtils.getUserRole();
    const endpoint = (role === 'donor') ? '/api/donations?action=my_listings' : '/api/donations?action=list';

    try {
        const res = await fetch(endpoint);
        const data = await res.json();
        const items = data.donations || data.listings || (Array.isArray(data) ? data : []);

        if (items.length > 0) {
            let html = `
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Food Item</th>
                                <th>Quantity</th>
                                <th>Category</th>
                                <th>Expiry</th>
                                <th>Status / Action</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            items.forEach(item => {
                const itemId = item.donation_id || item.id;
                const rawFoodName = item.food_name || 'Food Item';
                const foodName = DashboardUtils.escape(rawFoodName);
                const safeAttrFoodName = DashboardUtils.escapeAttr(rawFoodName);
                const quantity = DashboardUtils.escape(`${item.quantity} ${item.unit || ''}`);
                const category = DashboardUtils.escape(item.category_name || item.category || 'General');
                const expiry = item.expiry_date ? new Date(item.expiry_date).toLocaleDateString() : 'N/A';
                const status = item.status || 'Available';

                let actionCell = `<span class="status-badge ${status.toLowerCase()}">${status}</span>`;

                if ((role === 'recipient' || role === 'admin') && status.toLowerCase() === 'available') {
                    actionCell = `<button class="btn-primary claim-donation-btn" style="padding: 6px 12px; font-size: 0.8rem;" data-id="${itemId}" data-name="${safeAttrFoodName}">Claim</button>`;
                }

                html += `
                    <tr>
                        <td><strong>${foodName}</strong></td>
                        <td>${quantity}</td>
                        <td><span class="category-pill">${category}</span></td>
                        <td>${expiry}</td>
                        <td>${actionCell}</td>
                    </tr>`;
            });
            html += `</tbody></table></div>`;
            container.innerHTML = html;

            // Event Delegation for Claim Buttons
            container.onclick = (e) => {
                const claimBtn = e.target.closest('.claim-donation-btn');
                if (claimBtn) {
                    const id = claimBtn.getAttribute('data-id');
                    const name = claimBtn.getAttribute('data-name');
                    claimDonation(id, name);
                }
            };
        } else {
            renderEmptyDonationsState(container);
        }
    } catch (err) {
        renderEmptyDonationsState(container);
    }
}

function renderEmptyDonationsState(container) {
    container.innerHTML = `
        <div class="empty-state" style="text-align: center; padding: 24px;">
            <i class="fa-solid fa-basket-shopping" style="font-size: 2rem; color: #94a3b8;"></i>
            <p style="color: #94a3b8; margin-top: 8px;">No active food surplus available right now.</p>
        </div>`;
}

// 5. Claim Donation Handler
async function claimDonation(donationId, foodName = 'Food Surplus') {
    if (!confirm(`Are you sure you want to claim "${foodName}"?`)) return;

    try {
        const res = await fetch('/api/requests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', donation_id: donationId })
        });
        const data = await res.json();

        if (res.ok && data.success) {
            DashboardUtils.notify('Donation claimed successfully!', 'success');
            loadDashboardMetrics();
            loadDonationListings();
            loadUserNotifications();
        } else {
            DashboardUtils.notify(data.error || 'Failed to claim donation.', 'error');
        }
    } catch (err) {
        DashboardUtils.notify('Failed to reach server to claim item.', 'error');
    }
}

// 6. Notifications Engine
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

    // Centralized Event Delegation for Notifications List
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

// 7. Global Event Handlers
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