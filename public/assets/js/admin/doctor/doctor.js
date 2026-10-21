    /**
     *-----------------------------------------------------------
     * open add doctor modal
     *-----------------------------------------------------------
     */
    const doctorModal = '#doctorModal';
    const doctorForm = '#doctorForm';

    function initializeDoctorSelects() {
        $(doctorModal + ' select.btSelect').each(function () {
            const select = $(this);
            if (select.hasClass('selectpicker')) select.selectpicker('destroy');
            select.selectpicker({
                container: 'body',
                liveSearch: select.data('live-search') === true || select.data('live-search') === 'true',
                actionsBox: select.data('actions-box') === true || select.data('actions-box') === 'true',
                width: select.data('width') || '100%',
                noneSelectedText: 'Select an option',
                liveSearchPlaceholder: 'Type to search...'
            });
        });
    }

    $(function () { initializeDoctorSelects(); });

    function setDoctorSubmitState(disabled, label) {
        const button = $(doctorForm).find(':submit');
        button.prop('disabled', disabled);
        if (label) button.text(label);
    }

    function resetDoctorForm() {
        resetForm(doctorForm);
        clearFormErrors(doctorForm);
        $(doctorForm).find('[name="doctor_id"]').val('');
        setDoctorSubmitState(false, 'Save Doctor');
    }

    $(document).on('click', '.open_doctor_modal', function(){
        initializeDoctorSelects();
        resetDoctorForm();
        $(doctorModal + ' .modal-title').text('Add New Doctor');
        $(doctorModal).modal('show');
    });
    


    /**
     *----------------------------------------------------------
     * save/update doctor record
     *----------------------------------------------------------
     */
    
    $(doctorForm).on('submit', function (e) {

        e.preventDefault();
        clearFormErrors(doctorForm);

        const urL = $('[name=doctor_id]').val()
        ? ROUTES.doctor.update
        : ROUTES.doctor.create;

        const form = this;
        const submitButton = $(form).find(':submit');
        submitButton.prop('disabled', true).text('Saving...');
        ajaxRequest({

            url:  urL,

            method: 'POST',

            data: $(this).serialize(),

            success: function (response) {

                if (response.status) {
                    showSuccess(response.msg); 
                    reload_page(500);

                } else {

                    showError(response.msg);
                    bindFormError('#doctorForm',response.errors);

                }

            },
            complete: function () {
                submitButton.prop('disabled', false).text($(form).find('[name="doctor_id"]').val() ? 'Update Doctor' : 'Save Doctor');
            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open edit doctor modal
     *-----------------------------------------------------------------------
     */
    
    function editDoctorForm(doctorId)
    {
        const id = Number(doctorId);
        if (!Number.isInteger(id) || id < 1) {
            showError('The selected doctor could not be loaded.');
            return;
        }
        resetDoctorForm();
        initializeDoctorSelects();
        $(doctorModal + ' .modal-title').text('Loading Doctor...');
        $(doctorModal).modal('show');
        ajaxRequest({

            url: ROUTES.doctor.edit + id,

            method: 'GET',

            success: function (response) {

                if (!response || response.status !== true || !response.data || !response.data.doctor) {
                    showError(response && response.msg ? response.msg : 'Doctor details could not be loaded.');
                    return;
                }
                let doctor = response.data.doctor;

                resetDoctorForm();

                $('#doctor_id').val(doctor.id);

                $('#clinic_id')
                    .val(doctor.clinic_id)
                    .selectpicker('refresh');

                $('#city_id')
                    .val(doctor.city_id)
                    .selectpicker('refresh');

                $('#specialization_ids')
                    .val(response.data.specialization_ids)
                    .selectpicker('refresh');

                $('#language_ids')
                    .val(response.data.language_ids || [])
                    .selectpicker('refresh');

                $('#treatment_ids')
                    .val(response.data.treatment_ids || [])
                    .selectpicker('refresh');

                $('#doctor_code').val(doctor.doctor_code);
                $('#first_name').val(doctor.first_name);
                $('#last_name').val(doctor.last_name);
                $('#gender').val(doctor.gender);
                $('#dob').val(doctor.dob);
                $('#qualification').val(doctor.qualification);
                $('#experience').val(doctor.experience);
                $('#registration_number').val(doctor.registration_number);
                $('#doctor-mobile').val(doctor.mobile);
                $('#doctor-country-code').val(doctor.telephone_country_code_id || '').selectpicker('refresh');
                $('#doctor-country-name').val(doctor.telephone_country_name || '');
                $('#doctor-country-code-input').val(doctor.telephone_country_code || '');
                $('#doctor-country-code-manual').prop('checked', !doctor.telephone_country_code_id && !!doctor.telephone_country_code);
                bindCountryCodeToggles('#doctorForm');
                $('#email').val(doctor.email);
                $('#address').val(doctor.address);
                $('#consultation_duration').val(doctor.consultation_duration);
                $('#notes').val(doctor.notes);
                $('#display_order').val(doctor.display_order);
                $('#status').val(doctor.status);

                $('#online_booking').prop(
                    'checked',
                    doctor.online_booking == 1
                );
 
                $(doctorModal + ' .modal-title').text('Edit Doctor');
                setDoctorSubmitState(false, 'Update Doctor');
            },
            error: function (xhr) {
                handleFormAjaxError(doctorForm, xhr, 'Doctor details could not be loaded.');
            },
            complete: function () {
                setDoctorSubmitState(false, 'Update Doctor');
            }

        });
    }

    window.editDoctorForm = editDoctorForm;
    $(document).on('click', '.doctor-edit-trigger', function () {
        editDoctorForm($(this).data('doctor-id'));
    });
        


