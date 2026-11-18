/**
 * RePlate Admin User Moderation Module
 */
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading users...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=users');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.users || data.users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="table-empty">No registered users found.</td></tr>`;
            return;
        }

        window.adminUsersList = data.users;

        tbody.innerHTML = data.users.map(u => {
            const isVerified = parseInt(u.is_verified, 10) === 1;
            const isSuspended = (u.status || '').toLowerCase() === 'suspended';
            const isAdmin = (u.role || '').toLowerCase() === 'admin';
            const displayName = u.name || u.org_name || u.business_name || 'N/A';

            const verifyBadge = isVerified
                ? `<span class="badge badge-success"><i class="fa-solid fa-check"></i> Verified</span>`
                : `<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Unverified</span>`;

            const statusBadge = isSuspended
                ? `<span class="badge badge-danger">Suspended</span>`
                : `<span class="badge badge-success">Active</span>`;

            const roleBadge = isAdmin
                ? `<span class="badge badge-primary"><i class="fa-solid fa-shield"></i> Admin</span>`
                : `<span class="badge badge-role">${escapeHTML(u.role || 'User')}</span>`;

            return `
                <tr>
                    <td><strong>${escapeHTML(displayName)}</strong></td>
                    <td><small>${escapeHTML(u.email || 'N/A')}</small></td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${verifyBadge}</td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn-sm btn-outline-primary user-action-btn" data-op="details" data-id="${u.user_id}"><i class="fa-solid fa-eye"></i> Details</button>
                            ${isVerified
                                ? `<button type="button" class="btn-sm btn-outline-danger user-action-btn" data-op="verify" data-val="0" data-id="${u.user_id}">Unverify</button>`
                                : `<button type="button" class="btn-sm btn-outline-success user-action-btn" data-op="verify" data-val="1" data-id="${u.user_id}">Verify</button>`
                            }
                            ${isSuspended
                                ? `<button type="button" class="btn-sm btn-success user-action-btn" data-op="status" data-val="Active" data-id="${u.user_id}">Activate</button>`
                                : `<button type="button" class="btn-sm btn-warning user-action-btn" data-op="status" data-val="Suspended" data-id="${u.user_id}">Suspend</button>`
                            }
                            ${isAdmin
                                ? `<button type="button" class="btn-sm btn-outline-primary user-action-btn" data-op="role" data-val="donor" data-id="${u.user_id}">Revoke Admin</button>`
                                : `<button type="button" class="btn-sm btn-info user-action-btn" data-op="role" data-val="admin" data-id="${u.user_id}">Make Admin</button>`
                            }
                            <button type="button" class="btn-sm btn-danger user-action-btn" data-op="delete" data-name="${escapeHTML(displayName)}" data-id="${u.user_id}"><i class="fa-solid fa-user-xmark"></i> Remove</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.onclick = (e) => {
            const btn = e.target.closest('.user-action-btn');
            if (!btn) return;
            const userId = btn.getAttribute('data-id');
            const op = btn.getAttribute('data-op');
            const val = btn.getAttribute('data-val');

            if (op === 'details') viewUserDetails(userId);
            else if (op === 'verify') confirmUserVerification(userId, parseInt(val, 10));
            else if (op === 'status') confirmUserStatus(userId, val);
            else if (op === 'role') confirmAdminRoleToggle(userId, val);
            else if (op === 'delete') confirmDeleteUser(userId, btn.getAttribute('data-name') || 'this user');
        };
    } catch (err) {
        console.error('Failed to load users:', err);
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty text-danger">Failed to load user records.</td></tr>`;
    }
}

function viewUserDetails(userId) {
    const user = (window.adminUsersList || []).find(u => String(u.user_id) === String(userId));
    const modalBody = document.getElementById('userDetailsBody');
    if (!user || !modalBody) return;

    const rowStyle = 'padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; gap: 12px; font-size: 0.9rem;';

    modalBody.innerHTML = `
        <div style="${rowStyle}"><strong>User ID:</strong> <span>#${user.user_id}</span></div>
        <div style="${rowStyle}"><strong>Full Name:</strong> <span>${escapeHTML(user.name || 'N/A')}</span></div>
        <div style="${rowStyle}"><strong>Business / Organization:</strong> <span>${escapeHTML(user.org_name || user.business_name || 'N/A')}</span></div>
        <div style="${rowStyle}"><strong>Email:</strong> <span>${escapeHTML(user.email || 'N/A')}</span></div>
        <div style="${rowStyle}"><strong>Phone Number:</strong> <span>${escapeHTML(user.phone_number || user.phone || 'Not provided')}</span></div>
        <div style="${rowStyle}"><strong>Role:</strong> <span style="text-transform: capitalize;">${escapeHTML(user.role || 'User')}</span></div>
        <div style="${rowStyle}"><strong>Account Status:</strong> <span>${escapeHTML(user.status || 'Active')}</span></div>
        <div style="${rowStyle}"><strong>Verification:</strong> <span>${parseInt(user.is_verified, 10) === 1 ? 'Verified' : 'Unverified'}</span></div>
        <div style="${rowStyle}"><strong>Address / Pickup Notes:</strong> <span>${escapeHTML(user.pickup_instructions || user.address || 'Not provided')}</span></div>
        <div style="padding: 9px 0; display: flex; justify-content: space-between; font-size: 0.9rem;"><strong>Registered:</strong> <span>${formatDate(user.created_at)}</span></div>
    `;

    toggleModal('userDetailsModal', true);
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
    const extraMsg = status === 'Suspended'
        ? ' They will immediately be blocked from logging in and shown a suspension notice.'
        : ' They will regain full access to RePlate.';
    showConfirmModal(
        `Confirm Account ${status}`,
        `Are you sure you want to set status to "${status}" for user ID #${userId}?${extraMsg}`,
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

function confirmAdminRoleToggle(userId, newRole) {
    const actionText = newRole === 'admin' ? 'grant Admin rights to' : 'revoke Admin rights from';
    showConfirmModal(
        'Confirm Role Change',
        `Are you sure you want to ${actionText} user ID #${userId}?`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=update_user_role', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, role: newRole })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'User role updated successfully.', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to update user role.', 'danger');
                }
            } catch (err) {
                showToast('Network error updating user role.', 'danger');
            }
        }
    );
}

function confirmDeleteUser(userId, displayName) {
    showConfirmModal(
        'Permanently Remove User Account',
        `Are you sure you want to permanently remove "${displayName}" (#${userId}) from RePlate? All their listings, requests, and active sessions will be deleted immediately.`,
        async () => {
            try {
                const res = await fetch('/api/admin?action=delete_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'User removed from platform.', 'success');
                    loadUsers();
                } else {
                    showToast(data.error || 'Failed to remove user.', 'danger');
                }
            } catch (err) {
                showToast('Network error removing user account.', 'danger');
            }
        }
    );
}