/**
 * RePlate Request Management Controller
 * Location: public/js/requests.js
 */

document.addEventListener('DOMContentLoaded', () => {
    loadRequests();
});

async function loadRequests() {
    const incomingContainer = document.getElementById('incomingRequestsContainer');
    const myRequestsContainer = document.getElementById('myRequestsContainer');

    if (incomingContainer) {
        try {
            let res = await fetch('/api/requests.php?action=incoming');
            if (!res.ok) {
                res = await fetch('/api/requests?action=incoming');
            }
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
            if (!res.ok) {
                res = await fetch('/api/requests?action=my_requests');
            }
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

function renderIncomingRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 20px;">
                <p style="color: #94a3b8;">No pending claim requests for your food listings.</p>
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
        const status = (req.status || 'pending').toLowerCase();
        const isPending = status === 'pending';
        const foodName = escapeHTML(req.food_name || 'Food Item');
        const recipientName = escapeHTML(req.recipient_name || req.user_name || 'Anonymous User');
        const recipientEmail = escapeHTML(req.recipient_email || req.email || '');
        const quantity = escapeHTML(`${req.quantity_requested || req.quantity || '1'}`);

        const actionButtons = isPending ? `
            <button class="btn-success" style="padding: 4px 8px; font-size: 0.8rem; margin-right: 4px;" onclick="handleRequestAction(${requestId}, 'approve')">Approve</button>
            <button class="btn-danger" style="padding: 4px 8px; font-size: 0.8rem;" onclick="handleRequestAction(${requestId}, 'reject')">Reject</button>
        ` : `<span class="status-badge ${status}">${escapeHTML(req.status)}</span>`;

        html += `
            <tr>
                <td><strong>${foodName}</strong></td>
                <td>${recipientName} ${recipientEmail ? `(${recipientEmail})` : ''}</td>
                <td>${quantity}</td>
                <td><span class="status-badge ${status}">${escapeHTML(req.status)}</span></td>
                <td>${actionButtons}</td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
}

function renderMyRequests(requests, container) {
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 20px;">
                <p style="color: #94a3b8;">You haven't requested any food donations yet.</p>
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
                    </tr>
                </thead>
                <tbody>`;

    requests.forEach(req => {
        const foodName = escapeHTML(req.food_name || 'Food Item');
        const donorName = escapeHTML(req.donor_name || req.organization_name || 'Community Donor');
        const location = escapeHTML(req.pickup_location || 'N/A');
        const statusClass = escapeHTML((req.status || 'pending').toLowerCase());

        html += `
            <tr>
                <td><strong>${foodName}</strong></td>
                <td>${donorName}</td>
                <td>${location}</td>
                <td><span class="status-badge ${statusClass}">${escapeHTML(req.status)}</span></td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
}

async function handleRequestAction(requestId, action) {
    if (!requestId) {
        showToastMessage('Invalid request identifier.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to ${action} this request?`)) return;

    try {
        let res = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, request_id: requestId })
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/requests', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, request_id: requestId })
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage(`Request ${action}d successfully!`, 'success');
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadRequests();
        } else {
            showToastMessage(data.error || `Failed to ${action} request`, 'error');
        }
    } catch (err) {
        showToastMessage(`Server error processing request`, 'error');
    }
}

function renderErrorState(container, message) {
    if (!container) return;
    container.innerHTML = `
        <div class="empty-state" style="text-align: center; padding: 20px;">
            <p style="color: #ef4444;">${escapeHTML(message)}</p>
        </div>`;
}

function showToastMessage(msg, type = 'info') {
    if (window.UIEngine && typeof window.UIEngine.showToast === 'function') {
        window.UIEngine.showToast(msg, type);
    } else if (typeof window.showToast === 'function') {
        window.showToast(msg, type);
    } else {
        console.log(`[Toast - ${type}]: ${msg}`);
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