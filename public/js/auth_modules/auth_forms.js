/**
 * RePlate Authentication Forms Controller
 * Location: public/js/auth_modules/auth_forms.js
 */

function setupPasswordGenerator() {
    const genPassBtn = document.getElementById('generatePasswordBtn');
    if (genPassBtn) {
        genPassBtn.addEventListener('click', () => {
            const newPassword = window.UIEngine ? window.UIEngine.generateStrongPassword(16) : Math.random().toString(36).slice(-10);
            const passInput = document.getElementById('password') || document.getElementById('regPassword');
            const confirmPassInput = document.getElementById('confirmPassword') || document.getElementById('regConfirmPassword');
            
            if (passInput) {
                passInput.value = newPassword;
                passInput.type = 'text';
                passInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (confirmPassInput) {
                confirmPassInput.value = newPassword;
                confirmPassInput.type = 'text';
                confirmPassInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            if (window.UIEngine) window.UIEngine.showToast('Strong password generated!', 'success');
        });
    }
}

function setupLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn') || loginForm.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerText : 'Log In';
            
            const email = document.getElementById('email')?.value.trim();
            const password = document.getElementById('password')?.value;
            const remember = document.getElementById('rememberMe')?.checked || false;

            if (!email || !password) {
                if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_01'), 'error', 'alertBox');
                return;
            }

            try {
                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerText = 'Logging in...'; }
                await AuthEngine.login({ email, password, remember }, { targetId: 'alertBox' });
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = originalText; }
            }
        });
    }
}

function setupRegisterForm() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('regSubmitBtn') || registerForm.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerText : 'Sign Up';

            const name = (document.getElementById('name') || document.getElementById('fullName'))?.value.trim();
            const email = (document.getElementById('regEmail') || document.getElementById('email'))?.value.trim();
            const password = (document.getElementById('regPassword') || document.getElementById('password'))?.value;
            const confirmPassword = (document.getElementById('regConfirmPassword') || document.getElementById('confirmPassword'))?.value;
            const role = (document.getElementById('role') || document.getElementById('accountType'))?.value || 'recipient';

            if (!name) { if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_05'), 'error', 'formAlert'); return; }
            if (!email) { if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_02'), 'error', 'formAlert'); return; }
            if (!password || password.length < 8) { if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_03'), 'error', 'formAlert'); return; }
            if (confirmPassword !== undefined && password !== confirmPassword) { if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_04'), 'error', 'formAlert'); return; }

            try {
                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerText = 'Creating Account...'; }
                await AuthEngine.register({ name, email, password, role }, { targetId: 'formAlert', rethrow: false });
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = originalText; }
            }
        });
    }
}