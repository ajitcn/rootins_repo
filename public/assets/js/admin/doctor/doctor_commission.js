    /**
     *-----------------------------------------------------------
     * open add schedule modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_commission_modal', function(){
        $('#doctorCommissionModal .modal-title').text('Add Consultation Commission');
        $('#doctorCommissionModal .save').text('Save Commission');
        $('#doctor_commission_id').val('');

        $('#doctorCommissionModal').modal('show');
        resetForm('#doctorCommissionForm');
        clearFormErrors();
        $('#commission_type').trigger('change');
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
                if (window.setSharedDateValue) {
                    setSharedDateValue('#effective_from', commission.effective_from);
                } else {
                    $('#effective_from').val(commission.effective_from);
                }
                $('#doctor_commission_status').val(commission.status).selectpicker('refresh');
                $('#commission_type').trigger('change');
                 
                 
 
                $('#doctorCommissionModal .modal-title').text('Edit Commission');
                $('#doctorCommissionForm .save').text('Update Commission');



                $('#doctorCommissionModal').modal('show');
            }

        });
    }

$(document).on('change', '#commission_type', function () {
    const isPercentage = $(this).val() === 'PERCENTAGE';
    $('#commissionValuePrefix').html(isPercentage ? '%' : '&#8377;');
    $('#commission_value').attr('max', isPercentage ? '100' : null);
});
        


