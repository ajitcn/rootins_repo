(function ($) {
    'use strict';

    function csrfHeaders() {
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
        if (!payload || !payload.csrf || !payload.csrf.hash) return;
        $('input[name="' + (payload.csrf.name || 'csrf_rtn') + '"]').val(payload.csrf.hash);
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
        var $form = $(form), $button = $form.find('[type="submit"]'), original = $button.html(), succeeded = false;
        if ($button.prop('disabled')) return;
        if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
        $button.prop('disabled', true).attr('aria-busy', 'true');
        try {
            if (typeof window.show_loader === 'function') window.show_loader();
            var response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': $(form).find('[name="csrf_rtn"]').val() || '', Accept: 'application/json' } });
            var text = await response.text(), payload;
            try { payload = JSON.parse(text); } catch (parseError) { throw new Error('The server returned an invalid response. Please try again.'); }
            refreshCsrf(payload);
            if (!response.ok || !payload.status) throw new Error(payload.msg || ('The action could not be completed (HTTP ' + response.status + ').'));
            succeeded = true;
            feedback(payload.msg || 'Shopify order updated.', false);
            $('#shopifyProcessOrderModal').one('hidden.bs.modal', function () { clearStaleLoadingState(); }).modal('hide');
            window.setTimeout(function () { window.location.reload(); }, 350);
        } catch (error) {
            feedback(error && error.message ? error.message : 'The action could not be completed safely.', true);
        } finally {
            clearStaleLoadingState();
            $button.prop('disabled', false).removeAttr('aria-busy').html(original);
        }
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

    $(document).off('submit.shopifyOrders', '.shopify-order-action-form, .shopify-prescription-review-form').on('submit.shopifyOrders', '.shopify-order-action-form, .shopify-prescription-review-form', function (event) { event.preventDefault(); handle(this); });

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

    $(document).off('click.shopifyOrders', '.js-shopify-recheck').on('click.shopifyOrders', '.js-shopify-recheck', function () {
        var $button = $(this), data = { action: 'recheck_mapping', csrf_rtn: $('input[name="csrf_rtn"]').first().val() || '' };
        if ($button.prop('disabled')) return;
        $button.prop('disabled', true);
        $.ajax({ url: $button.data('action-url'), method: 'POST', data: data, dataType: 'json', headers: csrfHeaders() }).done(function (response) { if (response.status) { if (typeof showSuccess === 'function') showSuccess(response.msg || 'Mapping rechecked.'); window.setTimeout(function () { window.location.reload(); }, 250); } else if (typeof showError === 'function') showError(response.msg || 'Mapping could not be rechecked.'); }).fail(function (xhr) { if (typeof showError === 'function') showError((xhr.responseJSON && xhr.responseJSON.msg) || 'Mapping could not be rechecked safely.'); }).always(function () { $button.prop('disabled', false); });
    });
}(window.jQuery));
