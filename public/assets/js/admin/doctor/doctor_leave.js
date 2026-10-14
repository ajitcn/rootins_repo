    /**
     *-----------------------------------------------------------
     * open add leave modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_leave_modal', function(){ 
        resetForm('#doctorLeaveForm');
        $('#doctorLeaveModal .modal-title').text('Add Doctor Leave');
        $('#doctorLeaveForm .save').text('Save Leave');
        $('#doctorLeaveModal').modal('show'); 
        clearFormErrors();
    });
    


    /**
     *----------------------------------------------------------
     * save doctor leave record
     *----------------------------------------------------------
     */
    
    $('#doctorLeaveForm').on('submit', function (e) {

        e.preventDefault();
        clearFormErrors();

        const urL = ROUTES.doctorLeave.create;

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
                    bindFormError('#doctorLeaveForm',response.errors);

                }

            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open  doctor leave status modal
     *-----------------------------------------------------------------------
     */
    
    function openLeaveStatusForm(leaveId, statusVal)
    {
        $('#leave_id').val(leaveId);
        $('#doctorLeaveUpdateModal #status').val(statusVal).selectpicker('refresh');
        $('#doctorLeaveUpdateModal').modal('show');
    }
        


