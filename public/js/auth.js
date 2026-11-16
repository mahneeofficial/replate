/**
 * RePlate Authentication Controller (Modular Entry Point)
 * Location: public/js/auth.js
 */

document.addEventListener('DOMContentLoaded', () => {
    setupPasswordGenerator();
    setupLoginForm();
    setupRegisterForm();
    setupForgotPasswordFlow();
    setupLogoutTriggers();
});