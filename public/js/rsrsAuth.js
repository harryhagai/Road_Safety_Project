document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-password-toggle');
            const input = document.getElementById(targetId);
            if (!input) return;

            const icon = button.querySelector('i');
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            if (icon) {
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            }
        });
    });

    const phoneInput = document.querySelector('[data-phone-input]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            let phone = this.value.replace(/\D/g, '');
            if (phone.startsWith('0')) {
                phone = phone.slice(1);
            }
            this.value = phone.slice(0, 9);
        });
    }

    const otpInput = document.querySelector('[data-otp-input]');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    const countdown = document.querySelector('[data-auth-countdown]');
    if (countdown) {
        let timeLeft = parseInt(countdown.getAttribute('data-auth-countdown'), 10) || 300;
        const output = countdown.querySelector('[data-countdown-output]');
        const unlockTargets = document.querySelectorAll('[data-enable-when-countdown-ends]');

        const render = () => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            if (output) {
                output.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            if (timeLeft <= 0) {
                unlockTargets.forEach((target) => {
                    target.disabled = false;
                });
                clearInterval(timer);
                return;
            }

            timeLeft -= 1;
        };

        const timer = setInterval(render, 1000);
        render();
    }

    const passwordField = document.querySelector('[data-password-rules-target]');
    const passwordRules = document.querySelectorAll('[data-password-rule]');
    if (passwordField && passwordRules.length) {
        const checks = {
            length: (value) => value.length >= 8,
            letter: (value) => /[A-Za-z]/.test(value),
            number: (value) => /\d/.test(value),
        };

        const updateRules = () => {
            const value = passwordField.value;
            passwordRules.forEach((rule) => {
                const ruleName = rule.getAttribute('data-password-rule');
                const valid = checks[ruleName] ? checks[ruleName](value) : false;
                rule.classList.toggle('is-valid', valid);
            });
        };

        passwordField.addEventListener('input', updateRules);
        updateRules();
    }
});
