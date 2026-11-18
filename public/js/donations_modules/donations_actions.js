/**
 * RePlate Donations Actions & Submissions
 * Location: public/js/donations_modules/donations_actions.js
 */

async function handleDonationSubmit(e) {
    e.preventDefault();

    const btn = document.getElementById('donationsSubmitBtn');
    if (btn) btn.disabled = true;

    const payload = {
        food_name: document.getElementById('page_food_name')?.value.trim(),
        category_id: document.getElementById('page_category_id')?.value,
        quantity: document.getElementById('page_quantity')?.value,
        unit: document.getElementById('page_unit')?.value.trim(),
        expiry_date: document.getElementById('page_expiry_date')?.value,
        pickup_location: document.getElementById('page_pickup_location')?.value.trim()
    };

    try {
        let res = await fetch('/api/donations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/donations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage('Donation posted successfully!', 'success');
            document.getElementById('donationsPageForm')?.reset();
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to post donation', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while posting donation', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

function openEditDonationModal(donationId) {
    const item = (window.cachedDonationsList || []).find(d => String(d.donation_id || d.id) === String(donationId));
    const modal = document.getElementById('editDonationModal');
    if (!item || !modal) return;

    document.getElementById('edit_donation_id').value = item.donation_id || item.id;
    document.getElementById('edit_food_name').value = item.food_name || '';
    document.getElementById('edit_category_id').value = item.category_id || '';
    document.getElementById('edit_quantity').value = item.quantity || '';
    document.getElementById('edit_unit').value = item.unit || '';
    document.getElementById('edit_pickup_location').value = item.pickup_location || '';

    if (item.expiry_date) {
        const formattedForInput = String(item.expiry_date).replace(' ', 'T').slice(0, 16);
        document.getElementById('edit_expiry_date').value = formattedForInput;
    }

    modal.classList.remove('hidden');
}

function closeEditDonationModal() {
    const modal = document.getElementById('editDonationModal');
    if (modal) modal.classList.add('hidden');
}

async function handleEditDonationSubmit(e) {
    e.preventDefault();

    const btn = document.getElementById('saveEditDonationBtn');
    if (btn) btn.disabled = true;

    const payload = {
        action: 'update',
        donation_id: document.getElementById('edit_donation_id')?.value,
        food_name: document.getElementById('edit_food_name')?.value.trim(),
        category_id: document.getElementById('edit_category_id')?.value,
        quantity: document.getElementById('edit_quantity')?.value,
        unit: document.getElementById('edit_unit')?.value.trim(),
        expiry_date: document.getElementById('edit_expiry_date')?.value,
        pickup_location: document.getElementById('edit_pickup_location')?.value.trim()
    };

    try {
        let res = await fetch('/api/donations.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/donations?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage(data.message || 'Food listing updated successfully!', 'success');
            closeEditDonationModal();
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to update food listing.', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while updating listing.', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function claimDonationFromPage(donationId, foodName) {
    if (!donationId) {
        showToastMessage('Invalid donation identifier.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to claim "${foodName}"?`)) return;

    try {
        let res = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', donation_id: donationId })
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/requests', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', donation_id: donationId })
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage('Donation claimed successfully!', 'success');
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to claim donation.', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while claiming item.', 'error');
    }
}

async function deleteDonationFromPage(donationId, foodName) {
    if (!donationId) {
        showToastMessage('Invalid donation identifier.', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to remove listing "${foodName}"?`)) return;

    try {
        let res = await fetch('/api/donations.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ donation_id: donationId })
        });

        if (!res.ok && res.status === 404) {
            res = await fetch('/api/donations', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ donation_id: donationId })
            });
        }

        const data = await res.json();

        if (res.ok && data.success) {
            showToastMessage('Donation listing removed successfully.', 'success');
            window.dispatchEvent(new Event('replate_notifications_updated'));
            loadDonations();
        } else {
            showToastMessage(data.error || 'Failed to remove donation listing.', 'error');
        }
    } catch (err) {
        showToastMessage('Server error while deleting item.', 'error');
    }
}