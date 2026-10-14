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
        $('#review-header-number').text(record.prescription_number || 'Prescription not recorded');
        $('#review-header-date').text(record.prescription_date || 'Date not recorded');
        $('#review-header-status').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.status_label)).text(record.status_label || 'Status unavailable');
        $('#review-header-state').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.review_state)).text(record.review_state || 'Review state unavailable');
        $('#pharmacy-prescription-modal-subtitle').text((record.review_state || '') + (record.notes ? ' · ' + record.notes : ''));
        const rows = (record.items || []).map(function (item) {
            return '<tr><td>' + esc(item.medicine_name || 'Not recorded') + '</td><td>' + esc(item.duration || 'Not recorded') + '</td><td>' + esc(item.frequency || 'Not recorded') + '</td><td>' + esc(item.dose || 'Not recorded') + '</td><td>' + esc(item.intake || 'Not recorded') + '</td><td>' + esc(item.note || 'Not recorded') + '</td></tr>';
        });
        $('#review-medicine-rows').html(rows.length ? rows.join('') : '<tr><td colspan="6" class="text-muted">No medicine details recorded.</td></tr>');
        $('#review-decision').val(decision || '');
        $('#review-reason').val('');
        $('#review-approve-confirmation').prop('checked', false);
        clearFormErrors('#pharmacy-review-form');
        setReviewMode(decision || '');
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
    });
})(jQuery);
