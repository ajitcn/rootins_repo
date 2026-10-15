(function () {
    'use strict';

    var csrf = document.getElementById('shopify-webhooks-csrf');
    var csrfName = csrf ? csrf.name : '';
    var csrfValue = csrf ? csrf.value : '';

    function managementRequest(url, button, confirmMessage) {
        if (button.dataset.loading === '1') return;
        if (confirmMessage && !window.confirm(confirmMessage)) return;
        button.dataset.loading = '1';
        button.disabled = true;
        var form = new FormData();
        if (csrfName) form.append(csrfName, csrfValue);
        if (button.dataset.action) form.append('action', button.dataset.action);
        var controller = window.AbortController ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, 30000);
        fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form, signal: controller ? controller.signal : undefined })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) {
                var status = document.getElementById('shopify-webhook-management-status');
                if (!status) return;
                var data = payload.data || {};
                var errors = data.errors ? Object.values(data.errors).join(' ') : '';
                status.className = 'alert mt-3 ' + (payload.response.ok && data.status ? 'alert-success' : 'alert-danger');
                var summary = button.dataset.action === 'check-subscriptions'
                    ? 'Registered: ' + (data.data?.registered_correctly ?? 0) + ', Missing: ' + (data.data?.missing ?? 0) + ', URL mismatch: ' + (data.data?.url_mismatch ?? 0) + ', Inactive: ' + (data.data?.inactive ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.'
                    : button.dataset.action === 'verify-live-delivery'
                    ? 'Status: ' + (data.data?.status ?? 'Not yet verified') + ', Received: ' + (data.data?.received_count ?? 0) + '.'
                    : button.dataset.action === 'sync'
                    ? 'Registered correctly: ' + (data.data?.registered_correctly ?? 0) + ', Missing: ' + (data.data?.missing ?? 0) + ', URL mismatch: ' + (data.data?.url_mismatch ?? 0) + ', Inactive: ' + (data.data?.inactive ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.'
                    : 'Existing: ' + (data.data?.existing ?? 0) + ', Created: ' + (data.data?.created ?? 0) + ', Skipped: ' + (data.data?.skipped ?? 0) + ', Missing: ' + (data.data?.missing ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.';
                status.textContent = payload.response.ok && data.status
                    ? (data.msg || 'Shopify webhook operation completed.') + ' ' + summary
                    : ((data.code ? data.code + ': ' : '') + (errors || data.msg || 'Shopify webhook operation failed safely.'));
            })
            .catch(function (error) {
                var status = document.getElementById('shopify-webhook-management-status');
                if (status) { status.className = 'alert mt-3 alert-danger'; status.textContent = error.name === 'AbortError' ? 'Shopify webhook operation timed out.' : 'Shopify webhook operation failed safely.'; }
            })
            .finally(function () { window.clearTimeout(timer); button.dataset.loading = '0'; button.disabled = false; });
    }

    function request(url, topic, button) {
        if (button.dataset.loading === '1') return Promise.resolve(null);
        button.dataset.loading = '1';
        button.disabled = true;
        var controller = window.AbortController ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, 30000);
        var form = new FormData();
        if (csrfName) form.append(csrfName, csrfValue);
        form.append('topic', topic);
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form, signal: controller ? controller.signal : undefined })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .finally(function () { window.clearTimeout(timer); button.dataset.loading = '0'; button.disabled = false; });
    }

    function display(topic, data, fallback) {
        var result = document.querySelector('[data-result-topic="' + CSS.escape(topic) + '"]');
        if (!result) return;
        if (!data || !data.status) {
            var errors = data && data.errors ? Object.values(data.errors).join(' ') : '';
            result.textContent = (data && data.code ? data.code + ': ' : '') + (errors || (data && data.msg) || fallback || 'Validation failed.');
            return;
        }
        var item = data.data || {};
        if (item.rootins_endpoint) {
            result.textContent = 'ROOTINS: ' + item.rootins_endpoint.status + ' | Shopify: ' + ((item.shopify_subscription || {}).status || 'Unknown') + ' | Error: ' + (item.error_category || 'None');
        } else {
            result.textContent = 'ROOTINS Endpoint Test: ' + (item.status || 'Unknown') + ' | Response: ' + (item.response_code || 'n/a') + ' | Idempotency: ' + (item.idempotency || 'n/a');
        }
    }

    document.querySelectorAll('[data-action="validate"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var topic = button.dataset.topic;
            display(topic, { status: true, data: { rootins_endpoint: { status: 'Loading…' }, shopify_subscription: { status: 'Loading…' } } });
            request(button.dataset.actionUrl, '', button).then(function (result) { if (result) display(topic, result.data, 'Validation failed.'); }).catch(function (error) { display(topic, null, error.name === 'AbortError' ? 'Validation timed out.' : 'Validation failed safely.'); });
        });
    });

    document.querySelectorAll('[data-action="test-endpoint"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var topic = button.dataset.topic;
            display(topic, { status: true, data: { status: 'Loading…', response_code: 'n/a', idempotency: 'Loading…' } });
            request(button.dataset.actionUrl, topic, button).then(function (result) { if (result) display(topic, result.data, 'ROOTINS Endpoint Test failed.'); }).catch(function (error) { display(topic, null, error.name === 'AbortError' ? 'ROOTINS Endpoint Test timed out.' : 'ROOTINS Endpoint Test failed safely.'); });
        });
    });

    document.getElementById('shopify-webhooks-validate-all')?.addEventListener('click', function () {
        var button = this;
        if (button.dataset.loading === '1') return;
        button.dataset.loading = '1'; button.disabled = true; button.textContent = 'Validating…';
        var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue);
        var controller = window.AbortController ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, 30000);
        fetch(window.rootinsShopifyWebhookValidateAll, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form, signal: controller ? controller.signal : undefined })
            .then(function (response) { return response.json(); })
            .then(function (data) { (data.data || []).forEach(function (item) { display(item.topic, { status: true, data: item }); }); })
            .catch(function () { document.querySelectorAll('.webhook-validation-result').forEach(function (node) { node.textContent = 'Validate All failed or timed out.'; }); })
            .finally(function () { window.clearTimeout(timer); button.dataset.loading = '0'; button.disabled = false; button.textContent = 'Validate All'; });
    });

    document.getElementById('shopify-webhooks-sync')?.addEventListener('click', function () {
        managementRequest(this.dataset.actionUrl, this, 'Synchronize Shopify webhook subscriptions? Existing subscriptions will not be modified or deleted.');
    });

    document.getElementById('shopify-webhooks-check-subscriptions')?.addEventListener('click', function () {
        managementRequest(this.dataset.actionUrl, this, null);
    });

    document.getElementById('shopify-webhooks-verify-live')?.addEventListener('click', function () {
        managementRequest(this.dataset.actionUrl, this, null);
    });

    document.getElementById('shopify-webhooks-register-missing')?.addEventListener('click', function () {
        managementRequest(this.dataset.actionUrl, this, 'Register only missing Shopify webhooks? Existing subscriptions will not be modified or deleted.');
    });

    document.querySelectorAll('.shopify-copy').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.querySelector(button.dataset.copyTarget || '');
            if (!target || !navigator.clipboard) return;
            navigator.clipboard.writeText(target.textContent.trim()).then(function () {
                var original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(function () { button.textContent = original; }, 1200);
            });
        });
    });
}());
