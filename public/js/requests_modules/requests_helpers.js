/**
 * RePlate Request Helpers & UI Utilities
 * Location: public/js/requests_modules/requests_helpers.js
 */

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