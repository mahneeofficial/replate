/**
 * RePlate Admin Helpers & UI Utility Functions
 */
function toggleModal(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    if (show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function showConfirmModal(title, description, onConfirmCallback) {
    const modalTitle = document.getElementById('modalTitle');
    const modalDesc = document.getElementById('modalDescription');
    const confirmBtn = document.getElementById('modalConfirmBtn');

    if (modalTitle) modalTitle.textContent = title;
    if (modalDesc) modalDesc.textContent = description;

    if (confirmBtn) {
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async () => {
            toggleModal('adminActionModal', false);
            if (typeof onConfirmCallback === 'function') {
                await onConfirmCallback();
            }
        });
    }

    toggleModal('adminActionModal', true);
}

function handleUnauthorizedAccess() {
    showToast('Admin session expired or unauthorized. Redirecting...', 'danger');
    setTimeout(() => {
        localStorage.removeItem('user');
        window.location.replace('/login');
    }, 1500);
}

function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function' && window.showToast !== showToast) {
        window.showToast(message, type);
        return;
    }

    const container = document.getElementById('toastContainer');
    if (!container) {
        alert(message);
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${escapeHTML(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 4000);
}

function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'approved' || s === 'collected' || s === 'completed') return 'badge-success';
    if (s === 'pending') return 'badge-warning';
    if (s === 'rejected' || s === 'cancelled') return 'badge-danger';
    return 'badge-secondary';
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return dateStr;
    }
}

function escapeHTML(str) {
    if (typeof str !== 'string') return str;
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

function escapeAttributeJS(str) {
    return JSON.stringify(str || '').replace(/"/g, '&quot;');
}