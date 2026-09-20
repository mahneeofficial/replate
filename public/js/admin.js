/**
 * RePlate — Admin Control Center Engine
 * File: public/js/admin.js
 */

document.addEventListener('DOMContentLoaded', () => {
    initAdminSession();
    setupEventListeners();
    loadAnalytics(); // Default tab on load
});

// ==========================================
// 1. Session & Auth Initialization
// ==========================================
function initAdminSession() {
    try {
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        const adminNameEl = document.getElementById('adminNameDisplay');
        if (adminNameEl && user.name) {
            adminNameEl.textContent = user.name;
        }
    } catch (e) {
        console.error('Error parsing admin user session:', e);
    }
}

// ==========================================
// 2. Global Event Listeners & Router Hooks
// ==========================================
function setupEventListeners() {
    // Add Category Modal Triggers
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const cancelCatBtn = document.getElementById('cancelCatBtn');
    const addCategoryForm = document.getElementById('addCategoryForm');

    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', () => toggleModal('addCategoryModal', true));
    }
    if (cancelCatBtn) {
        cancelCatBtn.addEventListener('click', () => toggleModal('addCategoryModal', false));
    }
    if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', handleAddCategorySubmit);
    }

    // Notification Form
    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', handleSendNotificationSubmit);
    }

    // Modal Cancel Listener
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    if (modalCancelBtn) {
        modalCancelBtn.addEventListener('click', () => toggleModal('adminActionModal', false));
    }
}

/**
 * Global Tab Switching Hook (Called from admin-dashboard.html inline switchTab)
 */
window.onAdminTabSwitch = function (tabId) {
    switch (tabId) {
        case 'analytics':
            loadAnalytics();
            break;
        case 'users':
            loadUsers();
            break;
        case 'categories':
            loadCategories();
            break;
        case 'listings':
            loadListings();
            break;
        case 'requests':
            loadDonationRequests();
            break;
        case 'reports':
            // Reports tab generates downloads on demand
            break;
        case 'notifications':
            // Form is ready by default
            break;
        case 'audit':
            loadAuditLogs();
            break;
        default:
            break;
    }
};

// ==========================================
// 3. Impact Analytics Loader
// ==========================================
async function loadAnalytics() {
    try {
        const res = await fetch('/api/admin?action=analytics');
        const data = await res.json();

        if (data.success && data.analytics) {
            document.getElementById('metricRescued').textContent = data.analytics.total_rescued || '0 kg';
            document.getElementById('metricListings').textContent = data.analytics.active_listings || '0';
            document.getElementById('metricClaims').textContent = data.analytics.completed_claims || '0';
            document.getElementById('metricCO2').textContent = data.analytics.co2_avoided || '0 kg';
        } else if (res.status === 403) {
            handleUnauthorizedAccess();
        }
    } catch (err) {
        console.error('Failed to load analytics:', err);
        showToast('Error loading analytics data.', 'danger');
    }
}

// ==========================================
// 4. User Moderation Panel
// ==========================================
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading users...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=users');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.users || data.users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="table-empty">No registered users found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.users.map(u => {
            const isVerified = parseInt(u.is_verified, 10) === 1;
            const isSuspended = (u.status || '').toLowerCase() === 'suspended';

            const verifyBadge = isVerified
                ? `<span class="badge badge-success"><i class="fa-solid fa-check"></i> Verified</span>`
                : `<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Unverified</span>`;

            const statusBadge = isSuspended
                ? `<span class="badge badge-danger">Suspended</span>`
                : `<span class="badge badge-success">Active</span>`;

            return `
                <tr>
                    <td>
                        <strong>${escapeHTML(u.name || 'N/A')}</strong><br>
                        <small class="text-muted">${escapeHTML(u.email || '')}</small>
                    </td>
                    <td><span class="badge badge-role">${escapeHTML(u.role || 'User')}</span></td>
                    <td>${statusBadge}</td>
                    <td>${verifyBadge}</td>
                    <td>
                        <div class="action-buttons">
                            ${isVerified
                                ? `<button class="btn-sm btn-outline-danger" onclick="confirmUserVerification(${u.user_id}, 0)">Unverify</button>`
                                : `<button class="btn-sm btn-outline-success" onclick="confirmUserVerification(${u.user_id}, 1)">Verify</button>`
                            }
                            ${isSuspended
                                ? `<button class="btn-sm btn-success" onclick="confirmUserStatus(${u.user_id}, 'Active')">Approve</button>`
                                : `<button class="btn-sm btn-danger" onclick="confirmUserStatus(${u.user_id}, 'Suspended')">Suspend</button>`
                            }
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        console.error('Failed to load users:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty text-danger">Failed to load user records.</td></tr>`;
    }
}

function confirmUserVerification(userId, verifyStatus) {
    const actionText = verifyStatus === 1 ? 'verify' : 'unverify';
    showConfirmModal(
        `Confirm ${actionText.toUpperCase()} Account`,
        `Are you sure you want to ${actionText} user ID #${userId}?`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=verify_account', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, is_verified: verifyStatus })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Verification updated successfully.', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to update verification.', 'danger');
                }
            } catch (err) {
                showToast('Network error updating user verification.', 'danger');
            }
        }
    );
}

function confirmUserStatus(userId, status) {
    showConfirmModal(
        `Confirm Account ${status}`,
        `Are you sure you want to set status to "${status}" for user ID #${userId}?`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=update_user_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, status: status })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'User status updated.', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to update user status.', 'danger');
                }
            } catch (err) {
                showToast('Network error updating user status.', 'danger');
            }
        }
    );
}

// ==========================================
// 5. Food Categories Management
// ==========================================
async function loadCategories() {
    const tbody = document.getElementById('categoriesTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading categories...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=categories');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.categories || data.categories.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="table-empty">No categories available. Click "Add Category" to create one.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.categories.map(c => `
            <tr>
                <td>#${c.category_id}</td>
                <td><strong>${escapeHTML(c.name || '')}</strong></td>
                <td>${escapeHTML(c.description || 'No description provided')}</td>
                <td><span class="badge badge-info">${c.listings_count || 0} donations</span></td>
                <td>
                    <button class="btn-sm btn-danger" onclick="confirmDeleteCategory(${c.category_id}, '${escapeHTML(c.name)}')">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Failed to load categories:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty text-danger">Failed to load categories.</td></tr>`;
    }
}

async function handleAddCategorySubmit(e) {
    e.preventDefault();
    const nameInput = document.getElementById('catName');
    const descInput = document.getElementById('catDesc');

    if (!nameInput || !nameInput.value.trim()) {
        showToast('Category name is required.', 'warning');
        return;
    }

    try {
        const res = await fetch('/api/admin?action=add_category', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: nameInput.value.trim(),
                description: descInput ? descInput.value.trim() : ''
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Category added successfully.', 'success');
            toggleModal('addCategoryModal', false);
            nameInput.value = '';
            if (descInput) descInput.value = '';
            loadCategories();
        } else {
            showToast(data.error || 'Failed to add category.', 'danger');
        }
    } catch (err) {
        showToast('Network error creating food category.', 'danger');
    }
}

function confirmDeleteCategory(categoryId, categoryName) {
    showConfirmModal(
        'Delete Food Category',
        `Are you sure you want to delete category "${categoryName}"? Existing donations linked to it may lose classification.`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=delete_category', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category_id: categoryId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Category deleted.', 'success');
                    loadCategories();
                } else {
                    showToast(data.error || 'Failed to delete category.', 'danger');
                }
            } catch (err) {
                showToast('Network error deleting category.', 'danger');
            }
        }
    );
}

// ==========================================
// 6. Food Donation Moderation
// ==========================================
async function loadListings() {
    const tbody = document.getElementById('listingsTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading donations...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=listings');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.listings || data.listings.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="table-empty">No active or past food donations logged.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.listings.map(l => `
            <tr>
                <td><strong>${escapeHTML(l.food_name || 'Unnamed Item')}</strong> (${escapeHTML(l.quantity || '')} ${escapeHTML(l.unit || '')})</td>
                <td>${escapeHTML(l.donor_name || 'Unknown Donor')}</td>
                <td><span class="badge badge-secondary">${escapeHTML(l.category_name || 'Uncategorized')}</span></td>
                <td>${formatDate(l.expiry_date)}</td>
                <td>
                    <button class="btn-sm btn-danger" onclick="confirmRemoveListing(${l.donation_id}, '${escapeHTML(l.food_name)}')">
                        <i class="fa-solid fa-trash"></i> Remove
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Failed to load listings:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty text-danger">Failed to load donation listings.</td></tr>`;
    }
}

function confirmRemoveListing(donationId, foodName) {
    showConfirmModal(
        'Remove Food Donation Listing',
        `Are you sure you want to permanently remove listing "${foodName}"?`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=remove_listing', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ donation_id: donationId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Donation listing removed.', 'success');
                    loadListings();
                } else {
                    showToast(data.error || 'Failed to remove listing.', 'danger');
                }
            } catch (err) {
                showToast('Network error removing listing.', 'danger');
            }
        }
    );
}

// ==========================================
// 7. Donation Requests & Claims Monitor
// ==========================================
async function loadDonationRequests() {
    const tbody = document.getElementById('requestsTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading requests...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=donation_requests');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.requests || data.requests.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="table-empty">No donation requests or claims recorded yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.requests.map(r => {
            const statusClass = getStatusBadgeClass(r.status);
            return `
                <tr>
                    <td>#REQ-${r.request_id}</td>
                    <td><strong>${escapeHTML(r.food_name || 'Item')}</strong> (${escapeHTML(r.quantity || '')} ${escapeHTML(r.unit || '')})</td>
                    <td>${escapeHTML(r.recipient_name || 'N/A')}</td>
                    <td><span class="badge ${statusClass}">${escapeHTML(r.status || 'Pending')}</span></td>
                    <td>${formatDate(r.created_at)}</td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        console.error('Failed to load donation requests:', err);
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty text-danger">Failed to load donation requests.</td></tr>`;
    }
}

// ==========================================
// 8. System CSV Report Export Engine
// ==========================================
window.exportReport = async function (type) {
    showToast(`Preparing ${type.toUpperCase()} report export...`, 'info');

    try {
        let action = '';
        let filename = `RePlate_${type}_Report_${new Date().toISOString().slice(0, 10)}.csv`;

        if (type === 'impact') action = 'listings';
        else if (type === 'users') action = 'users';
        else if (type === 'claims') action = 'donation_requests';
        else action = 'reports';

        const res = await fetch(`/api/admin?action=${action}`);
        const data = await res.json();

        if (!data.success) {
            showToast('Failed to retrieve report data from server.', 'danger');
            return;
        }

        let dataset = data.listings || data.users || data.requests || data.reports || [];

        if (dataset.length === 0) {
            showToast('No records found to generate CSV report.', 'warning');
            return;
        }

        // Convert JSON array to CSV format
        const headers = Object.keys(dataset[0]);
        const csvRows = [headers.join(',')];

        dataset.forEach(row => {
            const values = headers.map(header => {
                const val = row[header] === null || row[header] === undefined ? '' : row[header];
                const escaped = ('' + val).replace(/"/g, '""');
                return `"${escaped}"`;
            });
            csvRows.push(values.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvRows.join('\n'));
        const downloadAnchor = document.createElement('a');
        downloadAnchor.setAttribute('href', csvContent);
        downloadAnchor.setAttribute('download', filename);
        document.body.appendChild(downloadAnchor);
        downloadAnchor.click();
        downloadAnchor.remove();

        showToast(`Report downloaded successfully: ${filename}`, 'success');
    } catch (err) {
        console.error('CSV Generation Error:', err);
        showToast('Failed to generate report export.', 'danger');
    }
};

// ==========================================
// 9. System Notification Broadcasting
// ==========================================
async function handleSendNotificationSubmit(e) {
    e.preventDefault();

    const targetSelect = document.getElementById('notifTarget');
    const titleInput = document.getElementById('notifTitle');
    const messageInput = document.getElementById('notifMessage');

    if (!titleInput || !titleInput.value.trim() || !messageInput || !messageInput.value.trim()) {
        showToast('Title and Message are required for broadcast.', 'warning');
        return;
    }

    const payload = {
        target: targetSelect ? targetSelect.value : 'all',
        title: titleInput.value.trim(),
        message: messageInput.value.trim(),
        type: 'info'
    };

    try {
        const res = await fetch('/api/admin?action=send_notification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Notification broadcast sent successfully!', 'success');
            titleInput.value = '';
            messageInput.value = '';
        } else {
            showToast(data.error || 'Failed to send notification.', 'danger');
        }
    } catch (err) {
        showToast('Network error sending system notification.', 'danger');
    }
}

// ==========================================
// 10. Audit Logs Loader
// ==========================================
async function loadAuditLogs() {
    const tbody = document.getElementById('auditTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading system logs...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=audit_logs');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.logs || data.logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="table-empty">No system audit logs found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.logs.map(log => `
            <tr>
                <td>${formatDate(log.created_at)}</td>
                <td><strong>${escapeHTML(log.title || 'System Event')}</strong><br><small>${escapeHTML(log.message || '')}</small></td>
                <td>${escapeHTML(log.user_name || 'System')} (${escapeHTML(log.email || '')})</td>
                <td><span class="badge badge-info">${escapeHTML(log.type || 'info')}</span></td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Failed to load audit logs:', err);
        tbody.innerHTML = `<tr><td colspan="4" class="table-empty text-danger">Failed to load system audit logs.</td></tr>`;
    }
}

// ==========================================
// Helper Utilities & UI Extensions
// ==========================================
function toggleModal(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    if (show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function showConfirmModal(title, description, onConfirmCallback) {
    const modalTitle = document.getElementById('modalTitle');
    const modalDesc = document.getElementById('modalDescription');
    const confirmBtn = document.getElementById('modalConfirmBtn');

    if (modalTitle) modalTitle.textContent = title;
    if (modalDesc) modalDesc.textContent = description;

    if (confirmBtn) {
        // Clone button to strip existing single-click handlers
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async () => {
            toggleModal('adminActionModal', false);
            if (typeof onConfirmCallback === 'function') {
                await onConfirmCallback();
            }
        });
    }

    toggleModal('adminActionModal', true);
}

function handleUnauthorizedAccess() {
    showToast('Admin session expired or unauthorized. Redirecting...', 'danger');
    setTimeout(() => {
        localStorage.removeItem('user');
        window.location.replace('/login');
    }, 1500);
}

function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function' && window.showToast !== showToast) {
        window.showToast(message, type);
        return;
    }

    const container = document.getElementById('toastContainer');
    if (!container) {
        alert(message);
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${escapeHTML(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 4000);
}

function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'approved' || s === 'collected' || s === 'completed') return 'badge-success';
    if (s === 'pending') return 'badge-warning';
    if (s === 'rejected' || s === 'cancelled') return 'badge-danger';
    return 'badge-secondary';
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return dateStr;
    }
}

function escapeHTML(str) {
    if (typeof str !== 'string') return str;
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}