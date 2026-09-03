$(function () {
    const form = '#consultationForm';

    function refreshSelects() {
        $(form + ' .btSelect').selectpicker('refresh');
    }

    function resetConsultationForm() {
        resetForm(form);
        clearFormErrors();
        $('#consultation_id').val('');
        if (window.setSharedDateValue) {
            setSharedDateValue('#consultation_date', new Date().toISOString().slice(0, 10));
        } else {
            $('#consultation_date').val(new Date().toISOString().slice(0, 10));
        }
        $('#consultation_status').val('draft');
        $('#consultationModal .modal-title').text('Add Consultation');
        $(form + ' .save').text('Save Consultation');
        refreshSelects();
    }

    $('#openConsultationModal').on('click', function () {
        resetConsultationForm();
        $('#consultationModal').modal('show');
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();

        const id = $('#consultation_id').val();
        ajaxRequest({
            url: id ? ROUTES.consultations.update + id : ROUTES.consultations.save,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#consultationModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
                bindFormError(form, response.errors);
            }
        });
    });

    $('.edit-consultation').on('click', function () {
        const id = $(this).data('id');
        clearFormErrors();

        ajaxRequest({
            url: ROUTES.consultations.edit + id,
            method: 'GET',
            success: function (response) {
                if (! response.status) {
                    showError(response.msg);
                    return;
                }

                resetConsultationForm();
                const consultation = response.data;
                $('#consultation_id').val(consultation.id);
                $('#consultation_patient_id').val(consultation.patient_id);
                $('#consultation_doctor_id').val(consultation.doctor_id);
                $('#consultation_clinic_id').val(consultation.clinic_id);
                $('#consultation_appointment_id').val(consultation.appointment_id || '');
                if (window.setSharedDateValue) {
                    setSharedDateValue('#consultation_date', consultation.consultation_date);
                } else {
                    $('#consultation_date').val(consultation.consultation_date);
                }
                $('#consultation_time').val((consultation.consultation_time || '').substring(0, 5));
                $('#consultation_status').val(consultation.status);
                $('#consultation_chief_complaint').val(consultation.chief_complaint || '');
                $('#consultation_clinical_notes').val(consultation.clinical_notes || '');
                $('#consultation_diagnosis').val(consultation.diagnosis || '');
                $('#consultation_treatment_plan').val(consultation.treatment_plan || '');
                if (window.setSharedDateValue) {
                    setSharedDateValue('#follow_up_date', consultation.follow_up_date || '');
                } else {
                    $('#follow_up_date').val(consultation.follow_up_date || '');
                }
                refreshSelects();
                $('#consultationModal .modal-title').text('Edit Consultation');
                $(form + ' .save').text('Update Consultation');
                $('#consultationModal').modal('show');
            }
        });
    });

    $('.delete-consultation').on('click', function () {
        const id = $(this).data('id');

        if (! window.confirm('Delete this consultation?')) {
            return;
        }

        ajaxRequest({
            url: ROUTES.consultations.delete + id,
            method: 'POST',
            data: {csrf_rtn: $('input[name="csrf_rtn"]').first().val()},
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
            }
        });
    });

    const editId = new URLSearchParams(window.location.search).get('edit_id');

    if (editId && $('.edit-consultation[data-id="' + editId + '"]').length) {
        $('.edit-consultation[data-id="' + editId + '"]').trigger('click');
    }

});
