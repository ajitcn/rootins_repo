    /**
     *-----------------------------------------------------------
     * open add doctor modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_modal', function(){
        $('#doctorModal .modal-title').text('Add New Doctor');
        $('#doctorModal .save').text('Save Doctor');
        $('[name=doctor_id]').val('');
        $('#doctorModal').modal('show');
        resetForm('#doctorForm');
        clearFormErrors();
    });
    


    /**
     *----------------------------------------------------------
     * save/update doctor record
     *----------------------------------------------------------
     */
    
    $('#doctorForm').on('submit', function (e) {

        e.preventDefault();
        clearFormErrors();

        const urL = $('[name=doctor_id]').val()
        ? ROUTES.doctor.update
        : ROUTES.doctor.create;

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
                    bindFormError('#doctorForm',response.errors);

                }

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
        clearFormErrors();
        ajaxRequest({

            url: ROUTES.doctor.edit + doctorId,

            method: 'GET',

            success: function (response) {

                let doctor = response.data.doctor;

                resetForm('#doctorForm');

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

                $('#doctor_code').val(doctor.doctor_code);
                $('#first_name').val(doctor.first_name);
                $('#last_name').val(doctor.last_name);
                $('#gender').val(doctor.gender);
                $('#dob').val(doctor.dob);
                $('#qualification').val(doctor.qualification);
                $('#experience').val(doctor.experience);
                $('#registration_number').val(doctor.registration_number);
                $('#mobile').val(doctor.mobile);
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
 
                $('#doctorModal .modal-title').text('Edit Doctor');
                $('#doctorModal .save').text('Update Doctor');

                $('#doctorModal').modal('show');
            }

        });
    }
        


