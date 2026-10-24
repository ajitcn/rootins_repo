(function () {
    'use strict';

    var active = false;

    function headers() {
        return window.RootinsCsrf
            ? window.RootinsCsrf.headers()
            : {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('input[name="csrf_rtn"]') || {}).value || ''};
    }

    function refreshCsrf(payload) {
        if (window.RootinsCsrf) {
            window.RootinsCsrf.update(payload);
            return;
        }
        if (payload && payload.csrf && payload.csrf.hash) {
            var field = document.querySelector('input[name="' + (payload.csrf.name || 'csrf_rtn') + '"]');
            if (field) field.value = payload.csrf.hash;
        }
    }

    function request(url, options, timeout) {
        var controller = window.AbortController ? new AbortController() : null;
        var rejectTimeout;
        var timeoutPromise = new Promise(function (_, reject) { rejectTimeout = reject; });
        var timer = window.setTimeout(function () {
            if (controller) controller.abort();
            var timeoutError = new Error('The request timed out. Please retry.');
            timeoutError.name = 'AbortError';
            rejectTimeout(timeoutError);
        }, timeout);
        if (controller) options.signal = controller.signal;
        return Promise.race([fetch(url, options), timeoutPromise]).finally(function () { window.clearTimeout(timer); });
    }

    function parse(response) {
        return response.text().then(function (text) {
            var payload;
            try { payload = JSON.parse(text); } catch (error) { throw new Error('The server returned an unexpected response.'); }
            if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new Error('The server returned an unexpected response.');
            refreshCsrf(payload);
            if (!response.ok || payload.status !== true) {
                var serverError = payload.error || {};
                var failure = new Error((serverError.code || 'SHOPIFY_ORDER_OPERATION_FAILED') + ': ' + (serverError.message || payload.message || payload.msg || 'Shopify order operation failed.'));
                failure.code = serverError.code;
                failure.payload = payload;
                throw failure;
            }
            return payload;
        });
    }

    function cleanup(button) {
        try {
            if (typeof window.hide_loader === 'function') window.hide_loader();
            if (window.RootinsPreloader) window.RootinsPreloader.reset();
            document.querySelectorAll('.modal-backdrop').forEach(function (element) { element.remove(); });
            document.body.classList.remove('rootins-loading', 'modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } finally {
            if (button) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }
    }

    function show(message, isError) {
        var box = document.getElementById('shopifyAdminOperationFeedback');
        if (box) {
            box.className = 'alert ' + (isError ? 'alert-danger' : 'alert-success');
            box.textContent = message;
        }
        if (typeof window[isError ? 'showError' : 'showSuccess'] === 'function') window[isError ? 'showError' : 'showSuccess'](message);
    }

    function refreshStatus() {
        var page = document.getElementById('shopifyAdminOrderOperationPage');
        if (!page || !page.dataset.detailsUrl) return Promise.resolve();
        return request(page.dataset.detailsUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, 15000)
            .then(parse)
            .then(function (payload) {
                var data = payload.data && payload.data.import ? payload.data.import : null;
                if (!data) return;
                var status = document.querySelector('[data-operation-status]');
                var error = document.querySelector('[data-operation-error]');
                if (status) status.textContent = data.status || 'unknown';
                if (error) error.textContent = data.error_message || data.last_error || data.failure_reason || '';
            });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-shopify-operation-form]');
        if (!form) return;
        event.preventDefault();
        if (active) return;
        var button = form.querySelector('button[type="submit"]');
        if (button && button.disabled) return;
        if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
        active = true;
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
        if (typeof window.show_loader === 'function') window.show_loader();
        var data = new FormData(form);
        if (window.RootinsCsrf) window.RootinsCsrf.formData(data);
        request(form.action, {method: 'POST', credentials: 'same-origin', headers: headers(), body: data}, 30000)
            .then(parse)
            .then(function (payload) {
                show(payload.message || 'Shopify order operation completed.', false);
                return refreshStatus().catch(function () {
                    show('The action succeeded, but the order status could not be refreshed.', true);
                });
            })
            .catch(function (error) { show(error && error.name === 'AbortError' ? 'The request timed out. Please retry.' : (error.message || 'Shopify order operation failed.'), true); })
            .finally(function () { active = false; cleanup(button); });
    });
}());
