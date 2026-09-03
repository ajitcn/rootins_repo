$(function () {
    const form = '#medicineForm';

    function resetMedicineForm() {
        resetForm(form);
        clearFormErrors();
        $('#medicine_id').val('');
        $('[name="status"]').val('active');
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
                $.each(['medicine_name', 'generic_name', 'medicine_code', 'dosage_form', 'strength', 'manufacturer', 'description', 'status'], function (_, field) {
                    $('[name="' + field + '"]').val(medicine[field] || '');
                });
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
