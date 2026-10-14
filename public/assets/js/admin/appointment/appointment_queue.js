$(function () {
    const table = {
        rows: [],
        clear() { this.rows = []; return this; },
        add(rows) { this.rows = this.rows.concat(rows); return this; },
        draw() {
            $('#appointmentQueueTable tbody').html(this.rows.length ? this.rows.map(row => '<tr><td>' + row.queue_position + '</td><td>' + row.appointment_number + '</td><td>' + row.patient + '</td><td>' + row.doctor + '</td><td>' + row.appointment_time + '</td><td>' + row.appointment_type + '</td><td>' + row.checked_in_at + '</td><td>' + row.status + '</td><td>' + row.actions + '</td></tr>').join('') : '<tr><td colspan="9" class="text-center text-muted py-5">No patients are currently in the queue.</td></tr>');
        }
    };
    const csrf = () => ({ csrf_rtn: $('#appointmentQueueForm input[name="csrf_rtn"]').val() });
    const label = value => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
    const statusTone = status => ({ scheduled: 'blue', confirmed: 'blue', checked_in: 'amber', in_queue: 'orange', completed: 'green', cancelled: 'red', no_show: 'gray' })[status] || 'charcoal';
    function loadQueue() {
        ajaxRequest({ url: ROUTES.appointments.queueData, method: 'GET', data: { clinic_id: $('#queueClinic').val(), appointment_date: window.rootinsDateToIso ? window.rootinsDateToIso('#queueDate') : $('#queueDate').val() }, success: function (response) {
            if (!response.status) { showError(response.msg); return; }
            table.clear().add((response.data || []).map(row => ({ id: row.id, queue_position: row.queue_position, appointment_number: escapeHtml(row.appointment_number), patient: escapeHtml((row.patient_name || '') + (row.registration_number ? ' (' + row.registration_number + ')' : '')), doctor: escapeHtml(row.doctor_name), appointment_time: escapeHtml(row.appointment_time), appointment_type: escapeHtml(label(row.appointment_type)), checked_in_at: escapeHtml(row.checked_in_at || ''), status: '<span class="badge ri-status ri-status--' + statusTone(row.status) + '">' + escapeHtml(label(row.status)) + '</span>', actions: actionMarkup(row) }))).draw();
        } });
    }
    function actionMarkup(row) {
        const id = Number(row.id);
        let items = '';
        if (row.status === 'scheduled' || row.status === 'confirmed') items = '<button type="button" class="dropdown-item queue-check-in" data-id="' + id + '">Check In</button>';
        else {
            items += '<button type="button" class="dropdown-item queue-move" data-id="' + id + '" data-direction="up">Move up</button><button type="button" class="dropdown-item queue-move" data-id="' + id + '" data-direction="down">Move down</button>';
            if (row.status === 'checked_in') items += '<button type="button" class="dropdown-item queue-status" data-id="' + id + '" data-status="in_queue">Start</button>';
            if (row.status === 'in_queue') items += '<button type="button" class="dropdown-item queue-status" data-id="' + id + '" data-status="completed">Complete</button>';
            items += '<button type="button" class="dropdown-item queue-status" data-id="' + id + '" data-status="no_show">No Show</button><button type="button" class="dropdown-item text-danger queue-status" data-id="' + id + '" data-status="cancelled">Cancel</button>';
        }
        return '<div class="dropdown ri-actions-wrapper"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More Actions</button><div class="dropdown-menu dropdown-menu-right ri-actions-menu">' + items + '</div></div>';
    }
    $('#loadAppointmentQueue, #queueClinic, #queueDate').on('change click', loadQueue);
    $('#appointmentQueueTable').on('click', '.queue-move', function () { ajaxRequest({ url: ROUTES.appointments.queueMove + Number($(this).data('id')) + '/' + $(this).data('direction'), method: 'POST', data: csrf(), success: function (response) { response.status ? loadQueue() : showError(response.msg); } }); });
    $('#appointmentQueueTable').on('click', '.queue-status', function () { const button = $(this), status = button.data('status'); if (status === 'cancelled' && !window.confirm('Cancel this appointment?')) return; ajaxRequest({ url: ROUTES.appointments.queueStatus + Number(button.data('id')), method: 'POST', data: $.extend(csrf(), { status: status }), success: function (response) { response.status ? loadQueue() : showError(response.msg); } }); });
    $('#appointmentQueueTable').on('click', '.queue-check-in', function () { ajaxRequest({ url: ROUTES.appointments.checkIn + Number($(this).data('id')), method: 'POST', data: csrf(), success: function (response) { response.status ? loadQueue() : showError(response.msg); } }); });
    loadQueue();
});
