$(function () {
    const form = '#medicineForm';
    const modal = '#medicineEditModal';

    function portalMedicineModal() {
        const element = document.getElementById('medicineEditModal');
        if (element && element.parentNode !== document.body) document.body.appendChild(element);
        return element;
    }

    function clearStaleModalLayer() {
        $(modal).removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({'padding-right': '', 'overflow': ''});
    }

    function showMedicineModal() {
        const element = portalMedicineModal();
        if (!element || typeof $.fn.modal !== 'function') return;
        clearStaleModalLayer();
        $(element).modal('show');
    }

    function initializeMedicinePickers() {
        $('#medicineEditModal select.btSelect').each(function () {
            const select = $(this);
            if (typeof select.selectpicker !== 'function') return;
            if (!select.parent().hasClass('bootstrap-select')) {
                select.selectpicker({liveSearch: true, dropupAuto: false, container: 'body'});
            } else {
                select.selectpicker('refresh');
            }
        });
    }

    function resetMedicineForm() {
        const formElement = $(form)[0];
        if (formElement) formElement.reset();
        initializeMedicinePickers();
        clearFormErrors();
        $('#medicineEditId').val('');
        $('#medicine_prescription_required_status').val('Not configured');
        $('#medicineEditModal select option[data-inactive="1"]').remove();
        const statusSelect = $('#medicine_status_id').val($('#medicine_status_id option:not(:first)').first().val() || '');
        if (typeof statusSelect.selectpicker === 'function') statusSelect.selectpicker('refresh');
        initializeMedicinePickers();
        $('#medicineEditModal .modal-title').text('Add Medicine');
        $(form + ' .save').text('Save Medicine');
    }

    $('#openMedicineModal').on('click', function () {
        resetMedicineForm();
        showMedicineModal();
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();
        const id = $('#medicineEditId').val();
        ajaxRequest({
            url: id ? ROUTES.medicines.update + id : ROUTES.medicines.save,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#medicineEditModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }
                showError(response.msg);
                bindFormError(form, response.errors);
            },
            error: function (xhr) {
                handleFormAjaxError(form, xhr, 'Unable to create medicine safely.');
            }
        });
    });

    $(document).off('click.medicineEdit', '.edit-medicine-btn')
    .on('click.medicineEdit', '.edit-medicine-btn', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const button = $(this);
        const modal = $('#medicineEditModal');
        const id = String(button.data('medicine-id') || '').trim();
        if (!/^\d+$/.test(id) || Number(id) < 1) {
            console.error('Medicine id missing on edit button');
            showError('The selected medicine could not be identified.');
            return;
        }
        if (!modal.length) {
            console.error('medicineEditModal not found');
            return;
        }
        if (typeof $.fn.modal !== 'function') {
            console.error('Bootstrap 4 modal API not available');
            return;
        }
        $('.modal.show').not(modal).each(function () {
            if (typeof $(this).modal === 'function') $(this).modal('hide');
        });
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
        resetMedicineForm();
        $('#medicineEditId').val(id);
        $('#medicineEditName').val(button.data('medicine-name') || '');
        $('[name="generic_name"]').val(button.data('generic-name') || '');
        $('[name="medicine_code"]').val(button.data('medicine-code') || '');
        $('[name="unit_price"]').val(button.data('unit-price') || '0.00');
        $('[name="description"]').val(button.data('description') || '');
        $('[name="prescription_note"]').val(button.data('prescription-note') || '');
        $('#medicineEditForm').val(button.data('form-id') || '');
        $('#medicineEditStrength').val(button.data('strength-id') || '');
        $('#medicineEditUnit').val(button.data('unit-id') || '');
        $('#medicineEditManufacturer').val(button.data('manufacturer-id') || '');
        $('#medicineEditDuration').val(button.data('prescription-duration-id') || '');
        $('#medicineEditFrequency').val(button.data('prescription-frequency-id') || '');
        $('#medicineEditDose').val(button.data('prescription-dose-id') || '');
        $('#medicineEditIntake').val(button.data('prescription-intake-id') || '');
        $('#medicine_status_id').val(button.data('status-id') || '');
        const prescriptionRequired = button.attr('data-prescription-required');
        $('#medicine_prescription_required_status').val(
            prescriptionRequired === '1' ? 'Required' : prescriptionRequired === '0' ? 'Not required' : 'Not configured'
        );
        initializeMedicinePickers();
        $('#medicineEditModal .modal-title').text('Edit Medicine');
        $(form + ' .save').text('Update Medicine');
        modal.appendTo('body');
        modal.modal({backdrop: true, keyboard: true, show: true});
    });

    $(document).on('click', '#medicines-table .delete-medicine', function () {
        const id = $(this).data('id');
        if (! window.confirm('Delete this medicine?')) return;
        ajaxRequest({
            url: ROUTES.medicines.delete + id,
            method: 'POST',
            data: {csrf_rtn: $('input[name="csrf_rtn"]').first().val()},
            success: function (response) {
                if (response.status) { showSuccess(response.msg); reload_page(500); return; }
                showError(response.msg);
            }
        });
    });

    portalMedicineModal();
    initializeMedicinePickers();

});
