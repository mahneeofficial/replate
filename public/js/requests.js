/**
 * RePlate Request Management Controller (Modular Entry Point)
 * Location: public/js/requests.js
 */

document.addEventListener('DOMContentLoaded', () => {
    if (typeof initRequestFilters === 'function') {
        initRequestFilters();
    }
    loadRequests();
});