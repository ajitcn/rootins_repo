$(function () {
    const table = $('#appointmentReportTable').DataTable({
        data: [],
        columns: [
            { data: 'appointment_number' }, { data: 'appointment_date' }, { data: 'appointment_time' },
            { data: 'registration_number' }, { data: 'patient_name' }, { data: 'doctor' },
            { data: 'clinic' }, { data: 'appointment_type' }, { data: 'status' },
            { data: 'booking_source' }, { data: 'created_by' }, { data: 'created_at' }
        ],
        language: { emptyTable: 'No appointments match the selected filters.' }
    });
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
                table.rows.add((response.data.rows || []).map(row => ({
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
