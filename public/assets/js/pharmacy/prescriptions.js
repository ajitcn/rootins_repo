(function ($) {
    'use strict';

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function recordFrom(button) { try { return JSON.parse($(button).attr('data-record') || '{}'); } catch (error) { return {}; } }
    function statusBadgeClass(status) {
        status = String(status || '').toLowerCase();
        if (status.indexOf('approved') !== -1) return 'badge-success';
        if (status.indexOf('rejected') !== -1 || status.indexOf('cancel') !== -1 || status.indexOf('expired') !== -1 || status.indexOf('inactive') !== -1) return 'badge-danger';
        if (status.indexOf('clarif') !== -1 || status.indexOf('pending') !== -1 || status.indexOf('submitted') !== -1) return 'badge-warning';
        if (status === '' || status === 'not recorded' || status === 'unknown') return 'badge-light';
        return 'badge-info';
    }
    function setStatusBadge(selector, value) {
        const text = String(value || 'Not recorded');
        $(selector).removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(text)).text(text);
    }
    function loadRecord(record, decision) {
        window.pharmacyActivePrescription = record;
        $('#review-prescription-id').val(record.id || '');
        $('#review-patient-name').text(record.patient_name || 'Patient not recorded');
        $('#review-patient-number').text(record.patient_number || 'Not recorded');
        $('#review-patient-mobile').text(record.patient_mobile || 'Not recorded');
        $('#review-doctor-name').text(record.doctor_name || 'Doctor not recorded');
        $('#review-doctor-code').text(record.doctor_code || 'Not recorded');
        $('#review-prescription-date').text(record.prescription_date || 'Not recorded');
        $('#review-prescription-number').text(record.prescription_number || 'Not recorded');
        setStatusBadge('#review-current-status', record.status_label);
        setStatusBadge('#review-state', record.review_state);
        $('#review-date-label').text(record.review_date_label || 'Last updated');
        $('#review-date').text(record.review_date || 'Not recorded');
        $('#review-valid-until').text(record.valid_until || 'Not recorded');
        setStatusBadge('#review-cart-sync', String(record.cart_sync_status || 'not_synced').replace(/_/g, ' '));
        $('#review-prescription-notes').text(record.notes || 'No prescription notes recorded.');
        $('#review-header-number').text(record.prescription_number || 'Prescription not recorded');
        $('#review-header-date').text(record.prescription_date || 'Date not recorded');
        $('#review-header-status').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.status_label)).text(record.status_label || 'Status unavailable');
        $('#review-header-state').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.review_state)).text(record.review_state || 'Review state unavailable');
        $('#pharmacy-prescription-modal-subtitle').text((record.review_state || '') + (record.notes ? ' · ' + record.notes : ''));
        const rows = (record.items || []).map(function (item) {
            return '<tr><td data-label="Medicine">' + esc(item.medicine_name || 'Not recorded') + '</td><td data-label="Duration">' + esc(item.duration || 'Not recorded') + '</td><td data-label="Frequency">' + esc(item.frequency || 'Not recorded') + '</td><td data-label="Dose">' + esc(item.dose || 'Not recorded') + '</td><td data-label="Quantity">' + esc(item.quantity || 'Not recorded') + '</td><td data-label="Intake">' + esc(item.intake || 'Not recorded') + '</td><td data-label="Note">' + esc(item.note || 'Not recorded') + '</td></tr>';
        });
        $('#review-medicine-rows').html(rows.length ? rows.join('') : '<tr><td colspan="7" class="text-muted">No medicine details recorded.</td></tr>');
        $('#review-decision').val(decision || '');
        $('#review-reason').val('');
        $('#review-approve-confirmation').prop('checked', false);
        clearFormErrors('#pharmacy-review-form');
        setReviewMode(decision || '');
    }
    function printPrescription() {
        const record = window.pharmacyActivePrescription || {};
        const printWindow = window.open('', '_blank', 'width=980,height=760');
        if (!printWindow) return;
        const rows = (record.items || []).map(function (item) { return '<tr><td>' + esc(item.medicine_name || 'Not recorded') + '</td><td>' + esc(item.duration || 'Not recorded') + '</td><td>' + esc(item.frequency || 'Not recorded') + '</td><td>' + esc(item.dose || 'Not recorded') + '</td><td>' + esc(item.quantity || 'Not recorded') + '</td><td>' + esc(item.intake || 'Not recorded') + '</td><td>' + esc(item.note || 'Not recorded') + '</td></tr>'; }).join('');
        printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(record.prescription_number || 'Prescription') + '</title><style>body{font:12px Arial,sans-serif;color:#172033;padding:28px}h1{font-size:22px;margin:0 0 6px}h2{font-size:14px;margin:22px 0 8px;border-bottom:1px solid #ccd3df;padding-bottom:6px}.meta{color:#5e6b82;margin:4px 0}.box{border:1px solid #ccd3df;padding:12px;margin:8px 0}table{width:100%;border-collapse:collapse;table-layout:auto}th,td{padding:7px;border:1px solid #ccd3df;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#f0f3f8}@media print{body{padding:0}}</style></head><body><h1>Prescription ' + esc(record.prescription_number || '') + '</h1><p class="meta">Date: ' + esc(record.prescription_date || 'Not recorded') + ' · Status: ' + esc(record.status_label || 'Not recorded') + '</p><div class="box"><strong>Patient</strong><br>' + esc(record.patient_name || 'Not recorded') + '<br>' + esc(record.patient_number || 'Not recorded') + ' · ' + esc(record.patient_mobile || 'Not recorded') + '</div><div class="box"><strong>Doctor</strong><br>' + esc(record.doctor_name || 'Not recorded') + ' · ' + esc(record.doctor_code || 'Not recorded') + '</div><h2>Medicine plan</h2><table><thead><tr><th>Medicine</th><th>Duration</th><th>Frequency</th><th>Dose</th><th>Quantity</th><th>Intake</th><th>Note</th></tr></thead><tbody>' + (rows || '<tr><td colspan="7">No medicine details recorded.</td></tr>') + '</tbody></table><h2>Instructions and notes</h2><p>' + esc(record.notes || 'No prescription notes recorded.') + '</p></body></html>');
        printWindow.document.close(); printWindow.focus(); window.setTimeout(function () { printWindow.print(); }, 250);
    }
    function setReviewMode(decision) {
        const active = decision !== '';
        $('.review-submit-action').addClass('d-none');
        if (active) $('.review-submit-action[data-decision="' + decision + '"]').removeClass('d-none');
        $('.review-reason-group').toggleClass('d-none', !active || decision === 'approve');
        $('.review-confirmation-group').toggleClass('d-none', !active || decision !== 'approve');
        $('.review-reason-required').toggle(decision !== 'approve');
        $('#review-modal-help').text(active ? 'Review action: ' + decision.replace('_', ' ') : 'Select a review action to continue.');
    }
    function submitReview(button) {
        const form = $('#pharmacy-review-form');
        const submit = $(button);
        if (submit.prop('disabled')) return;
        const decision = String(submit.data('decision') || '');
        $('#review-decision').val(decision);
        if (decision === 'cancel' && !window.confirm('Cancel this prescription review?')) return;
        clearFormErrors(form);
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({url: form.data('review-url'), method: 'POST', data: form.serialize(), dataType: 'json'}).done(function (response) {
            if (response.status) {
                $('#pharmacy-prescription-modal').modal('hide');
                showSuccess(response.msg);
                reload_page(700);
                return;
            }
            bindFormError(form, response.errors || {});
            showError(response.msg || 'Review could not be applied.');
        }).fail(function (xhr) {
            handleFormAjaxError(form, xhr, 'Review could not be applied safely.');
        }).always(function () {
            submit.prop('disabled', false).removeAttr('aria-busy');
        });
    }
    $(function () {
        $(document).on('click', '.prescription-detail-action, .prescription-review-action', function () { loadRecord(recordFrom(this), $(this).data('decision') || ''); });
        $(document).on('click', '.review-submit-action', function () { submitReview(this); });
        $(document).on('click', '#review-print-prescription', printPrescription);
    });
})(jQuery);
