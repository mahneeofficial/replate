/**
 * RePlate Food Surplus Controller (Modular Entry Point)
 * Location: public/js/donations.js
 */

document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    loadDonations();

    const form = document.getElementById('donationsPageForm');
    if (form) {
        form.addEventListener('submit', handleDonationSubmit);
    }

    // Live Search & Filter Listeners
    ['filterSearchInput', 'filterCategorySelect', 'filterMinQtyInput', 'filterExpiryBeforeInput'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', applyDonationFilters);
            el.addEventListener('change', applyDonationFilters);
        }
    });

    const resetFiltersBtn = document.getElementById('resetDonationFiltersBtn');
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            const searchEl = document.getElementById('filterSearchInput');
            const catEl = document.getElementById('filterCategorySelect');
            const qtyEl = document.getElementById('filterMinQtyInput');
            const expEl = document.getElementById('filterExpiryBeforeInput');
            if (searchEl) searchEl.value = '';
            if (catEl) catEl.value = '';
            if (qtyEl) qtyEl.value = '';
            if (expEl) expEl.value = '';
            applyDonationFilters();
        });
    }

    // Edit Modal Listeners
    const editForm = document.getElementById('editDonationForm');
    if (editForm) {
        editForm.addEventListener('submit', handleEditDonationSubmit);
    }
    document.getElementById('closeEditDonationModalBtn')?.addEventListener('click', closeEditDonationModal);
    document.getElementById('cancelEditDonationBtn')?.addEventListener('click', closeEditDonationModal);

    // Event delegation for action buttons (Claim, Edit, and Delete)
    const container = document.getElementById('userDonationsContainer');
    if (container) {
        container.addEventListener('click', (e) => {
            const claimBtn = e.target.closest('.btn-claim-donation');
            if (claimBtn) {
                const id = claimBtn.getAttribute('data-id');
                const name = claimBtn.getAttribute('data-name');
                if (id && name) {
                    claimDonationFromPage(id, name);
                }
                return;
            }

            const editBtn = e.target.closest('.btn-edit-donation');
            if (editBtn) {
                const id = editBtn.getAttribute('data-id');
                if (id) {
                    openEditDonationModal(id);
                }
                return;
            }

            const deleteBtn = e.target.closest('.btn-delete-donation');
            if (deleteBtn) {
                const id = deleteBtn.getAttribute('data-id');
                const name = deleteBtn.getAttribute('data-name');
                if (id && name) {
                    deleteDonationFromPage(id, name);
                }
            }
        });
    }
});