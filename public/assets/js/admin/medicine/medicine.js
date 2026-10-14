$(function () {
    const form = '#medicineForm';

    function resetMedicineForm() {
        resetForm(form);
        clearFormErrors();
        $('#medicine_id').val('');
        $('#medicineModal select option[data-inactive="1"]').remove();
        $('#medicineModal select.btSelect').each(function () {
            const select = $(this);
            if (typeof select.selectpicker === 'function' && !select.hasClass('selectpicker')) {
                select.selectpicker({liveSearch: true});
            }
        });
        const statusSelect = $('#medicine_status_id').val($('#medicine_status_id option:not(:first)').first().val() || '');
        if (typeof statusSelect.selectpicker === 'function') statusSelect.selectpicker('refresh');
        $('#medicineModal .modal-title').text('Add Medicine');
        $(form + ' .save').text('Save Medicine');
    }

    $('#openMedicineModal').on('click', function () {
        resetMedicineForm();
        $('#medicineModal').modal('show');
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();
        const id = $('#medicine_id').val();
        ajaxRequest({
            url: id ? ROUTES.medicines.update + id : ROUTES.medicines.save,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#medicineModal').modal('hide');
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

    $('.edit-medicine').on('click', function () {
        const id = $(this).data('id');
        ajaxRequest({
            url: ROUTES.medicines.edit + id,
            method: 'GET',
            success: function (response) {
                if (! response.status) { showError(response.msg); return; }
                resetMedicineForm();
                const medicine = response.data;
                $('#medicine_id').val(medicine.id);
                    $.each(['form_id', 'strength_id', 'unit_id', 'manufacturer_id', 'status_id', 'prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id'], function (_, field) {
                        const select = $('[name="' + field + '"]');
                        const value = medicine[field] || '';
                        const labels = {form_id: 'form', strength_id: 'strength', unit_id: 'unit', manufacturer_id: 'manufacturer', status_id: 'status', prescription_duration_id: 'prescription_duration', prescription_frequency_id: 'prescription_frequency', prescription_dose_id: 'prescription_dose', prescription_intake_id: 'prescription_intake'};
                        if (value && !select.find('option[value="' + value + '"]').length && medicine[labels[field]]) select.append($('<option>', {value: value, text: medicine[labels[field]] + ' (inactive)', 'data-inactive': '1'}));
                        select.val(value);
                    });
                    $.each(['medicine_name', 'generic_name', 'medicine_code', 'unit_price', 'description', 'prescription_note'], function (_, field) {
                        $('[name="' + field + '"]').val(medicine[field] || '');
                    });
                    $('#medicineModal select.btSelect').selectpicker('refresh');
                $('#medicineModal .modal-title').text('Edit Medicine');
                $(form + ' .save').text('Update Medicine');
                $('#medicineModal').modal('show');
            }
        });
    });

    $('.delete-medicine').on('click', function () {
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

});
