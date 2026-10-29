document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;

    const setVisible = (visible) => {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', `${visible ? 'Ocultar' : 'Mostrar'} ${button.dataset.passwordLabel}`);
        button.querySelector('[data-password-eye-slash]').classList.toggle('hidden', !visible);
    };

    button.classList.replace('hidden', 'flex');
    button.addEventListener('click', () => setVisible(input.type === 'password'));
    window.addEventListener('pageshow', () => setVisible(false));
});

document.querySelectorAll('[data-auth-actions]').forEach((container) => {
    const forms = [...container.querySelectorAll('[data-auth-form]')];
    const buttons = forms.flatMap((form) => [...form.querySelectorAll('button[type="submit"]')]);
    const status = container.querySelector('[data-submit-status]');
    const resend = container.querySelector('[data-resend-seconds]');
    const resendDeadline = Date.now() + Number(resend?.dataset.resendSeconds ?? 0) * 1000;
    const labels = new Map(buttons.map((button) => [button, button.querySelector('[data-button-label]').textContent]));
    const passwordForm = forms.find((form) => form.querySelector('[data-password-requirements]'));
    let submitting = false;

    const showRequirement = (element, state, message) => {
        element.classList.toggle('text-emerald-700', state === 'valid');
        element.classList.toggle('text-red-700', state === 'invalid');
        element.classList.toggle('text-[#526871]', state === 'pending');
        const icon = element.querySelector('[data-requirement-icon]');
        const label = element.querySelector('[data-requirement-label]');
        const symbol = state === 'valid' ? '✓' : state === 'invalid' ? '✕' : '○';
        if (icon.textContent !== symbol) icon.textContent = symbol;
        if (label.textContent !== message) label.textContent = message;
    };

    const updatePasswordRequirements = () => {
        if (!passwordForm) return true;

        const password = passwordForm.elements.namedItem('password');
        const confirmation = passwordForm.elements.namedItem('password_confirmation');
        const length = [...password.value].length;
        const validLength = length >= 8 && length <= 72;
        const hasUppercase = /\p{Lu}/u.test(password.value);
        const hasSymbol = /[\p{P}\p{S}]/u.test(password.value);
        const validPassword = validLength && hasUppercase && hasSymbol;
        const matches = confirmation.value.length > 0 && password.value === confirmation.value;
        const mismatch = confirmation.value.length > 0 && !matches;

        showRequirement(passwordForm.querySelector('[data-password-length]'), validLength ? 'valid' : 'pending',
            validLength ? 'Longitud válida: entre 8 y 72 caracteres.' : 'Entre 8 y 72 caracteres.');
        showRequirement(passwordForm.querySelector('[data-password-uppercase]'), hasUppercase ? 'valid' : 'pending',
            hasUppercase ? 'Incluye una letra mayúscula.' : 'Al menos una letra mayúscula.');
        showRequirement(passwordForm.querySelector('[data-password-symbol]'), hasSymbol ? 'valid' : 'pending',
            hasSymbol ? 'Incluye un símbolo.' : 'Al menos un símbolo, como !, @, # o $.');
        showRequirement(passwordForm.querySelector('[data-password-match]'), matches ? 'valid' : mismatch ? 'invalid' : 'pending',
            matches ? 'Las contraseñas coinciden.' : mismatch ? 'Las contraseñas no coinciden.' : 'Confirma tu contraseña para comprobar que coinciden.');

        let passwordError = '';
        if (password.value) {
            if (!validLength) passwordError = 'La contraseña debe contener entre 8 y 72 caracteres.';
            else if (!hasUppercase) passwordError = 'La contraseña debe contener al menos una letra mayúscula.';
            else if (!hasSymbol) passwordError = 'La contraseña debe contener al menos un símbolo.';
        }
        password.setCustomValidity(passwordError);
        confirmation.setCustomValidity(mismatch ? 'Las contraseñas no coinciden.' : '');
        confirmation.setAttribute('aria-invalid', String(mismatch));
        passwordForm.querySelector('button[type="submit"]').disabled = submitting || !validPassword || !matches;

        return validPassword && matches;
    };

    const secondsRemaining = () => Math.max(0, Math.ceil((resendDeadline - Date.now()) / 1000));

    const updateCountdown = () => {
        if (!resend || submitting) return;

        const seconds = secondsRemaining();
        resend.disabled = seconds > 0;
        resend.querySelector('[data-button-label]').textContent = seconds > 0
            ? `Reenviar código en ${seconds} s`
            : 'Reenviar código';
    };

    const restore = () => {
        submitting = false;
        forms.forEach((form) => form.removeAttribute('aria-busy'));
        buttons.forEach((button) => {
            button.disabled = false;
            button.querySelector('[data-button-label]').textContent = labels.get(button);
            button.querySelector('[data-loading-indicator]').classList.add('hidden');
        });
        status.textContent = '';
        updateCountdown();
        updatePasswordRequirements();
    };

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const button = event.submitter ?? form.querySelector('button[type="submit"]');

            if (submitting || (button === resend && secondsRemaining() > 0)) {
                event.preventDefault();
                return;
            }

            if (event.defaultPrevented || (form === passwordForm && !updatePasswordRequirements()) || !form.reportValidity()) {
                event.preventDefault();
                return;
            }

            submitting = true;
            form.setAttribute('aria-busy', 'true');
            buttons.forEach((control) => { control.disabled = true; });
            button.querySelector('[data-button-label]').textContent = button.dataset.loadingLabel;
            button.querySelector('[data-loading-indicator]').classList.remove('hidden');
            status.textContent = button.dataset.loadingLabel;
        });
    });

    window.addEventListener('pageshow', restore);
    if (passwordForm) {
        passwordForm.addEventListener('input', updatePasswordRequirements);
        passwordForm.addEventListener('change', updatePasswordRequirements);
        passwordForm.addEventListener('focusin', updatePasswordRequirements);
        passwordForm.addEventListener('reset', () => window.setTimeout(updatePasswordRequirements, 0));
    }
    document.addEventListener('visibilitychange', updateCountdown);
    if (resend) window.setInterval(updateCountdown, 1000);
    restore();
});
