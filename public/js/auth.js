/**
 * RePlate Authentication Engine
 * Location: public/js/auth.js
 */

const AuthEngine = {
    endpoints: {
        users: '/api/users.php',
        donations: '/api/donations.php',
        forgotPassword: '/api/forgot_password.php'
    },

    errorDictionary: {
        'ERR_VAL_01': 'Email address and password are required.',
        'ERR_VAL_02': 'Please enter a valid email address format.',
        'ERR_VAL_03': 'Password must be at least 8 characters long.',
        'ERR_VAL_04': 'Passwords do not match.',
        'ERR_VAL_05': 'Full name is required for registration.',
        'ERR_VAL_06': 'Please select an account type.',
        
        'ERR_AUTH_01': 'Invalid email or password. Please check your credentials and try again.',
        'ERR_AUTH_02': 'No account found with this email address.',
        'ERR_AUTH_03': 'Account suspended or unauthorized. Please contact support for assistance.',
        'ERR_AUTH_04': 'Email verification required. Please verify your email before logging in.',
        'ERR_AUTH_05': 'Your session has expired. Please log in again.',
        
        'ERR_SEC_01': 'Temporary or disposable email addresses are not permitted.',
        'ERR_SEC_02': 'An account with this email address already exists.',
        'ERR_SEC_03': 'Security token validation failed. Please refresh the page.',
        'ERR_SEC_04': 'Too many failed login attempts. Account temporarily locked.',
        'ERR_SEC_05': 'Rate limit exceeded. Please wait a few minutes before trying again.',
        
        'ERR_SYS_01': 'Internal database error. Please try again later.',
        'ERR_SYS_02': 'Invalid API action requested.',
        'ERR_SYS_03': 'Unable to connect to server. Please check your network connection.',
        'ERR_SYS_04': 'API endpoint missing or route not found.'
    },

    parseErrorMessage(rawError) {
        if (!rawError) return 'An unexpected error occurred. Please try again.';

        let errStr = rawError;
        if (typeof rawError === 'object') {
            errStr = rawError.message || rawError.error || rawError.code || JSON.stringify(rawError);
        }

        if (typeof errStr === 'string' && errStr.includes(':')) {
            const parts = errStr.split(':');
            const customMessage = parts.slice(1).join(':').trim();
            if (customMessage) return customMessage;
        }

        for (const [code, userMessage] of Object.entries(this.errorDictionary)) {
            if (typeof errStr === 'string' && errStr.includes(code)) {
                return userMessage;
            }
        }

        return typeof errStr === 'string' ? errStr.replace(/^ERR_[A-Z0-9_]+:\s*/, '') : 'An error occurred.';
    },

    async _handleResponse(response) {
        const responseText = await response.text();
        let data = {};

        try {
            data = responseText ? JSON.parse(responseText) : {};
        } catch (e) {
            if (responseText.trim().startsWith('<?php')) {
                throw new Error('Server Router Error: PHP source code was served unexecuted. Check router.php.');
            }
            if (!response.ok) {
                throw new Error(`Server Error (${response.status}): ${responseText.substring(0, 120)}`);
            }
            throw new Error(this.parseErrorMessage('ERR_SYS_01'));
        }

        if (!response.ok) {
            if (response.status === 404) {
                throw new Error(this.parseErrorMessage('ERR_SYS_04'));
            }
            const rawMessage = data.error || data.message || 'ERR_SYS_01';
            throw new Error(this.parseErrorMessage(rawMessage));
        }

        return data;
    },

    async register(userData, options = {}) {
        const { targetId = 'formAlert', suppressToast = false, rethrow = true } = options;
        if (!suppressToast && window.UIEngine) window.UIEngine.hideAlert(targetId);

        try {
            const response = await fetch(this.endpoints.users, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'register', ...userData })
            });

            const data = await this._handleResponse(response);
            
            if (!suppressToast && window.UIEngine) {
                window.UIEngine.showAlert('Account created successfully! Redirecting to login...', 'success', targetId);
            }

            setTimeout(() => { window.location.href = '/login'; }, 1500);
            return data;
        } catch (error) {
            if (!suppressToast && window.UIEngine) window.UIEngine.showAlert(error.message, 'error', targetId);
            if (rethrow) throw error;
            return null;
        }
    },

    async login(credentials, options = {}) {
        const { targetId = 'alertBox', suppressToast = false } = options;
        if (!suppressToast && window.UIEngine) window.UIEngine.hideAlert(targetId);

        try {
            const response = await fetch(this.endpoints.users, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'login', ...credentials })
            });

            const data = await this._handleResponse(response);
            
            if (!suppressToast && window.UIEngine) {
                window.UIEngine.showAlert('Login successful! Redirecting...', 'success', targetId);
            }

            if (data.user) {
                localStorage.setItem('replate_user', JSON.stringify(data.user));
            }

            setTimeout(() => {
                const redirectUrl = data.user && data.user.role === 'admin' ? '/admin-dashboard' : '/dashboard';
                window.location.href = redirectUrl;
            }, 1200);

            return data;
        } catch (error) {
            if (!suppressToast && window.UIEngine) window.UIEngine.showAlert(error.message, 'error', targetId);
            return null;
        }
    },

    async getCurrentUser() {
        try {
            const response = await fetch(`${this.endpoints.users}?action=me`, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            return await this._handleResponse(response);
        } catch (error) {
            return null;
        }
    },

    async logout() {
        try {
            await fetch(`${this.endpoints.users}?action=logout`, { method: 'POST' });
        } catch (e) {
            // Proceed with client side cleanup regardless of network status
        } finally {
            localStorage.removeItem('replate_user');
            window.location.href = '/login';
        }
    },

    async requestPasswordReset(email) {
        if (window.UIEngine) window.UIEngine.hideAlert('fpStatusBox');
        try {
            const response = await fetch(this.endpoints.forgotPassword, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'request', email })
            });
            return await this._handleResponse(response);
        } catch (error) {
            if (window.UIEngine) window.UIEngine.showAlert(error.message, 'error', 'fpStatusBox');
            return null;
        }
    },

    async confirmPasswordReset(email, token, password) {
        if (window.UIEngine) window.UIEngine.hideAlert('fpStatusBox');
        try {
            const response = await fetch(this.endpoints.forgotPassword, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset', email, token, password })
            });
            return await this._handleResponse(response);
        } catch (error) {
            if (window.UIEngine) window.UIEngine.showAlert(error.message, 'error', 'fpStatusBox');
            return null;
        }
    }
};

window.AuthEngine = AuthEngine;

document.addEventListener('DOMContentLoaded', () => {
    // Password Generator Trigger
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

    // Login Form
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

    // Registration Form
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

    // Forgot Password Flow
    const fpLink = document.getElementById('forgotPasswordLink');
    const fpModal = document.getElementById('forgotPasswordModal');
    const closeFpModal = document.getElementById('closeFpModal');
    const fpRequestForm = document.getElementById('fpRequestForm');
    const fpResetForm = document.getElementById('fpResetForm');

    if (fpLink && fpModal) {
        fpLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.UIEngine) window.UIEngine.hideAlert('fpStatusBox');
            fpModal.style.display = 'flex';
            if (fpRequestForm) fpRequestForm.style.display = 'block';
            if (fpResetForm) fpResetForm.style.display = 'none';

            const currentLoginEmail = document.getElementById('email')?.value.trim();
            if (currentLoginEmail && document.getElementById('fpEmail')) {
                document.getElementById('fpEmail').value = currentLoginEmail;
            }
        });

        if (closeFpModal) {
            closeFpModal.addEventListener('click', () => { fpModal.style.display = 'none'; });
        }

        fpModal.addEventListener('click', (e) => {
            if (e.target === fpModal) fpModal.style.display = 'none';
        });

        if (fpRequestForm) {
            fpRequestForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = document.getElementById('fpEmail')?.value.trim();
                const btn = document.getElementById('fpRequestBtn');

                if (!email) {
                    if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_02'), 'error', 'fpStatusBox');
                    return;
                }

                if (btn) { btn.disabled = true; btn.innerText = 'Sending Code...'; }
                const result = await AuthEngine.requestPasswordReset(email);
                if (btn) { btn.disabled = false; btn.innerText = 'Send Verification Code'; }

                if (result && result.success) {
                    if (window.UIEngine) window.UIEngine.showAlert(result.message || 'Verification code sent to your email!', 'success', 'fpStatusBox');
                    fpRequestForm.style.display = 'none';
                    if (fpResetForm) fpResetForm.style.display = 'block';
                }
            });
        }

        if (fpResetForm) {
            fpResetForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = document.getElementById('fpEmail')?.value.trim();
                const token = document.getElementById('fpCode')?.value.trim();
                const password = document.getElementById('fpNewPassword')?.value;
                const btn = document.getElementById('fpResetBtn');

                if (!token || token.length !== 6) {
                    if (window.UIEngine) window.UIEngine.showAlert('Please enter the full 6-digit code sent to your email.', 'error', 'fpStatusBox');
                    return;
                }
                if (!password || password.length < 8) {
                    if (window.UIEngine) window.UIEngine.showAlert(AuthEngine.parseErrorMessage('ERR_VAL_03'), 'error', 'fpStatusBox');
                    return;
                }

                if (btn) { btn.disabled = true; btn.innerText = 'Updating Password...'; }
                const result = await AuthEngine.confirmPasswordReset(email, token, password);
                if (btn) { btn.disabled = false; btn.innerText = 'Update Password'; }

                if (result && result.success) {
                    if (window.UIEngine) window.UIEngine.showAlert('Password updated successfully! You can now log in.', 'success', 'fpStatusBox');
                    setTimeout(() => { fpModal.style.display = 'none'; }, 2000);
                }
            });
        }
    }

    // Global Logout Trigger
    const logoutBtns = document.querySelectorAll('#logoutBtn, .btn-logout');
    logoutBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            AuthEngine.logout();
        });
    });
});