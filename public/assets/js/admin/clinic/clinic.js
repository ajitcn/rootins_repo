    /**
     *-----------------------------------------------------------
     * open add clinc modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_clinic_modal', function(){
        $('#clinicModal .modal-title').text('Add New Clinic');
    $('#clinicModal .save').text('Save Clinic');
        $('[name=clinic_id]').val('');
        $('#clinicModal').modal('show');
        resetForm('#clinicForm');
    });
    


    /**
     *----------------------------------------------------------
     * save/update clinic record
     *----------------------------------------------------------
     */
    
    $('#clinicForm').on('submit', function (e) {

        e.preventDefault();

        const urL = $('[name=clinic_id]').val()
        ? ROUTES.clinic.update
        : ROUTES.clinic.create;

        ajaxRequest({

            url:  urL,

            method: 'POST',

            data: $(this).serialize(),

            success: function (response) {

                if (response.status) {
                    hide_loader();
                    showSuccess(response.msg); 
                    reload_page(500); 

                } else {

                    showError(response.msg);
                    if (response.errors) bindFormError('#clinicForm', response.errors);

                }

            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open edit clinic modal
     *-----------------------------------------------------------------------
     */
    
function editClinicForm(clinicId) {
        ajaxRequest({

            url: ROUTES.clinic.edit + clinicId,

            method: 'GET',

            data: [],

            success: function (response) {

                if (response.status) {
                    hide_loader();

                     const clinic = response.data;

                    // Hidden ID
                    $('[name=clinic_id]').val(clinic.id);

                    // Textboxes
                    $('[name=clinic_name]').val(clinic.clinic_name);
                    $('[name=clinic_code]').val(clinic.clinic_code);
                    $('[name=contact_person]').val(clinic.contact_person);
                    $('[name=email]').val(clinic.email);
                    $('[name=mobile]').val(clinic.mobile);
                    $('[name=address]').val(clinic.address);
                    $('[name=pincode]').val(clinic.pincode);

                    // Country
                    $('#country_id')
                        .val(clinic.country_id)
                        .selectpicker('refresh');

                    // Load states, then select state
                    loadStates(clinic.country_id, '#state_id', function () {

                        $('#state_id')
                            .val(clinic.state_id)
                            .selectpicker('refresh'); 

                        // Load cities, then select city
                        loadCities(clinic.state_id, '#city_id', function () { 

                            $('#city_id')
                                .val(clinic.city_id)
                                .selectpicker('refresh');

                        }); 

                    });

                    // Status
                    $('#clinic_status')
                        .val(clinic.status)
                        .selectpicker('refresh');

                    $('#clinicModal .modal-title').text('Edit Clinic');
                    $('#clinicModal .save').text('Update Clinic');

                    $('#clinicModal').modal('show'); 

                } else {

                    showError(response.msg);

}

            }

        });
    }
    


