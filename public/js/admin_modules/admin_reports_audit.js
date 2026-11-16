/**
 * RePlate Admin Reports, Notifications, and Audit Logs Module
 */
window.exportReport = async function (type) {
    showToast(`Preparing ${type.toUpperCase()} report export...`, 'info');

    try {
        let action = '';
        let filename = `RePlate_${type}_Report_${new Date().toISOString().slice(0, 10)}.csv`;

        if (type === 'impact') action = 'listings';
        else if (type === 'users') action = 'users';
        else if (type === 'claims') action = 'donation_requests';
        else action = 'reports';

        const res = await fetch(`/api/admin?action=${action}`);
        const data = await res.json();

        if (!data.success) {
            showToast('Failed to retrieve report data from server.', 'danger');
            return;
        }

        let dataset = data.listings || data.users || data.requests || data.reports || [];

        if (dataset.length === 0) {
            showToast('No records found to generate CSV report.', 'warning');
            return;
        }

        const headers = Object.keys(dataset[0]);
        const csvRows = [headers.join(',')];

        dataset.forEach(row => {
            const values = headers.map(header => {
                const val = row[header] === null || row[header] === undefined ? '' : row[header];
                const escaped = ('' + val).replace(/"/g, '""');
                return `"${escaped}"`;
            });
            csvRows.push(values.join(','));
        });

        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const downloadAnchor = document.createElement('a');
        downloadAnchor.setAttribute('href', url);
        downloadAnchor.setAttribute('download', filename);
        document.body.appendChild(downloadAnchor);
        downloadAnchor.click();
        downloadAnchor.remove();
        URL.revokeObjectURL(url);

        showToast(`Report downloaded successfully: ${filename}`, 'success');
    } catch (err) {
        console.error('CSV Generation Error:', err);
        showToast('Failed to generate report export.', 'danger');
    }
};

async function handleSendNotificationSubmit(e) {
    e.preventDefault();

    const targetSelect = document.getElementById('notifTarget');
    const titleInput = document.getElementById('notifTitle');
    const messageInput = document.getElementById('notifMessage');

    if (!titleInput || !titleInput.value.trim() || !messageInput || !messageInput.value.trim()) {
        showToast('Title and Message are required for broadcast.', 'warning');
        return;
    }

    const payload = {
        target: targetSelect ? targetSelect.value : 'all',
        title: titleInput.value.trim(),
        message: messageInput.value.trim(),
        type: 'info'
    };

    try {
        const res = await fetch('/api/admin?action=send_notification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Notification broadcast sent successfully!', 'success');
            titleInput.value = '';
            messageInput.value = '';
        } else {
            showToast(data.error || 'Failed to send notification.', 'danger');
        }
    } catch (err) {
        showToast('Network error sending system notification.', 'danger');
    }
}

async function loadAuditLogs() {
    const tbody = document.getElementById('auditTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4" class="table-empty"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading system logs...</td></tr>`;

    try {
        const res = await fetch('/api/admin?action=audit_logs');
        const data = await res.json();

        if (res.status === 403) return handleUnauthorizedAccess();

        if (!data.success || !data.logs || data.logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="table-empty">No system audit logs found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.logs.map(log => `
            <tr>
                <td>${formatDate(log.created_at)}</td>
                <td><strong>${escapeHTML(log.title || 'System Event')}</strong><br><small>${escapeHTML(log.message || '')}</small></td>
                <td>${escapeHTML(log.user_name || 'System')} (${escapeHTML(log.email || '')})</td>
                <td><span class="badge badge-info">${escapeHTML(log.type || 'info')}</span></td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Failed to load audit logs:', err);
        tbody.innerHTML = `<tr><td colspan="4" class="table-empty text-danger">Failed to load system audit logs.</td></tr>`;
    }
}