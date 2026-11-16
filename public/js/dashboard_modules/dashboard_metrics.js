/**
 * RePlate Dashboard Categories & Metrics
 * Location: public/js/dashboard_modules/dashboard_metrics.js
 */

async function loadCategories() {
    const categorySelect = document.getElementById('category_id') || document.getElementById('page_category_id');
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

function applyRoleMetricLabels(role) {
    const totalLabel = document.getElementById('metricTotalLabel');
    const activeLabel = document.getElementById('metricActiveLabel');
    const normalizedRole = (role || 'recipient').toLowerCase().trim();

    if (normalizedRole === 'donor') {
        if (totalLabel) totalLabel.textContent = 'Total Food Posts';
        if (activeLabel) activeLabel.textContent = 'Incoming Claim Requests';
    } else if (normalizedRole === 'recipient') {
        if (totalLabel) totalLabel.textContent = 'Total Claims Made';
        if (activeLabel) activeLabel.textContent = 'Active Claim Requests';
    } else {
        if (totalLabel) totalLabel.textContent = 'Total Platform Listings';
        if (activeLabel) activeLabel.textContent = 'Active Platform Requests';
    }
}

async function loadDashboardMetrics() {
    try {
        let role = 'recipient';
        try {
            const rawUser = localStorage.getItem('replate_user') || localStorage.getItem('user');
            if (rawUser) {
                const u = JSON.parse(rawUser);
                role = (u.role || u.user_type || 'recipient').toLowerCase().trim();
            }
        } catch (e) {}

        applyRoleMetricLabels(role);

        const res = await fetch('/api/donations.php?action=metrics');
        if (!res.ok) return;
        const data = await res.json();

        if (data && data.success && data.metrics) {
            if (data.role) applyRoleMetricLabels(data.role);
            if (document.getElementById('metricTotal')) {
                document.getElementById('metricTotal').textContent = data.metrics.total_claims ?? data.metrics.total_donations ?? 0;
            }
            if (document.getElementById('metricActive')) {
                document.getElementById('metricActive').textContent = data.metrics.active_requests ?? data.metrics.active_listings ?? 0;
            }
            if (document.getElementById('metricCO2')) {
                document.getElementById('metricCO2').textContent = (data.metrics.co2_saved ?? data.metrics.co2_avoided ?? '0') + ' kg';
            }
        }
    } catch (err) {
        console.error('Failed to load dashboard metrics:', err);
    }
}