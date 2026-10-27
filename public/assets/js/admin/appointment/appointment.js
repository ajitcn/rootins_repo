$(function () {
    const editForm = '#appointmentForm';
    const rescheduleForm = '#appointmentRescheduleForm';
    const editModal = '#appointmentModal';
    const rescheduleModal = '#appointmentRescheduleModal';
    const slotState = { request: 0, loaded: false, values: [] };

    function portalAppointmentModals() {
        [editModal, rescheduleModal, '#appointmentFilterModal'].forEach(function (selector) {
            const modal = document.querySelector(selector);
            if (modal && modal.parentNode !== document.body) document.body.appendChild(modal);
        });
    }

    function cleanupModalState() {
        if ($('.modal.show').length === 0) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
        } else if ($('.modal-backdrop').length > 1) {
            $('.modal-backdrop').not(':last').remove();
        }
    }

    function toggleCancellationFields() {
        const cancelled = $('#appointment_status').val() === 'cancelled';
        $('#cancellationFields').toggle(cancelled);
        if (!cancelled) $('#cancellation_reason').val('');
    }

    function resetAppointmentForm() {
        resetForm(editForm);
        clearFormErrors(editForm);
        $('#appointment_id').val('');
        $('#duration_minutes').val(15);
        $('#appointment_type').val('in_person').selectpicker('refresh');
        $('#appointment_status').val('scheduled').selectpicker('refresh');
        $('#appointmentModal .modal-title').text('Add Appointment');
        $(editForm + ' .save').text('Save Appointment');
        clearAppointmentSlots();
        toggleCancellationFields();
    }

    function normalizeInputTime(value) {
        const normalized = window.rootinsNormalizeTime ? window.rootinsNormalizeTime(value) : null;
        return normalized ? normalized.slice(0, 5) : String(value || '').slice(0, 5);
    }

    function clearAppointmentSlots(message) {
        slotState.request += 1;
        slotState.loaded = false;
        slotState.values = [];
        $('#appointment_time').removeAttr('min max').empty().val('').prop('disabled', false);
        $('#appointmentTimeSlots').empty();
        $('#appointmentTimeHelp').text(message || 'Select a doctor and date to load available 15-minute slots.');
    }

    function loadAppointmentSlots(preserveTime) {
        const doctorId = $('#appointment_doctor_id').val();
        const clinicId = $('#appointment_clinic_id').val();
        const date = window.rootinsDateToIso ? window.rootinsDateToIso('#appointment_date') : $('#appointment_date').val();
        const selected = normalizeInputTime($('#appointment_time').val());
        const request = ++slotState.request;
        if (!doctorId || !clinicId || !date) {
            clearAppointmentSlots();
            return;
        }
        slotState.loaded = false;
        $('#appointment_time').prop('disabled', true);
        $('#appointmentTimeSlots').empty();
        $('#appointmentTimeHelp').text('Loading available times…');
        ajaxRequest({
            url: ROUTES.appointments.availableSlots,
            method: 'GET',
            data: { doctor_id: doctorId, clinic_id: clinicId, appointment_date: date, duration_minutes: $('#duration_minutes').val() || 15, appointment_id: $('#appointment_id').val() || 0 },
            success: function (response) {
                if (request !== slotState.request) return;
                const data = response && response.data ? response.data : {};
                slotState.loaded = true;
                slotState.values = (data.slots || []).map(function (slot) { return String(slot.value || '').slice(0, 5); });
                $('#appointment_time').attr({ min: data.min || '', max: data.max || '' }).prop('disabled', false);
                $('#appointmentTimeSlots').html((data.slots || []).map(function (slot) { return $('<option>').attr('value', slot.value).attr('label', slot.label || slot.value); }));
                $('#appointmentTimeHelp').text(data.message || (slotState.values.length ? 'Available times are shown in your local 12-hour format.' : 'No available times remain for this date.'));
                const nextTime = preserveTime && slotState.values.indexOf(selected) !== -1 ? selected : '';
                $('#appointment_time').val(nextTime);
                if (preserveTime && selected && !nextTime) $('#appointment_timeErr').text('The previous time is no longer available. Please select another time.');
                $('#appointment_end_time').val(endTime(nextTime, $('#duration_minutes').val()));
            },
            error: function () {
                if (request !== slotState.request) return;
                slotState.loaded = true;
                slotState.values = [];
                $('#appointment_time').prop('disabled', false).val('');
                $('#appointmentTimeHelp').text('Unable to load availability. Please try again.');
            }
        });
    }

    function validateAppointmentTime() {
        const value = String($('#appointment_time').val() || '').trim();
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(value)) {
            $('#appointment_timeErr').text('Select a valid appointment time.');
            return false;
        }
        if (Number(value.slice(3, 5)) % 15 !== 0) {
            $('#appointment_timeErr').text('Choose a time in 15-minute increments.');
            return false;
        }
        if (slotState.loaded && slotState.values.indexOf(value) === -1) {
            $('#appointment_timeErr').text('That time is outside the doctor’s availability or is already booked.');
            return false;
        }
        $('#appointment_timeErr').empty();
        return true;
    }

    function setBusy(form, busy) {
        $(form).find(':submit').prop('disabled', busy).attr('aria-busy', busy ? 'true' : 'false');
    }

    function appointmentId(button) {
        return String($(button).attr('data-appointment-id') || $(button).attr('data-id') || '').trim();
    }

    function endTime(start, duration) {
        const normalized = window.rootinsNormalizeTime ? window.rootinsNormalizeTime(start) : start;
        const parts = String(normalized || '').split(':').map(Number);
        if (parts.length < 2 || parts.some(Number.isNaN)) return '';
        const total = (parts[0] * 60) + parts[1] + Math.max(0, Number(duration) || 0);
        const hours = Math.floor((total % 1440) / 60);
        const minutes = total % 60;
        const raw = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        return window.rootinsFormatTime ? window.rootinsFormatTime(raw) : raw;
    }

    function setAppointmentFields(appointment) {
        $('#appointment_id').val(appointment.id);
        $('#appointment_patient_id').val(appointment.patient_id).selectpicker('refresh');
        $('#appointment_doctor_id').val(appointment.doctor_id).selectpicker('refresh');
        $('#appointment_clinic_id').val(appointment.clinic_id).selectpicker('refresh');
        $('#appointment_service_name').val(appointment.service_name || 'Service not specified');
        if (window.setSharedDateValue) setSharedDateValue('#appointment_date', appointment.appointment_date);
        else $('#appointment_date').val(appointment.appointment_date || '');
        $('#appointment_time').val(normalizeInputTime(appointment.appointment_time));
        $('#appointment_end_time').val(endTime(appointment.appointment_time, appointment.duration_minutes));
        $('#duration_minutes').val(appointment.duration_minutes);
        $('#appointment_type').val(appointment.appointment_type).selectpicker('refresh');
        $('#appointment_status').val(appointment.status).selectpicker('refresh');
        $('#reason_for_visit').val(appointment.reason_for_visit || '');
        $('#appointment_notes').val(appointment.notes || '');
        $('#cancellation_reason').val(appointment.cancellation_reason || '');
        toggleCancellationFields();
    }

    function setRescheduleFields(appointment) {
        const patientName = [appointment.patient_first_name, appointment.patient_middle_name, appointment.patient_last_name].filter(Boolean).join(' ');
        const doctorName = [appointment.doctor_first_name, appointment.doctor_last_name].filter(Boolean).join(' ');
        const currentTime = window.rootinsFormatTime ? rootinsFormatTime(appointment.appointment_time) : appointment.appointment_time;
        $(rescheduleModal).data('appointment-id', appointment.id);
        $('#reschedule_patient_id').val(appointment.patient_id);
        $('#reschedule_doctor_id').val(appointment.doctor_id);
        $('#reschedule_clinic_id').val(appointment.clinic_id);
        $('#reschedule_duration_minutes').val(appointment.duration_minutes);
        $('#reschedule_appointment_type').val(appointment.appointment_type);
        $('#reschedule_status').val(appointment.status);
        $('#reschedule_reason_for_visit').val(appointment.reason_for_visit || '');
        $('#reschedule_notes').val(appointment.notes || '');
        $('#reschedule_cancellation_reason').val(appointment.cancellation_reason || '');
        $('#reschedule_patient_name').val(patientName || 'Patient');
        $('#reschedule_doctor_name').val(doctorName || 'Doctor');
        const currentDate = window.rootinsFormatDate ? rootinsFormatDate(appointment.appointment_date) : appointment.appointment_date;
        $('#reschedule_current_datetime').val(currentDate + ' ' + currentTime);
        $('#reschedule_current_end_time').val(endTime(currentTime, appointment.duration_minutes));
        if (window.setSharedDateValue) setSharedDateValue('#reschedule_new_date', appointment.appointment_date);
        else $('#reschedule_new_date').val(appointment.appointment_date || '');
        $('#reschedule_new_start_time').val(currentTime);
        $('#reschedule_new_end_time').val(endTime(currentTime, appointment.duration_minutes));
        $('#reschedule_reason').val('');
    }

    function loadAppointment(id, mode) {
        const modal = mode === 'reschedule' ? rescheduleModal : editModal;
        const form = mode === 'reschedule' ? rescheduleForm : editForm;
        const loading = mode === 'reschedule' ? '#appointmentRescheduleLoading' : '#appointmentLoading';
        portalAppointmentModals();
        clearFormErrors(form);
        if (loading) $(loading).removeClass('d-none');
        $(modal).modal('show');
        setBusy(form, true);
        ajaxRequest({
            url: ROUTES.appointments.edit + encodeURIComponent(id), method: 'GET',
            success: function (response) {
                if (!response || !response.status || !response.data) {
                    showError((response && response.msg) || 'Unable to load appointment.');
                    return;
                }
                if (mode === 'reschedule') setRescheduleFields(response.data);
                else {
                    resetAppointmentForm();
                    setAppointmentFields(response.data);
                    loadAppointmentSlots(true);
                    $('#appointmentModal .modal-title').text('Edit Appointment');
                    $(editForm + ' .save').text('Update Appointment');
                }
            },
            error: function () { showError('Unable to load appointment.'); },
            complete: function () {
                if (loading) $(loading).addClass('d-none');
                setBusy(form, false);
            }
        });
    }

    portalAppointmentModals();

    $(document).off('click.rootinsAppointments', '#openAppointmentModal').on('click.rootinsAppointments', '#openAppointmentModal', function (event) {
        event.preventDefault(); portalAppointmentModals(); resetAppointmentForm(); $(editModal).modal('show');
    });
    $(document).off('click.rootinsAppointments', '.edit-appointment').on('click.rootinsAppointments', '.edit-appointment', function (event) {
        event.preventDefault(); loadAppointment(appointmentId(this), 'edit');
    });
    $(document).off('click.rootinsAppointments', '.reschedule-appointment').on('click.rootinsAppointments', '.reschedule-appointment', function (event) {
        event.preventDefault(); $('#appointmentRescheduleErrors').addClass('d-none').empty(); loadAppointment(appointmentId(this), 'reschedule');
    });
    $(document).off('change.rootinsAppointments', '#appointment_status').on('change.rootinsAppointments', '#appointment_status', toggleCancellationFields);
    $(document).off('change.rootinsAppointments changeDate.rootinsAppointments', '#appointment_doctor_id, #appointment_date').on('change.rootinsAppointments changeDate.rootinsAppointments', '#appointment_doctor_id, #appointment_date', function () {
        $('#appointment_time').val('');
        $('#appointment_timeErr').empty();
        loadAppointmentSlots(false);
    });
    $(document).off('change.rootinsAppointments', '#duration_minutes').on('change.rootinsAppointments', '#duration_minutes', function () {
        loadAppointmentSlots(Boolean($('#appointment_id').val()));
    });
    $(document).off('input.rootinsAppointments change.rootinsAppointments', '#appointment_time').on('input.rootinsAppointments change.rootinsAppointments', '#appointment_time', function () {
        $('#appointment_end_time').val(endTime($('#appointment_time').val(), $('#duration_minutes').val()));
        if (slotState.loaded && $('#appointment_time').val() && !validateAppointmentTime()) {
            $('#appointment_time').val('');
            $('#appointment_end_time').val('');
        }
    });
    $(document).off('input.rootinsAppointments', '#reschedule_new_start_time').on('input.rootinsAppointments', '#reschedule_new_start_time', function () {
        $('#reschedule_new_end_time').val(endTime($('#reschedule_new_start_time').val(), $('#reschedule_duration_minutes').val()));
    });

    $(document).off('submit.rootinsAppointments', editForm).on('submit.rootinsAppointments', editForm, function (event) {
        event.preventDefault();
        const form = this, id = $('#appointment_id').val();
        clearFormErrors(form); if (!validateAppointmentTime()) { $('#appointment_time').trigger('focus'); return; } setBusy(form, true);
        ajaxRequest({url: id ? ROUTES.appointments.update + encodeURIComponent(id) : ROUTES.appointments.save, method: 'POST', data: $(form).serialize(), form: form,
            success: function (response) {
                if (response && response.status) { $(editModal).modal('hide'); showSuccess(response.msg); reload_page(500); return; }
                showError((response && response.msg) || 'Unable to save appointment.'); bindFormError(form, response && response.errors ? response.errors : {});
            },
            complete: function () { setBusy(form, false); }
        });
    });

    $(document).off('submit.rootinsAppointments', rescheduleForm).on('submit.rootinsAppointments', rescheduleForm, function (event) {
        event.preventDefault();
        const form = this, id = $(rescheduleModal).data('appointment-id');
        clearFormErrors(form); $('#appointmentRescheduleErrors').addClass('d-none').empty();
        $('#reschedule_reason_for_visit').val($('#reschedule_reason').val()); setBusy(form, true);
        ajaxRequest({url: ROUTES.appointments.update + encodeURIComponent(id), method: 'POST', data: $(form).serialize(), form: form,
            success: function (response) {
                if (response && response.status) { $(rescheduleModal).modal('hide'); showSuccess(response.msg); reload_page(500); return; }
                $('#appointmentRescheduleErrors').text((response && response.msg) || 'Unable to reschedule appointment.').removeClass('d-none');
                bindFormError(form, response && response.errors ? response.errors : {});
            },
            complete: function () { setBusy(form, false); }
        });
    });

    $(document).off('show.bs.modal.rootinsAppointments', '.appointment-reschedule-modal, #appointmentModal').on('show.bs.modal.rootinsAppointments', '.appointment-reschedule-modal, #appointmentModal', function () {
        portalAppointmentModals(); $('.modal-backdrop').not(':last').remove();
    });
    $(document).off('hidden.bs.modal.rootinsAppointments', '.modal').on('hidden.bs.modal.rootinsAppointments', '.modal', function () {
        $('#appointmentRescheduleLoading, #appointmentLoading').addClass('d-none'); setBusy(rescheduleForm, false); setBusy(editForm, false); cleanupModalState();
    });

    $(document).off('click.rootinsAppointments', '.cancel-appointment').on('click.rootinsAppointments', '.cancel-appointment', function () {
        const button = $(this), id = appointmentId(button), number = button.data('appointment-number') || 'this appointment';
        if (!window.confirm('Cancel ' + number + '?')) return;
        ajaxRequest({url: ROUTES.appointments.queueStatus + id, method: 'POST', data: {csrf_rtn: $('#appointmentForm input[name="csrf_rtn"]').val(), status: 'cancelled'}, success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(500); } else showError(response.msg); }});
    });
    $(document).off('click.rootinsAppointments', '.delete-appointment').on('click.rootinsAppointments', '.delete-appointment', function () {
        const id = appointmentId(this); if (!window.confirm('Delete this appointment?')) return;
        ajaxRequest({url: ROUTES.appointments.delete + encodeURIComponent(id), method: 'POST', data: {csrf_rtn: $('#appointmentForm input[name="csrf_rtn"]').val()}, success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(500); } else showError(response.msg); }});
    });

    const editId = new URLSearchParams(window.location.search).get('edit');
    if (/^\d+$/.test(editId || '')) loadAppointment(editId, 'edit');
});
