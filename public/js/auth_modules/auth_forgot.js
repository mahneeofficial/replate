/**
 * RePlate Forgot Password Flow Controller
 * Location: public/js/auth_modules/auth_forgot.js
 */

function setupForgotPasswordFlow() {
    const fpLink = document.getElementById('forgotPasswordLink');
    if (!fpLink) return;

    const fpModal = document.getElementById('forgotPasswordModal');
    const closeFpModal = document.getElementById('closeFpModal');
    const fpRequestForm = document.getElementById('fpRequestForm');
    const fpResetForm = document.getElementById('fpResetForm');

    if (fpModal) {
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
}