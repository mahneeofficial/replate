/**
 * RePlate — Admin Control Center Engine (Modular Entry Point)
 * File: public/js/admin.js
 */

window.adminUsersList = [];

document.addEventListener('DOMContentLoaded', () => {
    initAdminSession();
    setupEventListeners();
    loadAnalytics();
});

function initAdminSession() {
    try {
        const rawUser = localStorage.getItem('replate_user') || localStorage.getItem('user') || '{}';
        const user = JSON.parse(rawUser);
        const adminNameEl = document.getElementById('adminNameDisplay');
        if (adminNameEl && (user.name || user.full_name || user.email)) {
            adminNameEl.textContent = user.name || user.full_name || user.email;
        }
    } catch (e) {
        console.error('Error parsing admin user session:', e);
    }
}

function setupEventListeners() {
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

    const closeUserDetailsBtn = document.getElementById('closeUserDetailsBtn');
    if (closeUserDetailsBtn) {
        closeUserDetailsBtn.addEventListener('click', () => toggleModal('userDetailsModal', false));
    }

    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', handleSendNotificationSubmit);
    }

    const modalCancelBtn = document.getElementById('modalCancelBtn');
    if (modalCancelBtn) {
        modalCancelBtn.addEventListener('click', () => toggleModal('adminActionModal', false));
    }
}

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
            break;
        case 'notifications':
            break;
        case 'audit':
            loadAuditLogs();
            break;
        default:
            break;
    }
};

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