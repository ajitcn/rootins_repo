$(function () {
    const form = '#appointmentForm';

    function toggleCancellationFields() {
        const cancelled = $('#appointment_status').val() === 'cancelled';
        $('#cancellationFields').toggle(cancelled);
        if (!cancelled) {
            $('#cancellation_reason').val('');
        }
    }

    function resetAppointmentForm() {
        resetForm(form);
        clearFormErrors();
        $('#appointment_id').val('');
        $('#duration_minutes').val(15);
        $('#appointment_type').val('in_person').selectpicker('refresh');
        $('#appointment_status').val('scheduled').selectpicker('refresh');
        $('#appointmentModal .modal-title').text('Add Appointment');
        $(form + ' .save').text('Save Appointment');
        toggleCancellationFields();
    }

    $('#openAppointmentModal').on('click', function () {
        resetAppointmentForm();
        $('#appointmentModal').modal('show');
    });

    $('#appointment_status').on('change', toggleCancellationFields);

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();

        const id = $('#appointment_id').val();
        const url = id
            ? ROUTES.appointments.update + id
            : ROUTES.appointments.save;

        ajaxRequest({
            url: url,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#appointmentModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
                bindFormError(form, response.errors);
            }
        });
    });

    $('.edit-appointment').on('click', function () {
        const id = $(this).data('id');

        clearFormErrors();

        ajaxRequest({
            url: ROUTES.appointments.edit + id,
            method: 'GET',
            success: function (response) {
                if (! response.status) {
                    showError(response.msg);
                    return;
                }

                resetAppointmentForm();
                const appointment = response.data;

                $('#appointment_id').val(appointment.id);
                $('#appointment_patient_id').val(appointment.patient_id).selectpicker('refresh');
                $('#appointment_doctor_id').val(appointment.doctor_id).selectpicker('refresh');
                $('#appointment_clinic_id').val(appointment.clinic_id).selectpicker('refresh');
                if (window.setSharedDateValue) {
                    setSharedDateValue('#appointment_date', appointment.appointment_date);
                } else {
                    $('#appointment_date').val(appointment.appointment_date);
                }
                $('#appointment_time').val((appointment.appointment_time || '').substring(0, 5));
                $('#duration_minutes').val(appointment.duration_minutes);
                $('#appointment_type').val(appointment.appointment_type).selectpicker('refresh');
                $('#appointment_status').val(appointment.status).selectpicker('refresh');
                $('#reason_for_visit').val(appointment.reason_for_visit || '');
                $('#appointment_notes').val(appointment.notes || '');
                $('#cancellation_reason').val(appointment.cancellation_reason || '');
                toggleCancellationFields();
                $('#appointmentModal .modal-title').text('Edit Appointment');
                $(form + ' .save').text('Update Appointment');
                $('#appointmentModal').modal('show');
            }
        });
    });

    $('.cancel-appointment').on('click', function () {
        const button = $(this);
        const id = button.data('id');
        const appointmentNumber = button.data('appointment-number') || 'this appointment';

        if (! window.confirm('Cancel ' + appointmentNumber + '?')) {
            return;
        }

        ajaxRequest({
            url: ROUTES.appointments.queueStatus + id,
            method: 'POST',
            data: {
                csrf_rtn: $('#appointmentForm input[name="csrf_rtn"]').val(),
                status: 'cancelled'
            },
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

    $('.delete-appointment').on('click', function () {
        const id = $(this).data('id');

        if (! window.confirm('Delete this appointment?')) {
            return;
        }

        ajaxRequest({
            url: ROUTES.appointments.delete + id,
            method: 'POST',
            data: {
                csrf_rtn: $('#appointmentForm input[name="csrf_rtn"]').val()
            },
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

    const editId = new URLSearchParams(window.location.search).get('edit');
    if (/^\d+$/.test(editId || '')) {
        $('.edit-appointment[data-id="' + editId + '"]').first().trigger('click');
    }
});
