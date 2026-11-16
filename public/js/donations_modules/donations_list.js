/**
 * RePlate Donations Listings Renderer
 * Location: public/js/donations_modules/donations_list.js
 */

async function loadDonations() {
    const container = document.getElementById('userDonationsContainer');
    if (!container) return;

    const role = getUserRole();
    const heading = document.getElementById('donationsListHeading');
    
    if (heading) {
        heading.textContent = (role === 'donor') ? 'Your Posted Surplus' : 'Available Food Surplus';
    }

    const endpoint = (role === 'donor') 
        ? '/api/donations.php?action=my_listings' 
        : '/api/donations.php?action=list';

    try {
        let res = await fetch(endpoint);
        if (!res.ok) {
            const altEndpoint = (role === 'donor') ? '/api/donations?action=my_listings' : '/api/donations?action=list';
            res = await fetch(altEndpoint);
        }

        if (!res.ok) throw new Error(`HTTP error ${res.status}`);
        
        const data = await res.json();
        const items = data.donations || (Array.isArray(data) ? data : []);
        
        renderDonationListings(items, container, role);
    } catch (err) {
        console.error('Failed to load donations:', err);
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 24px;">
                <i class="fa-solid fa-box-open" style="font-size: 2rem; color: #64748b; margin-bottom: 8px;"></i>
                <p style="color: #94a3b8;">No active food donations available at the moment.</p>
            </div>
        `;
    }
}

function renderDonationListings(items, container, role) {
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 24px;">
                <p style="color: #94a3b8;">${role === 'donor' ? "You haven't posted any food donations yet." : "No available food surplus found."}</p>
            </div>`;
        return;
    }

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
        const donationId = item.donation_id || item.id;
        const foodNameRaw = item.food_name || 'Food Surplus';
        const foodNameEscaped = escapeHTML(foodNameRaw);
        const quantity = escapeHTML(`${item.quantity || ''} ${item.unit || ''}`.trim());
        const category = escapeHTML(item.category_name || item.category || 'General');
        const expiry = parseLocalDate(item.expiry_date);
        const status = item.status || 'Available';
        const statusLower = status.toLowerCase();

        let actionCell = `<span class="status-badge ${statusLower}">${escapeHTML(status)}</span>`;

        if (role === 'recipient' && statusLower === 'available') {
            actionCell = `<button class="btn-primary btn-claim-donation" style="padding: 6px 12px; font-size: 0.8rem;" data-id="${donationId}" data-name="${foodNameEscaped}">Claim</button>`;
        } else if ((role === 'donor' || role === 'admin') && statusLower === 'available') {
            actionCell = `
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span class="status-badge ${statusLower}">${escapeHTML(status)}</span>
                    <button class="btn-secondary btn-delete-donation" style="padding: 4px 8px; font-size: 0.75rem; color: #ef4444; border-color: #fca5a5;" data-id="${donationId}" data-name="${foodNameEscaped}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>`;
        }

        html += `
            <tr>
                <td><strong>${foodNameEscaped}</strong></td>
                <td>${quantity}</td>
                <td><span class="category-pill">${category}</span></td>
                <td>${expiry}</td>
                <td>${actionCell}</td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
}