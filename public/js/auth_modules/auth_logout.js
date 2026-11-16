/**
 * RePlate Logout Controller
 * Location: public/js/auth_modules/auth_logout.js
 */

function setupLogoutTriggers() {
    const logoutBtns = document.querySelectorAll('#logoutBtn, .btn-logout');
    logoutBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            AuthEngine.logout();
        });
    });
}