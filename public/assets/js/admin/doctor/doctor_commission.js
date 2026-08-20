    /**
     *-----------------------------------------------------------
     * open add schedule modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_commission_modal', function(){
        $('#doctorCommissionModal .modal-title').text('Add Consultation Commission');
        $('#doctorCommissionModal .save').text('Save Commission');
        $('[name=doctor_commission_id]').val(''); 

        $('#doctorCommissionModal').modal('show');
        resetForm('#doctorCommissionForm');
        clearFormErrors();
    });
    


    /**
     *----------------------------------------------------------
     * save/update doctor schedule record
     *----------------------------------------------------------
     */
    
    $('#doctorCommissionForm').on('submit', function (e) {

        e.preventDefault();
        clearFormErrors();

        const urL = $('[name=id]').val()
        ? ROUTES.doctorCommission.update
        : ROUTES.doctorCommission.create;

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
                    bindFormError('#doctorCommissionForm',response.errors);
                    

                }

            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open edit doctor commission modal
     *-----------------------------------------------------------------------
     */
    
    function editDoctorCommissionForm(commissionId)
    {
        clearFormErrors();
        ajaxRequest({

            url: ROUTES.doctorCommission.edit + commissionId,

            method: 'GET',

            success: function (response) {

                let commission = response.data;

                resetForm('#doctorCommissionForm');

                $('#doctor_commission_id').val(commission.id);

                $('#doctor_id').val(commission.doctor_id).selectpicker('refresh'); 
                $('#commission_for').val(commission.commission_for).selectpicker('refresh'); 
                $('#commission_type').val(commission.commission_type).selectpicker('refresh'); 
                $('#commission_value').val(commission.commission_value);
                $('#effective_from').datepicker('update', commission.effective_from);;
                $('#status').val(commission.status);
                 
                 
 
                $('#doctorCommissionModal .modal-title').text('Edit Commission');
                $('#doctorCommissionForm .save').text('Update Commission');



                $('#doctorCommissionModal').modal('show');
            }

        });
    }
        


