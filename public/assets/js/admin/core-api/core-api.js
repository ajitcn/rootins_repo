(function () {
    'use strict';

    const page = document.getElementById('core-api-page');
    if (!page) return;

    const csrfInput = document.querySelector('#core-api-create-form input[type="hidden"]');
    const csrfName = csrfInput?.name || document.querySelector('meta[name="csrf-token"]')?.content;
    const csrfValue = csrfInput?.value || '';
    const alertBox = document.getElementById('core-api-alert');
    const secretInput = document.getElementById('core-api-secret-value');
    const secretModal = $('#core-api-secret-modal');

    const show = (message, ok) => {
        alertBox.textContent = message;
        alertBox.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    };

    const setBusy = (button, busy, label) => {
        if (!button) return;
        if (busy) {
            button.dataset.defaultLabel = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" aria-hidden="true"></span>' + (label || 'Working');
        } else {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.defaultLabel) button.innerHTML = button.dataset.defaultLabel;
        }
    };

    const post = (url, data) => rootinsFetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(Object.assign({}, data, csrfName ? { [csrfName]: csrfValue } : {}))
    }).then((response) => response.json().then((json) => ({ response, json })));

    const copy = (value) => {
        if (!value) return Promise.resolve();
        if (!navigator.clipboard?.writeText) {
            show('Clipboard access is unavailable in this browser.', false);
            return Promise.resolve();
        }
        return navigator.clipboard.writeText(value).then(() => show('Copied to clipboard.', true)).catch(() => show('Clipboard access is unavailable in this browser.', false));
    };

    const clearSecret = () => {
        if (!secretInput) return;
        secretInput.value = '';
        secretInput.removeAttribute('value');
    };

    const showSecret = (json) => {
        clearSecret();
        if (!secretInput || !json.data?.secret) return;
        secretInput.type = 'password';
        secretInput.value = json.data.secret;
        secretModal.modal('show');
    };

    secretModal.on('hidden.bs.modal', clearSecret);
    window.addEventListener('pagehide', clearSecret);
    window.addEventListener('beforeunload', clearSecret);

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', () => copy(document.querySelector(button.dataset.copyTarget)?.value || ''));
    });
    document.querySelectorAll('.js-core-api-copy-key').forEach((button) => {
        button.addEventListener('click', () => copy(button.dataset.key || ''));
    });
    document.getElementById('core-api-copy-secret')?.addEventListener('click', (event) => {
        copy(secretInput?.value || '').then(() => setBusy(event.currentTarget, false));
        setBusy(event.currentTarget, true, 'Copying');
    });

    document.getElementById('core-api-create-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const submit = form.querySelector('button[type="submit"]');
        const data = Object.fromEntries(new FormData(form).entries());
        setBusy(submit, true, 'Creating');
        post(page.dataset.createUrl, data).then(({ response, json }) => {
            show(json.msg || 'Credential creation failed.', response.ok && json.status);
            if (response.ok && json.status) {
                $('#core-api-create-modal').modal('hide');
                showSecret(json);
                setTimeout(() => { clearSecret(); window.location.reload(); }, 1200);
            }
        }).finally(() => setBusy(submit, false));
    });

    document.querySelectorAll('.js-core-api-revoke').forEach((button) => {
        button.addEventListener('click', () => {
            if (!window.confirm('Revoke this credential? Existing signed requests will stop working.')) return;
            setBusy(button, true, 'Revoking');
            post(page.dataset.actionBase + '/' + button.dataset.id + '/revoke', {}).then(({ response, json }) => {
                show(json.msg || 'Revoke failed.', response.ok && json.status);
                if (response.ok && json.status) window.location.reload();
            }).finally(() => setBusy(button, false));
        });
    });

    document.querySelectorAll('.js-core-api-rotate').forEach((button) => {
        button.addEventListener('click', () => {
            if (!window.confirm('Rotate this credential? The old secret will stop working immediately.')) return;
            setBusy(button, true, 'Rotating');
            post(page.dataset.actionBase + '/' + button.dataset.id + '/rotate', {}).then(({ response, json }) => {
                show(json.msg || 'Rotation failed.', response.ok && json.status);
                if (response.ok && json.status) {
                    showSecret(json);
                    setTimeout(() => { clearSecret(); window.location.reload(); }, 1200);
                }
            }).finally(() => setBusy(button, false));
        });
    });

    document.querySelectorAll('.js-core-api-test').forEach((button) => {
        button.addEventListener('click', () => {
            setBusy(button, true, 'Testing');
            post(page.dataset.actionBase + '/' + button.dataset.id + '/test', {}).then(({ response, json }) => {
                show(json.msg || 'Connection test failed.', response.ok && json.status);
            }).finally(() => setBusy(button, false));
        });
    });

    const instructions = () => 'ROOTINS Core API\nBase URL: ' + page.dataset.baseUrl + '\nHeaders: X-API-Key-Id, X-API-Timestamp, X-API-Signature; POST requests also require Idempotency-Key.\nSign: timestamp + newline + uppercase method + newline + /api/v1/path + newline + raw request body.';
    document.getElementById('core-api-copy-instructions')?.addEventListener('click', (event) => {
        copy(instructions()).then(() => setBusy(event.currentTarget, false));
        setBusy(event.currentTarget, true, 'Copying');
    });
    document.getElementById('core-api-copy-example')?.addEventListener('click', (event) => {
        copy(document.getElementById('core-api-client-example')?.innerText || '').then(() => setBusy(event.currentTarget, false));
        setBusy(event.currentTarget, true, 'Copying');
    });
    document.getElementById('core-api-download-instructions')?.addEventListener('click', (event) => {
        setBusy(event.currentTarget, true, 'Preparing');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([instructions()], { type: 'text/plain' }));
        link.download = 'rootins-core-api-instructions.txt';
        link.click();
        URL.revokeObjectURL(link.href);
        setTimeout(() => setBusy(event.currentTarget, false), 350);
    });
})();
