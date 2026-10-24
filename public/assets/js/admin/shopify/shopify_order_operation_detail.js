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

    function setActionLoading(form, loading) {
        var button = form && form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = loading;
            button.setAttribute('aria-busy', loading ? 'true' : 'false');
        }
        if (form) form.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    function hideGlobalPreloader() {
        try {
            if (typeof window.hide_loader === 'function') window.hide_loader();
            if (window.RootinsPreloader) window.RootinsPreloader.reset();
            var loader = document.querySelector('#globalPreloader');
            if (loader) {
                loader.classList.remove('show', 'active', 'd-block');
                loader.setAttribute('aria-hidden', 'true');
            }
            document.querySelectorAll('.modal-backdrop').forEach(function (element) { element.remove(); });
            document.body.classList.remove('rootins-loading', 'modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } catch (error) { /* cleanup must not mask the server/network result */ }
    }

    function showToast(message) {
        if (typeof window.showSuccess === 'function') window.showSuccess(message);
    }

    function showErrorToast(message) {
        if (typeof window.showError === 'function') window.showError(message);
    }

    function diagnostic(action, url, response, payload) {
        var development = window.ROOTINS_ENV === 'development' || document.documentElement.getAttribute('data-environment') === 'development';
        if (!development || !window.console || typeof window.console.debug !== 'function') return;
        window.console.debug('[Shopify order operation]', {
            action: action,
            url: url,
            http_status: response ? response.status : null,
            response_content_type: response ? (response.headers.get('content-type') || '') : '',
            parsed_error_code: payload && payload.error ? payload.error.code || null : null
        });
    }

    $(document).off('submit.shopifyOrderOperations', '.shopify-order-operation-action-form');
    $(document).on('submit', '.shopify-order-operation-action-form', async function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var form = this;
        var button = form.querySelector('button[type="submit"]');
        var loader = document.querySelector('#globalPreloader');
        var action = (form.querySelector('[name="action"]') || {}).value || '';

        if (active || (button && button.disabled)) return false;
        if (form.checkValidity && !form.checkValidity()) {
            form.reportValidity();
            return false;
        }

        active = true;
        setActionLoading(form, true);
        if (typeof window.show_loader === 'function') window.show_loader();
        if (loader) loader.setAttribute('aria-hidden', 'false');

        var controller = window.AbortController ? new AbortController() : null;
        var rejectTimeout;
        var timeoutPromise = new Promise(function (_, reject) { rejectTimeout = reject; });
        var timeout = window.setTimeout(function () {
            if (controller) controller.abort();
            var timeoutError = new Error('The request timed out. Please retry.');
            timeoutError.name = 'AbortError';
            rejectTimeout(timeoutError);
        }, 30000);
        try {
            var data = new FormData(form);
            if (window.RootinsCsrf) window.RootinsCsrf.formData(data);
            var requestHeaders = headers();
            var response = await Promise.race([fetch(form.action, {
                method: 'POST',
                headers: requestHeaders,
                body: data,
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }), timeoutPromise]);
            var contentType = response.headers.get('content-type') || '';
            var text = await response.text();
            var payload;
            if (!contentType.toLowerCase().includes('application/json')) {
                diagnostic(action, form.action, response, null);
                throw new Error('The server returned an unexpected HTML response.');
            }
            try { payload = JSON.parse(text); } catch (error) {
                diagnostic(action, form.action, response, null);
                throw new Error('The server returned an unexpected HTML response.');
            }
            refreshCsrf(payload);
            diagnostic(action, form.action, response, payload);
            if (!response.ok || payload.status === false) {
                throw new Error((payload.error && payload.error.message) || payload.message || 'The operation could not be completed.');
            }
            showToast(payload.message || 'Operation completed successfully.');
            window.location.reload();
        } catch (error) {
            showErrorToast(error && error.name === 'AbortError' ? 'The request timed out. Please retry.' : ((error && error.message) || 'The operation could not be completed.'));
        } finally {
            window.clearTimeout(timeout);
            active = false;
            setActionLoading(form, false);
            hideGlobalPreloader();
        }

        return false;
    });
}());
