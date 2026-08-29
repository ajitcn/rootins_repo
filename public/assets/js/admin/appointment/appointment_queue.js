$(function () {
    const table = $('#appointmentQueueTable').DataTable({
        data: [],
        columns: [
            { data: 'queue_position' },
            { data: 'appointment_number' },
            { data: 'patient' },
            { data: 'doctor' },
            { data: 'appointment_time' },
            { data: 'appointment_type' },
            { data: 'checked_in_at' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: { emptyTable: 'No patients are currently in the queue.' }
    });

    const csrf = () => ({ csrf_rtn: $('#appointmentQueueForm input[name="csrf_rtn"]').val() });
    const label = value => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

    function loadQueue() {
        ajaxRequest({
            url: ROUTES.appointments.queueData,
            method: 'GET',
            data: {
                clinic_id: $('#queueClinic').val(),
                appointment_date: $('#queueDate').val()
            },
            success: function (response) {
                if (!response.status) {
                    showError(response.msg);
                    return;
                }

                table.clear();
                table.rows.add((response.data || []).map(row => ({
                    id: row.id,
                    queue_position: row.queue_position,
                    appointment_number: escapeHtml(row.appointment_number),
                    patient: escapeHtml((row.patient_name || '') + (row.registration_number ? ' (' + row.registration_number + ')' : '')),
                    doctor: escapeHtml(row.doctor_name),
                    appointment_time: escapeHtml(row.appointment_time),
                    appointment_type: escapeHtml(label(row.appointment_type)),
                    checked_in_at: escapeHtml(row.checked_in_at || ''),
                    status: '<span class="badge badge-info">' + escapeHtml(label(row.status)) + '</span>',
                    actions: actionMarkup(row)
                })));
                table.draw();
            }
        });
    }

    function actionMarkup(row) {
        const id = Number(row.id);
        if (row.status === 'scheduled' || row.status === 'confirmed') {
            return '<button type="button" class="btn btn-sm btn-primary queue-check-in" data-id="' + id + '">Check In</button>';
        }
        let html = '<button type="button" class="btn btn-sm btn-outline-secondary queue-move mr-1" data-id="' + id + '" data-direction="up" title="Move up">↑</button>';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary queue-move mr-1" data-id="' + id + '" data-direction="down" title="Move down">↓</button>';
        if (row.status === 'checked_in') {
            html += '<button type="button" class="btn btn-sm btn-primary queue-status mr-1" data-id="' + id + '" data-status="in_queue">Start</button>';
        }
        if (row.status === 'in_queue') {
            html += '<button type="button" class="btn btn-sm btn-success queue-status mr-1" data-id="' + id + '" data-status="completed">Complete</button>';
        }
        html += '<button type="button" class="btn btn-sm btn-warning queue-status mr-1" data-id="' + id + '" data-status="no_show">No Show</button>';
        html += '<button type="button" class="btn btn-sm btn-danger queue-status" data-id="' + id + '" data-status="cancelled">Cancel</button>';
        return html;
    }

    $('#loadAppointmentQueue, #queueClinic, #queueDate').on('change click', function () {
        if (this.id === 'loadAppointmentQueue' || this.id === 'queueClinic' || this.id === 'queueDate') {
            loadQueue();
        }
    });

    $('#appointmentQueueTable').on('click', '.queue-move', function () {
        ajaxRequest({
            url: ROUTES.appointments.queueMove + Number($(this).data('id')) + '/' + $(this).data('direction'),
            method: 'POST',
            data: csrf(),
            success: function (response) {
                if (response.status) {
                    loadQueue();
                } else {
                    showError(response.msg);
                }
            }
        });
    });

    $('#appointmentQueueTable').on('click', '.queue-status', function () {
        const button = $(this);
        const status = button.data('status');
        if (status === 'cancelled' && !window.confirm('Cancel this appointment?')) {
            return;
        }

        ajaxRequest({
            url: ROUTES.appointments.queueStatus + Number(button.data('id')),
            method: 'POST',
            data: $.extend(csrf(), { status: status }),
            success: function (response) {
                if (response.status) {
                    loadQueue();
                } else {
                    showError(response.msg);
                }
            }
        });
    });

    $('#appointmentQueueTable').on('click', '.queue-check-in', function () {
        ajaxRequest({
            url: ROUTES.appointments.checkIn + Number($(this).data('id')),
            method: 'POST',
            data: csrf(),
            success: function (response) {
                if (response.status) {
                    loadQueue();
                } else {
                    showError(response.msg);
                }
            }
        });
    });

    loadQueue();
});
