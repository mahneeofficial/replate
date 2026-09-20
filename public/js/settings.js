document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Tab Switching
    initTabs();

    // 2. Fetch Initial User & Settings Data
    fetchSettings();

    // 3. Form Submit Listener
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings();
        });
    }

    // 4. Input Sanitization / Formatting
    const nameInput = document.getElementById('full_name');
    const phoneInput = document.getElementById('phone_number');

    if (nameInput) {
        nameInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z\s.-]/g, '');
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/(?!^\+)[^0-9\s-]/g, '');
        });
    }
});

function initTabs() {
    const tabButtons = document.querySelectorAll('.settings-tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');

            tabButtons.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const activePane = document.getElementById(targetTab);
            if (activePane) activePane.classList.add('active');
        });
    });
}

async function fetchSettings() {
    try {
        const response = await fetch('/api/settings.php');
        const result = await response.json();

        if (!response.ok) throw new Error(result.error || 'Failed to fetch settings');

        const data = result.data || {};

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val || '';
        };

        const setCheck = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.checked = Boolean(val);
        };

        // Populate Form Fields matching settings.html
        setVal('full_name', data.full_name || data.name || data.org_name);
        setVal('phone_number', data.phone_number);
        setVal('email', data.email);
        setVal('address', data.address || data.pickup_instructions);

        setCheck('notify_inapp', data.notify_inapp ?? true);
        setCheck('notify_whatsapp', data.notify_whatsapp ?? true);
        setCheck('notify_email', data.notify_email ?? true);

    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function saveSettings() {
    const getVal = (id) => document.getElementById(id)?.value.trim() || '';
    const getCheck = (id) => Boolean(document.getElementById(id)?.checked);

    const payload = {
        full_name: getVal('full_name'),
        phone_number: getVal('phone_number'),
        email: getVal('email'),
        address: getVal('address'),
        current_password: getVal('current_password'),
        new_password: getVal('new_password'),
        notify_inapp: getCheck('notify_inapp'),
        notify_whatsapp: getCheck('notify_whatsapp'),
        notify_email: getCheck('notify_email')
    };

    try {
        const response = await fetch('/api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Failed to save settings');

        showToast(result.message || 'Settings saved successfully.', 'success');

        // Reset password fields after successful save
        const currentPass = document.getElementById('current_password');
        const newPass = document.getElementById('new_password');
        if (currentPass) currentPass.value = '';
        if (newPass) newPass.value = '';

    } catch (err) {
        showToast(err.message, 'error');
    }
}

function showToast(message, type = 'success') {
    if (window.UIEngine && typeof window.UIEngine.showToast === 'function') {
        window.UIEngine.showToast(message, type);
    } else {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.style.cssText = `
            background: #151b28;
            color: ${type === 'success' ? '#10b981' : '#f43f5e'};
            border: 1px solid ${type === 'success' ? 'rgba(16,185,129,0.3)' : 'rgba(244,63,94,0.3)'};
            padding: 12px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        `;
        toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> <span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }
}