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

    // Event delegation for action buttons (Claim and Delete)
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