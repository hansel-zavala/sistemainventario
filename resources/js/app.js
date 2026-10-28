document.querySelectorAll('[data-auth-actions]').forEach((container) => {
    const forms = [...container.querySelectorAll('[data-auth-form]')];
    const buttons = forms.flatMap((form) => [...form.querySelectorAll('button[type="submit"]')]);
    const status = container.querySelector('[data-submit-status]');
    const resend = container.querySelector('[data-resend-seconds]');
    const resendDeadline = Date.now() + Number(resend?.dataset.resendSeconds ?? 0) * 1000;
    const labels = new Map(buttons.map((button) => [button, button.querySelector('[data-button-label]').textContent]));
    let submitting = false;

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
    };

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const button = event.submitter ?? form.querySelector('button[type="submit"]');

            if (submitting || (button === resend && secondsRemaining() > 0)) {
                event.preventDefault();
                return;
            }

            if (event.defaultPrevented || !form.reportValidity()) {
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
    document.addEventListener('visibilitychange', updateCountdown);
    if (resend) window.setInterval(updateCountdown, 1000);
    restore();
});
