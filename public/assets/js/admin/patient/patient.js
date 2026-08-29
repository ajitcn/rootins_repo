$(document).on('click', '.open_patient_modal', function () {
    $('#patient_clinic_id option[data-patient-inactive="1"]').remove();
    resetForm('#patientForm');
    clearFormErrors();

    $('#patient_id').val('');
    $('#patient_clinic_id').prop('disabled', false).selectpicker('refresh');
    $('#patientModal .modal-title').text('Add Patient');
    $('#patientForm .save').text('Save Patient');

    $('#patient_state_id').html('<option value="">Select State</option>');
    $('#patient_city_id').html('<option value="">Select City</option>');
    $('#patientForm .btSelect').selectpicker('refresh');
    $('#patientModal').modal('show');
});

$('#patientForm').on('submit', function (e) {
    e.preventDefault();
    clearFormErrors();

    const patientId = $('#patient_id').val();
    const url = patientId
        ? ROUTES.patients.update + patientId
        : ROUTES.patients.save;

    ajaxRequest({
        url: url,
        method: 'POST',
        data: $(this).serialize(),
        success: function (response) {
            if (response.status) {
                $('#patientModal').modal('hide');
                resetForm('#patientForm');
                showSuccess(response.msg);
                reload_page(500);
            } else {
                showError(response.msg);
                bindFormError('#patientForm', response.errors);
            }
        }
    });
});

function editPatientForm(patientId) {
    clearFormErrors();

    ajaxRequest({
        url: ROUTES.patients.edit + patientId,
        method: 'GET',
        success: function (response) {
            if (! response.status) {
                showError(response.msg);
                return;
            }

            const patient = response.data;

            $('#patient_clinic_id option[data-patient-inactive="1"]').remove();
            resetForm('#patientForm');
            clearFormErrors();

            $('#patient_id').val(patient.id);
            $('#first_name').val(patient.first_name);
            $('#middle_name').val(patient.middle_name);
            $('#last_name').val(patient.last_name);
            $('#gender').val(patient.gender);
            $('#date_of_birth').val(patient.date_of_birth);
            $('#country_code').val(patient.country_code).selectpicker('refresh');
            $('#mobile_number').val(patient.mobile_number);
            $('#email').val(patient.email);
            $('#address').val(patient.address);
            $('#postal_code').val(patient.postal_code);
            const clinicId = patient.clinic_id === null || patient.clinic_id === undefined
                ? ''
                : String(patient.clinic_id);

            if (clinicId && $('#patient_clinic_id option').filter(function () {
                return String($(this).val()) === clinicId;
            }).length === 0 && patient.clinic_name) {
                $('<option>', {
                    value: clinicId,
                    text: patient.clinic_name
                })
                    .attr('data-patient-inactive', '1')
                    .appendTo('#patient_clinic_id');
            }

            $('#patient_clinic_id').val(clinicId).prop('disabled', true).selectpicker('refresh');
            $('#patient_country_id').val(patient.country_id || '').selectpicker('refresh');
            $('#patient_status').val(patient.status);

            const countryId = patient.country_id || '';
            const stateId = patient.state_id || '';

            if (countryId) {
                loadStates(countryId, '#patientForm #patient_state_id', function () {
                    $('#patient_state_id').val(stateId).selectpicker('refresh');

                    if (stateId) {
                        loadCities(stateId, '#patientForm #patient_city_id', function () {
                            $('#patient_city_id').val(patient.city_id || '').selectpicker('refresh');
                        });
                    } else {
                        $('#patient_city_id').html('<option value="">Select City</option>');
                        $('#patient_city_id').selectpicker('refresh');
                    }
                });
            } else {
                $('#patient_state_id').html('<option value="">Select State</option>');
                $('#patient_city_id').html('<option value="">Select City</option>');
                $('#patientForm .btSelect').selectpicker('refresh');
            }

            $('#patientModal .modal-title').text('Edit Patient');
            $('#patientForm .save').text('Update Patient');
            $('#patientModal').modal('show');
        }
    });
}

$(function () {
    if ($.fn.DataTable) {
        $('.datatable').DataTable();
    }

    $('#patient_country_id').on('change', function () {
        const countryId = $(this).val();

        $('#patient_city_id').html('<option value="">Select City</option>');
        $('#patient_city_id').selectpicker('refresh');

        if (! countryId) {
            $('#patient_state_id').html('<option value="">Select State</option>');
            $('#patient_state_id').selectpicker('refresh');
            return;
        }

        loadStates(countryId, '#patientForm #patient_state_id');
    });

    $('#patient_state_id').on('change', function () {
        const stateId = $(this).val();

        if (! stateId) {
            $('#patient_city_id').html('<option value="">Select City</option>');
            $('#patient_city_id').selectpicker('refresh');
            return;
        }

        loadCities(stateId, '#patientForm #patient_city_id');
    });
});
