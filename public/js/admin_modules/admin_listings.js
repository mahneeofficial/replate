/**
 * RePlate Admin Listings and Requests Module
 */
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

        tbody.innerHTML = data.listings.map(l => {
            const safeFoodName = escapeAttributeJS(l.food_name || 'Unnamed Item');
            return `
                <tr>
                    <td><strong>${escapeHTML(l.food_name || 'Unnamed Item')}</strong> (${escapeHTML(l.quantity || '')} ${escapeHTML(l.unit || '')})</td>
                    <td>${escapeHTML(l.donor_name || 'Unknown Donor')}</td>
                    <td><span class="badge badge-secondary">${escapeHTML(l.category_name || 'Uncategorized')}</span></td>
                    <td>${formatDate(l.expiry_date)}</td>
                    <td>
                        <button class="btn-sm btn-danger" onclick="confirmRemoveListing(${l.donation_id}, ${safeFoodName})">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
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