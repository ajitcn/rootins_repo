(function ($) {
    'use strict';

    const formSelector = '#clinicForm';

    function clearClinicErrors() {
        clearFormErrors(formSelector);
        $('#clinicFormError').addClass('d-none').empty();
    }

    function showClinicErrors(response) {
        if (response && response.errors) {
            bindFormError(formSelector, response.errors);
        }
        if (response && response.msg) {
            $('#clinicFormError').removeClass('d-none').text(response.msg);
        }
    }

    function refreshClinicSelect(selector) {
        const select = $(selector);
        if (select.length && select.hasClass('selectpicker')) {
            select.selectpicker('refresh');
        }
    }

    $(document).on('click', '.open_clinic_modal', function () {
        clearClinicErrors();
        resetForm(formSelector);
        bindCountryCodeToggles(formSelector);
        $('#clinicModalTitle').text('Add New Clinic');
        $('#clinicForm .save').html('<i class="bi bi-check-circle mr-1" aria-hidden="true"></i> Save Clinic');
        $('#clinicModal').modal('show');
    });

    $(document).on('submit', formSelector, function (event) {
        event.preventDefault();
        const form = $(this);
        const url = form.find('[name="clinic_id"]').val() ? ROUTES.clinic.update : ROUTES.clinic.create;
        clearClinicErrors();

        ajaxRequest({
            url: url,
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    $('#clinicModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }
                showClinicErrors(response);
                showError(response.msg);
            }
        });
    });

    window.editClinicForm = function (clinicId) {
        clearClinicErrors();
        ajaxRequest({
            url: ROUTES.clinic.edit + clinicId,
            method: 'GET',
            data: [],
            success: function (response) {
                if (!response.status) {
                    showError(response.msg);
                    return;
                }

                const clinic = response.data;
                const form = $(formSelector);
                form.find('[name="clinic_id"]').val(clinic.id);
                form.find('[name="clinic_name"]').val(clinic.clinic_name || '');
                form.find('[name="clinic_code"]').val(clinic.clinic_code || '');
                form.find('[name="contact_person"]').val(clinic.contact_person || '');
                form.find('[name="email"]').val(clinic.email || '');
                form.find('[name="mobile"]').val(clinic.mobile || '');
                $('#clinic-country-code').val(clinic.telephone_country_code_id || '');
                $('#clinic-country-name').val(clinic.telephone_country_name || '');
                $('#clinic-country-code-input').val(clinic.telephone_country_code || '');
                $('#clinic-country-code-manual').prop('checked', !clinic.telephone_country_code_id && !!clinic.telephone_country_code);
                form.find('[name="address"]').val(clinic.address || '');
                form.find('[name="pincode"]').val(clinic.pincode || '');
                $('#clinic_status').val(String(clinic.status));
                $('#country_id').val(String(clinic.country_id));
                refreshClinicSelect('#clinic-country-code');
                refreshClinicSelect('#clinic_status');
                refreshClinicSelect('#country_id');
                bindCountryCodeToggles(formSelector);

                loadStates(clinic.country_id, '#clinicForm #state_id', function () {
                    $('#state_id').val(String(clinic.state_id));
                    refreshClinicSelect('#state_id');
                    loadCities(clinic.state_id, '#clinicForm #city_id', function () {
                        $('#city_id').val(String(clinic.city_id));
                        refreshClinicSelect('#city_id');
                    });
                });

                $('#clinicModalTitle').text('Edit Clinic');
                $('#clinicForm .save').html('<i class="bi bi-check-circle mr-1" aria-hidden="true"></i> Update Clinic');
                $('#clinicModal').modal('show');
            }
        });
    };
}(jQuery));
