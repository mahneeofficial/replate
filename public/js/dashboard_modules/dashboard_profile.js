/**
 * RePlate Dashboard Profile Loader
 * Location: public/js/dashboard_modules/dashboard_profile.js
 */

async function initUserProfile() {
    const cachedUser = DashboardUtils.getUser();

    if (cachedUser && (cachedUser.name || cachedUser.full_name || cachedUser.organization_name || cachedUser.email)) {
        renderUser(cachedUser);
    }

    try {
        const res = await fetch('/api/users?action=me');
        if (res.ok) {
            const data = await res.json();
            if (data && data.user) {
                const updatedUser = data.user;
                const currentRole = cachedUser ? (cachedUser.role || cachedUser.user_type) : null;
                const newRole = updatedUser.role || updatedUser.user_type;

                localStorage.setItem('replate_user', JSON.stringify(updatedUser));
                renderUser(updatedUser);

                if (currentRole && currentRole.toLowerCase() !== String(newRole).toLowerCase()) {
                    loadDonationListings();
                }
                return;
            }
        }
    } catch (e) {
        console.error('Failed to sync user profile with backend:', e);
    }

    if (!cachedUser) {
        renderFallbackUser();
    }
}

function renderUser(u) {
    const name = u.name || u.full_name || u.organization_name || u.username || 'Member Profile';
    const role = (u.role || u.user_type || 'recipient').toLowerCase().trim();
    
    const nameEl = document.getElementById('userNameDisplay');
    const roleEl = document.getElementById('userRoleDisplay');
    const welcomeEl = document.getElementById('welcomeHeading');
    const postBtn = document.getElementById('addDonationBtn');

    if (nameEl) nameEl.textContent = name;
    if (roleEl) roleEl.textContent = role.toUpperCase();
    if (welcomeEl) welcomeEl.textContent = `Welcome Back, ${name.split(' ')[0]}`;

    if (postBtn) {
        postBtn.style.display = (role === 'donor' || role === 'admin') ? 'inline-flex' : 'none';
    }
}

function renderFallbackUser() {
    const nameEl = document.getElementById('userNameDisplay');
    const roleEl = document.getElementById('userRoleDisplay');
    if (nameEl) nameEl.textContent = 'Guest User';
    if (roleEl) roleEl.textContent = 'RECIPIENT';
}