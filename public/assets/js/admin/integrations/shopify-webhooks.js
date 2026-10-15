(function () {
    'use strict';

    var csrf = document.getElementById('shopify-webhooks-csrf');
    var csrfName = csrf ? csrf.name : '';
    var csrfValue = csrf ? csrf.value : '';

    function request(url, topic, button) {
        if (button.dataset.loading === '1') return Promise.resolve(null);
        button.dataset.loading = '1';
        button.disabled = true;
        var controller = window.AbortController ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, 30000);
        var form = new FormData();
        if (csrfName) form.append(csrfName, csrfValue);
        var pathTopic = topic.split('/').map(function (part) { return encodeURIComponent(part); }).join('/');
        return fetch(url + pathTopic, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form, signal: controller ? controller.signal : undefined })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .finally(function () { window.clearTimeout(timer); button.dataset.loading = '0'; button.disabled = false; });
    }

    function display(topic, data, fallback) {
        var result = document.querySelector('[data-result-topic="' + CSS.escape(topic) + '"]');
        if (!result) return;
        if (!data || !data.status) { result.textContent = fallback || 'Validation failed.'; return; }
        var item = data.data || {};
        if (item.rootins_endpoint) {
            result.textContent = 'ROOTINS: ' + item.rootins_endpoint.status + ' | Shopify: ' + ((item.shopify_subscription || {}).status || 'Unknown') + ' | Error: ' + (item.error_category || 'None');
        } else {
            result.textContent = 'Endpoint: ' + (item.status || 'Unknown') + ' | Response: ' + (item.response_code || 'n/a') + ' | Idempotency: ' + (item.idempotency || 'n/a');
        }
    }

    document.querySelectorAll('.shopify-webhook-validate').forEach(function (button) {
        button.addEventListener('click', function () {
            var topic = button.dataset.topic;
            display(topic, { status: true, data: { rootins_endpoint: { status: 'Loading…' }, shopify_subscription: { status: 'Loading…' } } });
            request(window.rootinsShopifyWebhookValidateBase, topic, button).then(function (result) { if (result) display(topic, result.data, 'Validation failed.'); }).catch(function (error) { display(topic, null, error.name === 'AbortError' ? 'Validation timed out.' : 'Validation failed safely.'); });
        });
    });

    document.querySelectorAll('.shopify-webhook-test').forEach(function (button) {
        button.addEventListener('click', function () {
            var topic = button.dataset.topic;
            display(topic, { status: true, data: { status: 'Loading…', response_code: 'n/a', idempotency: 'Loading…' } });
            request(window.rootinsShopifyWebhookTestBase, topic, button).then(function (result) { if (result) display(topic, result.data, 'Endpoint test failed.'); }).catch(function (error) { display(topic, null, error.name === 'AbortError' ? 'Endpoint test timed out.' : 'Endpoint test failed safely.'); });
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
