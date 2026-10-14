(function ($) {
    'use strict';
    const appBase = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '/')).replace(/\/+$/, '');
    const base = appBase + '/pharmacy/delivery/';
    const statuses = ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'];
    const statusRank = {pending: 0, packed: 1, dispatched: 2, out_for_delivery: 3, delivered: 4, failed: 5, returned: 6};
    const sequentialStatuses = {pending: ['pending', 'packed', 'failed'], packed: ['packed', 'dispatched', 'failed'], dispatched: ['dispatched', 'out_for_delivery', 'failed'], out_for_delivery: ['out_for_delivery', 'delivered', 'failed'], failed: ['failed', 'returned'], delivered: ['delivered', 'returned'], returned: ['returned']};
    const labels = {packed: 'Packed', dispatched: 'Dispatched', out_for_delivery: 'Out for delivery', delivered: 'Delivered', failed: 'Failed', returned: 'Returned'};
    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function human(value) { return String(value || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
    function display(value) { const raw = String(value || '').trim(); return raw === '' || raw.toLowerCase() === 'not recorded' ? '—' : raw; }
    function clearErrors(form) { $(form).find('.validation-error').empty(); $(form).find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid'); }
    function loading(message) { return '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>' + esc(message) + '</div>'; }
    function statusBadge(label, value, className) { const source = String(value || '').trim(); const missing = source === '' || source.toLowerCase() === 'not recorded' || source.toLowerCase() === 'unknown'; return '<span class="badge delivery-modal-status ' + (className || (missing ? 'badge-light' : '')) + '"><span class="sr-only">' + esc(label) + ': </span>' + esc(missing ? 'Not recorded' : human(source)) + '</span>'; }
    function showDetail(id) {
        $('#deliveryDetailBody').html(loading('Loading delivery details...')); $('#deliveryDetailSummary').text('Loading delivery details...'); $('#pharmacyDeliveryDetailModal').modal('show');
        ajaxRequest({url: base + 'detail/' + encodeURIComponent(id), method: 'GET', dataType: 'json'}).done(function (response) {
            const d = response.data && response.data.delivery;
            if (!response.status || !d) { $('#deliveryDetailBody').html('<div class="alert alert-danger" role="alert">' + esc(response.msg || 'Delivery details are unavailable.') + '</div>'); return; }
            $('#deliveryDetailSummary').text((d.order_number || 'Order') + ' · ' + human(d.delivery_status));
            const items = (d.items || []).map(function (i) { return '<tr><td>' + esc(i.medicine_name || 'Medicine') + '</td><td>' + esc(i.batch_number || 'Not recorded') + '</td><td>' + esc(i.quantity || 0) + '</td></tr>'; }).join('');
            const html = '<section class="delivery-detail-section"><h3 class="delivery-detail-heading">Order and patient</h3><div class="delivery-detail-info-grid"><div><span class="delivery-card-label">Order</span><strong>' + esc(d.order_number) + '</strong></div><div><span class="delivery-card-label">Order date</span><strong>' + esc(display(d.created_at)) + '</strong></div><div><span class="delivery-card-label">Prescription</span><strong>' + esc(d.prescription_number) + '</strong></div><div><span class="delivery-card-label">Patient</span><strong>' + esc(d.patient_name) + '</strong></div><div><span class="delivery-card-label">Patient number</span><strong>' + esc(d.patient_number) + '</strong></div><div><span class="delivery-card-label">Contact</span><strong>' + esc(d.patient_mobile) + '</strong></div></div></section><section class="delivery-detail-section"><h3 class="delivery-detail-heading">Statuses</h3><div class="delivery-detail-statuses">' + statusBadge('Order status', d.order_status) + statusBadge('Payment status', d.payment_status) + statusBadge('Delivery status', d.delivery_status, d.delivery_status === 'delivered' ? 'badge-success' : (['failed', 'returned'].indexOf(String(d.delivery_status || '').toLowerCase()) !== -1 ? 'badge-danger' : '')) + '</div></section><section class="delivery-detail-section"><h3 class="delivery-detail-heading">Delivery snapshot</h3><div class="delivery-detail-info-grid"><div><span class="delivery-card-label">Provider</span><strong>' + esc(display(d.provider)) + '</strong></div><div><span class="delivery-card-label">Tracking reference</span><strong>' + esc(display(d.tracking_reference)) + '</strong></div><div class="delivery-detail-wide"><span class="delivery-card-label">Address snapshot</span><strong>' + esc(display(d.delivery_address)) + '</strong></div><div><span class="delivery-card-label">Contact snapshot</span><strong>' + esc(display(d.delivery_contact)) + '</strong></div><div><span class="delivery-card-label">Dispatched</span><strong>' + esc(display(d.dispatched_at)) + '</strong></div><div><span class="delivery-card-label">Delivered</span><strong>' + esc(display(d.delivered_at)) + '</strong></div></div></section><section class="delivery-detail-section"><h3 class="delivery-detail-heading">Order medicines</h3><div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Order medicines</caption><thead><tr><th>Medicine</th><th>Batch</th><th>Quantity</th></tr></thead><tbody>' + (items || '<tr><td colspan="3">No medicines recorded.</td></tr>') + '</tbody></table></div></section>';
            $('#deliveryDetailBody').html(html);
        }).fail(function (xhr) { $('#deliveryDetailBody').html('<div class="alert alert-danger" role="alert">' + esc((xhr.responseJSON && xhr.responseJSON.msg) || 'Delivery details could not be loaded safely.') + '</div>'); });
    }
    function showRelated(button) {
        $('#pharmacyDeliveryRelatedTitle').text(button.data('related-title') || 'Details'); $('#pharmacyDeliveryRelatedBody').html(loading('Loading details...')); $('#pharmacyDeliveryRelatedModal').modal('show');
        ajaxRequest({url: button.data('related-url'), method: 'GET', dataType: 'json'}).done(function (response) { const data = response.data || {}; const record = data.order || data.billing || data; if (!response.status || !record) { $('#pharmacyDeliveryRelatedBody').html('<div class="alert alert-danger" role="alert">Related details are unavailable.</div>'); return; } $('#pharmacyDeliveryRelatedBody').html('<dl class="row small"><dt class="col-sm-4 text-muted">Order</dt><dd class="col-sm-8">' + esc(display(record.order_number)) + '</dd><dt class="col-sm-4 text-muted">Patient</dt><dd class="col-sm-8">' + esc(display(record.patient_name)) + '</dd><dt class="col-sm-4 text-muted">Status</dt><dd class="col-sm-8">' + esc(display(record.status || record.payment_status || record.delivery_status)) + '</dd><dt class="col-sm-4 text-muted">Updated</dt><dd class="col-sm-8">' + esc(display(record.updated_at)) + '</dd></dl>'); }).fail(function (xhr) { $('#pharmacyDeliveryRelatedBody').html('<div class="alert alert-danger" role="alert">' + esc((xhr.responseJSON && xhr.responseJSON.msg) || 'Related details could not be loaded safely.') + '</div>'); });
    }
    function fillTargets(current) { const select = $('#deliveryTarget').empty().data('current-status', current); const allowed = String(select.data('can-override')) === '1' ? statuses : (sequentialStatuses[current] || [current]); allowed.forEach(function (status) { select.append($('<option>', {value: status, text: labels[status] || human(status), selected: status === current})); }); }
    function updateTrackingPreview() { var option = $('#deliveryProviderInput option:selected'), template = option.data('template') || '', reference = String($('#trackingReference').val() || '').trim(); if (!template || !reference || !/^https?:\/\//i.test(template)) { $('#deliveryTrackingPreviewWrap').addClass('d-none'); return; } var url = template.replace(/\{\{tracking_reference\}\}|\{tracking_reference\}|\{tracking\}/g, encodeURIComponent(reference)); $('#deliveryTrackingPreview').attr('href', url).text(url); $('#deliveryTrackingPreviewWrap').removeClass('d-none'); }
    function updateReasonField() { $('#deliveryReasonLabel').text('Change reason (optional)'); $('#deliveryReason').prop('required', false).removeAttr('aria-required'); }
    function submitForm(form) { const submit = $(form).find('button[type="submit"]'), confirmation = $('#deliveryConfirmation'); clearErrors(form); if (!confirmation.prop('checked')) { confirmation.addClass('is-invalid').attr('aria-invalid', 'true').trigger('focus'); $('#confirmationErr').text('Confirm this delivery update before saving.'); showError('Please confirm this delivery update before saving.'); return; } if (submit.prop('disabled')) return; submit.prop('disabled', true).attr('aria-busy', 'true'); ajaxRequest({url: base + 'update', method: 'POST', data: $(form).serialize(), dataType: 'json'}).done(function (response) { if (response.status) { if (response.manual_update) { showSuccess(response.notice || response.msg); } form.reset(); $('#deliveryConfirmationValue').val(''); clearErrors(form); $('#pharmacyDeliveryUpdateModal').modal('hide'); window.setTimeout(function () { window.location.reload(); }, response.manual_update ? 500 : 0); return; } bindFormError(form, response.errors || {delivery_status: response.msg}); showError(response.msg || 'Delivery status could not be updated safely.'); }).fail(function (xhr) { handleFormAjaxError(form, xhr, 'Delivery status could not be updated safely.'); }).always(function () { submit.prop('disabled', false).removeAttr('aria-busy'); }); }
    $(document).on('click', '.js-delivery-detail', function () { showDetail($(this).data('order-id')); });
    $(document).on('click', '.js-delivery-related', function () { showRelated($(this)); });
    $(document).on('click', '.js-delivery-update', function () {
        const button = $(this);
        const form = $('#pharmacyDeliveryUpdateForm')[0];
        const attr = function (name) { return button.attr('data-' + name) || ''; };
        const currentStatus = attr('current-status').trim().toLowerCase();
        const valueOrNotRecorded = function (value) {
            const raw = String(value || '').trim();
            return raw === '' || raw.toLowerCase() === 'not recorded' ? 'Not recorded' : raw;
        };
        const statusText = function (value) {
            const raw = String(value || '').trim();
            return raw === '' || raw.toLowerCase() === 'not recorded' ? 'Not recorded' : human(raw);
        };
        const setStatus = function (selector, value) {
            const raw = String(value || '').trim().toLowerCase();
            const className = raw === 'paid' || raw === 'fulfilled' || raw === 'delivered' ? 'badge-success' : (raw === 'failed' || raw === 'cancelled' || raw === 'returned' || raw === 'refunded' ? 'badge-danger' : (raw === '' || raw === 'unknown' || raw === 'not recorded' ? 'badge-light' : 'badge-warning'));
            $(selector).removeClass('badge-success badge-danger badge-warning badge-light badge-info').addClass(className).text(statusText(value));
        };

        form.reset();
        $('#deliveryOrderId').val(attr('order-id'));
        $('#deliveryOrderLabel').text('Update ' + valueOrNotRecorded(attr('order-number')));
        $('#deliverySummaryOrderNumber').text(valueOrNotRecorded(attr('order-number')));
        $('#deliverySummaryPatient').text(valueOrNotRecorded(attr('patient-name')));
        $('#deliverySummaryPatientNumber').text(valueOrNotRecorded(attr('patient-number')));
        $('#deliverySummaryPrescription').text(valueOrNotRecorded(attr('prescription-number')));
        setStatus('#deliverySummaryOrderStatus', attr('order-status'));
        setStatus('#deliverySummaryPaymentStatus', attr('payment-status'));
        setStatus('#deliverySummaryDeliveryStatus', currentStatus);
        $('#deliveryCurrentStatus').val(statusText(currentStatus));
        fillTargets(currentStatus);
        updateReasonField();
        var providerId = String(attr('provider-id') || '0'); $('#deliveryProviderInput option.legacy-provider-option').remove(); if (providerId !== '0' && $('#deliveryProviderInput option[value="' + providerId + '"]').length) { $('#deliveryProviderInput').val(providerId); } else { var legacy = attr('provider'); if (legacy && legacy.toLowerCase() !== 'not recorded' && legacy.toLowerCase() !== 'manual') { $('#deliveryProviderInput').append($('<option>', {value: 'legacy', text: 'Legacy: ' + legacy, class: 'legacy-provider-option', selected: true}).attr('data-code', legacy)); $('#deliveryProviderInput').val('legacy'); } else { $('#deliveryProviderInput').val(''); } } $('#deliveryProviderCode').val($('#deliveryProviderInput option:selected').data('code') || attr('provider') || 'manual');
        $('#trackingReference').val(attr('tracking').toLowerCase() === 'not recorded' ? '' : attr('tracking'));
        $('#deliveryAddress').val(valueOrNotRecorded(attr('address')));
        $('#deliveryContact').val(valueOrNotRecorded(attr('contact')));
        $('#deliveryDispatchedAt').val(valueOrNotRecorded(attr('dispatched-at')));
        $('#deliveryDeliveredAt').val(valueOrNotRecorded(attr('delivered-at')));
        updateTrackingPreview();
        $('#deliveryReason').val('');
        $('#deliveryConfirmationValue').val('');
        clearErrors(form);
        $('#pharmacyDeliveryUpdateModal').modal('show');
    });
    $(document).on('change', '#deliveryTarget', updateReasonField);
    $(document).on('change', '#deliveryProviderInput', function () { $('#deliveryProviderCode').val($('#deliveryProviderInput option:selected').data('code') || 'manual'); updateTrackingPreview(); });
    $(document).on('input', '#trackingReference', updateTrackingPreview);
    $('#deliveryConfirmation').on('change', function () { $('#deliveryConfirmationValue').val(this.checked ? 'I confirm this delivery update' : ''); if (this.checked) { $(this).removeClass('is-invalid').removeAttr('aria-invalid'); $('#confirmationErr').empty(); } });
    $('#pharmacyDeliveryUpdateForm').on('submit', function (event) { event.preventDefault(); submitForm(this); });
})(jQuery);
