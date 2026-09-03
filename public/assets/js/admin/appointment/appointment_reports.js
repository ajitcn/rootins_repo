$(function () {
    const table = {
        rows: [],
        clear() { this.rows = []; return this; },
        add(rows) { this.rows = this.rows.concat(rows); return this; },
        draw() {
            $('#appointmentReportTable tbody').html(this.rows.length ? this.rows.map(row => '<tr><td>' + row.appointment_number + '</td><td>' + row.appointment_date + '</td><td>' + row.appointment_time + '</td><td>' + row.registration_number + '</td><td>' + row.patient_name + '</td><td>' + row.doctor + '</td><td>' + row.clinic + '</td><td>' + row.appointment_type + '</td><td>' + row.status + '</td><td>' + row.booking_source + '</td><td>' + row.created_by + '</td><td>' + row.created_at + '</td></tr>').join('') : '<tr><td colspan="12" class="text-center text-muted py-4">No appointments match the selected filters.</td></tr>');
        }
    };
    const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
    const label = value => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());

    function loadReport() {
        ajaxRequest({
            url: ROUTES.appointments.reportsData,
            method: 'GET',
            data: $('#appointmentReportForm').serialize(),
            success: function (response) {
                if (!response.status) {
                    showError(response.msg);
                    return;
                }
                $.each(response.data.summary || {}, function (key, value) {
                    $('[data-summary="' + key + '"]').text(value);
                });
                table.clear();
                table.add((response.data.rows || []).map(row => ({
                    appointment_number: escapeHtml(row.appointment_number),
                    appointment_date: escapeHtml(row.appointment_date),
                    appointment_time: escapeHtml(row.appointment_time),
                    registration_number: escapeHtml(row.registration_number),
                    patient_name: escapeHtml(row.patient_name),
                    doctor: escapeHtml(row.doctor),
                    clinic: escapeHtml(row.clinic),
                    appointment_type: escapeHtml(label(row.appointment_type)),
                    status: '<span class="badge badge-' + (row.status === 'cancelled' ? 'danger' : 'info') + '">' + escapeHtml(label(row.status)) + '</span>',
                    booking_source: escapeHtml(label(row.booking_source)),
                    created_by: escapeHtml(row.created_by),
                    created_at: escapeHtml(row.created_at)
                })));
                table.draw();
            }
        });
    }

    $('#appointmentReportForm').on('submit', function (event) {
        event.preventDefault();
        loadReport();
    });
    $('#resetAppointmentReport').on('click', function () {
        $('#appointmentReportForm')[0].reset();
        $('.btSelect').selectpicker('refresh');
        loadReport();
    });
    loadReport();
});
