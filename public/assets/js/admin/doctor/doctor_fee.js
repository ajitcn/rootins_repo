    /**
     *-----------------------------------------------------------
     * open add schedule modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_fee_modal', function(){
        $('#consultationFeeModal .modal-title').text('Add Consultation Fee');
        $('#consultationFeeModal .save').text('Save Fee');
        $('#consultation_fee_id').val('');

        $('#consultationFeeModal').modal('show');
        resetForm('#consultationFeeForm');
        clearFormErrors();
    });
    


    /**
     *----------------------------------------------------------
     * save/update doctor schedule record
     *----------------------------------------------------------
     */
    
    $('#consultationFeeForm').on('submit', function (e) {

        e.preventDefault();
        clearFormErrors();

        const urL = $('[name=id]').val()
        ? ROUTES.doctorFee.update
        : ROUTES.doctorFee.create;

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
                    bindFormError('#consultationFeeForm',response.errors);
                    

                }

            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open edit doctor fee modal
     *-----------------------------------------------------------------------
     */
    
    function editDoctorFeeForm(feeId)
    {
        clearFormErrors();
        ajaxRequest({

            url: ROUTES.doctorFee.edit + feeId,

            method: 'GET',

            success: function (response) {

                let fee = response.data;

                resetForm('#consultationFeeForm');

                $('#consultation_fee_id').val(fee.id);

                $('#doctor_id').val(fee.doctor_id).selectpicker('refresh'); 
                $('#consultation_type').val(fee.consultation_type).selectpicker('refresh'); 
                $('#consultation_mode').val(fee.consultation_mode).selectpicker('refresh'); 
                $('#consultation_fee').val(fee.fee);
                if (window.setSharedDateValue) {
                    setSharedDateValue('#effective_from', fee.effective_from);
                } else {
                    $('#effective_from').val(fee.effective_from);
                }
                $('#consultation_fee_status').val(fee.status).selectpicker('refresh');
                 
                 
 
                $('#consultationFeeModal .modal-title').text('Edit Consultation Fee');
                $('#consultationFeeForm .save').text('Update Fee');



                $('#consultationFeeModal').modal('show');
            }

        });
    }
        


