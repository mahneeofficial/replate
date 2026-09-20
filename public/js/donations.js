/**
 * RePlate Food Surplus Controller
 * Location: public/js/donations.js
 */

document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    loadDonations();

    const form = document.getElementById('donationsPageForm');
    if (form) {
        form.addEventListener('submit', handleDonationSubmit);
    }

    // Event delegation for claim buttons (prevents inline onclick escaping bugs)
    const container = document.getElementById('userDonationsContainer');
    if (container) {
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-claim-donation');
            if (btn) {
                const id = btn.getAttribute('data-id');
                const name = btn.getAttribute('data-name');
                if (id && name) {
                    claimDonationFromPage(id, name);
                }
            }
        });
    }
});

function getUserRole() {
    try {
        const user = JSON.parse(localStorage.getItem('replate_user') || localStorage.getItem('user'));
        return (user?.role || user?.user_type || 'recipient').toLowerCase();
    } catch (e) {
        return 'recipient';
    }
}

async function loadCategories() {
    const select = document.getElementById('page_category_id');
    if (!select) return;

    try {
        let res = await fetch('/api/categories.php');
        if (!res.ok) {
            res = await fetch('/api/categories');
        }
        if (!res.ok) return;

        const data = await res.json();
        const categories = data.categories || (Array.isArray(data) ? data : []);

        if (categories.length > 0) {
            select.innerHTML = '<option value="">Select Category</option>' + 
                categories.map(c => `<option value="${c.category_id || c.id}">${escapeHTML(c.name)}</option>`).join('');
        }
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}

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

async function handleDonationSubmit(e) {
    e.preventDefault();

    const btn = document.getElementById('donationsSubmitBtn');
    if (btn) btn.disabled = true;

    const payload = {
        food_name: document.getElementById('page_food_name')?.value.trim(),
        category_id: document.getElementById('page_category_id')?.value,
        quantity: document.getElementById('page_quantity')?.value,
        unit: document.getElementById('page_unit')?.value.trim(),
        expiry_date: document.getElementById('page_expiry_date')?.value,
        pickup_location: document.getElementById('page_pickup_location')?.value.trim()
    };

    try {
        let res = await fetch('/api/donations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/donations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage('Donation posted successfully!', 'success');
            document.getElementById('donationsPageForm')?.reset();
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to post donation', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while posting donation', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function claimDonationFromPage(donationId, foodName) {
    if (!donationId) {
        showToastMessage('Invalid donation identifier.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to claim "${foodName}"?`)) return;

    try {
        let res = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', donation_id: donationId })
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/requests', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', donation_id: donationId })
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage('Donation claimed successfully!', 'success');
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to claim donation.', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while claiming item.', 'error');
    }
}

function parseLocalDate(dateString) {
    if (!dateString) return 'N/A';
    const parts = dateString.split(' ')[0].split('-');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        return new Date(year, month, day).toLocaleDateString();
    }
    return new Date(dateString).toLocaleDateString();
}

function showToastMessage(msg, type = 'info') {
    if (window.UIEngine && typeof window.UIEngine.showToast === 'function') {
        window.UIEngine.showToast(msg, type);
    } else if (typeof window.showToast === 'function') {
        window.showToast(msg, type);
    } else {
        alert(msg);
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}