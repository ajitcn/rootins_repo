(function () {
    'use strict';

    var csrf = document.getElementById('shopify-webhooks-csrf');
    var csrfName = csrf ? csrf.name : '';
    var csrfValue = csrf ? csrf.value : '';

    function escapeHtml(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]; }); }
    function channelNode(channel) { return document.getElementById('shopify-webhook-' + channel + '-result'); }
    function showChannel(channel, message, state, detail) {
        var node = channelNode(channel);
        if (!node) return;
        node.className = 'shopify-webhooks-result is-' + (state || 'info');
        node.innerHTML = '<strong>' + escapeHtml(message) + '</strong>' + (detail ? '<span>' + escapeHtml(detail) + '</span>' : '');
    }
    function markChecked() {
        var node = document.getElementById('shopify-webhooks-last-checked');
        if (node) node.textContent = new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    }
    function summary(data) {
        var item = data || {};
        return 'Created: ' + (item.created || 0) + ' | Already existing: ' + (item.already_existing || 0) + ' | Removed: ' + (item.removed || 0) + ' | Skipped: ' + (item.skipped || 0) + ' | Failed: ' + (item.failed || 0);
    }
    function managementRequest(url, button) {
        if (button.dataset.loading === '1') return;
        button.dataset.loading = '1'; button.disabled = true;
        var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue); if (button.dataset.action) form.append('action', button.dataset.action);
        showChannel(button.dataset.resultChannel || 'live', 'Checking live webhook delivery...', 'pending');
        fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) {
                var data = payload.data || {}, item = data.data || {}, channel = button.dataset.resultChannel || 'live';
                if (!payload.response.ok || !data.status) throw new Error(data.msg || 'Shopify webhook operation failed safely.');
                showChannel(channel, data.msg || 'Live webhook delivery checked.', 'success', 'Status: ' + (item.status || 'Not yet verified') + ' | Received: ' + (item.received_count || 0)); markChecked();
            })
            .catch(function (error) { showChannel(button.dataset.resultChannel || 'live', error.message || 'Shopify webhook operation failed safely.', 'failed'); })
            .finally(function () { button.dataset.loading = '0'; button.disabled = false; });
    }
    function renderPlanList(rows) {
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) return '<div class="small text-muted">None</div>';
        return '<ul class="small mb-0 pl-3">' + rows.map(function (item) { return '<li><code>' + escapeHtml(item.topic || 'Unknown topic') + '</code>' + (item.callback_url ? ' <span class="text-break">' + escapeHtml(item.callback_url) + '</span>' : '') + (item.reason ? ' <span class="text-muted">(' + escapeHtml(item.reason) + ')</span>' : '') + '</li>'; }).join('') + '</ul>';
    }
    function renderReview(plan, action) {
        var body = document.getElementById('shopify-webhook-review-body'), apply = document.getElementById('shopify-webhook-apply');
        if (!body || !apply) return;
        var ownership = plan.ownership_verified ? '<div class="alert alert-success small">Configured Shopify store ownership verified. Changes may be applied after confirmation.</div>' : '<div class="alert alert-danger small">Store ownership could not be verified. No create or delete operation is available.</div>';
        body.innerHTML = ownership + '<div class="row"><div class="col-md-6 mb-3"><h3 class="h6">Subscriptions to create (' + (plan.to_create || []).length + ')</h3>' + renderPlanList(plan.to_create) + '</div><div class="col-md-6 mb-3"><h3 class="h6">Already correct (' + (plan.already_existing || []).length + ')</h3>' + renderPlanList(plan.already_existing) + '</div><div class="col-md-6 mb-3"><h3 class="h6">Subscriptions to remove (' + (plan.to_remove || []).length + ')</h3>' + renderPlanList(plan.to_remove) + '</div><div class="col-md-6 mb-3"><h3 class="h6">URL or delivery mismatches (' + (plan.url_mismatches || []).length + ')</h3>' + renderPlanList(plan.url_mismatches) + '</div><div class="col-12"><h3 class="h6">Skipped (' + (plan.skipped || []).length + ')</h3>' + renderPlanList(plan.skipped) + '</div></div>';
        apply.dataset.planHash = plan.plan_hash || ''; apply.dataset.action = action || 'sync'; apply.disabled = !plan.ownership_verified || !plan.plan_hash;
        document.getElementById('shopify-webhook-review-title').textContent = action === 'remove-unwanted' ? 'Confirm removal of unwanted webhooks' : action === 'register-missing' ? 'Confirm registration of missing webhooks' : 'Review Shopify webhook changes';
        apply.textContent = action === 'remove-unwanted' ? 'Confirm and remove' : action === 'register-missing' ? 'Confirm and register' : 'Confirm and apply changes';
        if (window.jQuery) window.jQuery('#shopify-webhook-review-modal').modal('show');
    }
    function showReviewError(message) {
        var body = document.getElementById('shopify-webhook-review-body'), apply = document.getElementById('shopify-webhook-apply');
        if (body) body.innerHTML = '<div class="alert alert-danger mb-0"><strong>Shopify webhook request failed safely.</strong><div class="small mt-2">' + escapeHtml(message || 'The operation could not be completed.') + '</div></div>';
        if (apply) apply.disabled = true;
        if (window.jQuery) window.jQuery('#shopify-webhook-review-modal').modal('show');
    }
    function requestPreview(button) {
        if (button.dataset.loading === '1') return;
        var action = button.dataset.action || 'sync', channel = button.dataset.resultChannel || 'sync';
        button.dataset.loading = '1'; button.disabled = true; showChannel(channel, 'Querying Shopify subscriptions...', 'pending');
        var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue);
        fetch(button.dataset.actionUrl || window.rootinsShopifyWebhookPreview, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) { if (!payload.response.ok || !payload.data.status) throw new Error(payload.data.msg || 'Shopify webhook preview failed safely.'); var plan = payload.data.data || {}; showChannel(channel, payload.data.msg || 'Shopify subscription preview ready.', 'success', summary(plan)); markChecked(); if (action !== 'sync') renderReview(plan, action); })
            .catch(function (error) { showChannel(channel, error.message || 'Shopify webhook preview failed safely.', 'failed'); showReviewError(error.message || 'The subscription preview could not be completed.'); })
            .finally(function () { button.dataset.loading = '0'; button.disabled = false; });
    }
    ['shopify-webhooks-sync', 'shopify-webhooks-register-missing', 'shopify-webhooks-remove-unwanted'].forEach(function (id) { document.getElementById(id)?.addEventListener('click', function () { requestPreview(this); }); });
    document.getElementById('shopify-webhook-apply')?.addEventListener('click', function () {
        var button = this; if (button.disabled || button.dataset.loading === '1') return;
        button.dataset.loading = '1'; button.disabled = true;
        var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue); form.append('confirmation', window.rootinsShopifyWebhookConfirmation); form.append('plan_hash', button.dataset.planHash || ''); form.append('mode', button.dataset.action || 'all');
        fetch(window.rootinsShopifyWebhookApply, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form }).then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); }).then(function (payload) {
            var result = payload.data.data || {}, channel = button.dataset.action === 'remove-unwanted' ? 'removal' : button.dataset.action === 'register-missing' ? 'registration' : 'sync';
            showChannel(channel, payload.data.msg || 'Shopify webhook changes processed.', payload.response.ok && payload.data.status ? 'success' : 'failed', summary(result)); markChecked();
            if (payload.response.status === 409) renderReview(result, button.dataset.action); else if (window.jQuery && payload.response.ok && payload.data.status) window.jQuery('#shopify-webhook-review-modal').modal('hide');
        }).catch(function () { showChannel(button.dataset.action === 'remove-unwanted' ? 'removal' : button.dataset.action === 'register-missing' ? 'registration' : 'sync', 'Shopify webhook changes could not be applied safely.', 'failed'); }).finally(function () { button.dataset.loading = '0'; button.disabled = false; });
    });
    function request(url, topic, button) { if (button.dataset.loading === '1') return Promise.resolve(null); button.dataset.loading = '1'; button.disabled = true; var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue); form.append('topic', topic); return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form }).then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); }).finally(function () { button.dataset.loading = '0'; button.disabled = false; }); }
    function display(topic, data, fallback) { var result = document.querySelector('[data-result-topic="' + CSS.escape(topic) + '"]'); if (!result) return; if (!data || !data.status) { var errors = data && data.errors ? Object.values(data.errors).join(' ') : ''; result.textContent = (data && data.msg) || errors || fallback || 'Validation failed.'; return; } var item = data.data || {}; result.textContent = item.rootins_endpoint ? 'ROOTINS: ' + item.rootins_endpoint.status + ' | Shopify: ' + ((item.shopify_subscription || {}).status || 'Unknown') + ' | Error: ' + (item.error_category || 'None') : 'ROOTINS Endpoint Test: ' + (item.status || 'Unknown') + ' | Response: ' + (item.response_code || 'n/a') + ' | Idempotency: ' + (item.idempotency || 'n/a'); }
    document.querySelectorAll('[data-action="validate"]').forEach(function (button) { button.addEventListener('click', function () { var topic = button.dataset.topic; display(topic, { status: true, data: { rootins_endpoint: { status: 'Loading...' }, shopify_subscription: { status: 'Loading...' } } }); request(button.dataset.actionUrl, '', button).then(function (result) { if (result) display(topic, result.data, 'Validation failed.'); }); }); });
    document.querySelectorAll('[data-action="test-endpoint"]').forEach(function (button) { button.addEventListener('click', function () { var topic = button.dataset.topic; display(topic, { status: true, data: { status: 'Loading...', response_code: 'n/a', idempotency: 'Loading...' } }); request(button.dataset.actionUrl, topic, button).then(function (result) { if (result) display(topic, result.data, 'ROOTINS Endpoint Test failed.'); }); }); });
    document.getElementById('shopify-webhooks-validate-all')?.addEventListener('click', function () { var button = this; if (button.dataset.loading === '1') return; button.dataset.loading = '1'; button.disabled = true; showChannel('validation', 'Validating ROOTINS endpoints and Shopify subscriptions...', 'pending'); var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue); fetch(window.rootinsShopifyWebhookValidateAll, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form }).then(function (response) { return response.json(); }).then(function (data) { (data.data || []).forEach(function (item) { display(item.topic, { status: true, data: item }); }); showChannel('validation', data.msg || 'Endpoint validation completed.', data.status ? 'success' : 'failed', (data.data || []).length + ' topic(s) checked.'); markChecked(); }).catch(function () { showChannel('validation', 'Endpoint validation failed or timed out.', 'failed'); }).finally(function () { button.dataset.loading = '0'; button.disabled = false; }); });
    document.getElementById('shopify-webhooks-verify-live')?.addEventListener('click', function () { managementRequest(this.dataset.actionUrl, this); });
    document.querySelectorAll('.shopify-copy').forEach(function (button) { button.addEventListener('click', function () { var target = document.querySelector(button.dataset.copyTarget || ''); if (!target || !navigator.clipboard) return; navigator.clipboard.writeText(target.textContent.trim()).then(function () { var original = button.textContent; button.textContent = 'Copied'; window.setTimeout(function () { button.textContent = original; }, 1200); }); }); });
}());
