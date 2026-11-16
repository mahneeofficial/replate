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
                    <td><strong>${escapeHTML(u.name || u.business_name || 'N/A')}</strong></td>
                    <td><small class="text-muted">${escapeHTML(u.email || 'N/A')}</small></td>
                    <td>${roleBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${verifyBadge}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-sm btn-outline-primary" onclick="viewUserDetails(${u.user_id})"><i class="fa-solid fa-eye"></i> Details</button>
                            ${isVerified
                                ? `<button class="btn-sm btn-outline-danger" onclick="confirmUserVerification(${u.user_id}, 0)">Unverify</button>`
                                : `<button class="btn-sm btn-outline-success" onclick="confirmUserVerification(${u.user_id}, 1)">Verify</button>`
                            }
                            ${isSuspended
                                ? `<button class="btn-sm btn-success" onclick="confirmUserStatus(${u.user_id}, 'Active')">Approve</button>`
                                : `<button class="btn-sm btn-danger" onclick="confirmUserStatus(${u.user_id}, 'Suspended')">Suspend</button>`
                            }
                            ${isAdmin
                                ? `<button class="btn-sm btn-warning" onclick="confirmAdminRoleToggle(${u.user_id}, 'donor')">Revoke Admin</button>`
                                : `<button class="btn-sm btn-info" onclick="confirmAdminRoleToggle(${u.user_id}, 'admin')">Make Admin</button>`
                            }
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        console.error('Failed to load users:', err);
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty text-danger">Failed to load user records.</td></tr>`;
    }
}

function viewUserDetails(userId) {
    const user = (window.adminUsersList || []).find(u => u.user_id == userId);
    const modalBody = document.getElementById('userDetailsBody');
    if (!user || !modalBody) return;

    modalBody.innerHTML = `
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>User ID:</strong> #${user.user_id}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Full Name:</strong> ${escapeHTML(user.name || 'N/A')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Business / Organization:</strong> ${escapeHTML(user.business_name || 'N/A')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Email:</strong> ${escapeHTML(user.email || 'N/A')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Phone Number:</strong> ${escapeHTML(user.phone || 'N/A')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Role:</strong> ${escapeHTML(user.role || 'User')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Account Status:</strong> ${escapeHTML(user.status || 'Active')}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Verification Status:</strong> ${parseInt(user.is_verified, 10) === 1 ? 'Verified' : 'Unverified'}</div>
        <div style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Address / Location:</strong> ${escapeHTML(user.address || 'N/A')}</div>
        <div style="padding: 8px 0;"><strong>Registration Date:</strong> ${formatDate(user.created_at)}</div>
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
    showConfirmModal(
        `Confirm Account ${status}`,
        `Are you sure you want to set status to "${status}" for user ID #${userId}?`,
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