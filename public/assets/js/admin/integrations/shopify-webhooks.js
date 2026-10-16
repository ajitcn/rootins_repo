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
                renderManagementResults(data.data || {});
                var errors = data.errors ? Object.values(data.errors).join(' ') : '';
                status.className = 'alert mt-3 ' + (payload.response.ok && data.status ? 'alert-success' : 'alert-danger');
                var summary = button.dataset.action === 'check-subscriptions'
                    ? 'Registered: ' + (data.data?.registered_correctly ?? 0) + ', Missing: ' + (data.data?.missing ?? 0) + ', URL mismatch: ' + (data.data?.url_mismatch ?? 0) + ', Inactive: ' + (data.data?.inactive ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.'
                    : button.dataset.action === 'verify-live-delivery'
                    ? 'Status: ' + (data.data?.status ?? 'Not yet verified') + ', Received: ' + (data.data?.received_count ?? 0) + '.'
                    : button.dataset.action === 'sync'
                    ? 'Registered correctly: ' + (data.data?.registered_correctly ?? 0) + ', Missing: ' + (data.data?.missing ?? 0) + ', URL mismatch: ' + (data.data?.url_mismatch ?? 0) + ', Inactive: ' + (data.data?.inactive ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.'
                    : (button.dataset.action === 'preview' || button.dataset.action === 'apply')
                    ? 'Created: ' + (data.data?.created ?? 0) + ', Already existing: ' + (data.data?.already_existing ?? 0) + ', Removed: ' + (data.data?.removed ?? 0) + ', Skipped: ' + (data.data?.skipped ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.'
                    : 'Already existing: ' + (data.data?.already_existing ?? 0) + ', Created: ' + (data.data?.created ?? 0) + ', URL mismatch: ' + (data.data?.url_mismatch ?? 0) + ', Inactive: ' + (data.data?.inactive ?? 0) + ', Failed: ' + (data.data?.failed ?? 0) + '.';
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

    function renderManagementResults(result) {
        var container = document.getElementById('shopify-webhook-management-results');
        var body = container ? container.querySelector('tbody') : null;
        if (!container || !body) return;
        body.textContent = '';
        var rows = Array.isArray(result.webhooks) ? result.webhooks : [];
        rows.forEach(function (item) {
            var row = document.createElement('tr');
            [item.topic || '', item.callback_url || '', item.status || '', item.http_status || '—', item.error_category ? item.error_category + (item.error_code ? ' (' + item.error_code + ')' : '') + ': ' + (item.error_message || '') : '—'].forEach(function (value) {
                var cell = document.createElement('td');
                cell.className = 'small text-break';
                cell.textContent = String(value);
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
        container.classList.toggle('d-none', rows.length === 0);
    }

    function renderPlanList(label, rows) {
        var items = Array.isArray(rows) ? rows : [];
        if (!items.length) return '<div class="small text-muted">None</div>';
        function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, function (character) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]; }); }
        return '<ul class="small mb-0 pl-3">' + items.map(function (item) {
            var topic = escapeHtml(item.topic || 'Unknown topic');
            var detail = item.callback_url ? ' <code class="text-break">' + escapeHtml(item.callback_url) + '</code>' : '';
            var reason = item.reason ? ' <span class="text-muted">(' + escapeHtml(item.reason) + ')</span>' : '';
            return '<li><code>' + topic + '</code>' + detail + reason + '</li>';
        }).join('') + '</ul>';
    }

    function renderReview(plan) {
        var body = document.getElementById('shopify-webhook-review-body');
        var apply = document.getElementById('shopify-webhook-apply');
        if (!body || !apply) return;
        var ownership = plan.ownership_verified ? '<div class="alert alert-success small">Configured Shopify store ownership verified. Changes may be applied after confirmation.</div>' : '<div class="alert alert-danger small">Store ownership could not be verified. No create or delete operation is available.</div>';
        body.innerHTML = ownership + '<div class="row">' +
            '<div class="col-md-6 mb-3"><h3 class="h6">Subscriptions to create (' + (plan.to_create || []).length + ')</h3>' + renderPlanList('create', plan.to_create) + '</div>' +
            '<div class="col-md-6 mb-3"><h3 class="h6">Already correct (' + (plan.already_existing || []).length + ')</h3>' + renderPlanList('existing', plan.already_existing) + '</div>' +
            '<div class="col-md-6 mb-3"><h3 class="h6">Subscriptions to remove (' + (plan.to_remove || []).length + ')</h3>' + renderPlanList('remove', plan.to_remove) + '</div>' +
            '<div class="col-md-6 mb-3"><h3 class="h6">URL or delivery mismatches (' + (plan.url_mismatches || []).length + ')</h3>' + renderPlanList('mismatch', plan.url_mismatches) + '</div>' +
            '<div class="col-12"><h3 class="h6">Skipped (' + (plan.skipped || []).length + ')</h3>' + renderPlanList('skipped', plan.skipped) + '</div></div>';
        apply.dataset.planHash = plan.plan_hash || '';
        apply.disabled = !plan.ownership_verified || !plan.plan_hash;
        if (window.jQuery) window.jQuery('#shopify-webhook-review-modal').modal('show');
    }

    function requestPreview(button) {
        if (button.dataset.loading === '1') return;
        button.dataset.loading = '1'; button.disabled = true;
        var form = new FormData(); if (csrfName) form.append(csrfName, csrfValue);
        fetch(button.dataset.actionUrl || window.rootinsShopifyWebhookPreview, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) { if (!payload.response.ok || !payload.data.status) throw new Error(payload.data.msg || 'Shopify webhook preview failed safely.'); renderReview(payload.data.data || {}); })
            .catch(function (error) { var body = document.getElementById('shopify-webhook-review-body'); if (body) body.innerHTML = '<div class="alert alert-danger">' + String(error.message || 'Shopify webhook preview failed safely.') + '</div>'; if (window.jQuery) window.jQuery('#shopify-webhook-review-modal').modal('show'); })
            .finally(function () { button.dataset.loading = '0'; button.disabled = false; });
    }

    document.getElementById('shopify-webhooks-review')?.addEventListener('click', function () { requestPreview(this); });

    document.getElementById('shopify-webhook-apply')?.addEventListener('click', function () {
        var button = this;
        if (button.disabled || button.dataset.loading === '1') return;
        button.dataset.loading = '1'; button.disabled = true; button.textContent = 'Applying…';
        var form = new FormData();
        if (csrfName) form.append(csrfName, csrfValue);
        form.append('confirmation', window.rootinsShopifyWebhookConfirmation);
        form.append('plan_hash', button.dataset.planHash || '');
        fetch(window.rootinsShopifyWebhookApply, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: form })
            .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
            .then(function (payload) {
                var status = document.getElementById('shopify-webhook-management-status');
                if (status) { status.className = 'alert mt-3 ' + (payload.response.ok && payload.data.status ? 'alert-success' : 'alert-danger'); var result = payload.data.data || {}; status.textContent = (payload.data.msg || 'Shopify webhook changes processed.') + ' Created: ' + (result.created || 0) + ', Already existing: ' + (result.already_existing || 0) + ', Removed: ' + (result.removed || 0) + ', Skipped: ' + (result.skipped || 0) + ', Failed: ' + (result.failed || 0) + '.'; }
                renderManagementResults(payload.data.data || {});
                if (payload.response.status === 409) renderReview(payload.data.data || {});
                else if (window.jQuery) window.jQuery('#shopify-webhook-review-modal').modal('hide');
            })
            .catch(function () { var status = document.getElementById('shopify-webhook-management-status'); if (status) { status.className = 'alert mt-3 alert-danger'; status.textContent = 'Shopify webhook changes could not be applied safely.'; } })
            .finally(function () { button.dataset.loading = '0'; button.disabled = false; button.textContent = 'Confirm and apply changes'; });
    });

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

    document.getElementById('shopify-webhooks-verify-live')?.addEventListener('click', function () {
        managementRequest(this.dataset.actionUrl, this, null);
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
