(function ($) {
    'use strict';

    function clear(form) {
        clearFormErrors(form);
        $(form).find('input[name="schedule_id"], input[name="leave_id"]').val('');
    }

    function setSchedule(row) {
        const form = $('#schedule-form');
        clear(form);
        $('#scheduleModalTitle').text('Edit availability');
        form.find('[name="schedule_id"]').val(row.id || '');
        form.find('[name="day_of_week"]').val(row.day_of_week || '');
        form.find('[name="shift_name"]').val(row.shift_name || '');
        form.find('[name="start_time"]').val(String(row.start_time || '').slice(0, 5));
        form.find('[name="end_time"]').val(String(row.end_time || '').slice(0, 5));
        form.find('[name="break_start_time"]').val(String(row.break_start_time || '').slice(0, 5));
        form.find('[name="break_end_time"]').val(String(row.break_end_time || '').slice(0, 5));
        form.find('[name="slot_duration"]').val(row.slot_duration || 15);
        form.find('[name="max_patients"]').val(row.max_patients || 0);
    }

    function setLeave(row) {
        const form = $('#leave-form');
        clear(form);
        $('#leaveModalTitle').text('Edit leave request');
        form.find('[name="leave_id"]').val(row.id || '');
        form.find('[name="leave_type"]').val(row.leave_type || '');
        setDateValue('#start-date', row.start_date);
        setDateValue('#end-date', row.end_date);
        form.find('[name="start_time"]').val(String(row.start_time || '').slice(0, 5));
        form.find('[name="end_time"]').val(String(row.end_time || '').slice(0, 5));
        form.find('[name="reason"]').val(row.reason || '');
        form.find('[name="force_conflict"]').prop('checked', false);
    }

    function setDateValue(selector, value) {
        const field = $(selector);
        const display = toDisplayDate(value);
        if (typeof window.setSharedDateValue === 'function') {
            window.setSharedDateValue(selector, value || '');
            if (field.data('datepicker')) field.datepicker('update', display);
            return;
        }
        field.val(display);
    }

    function toDisplayDate(value) {
        const parts = String(value || '').split('-');
        return parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : '';
    }

    function bindLeaveErrors(form, errors) {
        const dateMessage = errors.leave_date || errors.start_date || errors.end_date;
        const dateFields = form.find('[name="start_date"], [name="end_date"]');
        form.find('#leave_dateErr').text(dateMessage || '');
        dateFields.toggleClass('is-invalid', Boolean(dateMessage)).attr('aria-invalid', dateMessage ? 'true' : 'false');
        const mapped = $.extend({}, errors);
        delete mapped.leave_date;
        delete mapped.start_date;
        delete mapped.end_date;
        if (Object.keys(mapped).length) bindFormError(form, mapped);
    }

    $(document).on('click', '[data-target="#scheduleModal"]:not(.js-edit-schedule)', function () {
        const form = $('#schedule-form');
        clear(form);
        form[0].reset();
        setDateValue('#start-date', '');
        setDateValue('#end-date', '');
        form.find('[name="slot_duration"]').val(15);
        form.find('[name="max_patients"]').val(0);
        $('#scheduleModalTitle').text('Add availability');
    }).on('click', '.js-edit-schedule', function () {
        setSchedule($(this).data('row'));
    }).on('click', '[data-target="#leaveModal"]:not(.js-edit-leave)', function () {
        const form = $('#leave-form');
        clear(form);
        form[0].reset();
        setDateValue('#start-date', '');
        setDateValue('#end-date', '');
        $('#leaveModalTitle').text('Request leave');
    }).on('click', '.js-edit-leave', function () {
        setLeave($(this).data('row'));
    });

    $('#leaveModal').on('show.bs.modal', function () {
        $(this).find('.modal-dialog').removeClass('modal-dialog-scrollable').addClass('modal-lg');
    });

    $('#schedule-form').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        clearFormErrors(form);
        ajaxRequest({
            url: BASE_URL + 'doctor/availability/schedule/save',
            data: $(form).serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    $('#scheduleModal').modal('hide');
                    window.setTimeout(function () { window.location.reload(); }, 350);
                } else {
                    showError(response.msg);
                    if (response.errors) bindLeaveErrors(form, response.errors);
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                if (response.errors) bindLeaveErrors(form, response.errors);
                showError(response.msg || 'Availability could not be saved.');
            }
        });
    });

    $('#leave-form').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        clearFormErrors(form);
        ajaxRequest({
            url: BASE_URL + 'doctor/availability/leave/save',
            data: $(form).serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    $('#leaveModal').modal('hide');
                    window.setTimeout(function () { window.location.reload(); }, 350);
                } else {
                    showError(response.msg);
                    if (response.errors) bindFormError(form, response.errors);
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                if (response.errors) bindFormError(form, response.errors);
                showError(response.msg || 'Leave could not be saved.');
            }
        });
    });

    function cancel(url, message) {
        Swal.fire({icon: 'warning', title: message, text: 'This can be reviewed later, but the record will no longer be active.', showCancelButton: true, confirmButtonText: 'Continue'}).then(function (result) {
            if (!result.isConfirmed) return;
            ajaxRequest({url: url, data: {}, success: function (response) {
                if (response.status) { showSuccess(response.msg); window.setTimeout(function () { window.location.reload(); }, 350); }
                else showError(response.msg);
            }});
        });
    }

    $(document).on('click', '.js-cancel-schedule', function () {
        cancel(BASE_URL + 'doctor/availability/schedule/' + $(this).data('id') + '/cancel', 'Disable this availability slot?');
    }).on('click', '.js-cancel-leave', function () {
        cancel(BASE_URL + 'doctor/availability/leave/' + $(this).data('id') + '/cancel', 'Cancel this leave request?');
    });
}(jQuery));
