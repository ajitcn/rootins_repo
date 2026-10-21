(function () {
    'use strict';
    const modal = '#doctorMasterModal';
    const form = '#doctorMasterForm';
    const id = '#doctorMasterId';
    const name = '#doctorMasterName';

    function resetMaster() {
        $(form)[0].reset();
        $(id).val('');
        clearFormErrors(form);
        $(modal + ' .modal-title').text('Add ' + $(modal).data('singular'));
    }

    $(document).on('click', '[data-doctor-master-add]', function () {
        resetMaster();
    });

    $(document).on('click', '[data-doctor-master-edit]', function () {
        const row = $(this).data('doctor-master-edit');
        resetMaster();
        $(id).val(row.id);
        $(name).val(row.name);
        $(modal + ' .modal-title').text('Edit value');
    });

    $(form).on('submit', function (event) {
        event.preventDefault();
        clearFormErrors(form);
        const button = $(form).find(':submit');
        button.prop('disabled', true).text('Saving...');
        ajaxRequest({
            url: $(form).attr('action'),
            method: 'POST',
            data: $(form).serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    $(modal).modal('hide');
                    window.setTimeout(function () { window.location.reload(); }, 500);
                    return;
                }
                if (response.errors) bindFormError(form, response.errors);
                showError(response.msg || 'Please correct the highlighted field.');
            },
            error: function (xhr) { handleFormAjaxError(form, xhr, 'The master value could not be saved safely.'); },
            complete: function () { button.prop('disabled', false).text('Save'); }
        });
    });

    $(document).on('submit', '.doctor-master-toggle', function (event) {
        event.preventDefault();
        const current = this;
        const button = $(current).find(':submit');
        button.prop('disabled', true);
        ajaxRequest({
            url: $(current).attr('action'), method: 'POST', data: $(current).serialize(),
            success: function (response) { if (response.status) window.location.reload(); else showError(response.msg || 'Status could not be changed.'); },
            error: function (xhr) { handleFormAjaxError(null, xhr, 'Status could not be changed safely.'); },
            complete: function () { button.prop('disabled', false); }
        });
    });
}());
