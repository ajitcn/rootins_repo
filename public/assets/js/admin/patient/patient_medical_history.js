$(function () {
    const patientId = window.patientMedicalHistoryId;
    const form = '#medicalHistoryForm';

    function resetHistoryForm() {
        resetForm(form);
        clearFormErrors();
        $('#medical_history_id').val('');
        $('#medicalHistoryModal .modal-title').text('Add Medical History');
        $(form + ' button[type="submit"]').text('Save History');
    }

    $('#openMedicalHistoryModal').on('click', function () {
        resetHistoryForm();
        $('#medicalHistoryModal').modal('show');
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();

        const historyId = $('#medical_history_id').val();
        const url = historyId
            ? ROUTES.patients.medicalHistory.update + patientId + '/medical-history/update/' + historyId
            : ROUTES.patients.medicalHistory.save + patientId + '/medical-history/save';

        ajaxRequest({
            url: url,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#medicalHistoryModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
                bindFormError(form, response.errors);
            }
        });
    });

    $('.edit-medical-history').on('click', function () {
        const historyId = $(this).data('id');

        clearFormErrors();

        ajaxRequest({
            url: ROUTES.patients.medicalHistory.edit + patientId + '/medical-history/edit/' + historyId,
            method: 'GET',
            success: function (response) {
                if (! response.status) {
                    showError(response.msg);
                    return;
                }

                resetHistoryForm();
                $('#medical_history_id').val(response.data.id);
                $('#medical_history_title').val(response.data.title);
                if (window.setSharedDateValue) {
                    setSharedDateValue('#medical_history_date', response.data.history_date || '');
                } else {
                    $('#medical_history_date').val(response.data.history_date || '');
                }
                $('#medical_history_details').val(response.data.details);
                $('#medicalHistoryModal .modal-title').text('Edit Medical History');
                $(form + ' button[type="submit"]').text('Update History');
                $('#medicalHistoryModal').modal('show');
            }
        });
    });
});
