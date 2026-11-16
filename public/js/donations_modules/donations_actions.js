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