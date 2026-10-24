(function ($) {
    'use strict';

    function csrfHeaders() {
        if (window.RootinsCsrf) return window.RootinsCsrf.headers();
        var token = $('input[name="csrf_rtn"]').first().val() || '';
        return { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, Accept: 'application/json' };
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function label(value) {
        return String(value || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function refreshCsrf(payload) {
        if (window.RootinsCsrf) { window.RootinsCsrf.update(payload); return; }
        if (!payload || !payload.csrf || !payload.csrf.hash) return;
        $('input[name="' + (payload.csrf.name || 'csrf_rtn') + '"]').val(payload.csrf.hash);
    }

    function fetchWithTimeout(url, options, timeoutMs) {
        var controller = window.AbortController ? new AbortController() : null;
        var timer = window.setTimeout(function () { if (controller) controller.abort(); }, timeoutMs);
        if (controller) options.signal = controller.signal;
        return fetch(url, options).finally(function () { window.clearTimeout(timer); });
    }

    function responseError(response, payload) {
        var error = payload && payload.error ? payload.error : {};
        var code = error.code || payload.error_code || 'SHOPIFY_ORDER_ACTION_FAILED';
        var message = error.message || payload.message || payload.msg || ('The action failed (HTTP ' + response.status + ').');
        var failure = new Error(code + ': ' + message);
        failure.payload = payload;
        failure.code = code;
        failure.httpStatus = response.status;
        return failure;
    }

    async function parseJsonResponse(response) {
        var text = await response.text(), payload;
        try { payload = JSON.parse(text); } catch (error) {
            var malformed = new Error('The server returned an unexpected response.');
            malformed.httpStatus = response.status;
            throw malformed;
        }
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
            var invalidShape = new Error('The server returned an unexpected response.');
            invalidShape.httpStatus = response.status;
            throw invalidShape;
        }
        refreshCsrf(payload);
        if (!response.ok || !payload.status) throw responseError(response, payload);
        return payload;
    }

    function clearStaleLoadingState() {
        if (typeof window.hide_loader === 'function') window.hide_loader();
        $('.loading, .is-loading').removeClass('loading is-loading').removeAttr('aria-busy');
        $('[aria-busy="true"]').not(':disabled').removeAttr('aria-busy');
        if (!$('.modal.show').length) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
        }
    }

    function feedback(message, isError) {
        var $feedback = $('#shopifyOrderActionFeedback');
        if ($feedback.length) $feedback.removeClass('d-none alert-success alert-danger').addClass(isError ? 'alert alert-danger' : 'alert alert-success').text(message || (isError ? 'The action could not be completed.' : 'Updated.'));
        if (typeof window[isError ? 'showError' : 'showSuccess'] === 'function') window[isError ? 'showError' : 'showSuccess'](message || (isError ? 'The action could not be completed.' : 'Updated.'));
    }

    async function handle(form) {
        var $form = $(form), $button = $form.find('[type="submit"]'), original = $button.html();
        if ($button.prop('disabled')) return;
        if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
        $button.prop('disabled', true).attr('aria-busy', 'true');
        try {
            if (typeof window.show_loader === 'function') window.show_loader();
            var formData = new FormData(form);
            if (window.RootinsCsrf) window.RootinsCsrf.formData(formData);
            var response = await fetchWithTimeout(form.action, { method: 'POST', body: formData, credentials: 'same-origin', headers: csrfHeaders() }, 30000);
            var payload = await parseJsonResponse(response);
            feedback(payload.message || payload.msg || 'Shopify order updated.', false);
            if ($('#shopifyProcessOrderModal').hasClass('show')) $('#shopifyProcessOrderModal').modal('hide');
            try {
                await refreshDetailStatus();
            } catch (refreshError) {
                feedback('The action completed, but the order status could not be refreshed.', true);
            }
        } catch (error) {
            var message = error && error.name === 'AbortError' ? 'The request timed out. Please retry.' : (error && error.message ? error.message : 'The action could not be completed safely.');
            feedback(message, true);
        } finally {
            clearStaleLoadingState();
            $button.prop('disabled', false).removeAttr('aria-busy').html(original);
        }
    }

    async function refreshDetailStatus() {
        var $page = $('#shopifyOrderDetailPage'), url = $page.data('details-url');
        if (!$page.length || !url) return;
        var response = await fetchWithTimeout(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, 15000);
        var payload = await parseJsonResponse(response), data = payload.data && payload.data.import ? payload.data.import : null;
        if (!data) return;
        var status = label(data.status), error = data.error_message || data.last_error || data.failure_reason || data.quarantine_reason || 'No active error.';
        var $status = $('#shopifyOrderDetailStatus');
        if ($status.length) $status.find('[data-import-status]').text(status).end().find('[data-import-error]').text(error);
    }

    function renderDetails(importData) {
        var lines = importData.lines || [], history = importData.history || [], html = '<div class="shopify-detail-summary">'
            + '<div><small>Shopify order</small><strong>' + escapeHtml(importData.shopify_order_number || importData.shopify_order_id) + '</strong><span>' + escapeHtml(importData.shopify_order_id) + '</span></div>'
            + '<div><small>Customer / patient</small><strong>' + escapeHtml(importData.customer_name_snapshot || importData.customer_email_snapshot || 'Unknown') + '</strong><span>' + escapeHtml(importData.registration_number || 'Patient not linked') + '</span></div>'
            + '<div><small>Status</small><strong>' + escapeHtml(label(importData.status)) + '</strong><span>' + escapeHtml(label(importData.mapping_status)) + ' · ' + escapeHtml(label(importData.prescription_status)) + '</span></div></div>';
        html += '<h3 class="h6 mt-4">Medicine lines</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Medicine</th><th>Shopify IDs</th><th>Qty</th><th>Mapping</th></tr></thead><tbody>';
        if (!lines.length) html += '<tr><td colspan="4" class="text-muted">No line items recorded.</td></tr>';
        lines.forEach(function (line) { html += '<tr><td><strong>' + escapeHtml(line.medicine_name || line.title_snapshot || 'Unmapped medicine') + '</strong><small class="d-block text-muted">' + escapeHtml((line.strength || '') + ' ' + (line.unit || '')) + '</small></td><td><small>Line: ' + escapeHtml(line.shopify_line_item_id) + '<br>Product: ' + escapeHtml(line.shopify_product_id || '—') + '<br>Variant: ' + escapeHtml(line.shopify_variant_id || '—') + '</small></td><td>' + escapeHtml(line.quantity) + '</td><td><span class="badge badge-status">' + escapeHtml(line.mapping_reason || label(line.status)) + '</span></td></tr>'; });
        html += '</tbody></table></div><h3 class="h6 mt-4">Order history</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>When</th><th>Action</th><th>Transition</th><th>Reason</th></tr></thead><tbody>';
        if (!history.length) html += '<tr><td colspan="4" class="text-muted">No order history recorded.</td></tr>';
        history.forEach(function (item) { html += '<tr><td>' + escapeHtml(item.created_at) + '</td><td>' + escapeHtml(item.action) + '</td><td>' + escapeHtml((item.from_status || '') + ' → ' + (item.to_status || '')) + '</td><td>' + escapeHtml(item.reason || item.error_message || '—') + '</td></tr>'; });
        return html + '</tbody></table></div>';
    }

    $(document).off('submit.shopifyOrders', '.shopify-order-action-form, .shopify-payment-action-form, .shopify-prescription-review-form').on('submit.shopifyOrders', '.shopify-order-action-form, .shopify-payment-action-form, .shopify-prescription-review-form', function (event) { event.preventDefault(); handle(this); });

    $(document).off('click.shopifyOrders', '.js-shopify-order-details').on('click.shopifyOrders', '.js-shopify-order-details', function () {
        var $button = $(this), $body = $('#shopifyOrderDetailsBody');
        $('.dropdown.show .dropdown-toggle').dropdown('hide');
        $body.html('<div class="text-center text-muted py-5">Loading order details…</div>');
        $('#shopifyOrderDetailsModal').appendTo('body').modal('show');
        $.getJSON($button.data('detail-url')).done(function (response) {
            if (response.status && response.data && response.data.import) { $body.html(renderDetails(response.data.import)); return; }
            $body.html('<div class="alert alert-warning">' + escapeHtml(response.msg || 'Order details could not be loaded.') + '</div>');
        }).fail(function () { $body.html('<div class="alert alert-warning">Order details could not be loaded safely.</div>'); });
    });

    $(document).off('click.shopifyOrders', '.js-shopify-recheck').on('click.shopifyOrders', '.js-shopify-recheck', function (event) {
        event.preventDefault();
        var $button = $(this);
        if ($button.prop('disabled')) return;
        $button.prop('disabled', true).attr('aria-busy', 'true');
        if (typeof window.show_loader === 'function') window.show_loader();
        var data = new FormData();
        data.append('action', 'recheck_mapping');
        if (window.RootinsCsrf) window.RootinsCsrf.formData(data);
        fetchWithTimeout($button.data('action-url'), { method: 'POST', credentials: 'same-origin', headers: csrfHeaders(), body: data }, 30000)
            .then(parseJsonResponse)
            .then(function (response) {
                feedback(response.message || response.msg || 'Mapping rechecked.', false);
            })
            .catch(function (error) {
                var message = error && error.name === 'AbortError' ? 'The request timed out. Please retry.'
                    : error && error.code === 'SHOPIFY_MAPPING_CSRF_INVALID' ? 'SHOPIFY_MAPPING_CSRF_INVALID: Your session expired. Refresh the page and try again.'
                    : (error && error.message ? error.message : 'Mapping could not be rechecked safely.');
                feedback(message, true);
            })
            .finally(function () {
                $button.prop('disabled', false).removeAttr('aria-busy');
                if (typeof window.hide_loader === 'function') window.hide_loader();
                $('.loading, .is-loading').removeClass('loading is-loading').removeAttr('aria-busy');
            });
    });
}(window.jQuery));
