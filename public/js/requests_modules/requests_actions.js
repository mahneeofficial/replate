/**
 * RePlate Request Action Handler (Approve / Reject / Cancel)
 * Location: public/js/requests_modules/requests_actions.js
 */

async function handleRequestAction(requestId, action, triggerBtn = null) {
    if (!requestId) {
        showToastMessage('Invalid request identifier.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to ${action} this request?`)) return;

    if (triggerBtn) {
        triggerBtn.disabled = true;
        triggerBtn.style.opacity = '0.6';
    }

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
            const verb = action === 'cancel' ? 'cancelled' : `${action}d`;
            showToastMessage(`Request ${verb} successfully!`, 'success');
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadRequests();
        } else {
            showToastMessage(data.error || `Failed to ${action} request`, 'error');
            if (triggerBtn) {
                triggerBtn.disabled = false;
                triggerBtn.style.opacity = '1.0';
            }
        }
    } catch (err) {
        showToastMessage('Server error processing request', 'error');
        if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.style.opacity = '1.0';
        }
    }
}