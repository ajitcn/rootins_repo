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

    function statusMarkup(group, value) {
        var status = window.rootinsShopifyStatus ? window.rootinsShopifyStatus(group, value) : { tone: 'gray', label: label(value) };
        return '<span data-status-group="' + escapeHtml(group) + '" data-status="' + escapeHtml(status.status || '') + '" class="badge badge-status ri-status ri-status--' + escapeHtml(status.tone) + ' ri-status-dot ' + escapeHtml(status.className || '') + '">' + escapeHtml(status.label) + '</span>';
    }

    function deliveryLabel(value) {
        return String(value || '').trim() ? label(value) : 'Not fulfilled';
    }

    function prescriptionMarkup(importData) {
        var status = String(importData.prescription_status || '').toLowerCase();
        if (status === 'not_required') return statusMarkup('prescription', status);
        var base = String(window.BASE_URL || '/').replace(/\/?$/, '/');
        if (importData.prescription_document_id) return '<a href="' + escapeHtml(base + 'pharmacy/shopify-orders/' + encodeURIComponent(importData.id) + '/prescription') + '">View prescription</a>';
        return statusMarkup('prescription', 'missing');
    }

    function refreshCsrf(payload) {
        if (window.RootinsCsrf) { window.RootinsCsrf.update(payload); return; }
        if (!payload || !payload.csrf || !payload.csrf.hash) return;
        $('input[name="' + (payload.csrf.name || 'csrf_rtn') + '"]').val(payload.csrf.hash);
    }

    function fetchWithTimeout(url, options, timeoutMs) {
        var controller = window.AbortController ? new AbortController() : null;
        var rejectTimeout;
        var timeoutPromise = new Promise(function (_, reject) { rejectTimeout = reject; });
        var timer = window.setTimeout(function () {
            if (controller) controller.abort();
            var error = new Error('The request timed out. Please retry.');
            error.name = 'AbortError';
            rejectTimeout(error);
        }, timeoutMs);
        if (controller) options.signal = controller.signal;
        return Promise.race([fetch(url, options), timeoutPromise]).finally(function () { window.clearTimeout(timer); });
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
        $('body').removeClass('loading preloader-active rootins-loading');
        $('.preloader, .loading-overlay').remove();
    }

    function setActionLoading(form, loading) {
        var $button = $(form).find('button[type="submit"]');
        $button.prop('disabled', loading).attr('aria-busy', loading ? 'true' : 'false');
        $(form).attr('aria-busy', loading ? 'true' : 'false');
    }

    function showToast(message) { feedback(message, false); }
    function showErrorToast(message) { feedback(message, true); }

    function feedback(message, isError) {
        var $feedback = $('#shopifyOrderActionFeedback');
        if ($feedback.length) $feedback.removeClass('d-none alert-success alert-danger').addClass(isError ? 'alert alert-danger' : 'alert alert-success').text(message || (isError ? 'The action could not be completed.' : 'Updated.'));
        if (typeof window[isError ? 'showError' : 'showSuccess'] === 'function') window[isError ? 'showError' : 'showSuccess'](message || (isError ? 'The action could not be completed.' : 'Updated.'));
    }

    async function handle(form) {
        var $form = $(form), $button = $form.find('[type="submit"]');
        if ($button.prop('disabled')) return false;
        if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
        var actionUrl = form.getAttribute('action');
        var actionField = form.querySelector('[name="action"]');
        if (actionField && actionField.value === 'retry_validation') actionField.value = 'retry_import';
        setActionLoading(form, true);
        try {
            if (!actionUrl || actionUrl.includes('loading-icon.gif') || actionUrl.includes('[object') || actionUrl.includes('undefined') || actionUrl.includes('null')) {
                throw new Error('Invalid Shopify order action URL.');
            }
            if (typeof window.show_loader === 'function') window.show_loader();
            var formData = new FormData(form);
            if (window.RootinsCsrf) window.RootinsCsrf.formData(formData);
            var response = await fetchWithTimeout(actionUrl, { method: 'POST', body: formData, credentials: 'same-origin', headers: csrfHeaders() }, 30000);
            var payload = await parseJsonResponse(response);
            showToast(payload.message || payload.msg || 'Operation completed successfully.');
            window.location.reload();
        } catch (error) {
            var message = error && error.name === 'AbortError' ? 'The request timed out. Please retry.' : (error && error.message ? error.message : 'The action could not be completed safely.');
            showErrorToast(message);
        } finally {
            clearStaleLoadingState();
            setActionLoading(form, false);
        }
        return false;
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
            + '<div><small>Status</small>' + statusMarkup('import', importData.status) + '<span>' + escapeHtml(label(importData.mapping_status)) + ' · ' + escapeHtml(label(importData.prescription_status)) + '</span></div></div>';
        html += '<div class="small text-muted mt-3"><strong>Prescription:</strong> ' + prescriptionMarkup(importData) + ' <span class="ml-3"><strong>Delivery:</strong> ' + statusMarkup('delivery', importData.fulfillment_status) + '</span></div><h3 class="h6 mt-4">Medicine lines</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Medicine</th><th>Shopify IDs</th><th>Qty</th><th>Mapping</th></tr></thead><tbody>';
        if (!lines.length) html += '<tr><td colspan="4" class="text-muted">No line items recorded.</td></tr>';
        lines.forEach(function (line) { html += '<tr><td><strong>' + escapeHtml(line.medicine_name || line.title_snapshot || 'Unmapped medicine') + '</strong><small class="d-block text-muted">' + escapeHtml((line.strength || '') + ' ' + (line.unit || '')) + '</small></td><td><small>Line: ' + escapeHtml(line.shopify_line_item_id) + '<br>Product: ' + escapeHtml(line.shopify_product_id || '—') + '<br>Variant: ' + escapeHtml(line.shopify_variant_id || '—') + '</small></td><td>' + escapeHtml(line.quantity) + '</td><td><span class="badge badge-status">' + escapeHtml(line.mapping_reason || label(line.status)) + '</span></td></tr>'; });
        html += '</tbody></table></div><h3 class="h6 mt-4">Order history</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>When</th><th>Action</th><th>Transition</th><th>Reason</th></tr></thead><tbody>';
        if (!history.length) html += '<tr><td colspan="4" class="text-muted">No order history recorded.</td></tr>';
        history.forEach(function (item) { html += '<tr><td>' + escapeHtml(item.created_at) + '</td><td>' + escapeHtml(item.action) + '</td><td>' + escapeHtml((item.from_status || '') + ' → ' + (item.to_status || '')) + '</td><td>' + escapeHtml(item.reason || item.error_message || '—') + '</td></tr>'; });
        return html + '</tbody></table></div>';
    }

    $('.shopify-order-action-form, .shopify-payment-action-form, .shopify-order-operation-action-form')
        .addClass('shopify-order-operation-action-form')
        .attr('data-no-global-loader', 'true')
        .find('[name="action"][value="retry_validation"]').val('retry_import');

    $(document).off('submit.shopifyOrders', '.shopify-order-action-form, .shopify-payment-action-form, .shopify-order-operation-action-form')
        .on('submit', '.shopify-order-action-form, .shopify-payment-action-form, .shopify-order-operation-action-form', async function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            await handle(this);
            return false;
        });

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
