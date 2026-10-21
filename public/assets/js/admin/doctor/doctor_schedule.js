(function ($) {
    'use strict';
    const modal = '#doctorScheduleModal';
    const form = '#doctorScheduleForm';
    const timeValue = value => String(value || '').slice(0, 5);
    function setDayMode(isEdit) {
        const day = $(form).find('[name="day_of_weeks[]"]');
        day.selectpicker('destroy').prop('multiple', !isEdit).selectpicker();
    }
    function resetScheduleForm() {
        const element = $(form)[0];
        if (element) element.reset();
        $(form).find('[name="schedule_id"]').val('');
        setDayMode(false);
        $(form).find('[name="status"]').val('1');
        $(form).find('[name="slot_duration"]').val('15');
        $(form).find('[name="max_patients"]').val('0');
        $(form).find('.btSelect').selectpicker('refresh');
        clearFormErrors(form);
        $(form).find('.save').text('Save Schedule');
        $(form).find('.modal-title').text('Add New Schedule');
    }
    function populateSchedule(schedule) {
        resetScheduleForm();
        $(form).find('[name="schedule_id"]').val(schedule.id || '');
        $(form).find('[name="doctor_id"]').val(String(schedule.doctor_id || '')).selectpicker('refresh');
        setDayMode(true);
        $(form).find('[name="day_of_weeks[]"]').val([String(schedule.day_of_week || '')]).selectpicker('refresh');
        $(form).find('[name="shift_name"]').val(schedule.shift_name || '');
        $(form).find('[name="status"]').val(String(schedule.status ?? '1')).selectpicker('refresh');
        $(form).find('[name="start_time"]').val(timeValue(schedule.start_time));
        $(form).find('[name="end_time"]').val(timeValue(schedule.end_time));
        $(form).find('[name="break_start_time"]').val(timeValue(schedule.break_start_time));
        $(form).find('[name="break_end_time"]').val(timeValue(schedule.break_end_time));
        $(form).find('[name="slot_duration"]').val(String(schedule.slot_duration || '15')).selectpicker('refresh');
        $(form).find('[name="max_patients"]').val(schedule.max_patients ?? '0');
        $(form).find('.save').text('Update Schedule');
        $(form).find('.modal-title').text('Edit Schedule');
    }
    $(document).on('click', '.open_doctor_schedule_modal', function () { resetScheduleForm(); $(modal).modal('show'); });
    $(document).on('click', '[data-schedule-id]', function () {
        const id = $(this).data('schedule-id');
        if (!id) return;
        resetScheduleForm();
        $(form).find('.modal-title').text('Loading Schedule');
        $(form).find('.save').prop('disabled', true);
        $(modal).modal('show');
        clearFormErrors(form);
        ajaxRequest({url: ROUTES.doctorSchedule.edit + encodeURIComponent(id), method: 'GET', success: function (response) {
            if (!response || !response.status || !response.data) { showError((response && response.msg) || 'Unable to load the schedule.'); return; }
            populateSchedule(response.data);
        }, complete: function () {
            $(form).find('.save').prop('disabled', false);
        }});
    });
    $(form).on('submit', function (event) {
        event.preventDefault();
        const scheduleForm = this;
        clearFormErrors(form);
        const isEdit = Boolean($(scheduleForm).find('[name="schedule_id"]').val());
        const submit = $(scheduleForm).find(':submit').prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: isEdit ? ROUTES.doctorSchedule.update : ROUTES.doctorSchedule.create,
            method: 'POST', data: $(scheduleForm).serialize(),
            success: function (response) {
                if (response && response.status) {
                    $(modal).one('hidden.bs.modal.schedule', function () { window.location.reload(); });
                    $(modal).modal('hide'); showSuccess(response.msg); return;
                }
                showError((response && response.msg) || 'Unable to save the schedule.');
                bindFormError(form, response && response.errors);
            },
            complete: function () { submit.prop('disabled', false).removeAttr('aria-busy'); }
        });
    });
    $(modal).on('hidden.bs.modal.schedule', resetScheduleForm);
}(jQuery));
