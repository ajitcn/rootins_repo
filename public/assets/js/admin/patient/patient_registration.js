$(function () {
    const form = '#patientRegistrationForm';
    bindLocationManualToggles(form);
    bindCountryCodeToggles(form);

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();
        $('#patientRegistrationResult').addClass('d-none');

        ajaxRequest({
            url: ROUTES.patients.registration.register,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    const registrationNumber = response.data?.registration_number ?? '';

                    $('#patientRegistrationNumber').text(registrationNumber);
                    $('#patientRegistrationResult').removeClass('d-none');
                    showSuccess(
                        registrationNumber
                            ? response.msg + ' Registration Number: ' + registrationNumber
                            : response.msg
                    );
                    resetForm(form);
                    resetLocationControls(form);
                    $('#registration_state_id').html('<option value="">Select State</option>');
                    $('#registration_city_id').html('<option value="">Select City</option>');
                    $('#registration_locality_id').html('<option value="">Select Locality</option>');
                    $('#registration_locality_name').val('');
                    $('#registration_country_name, #registration_state_name, #registration_city_name').val('');
                    $('.btSelect').selectpicker('refresh');
                } else {
                    showError(response.msg);
                    bindFormError(form, response.errors);
                }
            }
        });
    });

    $('#registration_country_id').on('change', function () {
        const countryId = $(this).val();

        $('#registration_city_id').html('<option value="">Select City</option>');
        $('#registration_locality_id').html('<option value="">Select Locality</option>');
        $('#registration_city_id').selectpicker('refresh');

        if (! countryId) {
            $('#registration_state_id').html('<option value="">Select State</option>');
            $('#registration_state_id').selectpicker('refresh');
            return;
        }

        loadStates(countryId, '#registration_state_id');
    });

    $('#registration_state_id').on('change', function () {
        const stateId = $(this).val();

        if (! stateId) {
            $('#registration_city_id').html('<option value="">Select City</option>');
            $('#registration_locality_id').html('<option value="">Select Locality</option>');
            $('#registration_city_id').selectpicker('refresh');
            return;
        }

        loadCities(stateId, '#registration_city_id');
    });

    $('#registration_city_id').on('change', function () {
        const cityId = $(this).val();
        $('#registration_locality_id').html('<option value="">Select Locality</option>').selectpicker('refresh');
        if (cityId) loadLocalities(cityId, '#registration_locality_id');
    });
});
