/**
 * RePlate Incoming & Role-Based Requests Manager
 * Location: public/js/requests_modules/requests_incoming.js
 */

let allCachedRequests = [];
let currentStatusFilter = 'all';

function getCurrentUserRole() {
    try {
        const rawUser = localStorage.getItem('replate_user') || localStorage.getItem('user');
        if (!rawUser) return 'recipient';
        const user = JSON.parse(rawUser);
        return (user.role || user.user_type || 'recipient').toLowerCase().trim();
    } catch (e) {
        return 'recipient';
    }
}

async function loadRequests() {
    const unifiedContainer = document.getElementById('requestsListContainer');
    const incomingContainer = document.getElementById('incomingRequestsContainer');
    const myRequestsContainer = document.getElementById('myRequestsContainer');
    const role = getCurrentUserRole();

    // Unified container in requests.html
    if (unifiedContainer) {
        const isDonor = (role === 'donor' || role === 'admin');
        const action = isDonor ? 'incoming' : 'my_requests';

        try {
            let res = await fetch(`/api/requests.php?action=${action}`);
            if (!res.ok && res.status === 404) {
                res = await fetch(`/api/requests?action=${action}`);
            }

            if (res.ok) {
                const data = await res.json();
                allCachedRequests = data.requests || (Array.isArray(data) ? data : []);
                applyRequestFilterAndRender();
            } else {
                const errData = await res.json().catch(() => ({}));
                renderErrorState(unifiedContainer, errData.error || 'Failed to load claim requests.');
            }
        } catch (err) {
            console.error('Error loading claim requests:', err);
            renderErrorState(unifiedContainer, 'Unable to connect to server.');
        }
        return;
    }

    // Fallback if split containers exist
    if (incomingContainer) {
        try {
            let res = await fetch('/api/requests.php?action=incoming');
            if (!res.ok) res = await fetch('/api/requests?action=incoming');
            if (res.ok) {
                const data = await res.json();
                renderIncomingRequests(data.requests || (Array.isArray(data) ? data : []), incomingContainer);
            } else {
                renderErrorState(incomingContainer, 'Failed to load incoming requests.');
            }
        } catch (err) {
            console.error('Error loading incoming requests:', err);
            renderErrorState(incomingContainer, 'Unable to connect to server.');
        }
    }

    if (myRequestsContainer) {
        try {
            let res = await fetch('/api/requests.php?action=my_requests');
            if (!res.ok) res = await fetch('/api/requests?action=my_requests');
            if (res.ok) {
                const data = await res.json();
                renderMyRequests(data.requests || (Array.isArray(data) ? data : []), myRequestsContainer);
            } else {
                renderErrorState(myRequestsContainer, 'Failed to load your requests.');
            }
        } catch (err) {
            console.error('Error loading my requests:', err);
            renderErrorState(myRequestsContainer, 'Unable to connect to server.');
        }
    }
}

function applyRequestFilterAndRender() {
    const unifiedContainer = document.getElementById('requestsListContainer');
    if (!unifiedContainer) return;

    const role = getCurrentUserRole();
    const isDonor = (role === 'donor' || role === 'admin');

    const filtered = currentStatusFilter === 'all'
        ? allCachedRequests
        : allCachedRequests.filter(r => (r.status || 'pending').toLowerCase() === currentStatusFilter.toLowerCase());

    if (isDonor) {
        renderIncomingRequests(filtered, unifiedContainer);
    } else {
        renderMyRequests(filtered, unifiedContainer);
    }
}

function initRequestFilters() {
    const filterBtns = document.querySelectorAll('.btn-filter');
    if (!filterBtns.length) return;

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentStatusFilter = btn.getAttribute('data-status') || 'all';
            applyRequestFilterAndRender();
        });
    });
}

function renderIncomingRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 28px;">
                <p style="color: #94a3b8;">No incoming claim requests found for this filter.</p>
            </div>`;
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Food Item</th>
                        <th>Requested By</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;

    requests.forEach(req => {
        const requestId = req.request_id || req.id;
        const rawStatus = req.status || 'pending';
        const status = rawStatus.toLowerCase();
        const isPending = status === 'pending';
        const foodName = escapeHTML(req.food_name || 'Food Item');
        const recipientName = escapeHTML(req.recipient_name || req.user_name || 'Recipient User');
        const recipientEmail = escapeHTML(req.recipient_email || req.email || '');
        const quantity = escapeHTML(`${req.quantity_requested || req.quantity || '1'}`);

        let actionButtons = `<span class="status-badge ${status}">${escapeHTML(rawStatus)}</span>`;
        if (isPending) {
            actionButtons = `
                <button class="btn-primary action-btn" data-action="approve" data-id="${requestId}" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 6px;">Approve</button>
                <button class="btn-danger action-btn" data-action="reject" data-id="${requestId}" style="padding: 6px 12px; font-size: 0.8rem;">Reject</button>
            `;
        } else if (status === 'approved' && req.whatsapp_url) {
            actionButtons = `
                <a href="${escapeHTML(req.whatsapp_url)}" target="_blank" rel="noopener noreferrer" class="btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 0.8rem; text-decoration: none; background: #10b981; color: #052e16;">
                    <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
                </a>
            `;
        }

        html += `
            <tr>
                <td><strong>${foodName}</strong></td>
                <td>${recipientName} ${recipientEmail ? `<span style="color:#94a3b8;font-size:0.82rem;">(${recipientEmail})</span>` : ''}</td>
                <td>${quantity}</td>
                <td><span class="status-badge ${status}">${escapeHTML(rawStatus)}</span></td>
                <td>${actionButtons}</td>
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