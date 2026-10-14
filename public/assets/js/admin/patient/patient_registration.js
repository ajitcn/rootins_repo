$(function () {
    const form = '#patientRegistrationForm';
    const appointmentToggle = $('#registration_create_appointment_now');
    const appointmentFields = $('#registration-appointment-fields');
    const appointmentInputs = appointmentFields.find(':input');
    const appointmentRequiredInputs = appointmentFields.find('#registration_appointment_date, #registration_appointment_time, #registration_appointment_service_id, #registration_appointment_doctor_id');

    function refreshSelects() {
        $(form).find('.btSelect').selectpicker('refresh');
    }

    function setAppointmentMode(enabled) {
        appointmentFields.toggleClass('d-none', ! enabled).attr('aria-hidden', enabled ? 'false' : 'true');
        appointmentInputs.prop('disabled', ! enabled);
        appointmentRequiredInputs.prop('required', enabled);
        if (! enabled) {
            appointmentInputs.filter('input, textarea').val('');
            appointmentInputs.filter('select').val('');
            clearFormErrors(form);
        }
        refreshSelects();
    }

    function filterAppointmentCatalogs() {
        const clinicId = String($('#registration_clinic_id').val() || '');
        ['#registration_appointment_service_id', '#registration_appointment_doctor_id'].forEach(function (selector) {
            const select = $(selector);
            select.find('option').each(function () {
                const option = $(this);
                const optionClinic = String(option.data('clinic-id') || '');
                const allowed = ! optionClinic || optionClinic === '0' || ! clinicId || optionClinic === clinicId || option.val() === '';
                option.prop('hidden', ! allowed).prop('disabled', ! allowed && option.val() !== '');
                if (! allowed && option.is(':selected')) select.val('');
            });
        });
        refreshSelects();
    }

    bindLocationManualToggles(form);
    bindCountryCodeToggles(form);
    setAppointmentMode(false);
    filterAppointmentCatalogs();

    appointmentToggle.on('change', function () {
        setAppointmentMode($(this).is(':checked'));
    });

    $(form).on('submit', function (event) {
        event.preventDefault();
        clearFormErrors(form);
        $('#patientRegistrationResult').addClass('d-none');

        ajaxRequest({
            url: ROUTES.patients.registration.register,
            method: 'POST',
            data: $(form).serialize(),
            form: form,
            success: function (response) {
                if (! response.status) {
                    showError(response.msg || 'Please correct the highlighted fields.');
                    bindFormError(form, response.errors || {});
                    return;
                }

                const registrationNumber = response.data?.registration_number ?? '';
                $('#patientRegistrationNumber').text(registrationNumber || 'Generated successfully');
                $('#patientRegistrationResult').removeClass('d-none');
                showSuccess(registrationNumber ? response.msg + ' Registration number: ' + registrationNumber : response.msg);
                $(form)[0].reset();
                resetForm(form);
                resetLocationControls(form);
                $('#registration_state_id').html('<option value="">Select state</option>');
                $('#registration_city_id').html('<option value="">Select city</option>');
                $('#registration_locality_id').html('<option value="">Select locality</option>');
                $('#registration_locality_name, #registration_country_name, #registration_state_name, #registration_city_name').val('');
                appointmentToggle.prop('checked', false);
                setAppointmentMode(false);
                filterAppointmentCatalogs();
                refreshSelects();
            },
            complete: function () {
                $(form).find(':submit').prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });

    $('#registration_clinic_id').on('changed.bs.select change', filterAppointmentCatalogs);

    $('#registration_country_id').on('change.registrationLocation', function () {
        const countryId = $(this).val();
        $('#registration_city_id').html('<option value="">Select city</option>');
        $('#registration_locality_id').html('<option value="">Select locality</option>');
        $('#registration_city_id, #registration_locality_id').selectpicker('refresh');
        if (! countryId) {
            $('#registration_state_id').html('<option value="">Select state</option>').selectpicker('refresh');
            return;
        }
        loadStates(countryId, '#registration_state_id');
    });

    $('#registration_state_id').on('change.registrationLocation', function () {
        const stateId = $(this).val();
        $('#registration_locality_id').html('<option value="">Select locality</option>').selectpicker('refresh');
        if (! stateId) {
            $('#registration_city_id').html('<option value="">Select city</option>').selectpicker('refresh');
            return;
        }
        loadCities(stateId, '#registration_city_id');
    });

    $('#registration_city_id').on('change.registrationLocation', function () {
        const cityId = $(this).val();
        $('#registration_locality_id').html('<option value="">Select locality</option>').selectpicker('refresh');
        if (cityId) loadLocalities(cityId, '#registration_locality_id');
    });
});
