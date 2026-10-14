(function ($) {
    'use strict';
    $(function () {
        $('.inventory-detail-action').on('click', function () {
            const button = $(this);
            $('#inventory-detail-medicine').text(button.attr('data-medicine') || 'Not recorded');
            $('#inventory-detail-batch').text(button.attr('data-batch') || 'Not recorded');
            $('#inventory-detail-expiry').text(button.attr('data-expiry') || 'Not recorded');
            $('#inventory-detail-quantity').text(button.attr('data-quantity') || '0');
            $('#inventory-detail-unit').text(button.attr('data-unit') || 'Unit not recorded');
            $('#inventory-detail-status').text(button.attr('data-status') || 'Unavailable');
            $('#inventory-detail-expiry-state').text(button.attr('data-expiry-state') || 'Not recorded');
        });
        $('.inventory-adjust-action').on('click', function () {
            const button = $(this);
            $('#inventory-adjust-batch-id').val(button.data('batch-id'));
            $('#inventory-adjust-summary').text((button.data('medicine') || 'Medicine') + ' · batch ' + (button.data('batch') || 'Not recorded'));
            $('#inventory-adjustment, #inventory-adjust-reason').val('').removeClass('is-invalid');
            $('#inventory-adjust-confirm').prop('checked', false);
            $('#inventory-adjust-form .validation-error').empty();
        });
        $('#pharmacy-inventory-adjust-form').on('submit', function (event) {
            event.preventDefault();
            const form = this;
            clearFormErrors(form);
            const data = $(form).serializeArray();
            data.push({name: 'confirmation', value: $('#inventory-adjust-confirm').is(':checked') ? 'I confirm this central stock adjustment' : ''});
            ajaxRequest({url: form.action, method: 'POST', data: $.param(data), success: function (response) {
                if (response.status) { showSuccess(response.msg); $('#pharmacy-inventory-adjust').modal('hide'); window.location.reload(); return; }
                bindFormError(form, response.errors || {});
                showError(response.msg || 'Stock adjustment could not be saved.');
            }});
        });
        $('#pharmacy-inventory-create-form').on('submit', function (event) {
            event.preventDefault();
            const form = this;
            const submit = $(form).find('button[type="submit"]');
            if (submit.prop('disabled')) return;
            clearFormErrors(form);
            const data = $(form).serializeArray().filter(function (field) {
                return field.name !== 'expiry_date';
            });
            data.push({name: 'expiry_date', value: String($('#inventory-create-expiry').val() || '').trim()});
            submit.prop('disabled', true).attr('aria-busy', 'true');
            ajaxRequest({url: form.action, method: 'POST', data: $.param(data), success: function (response) {
                if (response.status) { showSuccess(response.msg); $('#pharmacy-inventory-create').modal('hide'); window.location.reload(); return; }
                bindFormError(form, response.errors || {});
                showError(response.msg || 'Stock batch could not be created.');
            }, error: function (xhr, status, error) {
                const response = xhr.responseJSON || {};
                if (xhr.status === 422) {
                    bindFormError(form, response.errors || {});
                    showError(response.msg || 'Validation failed.');
                } else if (xhr.status === 403) {
                    showError(response.msg || 'Access denied.');
                } else if (xhr.status === 404) {
                    showError(response.msg || 'Stock batch endpoint was not found.');
                } else if (xhr.status === 0 && status === 'timeout') {
                    showError('The request timed out. Please try again.');
                } else if (xhr.status === 0 && status === 'abort') {
                    showError('The stock batch request was cancelled.');
                } else if (xhr.status === 0) {
                    showError('Unable to connect to the server.');
                } else {
                    showError(response.msg || error || 'Stock batch could not be created.');
                }
            }, complete: function () {
                submit.prop('disabled', false).removeAttr('aria-busy');
            }});
        });
        $('#pharmacy-inventory-create').on('shown.bs.modal', function () {
            const select = $(this).find('.btSelect');
            if (select.length && select.data('selectpicker')) select.selectpicker('refresh');
        });
    });
})(jQuery);
