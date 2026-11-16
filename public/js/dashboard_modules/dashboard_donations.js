/**
 * RePlate Dashboard Donation Listings & Claims
 * Location: public/js/dashboard_modules/dashboard_donations.js
 */

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