    /**
     *-----------------------------------------------------------
     * open add schedule modal
     *-----------------------------------------------------------
     */
    $(document).on('click', '.open_doctor_schedule_modal', function(){
        $('#doctorScheduleModal .modal-title').text('Add New Schedule');
        $('#doctorScheduleModal .save').text('Save Schedule');
        $('[name=doctor_id]').val('');
        let $day = $('#day_of_weeks');

        $day.selectpicker('destroy');

        $day.attr('multiple', true);

        $day.selectpicker();

        $day.val('');

        $day.selectpicker('refresh');

        $('#doctorScheduleModal').modal('show');
        resetForm('#doctorScheduleForm');
        clearFormErrors();
    });
    


    /**
     *----------------------------------------------------------
     * save/update doctor schedule record
     *----------------------------------------------------------
     */
    
    $('#doctorScheduleForm').on('submit', function (e) {

        e.preventDefault();
        clearFormErrors();
        const urL = $('[name=schedule_id]').val()
        ? ROUTES.doctorSchedule.update
        : ROUTES.doctorSchedule.create;

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
                    bindFormError('#doctorScheduleForm',response.errors);

                }

            }

        });

    }); 


   

 /**
     *-----------------------------------------------------------------------
     * open edit doctor schedule modal
     *-----------------------------------------------------------------------
     */
    
    function editDoctorScheduleForm(scheduleId)
    {
        clearFormErrors();
        ajaxRequest({

            url: ROUTES.doctorSchedule.edit + scheduleId,

            method: 'GET',

            success: function (response) {

                let schedule = response.data;

                resetForm('#doctorScheduleForm');

                $('#schedule_id').val(schedule.id);

                $('#doctor_id')
                    .val(schedule.doctor_id)
                    .selectpicker('refresh');

                 
 
                let $day = $('#day_of_weeks');

                $day.selectpicker('destroy');

                $day.removeAttr('multiple');

                $day.selectpicker();

                $day.val(String(schedule.day_of_week));

                $day.selectpicker('refresh');

                 
                $('#shift_name').val(schedule.shift_name);
                $('#status').val(schedule.status);
                $('#start_time').val(schedule.start_time);
                $('#end_time').val(schedule.end_time);
                $('#slot_duration').val(schedule.slot_duration);
                $('#max_patients').val(schedule.max_patients); 

                 
 
                $('#doctorScheduleForm .modal-title').text('Edit Schedule');
                $('#doctorScheduleForm .save').text('Update Schedule');



                $('#doctorScheduleModal').modal('show');
            }

        });
    }
        


