/**
 * RePlate Recipient Requests Manager
 * Location: public/js/requests_modules/requests_my.js
 */

function renderMyRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 28px;">
                <p style="color: #94a3b8;">No claim requests found. Visit Dashboard or Food Donations to claim available surplus food.</p>
            </div>`;
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Food Item</th>
                        <th>Donor</th>
                        <th>Pickup Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;

    requests.forEach(req => {
        const requestId = req.request_id || req.id;
        const foodName = escapeHTML(req.food_name || 'Food Item');
        const donorName = escapeHTML(req.donor_name || req.organization_name || 'Community Donor');
        const location = escapeHTML(req.pickup_location || 'N/A');
        const rawStatus = req.status || 'pending';
        const statusClass = escapeHTML(rawStatus.toLowerCase());
        const isPending = statusClass === 'pending';

        const actionCell = isPending
            ? `<button class="btn-danger action-btn" data-action="cancel" data-id="${requestId}" style="padding: 6px 12px; font-size: 0.8rem;">Cancel Claim</button>`
            : `<span style="color: #94a3b8; font-size: 0.82rem;">—</span>`;

        html += `
            <tr>
                <td><strong>${foodName}</strong></td>
                <td>${donorName}</td>
                <td>${location}</td>
                <td><span class="status-badge ${statusClass}">${escapeHTML(rawStatus)}</span></td>
                <td>${actionCell}</td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;

    container.onclick = (e) => {
        const btn = e.target.closest('.action-btn');
        if (btn) {
            const requestId = btn.getAttribute('data-id');
            const action = btn.getAttribute('data-action');
            if (requestId && action) {
                handleRequestAction(requestId, action, btn);
            }
        }
    };
}