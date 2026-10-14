(function ($) {
    'use strict';

    const pageSize = 15;
    const statusTones = {scheduled: 'blue', confirmed: 'blue', checked_in: 'amber', in_queue: 'orange', completed: 'green', cancelled: 'red', no_show: 'gray'};
    const $form = $('#appointmentReportForm');
    const $tbody = $('#appointmentReportTable tbody');
    const $loading = $('#appointmentReportLoading');
    const $resultLabel = $('#appointmentReportResultLabel');

    const escapeHtml = value => $('<div>').text(value == null ? '' : String(value)).html();
    const label = value => String(value || 'Not recorded').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    const text = value => escapeHtml(String(value || '').trim() || 'Not provided');

    function parseDate(value) {
        const match = String(value || '').trim().match(/^(\d{2})-(\d{2})-(\d{4})$/);
        if (!match) return null;
        const date = new Date(Date.UTC(Number(match[3]), Number(match[2]) - 1, Number(match[1])));
        return date.getUTCFullYear() === Number(match[3]) && date.getUTCMonth() === Number(match[2]) - 1 && date.getUTCDate() === Number(match[1]) ? date : null;
    }

    function clearErrors() {
        $form.find('.validation-error').empty();
        $form.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
    }

    function setErrors(errors) {
        clearErrors();
        Object.keys(errors || {}).forEach(function (name) {
            const $field = $form.find('[name="' + name + '"]');
            const $error = $('#' + name + 'Err');
            $field.addClass('is-invalid').attr('aria-invalid', 'true');
            $error.text(errors[name]);
        });
        const $first = $form.find('.is-invalid').first();
        if ($first.length) $first.trigger('focus');
    }

    function validateDates() {
        const fromValue = $('#reportDateFrom').val();
        const toValue = $('#reportDateTo').val();
        const from = fromValue ? parseDate(fromValue) : null;
        const to = toValue ? parseDate(toValue) : null;
        const errors = {};
        if (fromValue && !from) errors.date_from = 'Enter a valid From Date as dd-mm-yyyy.';
        if (toValue && !to) errors.date_to = 'Enter a valid To Date as dd-mm-yyyy.';
        if (from && to && to < from) errors.date_to = 'To Date must be on or after From Date.';
        if (Object.keys(errors).length) setErrors(errors);
        return Object.keys(errors).length === 0;
    }

    function statusBadge(status) {
        const tone = statusTones[status] || 'charcoal';
        return '<span class="badge ri-status ri-status--' + tone + '">' + escapeHtml(label(status)) + '</span>';
    }

    function stateText(value) {
        return '<span class="admin-report-muted">' + text(value ? label(value) : '') + '</span>';
    }

    function drawRows(rows) {
        if (!rows.length) {
            $tbody.html('<tr><td colspan="7"><div class="ri-empty-state admin-report-empty"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><h3>No appointments found</h3><p>No appointments match the selected report filters.</p></div></td></tr>');
            return;
        }
        $tbody.html(rows.map(function (row) {
            const appointmentUrl = BASE_URL + 'admin/appointments/view/' + Number(row.id || 0);
            return '<tr>' +
                '<td data-label="Appointment"><div class="ri-stack-cell"><strong class="admin-report-primary">' + text(row.appointment_date) + '</strong><span class="admin-report-time">' + text(row.appointment_time) + '</span><span class="admin-report-secondary">' + text(row.appointment_number) + '</span></div></td>' +
                '<td data-label="Patient"><div class="ri-stack-cell"><strong class="admin-report-primary">' + text(row.patient_name) + '</strong><span class="admin-report-secondary">' + text(row.registration_number) + '</span><span class="admin-report-secondary">' + text(row.patient_mobile) + '</span></div></td>' +
                '<td data-label="Doctor"><div class="ri-stack-cell"><strong class="admin-report-primary">Dr. ' + text(row.doctor) + '</strong><span class="admin-report-secondary">' + text(row.doctor_code) + '</span><span class="admin-report-secondary">' + text(row.clinic) + '</span></div></td>' +
                '<td data-label="Service"><div class="ri-stack-cell"><strong class="admin-report-primary">' + text(row.service_name) + '</strong><span class="admin-report-secondary">' + escapeHtml(label(row.appointment_type)) + '</span><span class="badge badge-light admin-report-queue">Queue ' + (row.queue_position == null || row.queue_position === '' ? '—' : '#' + escapeHtml(row.queue_position)) + '</span></div></td>' +
                '<td data-label="Status">' + statusBadge(row.status) + '</td>' +
                '<td data-label="Clinical / payment"><div class="ri-stack-cell">' + stateText(row.consultation_status) + stateText(row.payment_status) + '</div></td>' +
                '<td data-label="Actions" class="admin-report-actions text-right"><div class="dropdown ri-actions-wrapper"><button type="button" class="btn btn-sm btn-outline-secondary ri-more-actions-toggle dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="ri-actions-icon" aria-hidden="true"></span>More Actions</button><div class="dropdown-menu dropdown-menu-right ri-actions-menu"><a class="dropdown-item" href="' + appointmentUrl + '"><i class="bi bi-eye mr-2" aria-hidden="true"></i>View appointment</a></div></div></td>' +
                '</tr>';
        }).join(''));
    }

    function drawPagination(pagination) {
        const total = Number(pagination.total || 0);
        const pages = Number(pagination.pages || 1);
        const page = Number(pagination.page || 1);
        if (pages <= 1) {
            $('#appointmentReportPagination').html(total ? '<span class="admin-report-pagination-label">Showing ' + total + ' appointment' + (total === 1 ? '' : 's') + '</span>' : '');
            return;
        }
        let html = '<span class="admin-report-pagination-label">Page ' + page + ' of ' + pages + ' · ' + total + ' appointments</span><div class="admin-report-pagination-buttons">';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary report-page-button" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Previous</button>';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary report-page-button" data-page="' + (page + 1) + '" ' + (page >= pages ? 'disabled' : '') + '>Next</button></div>';
        $('#appointmentReportPagination').html(html);
    }

    function loadReport(page) {
        page = Number(page || 1);
        clearErrors();
        $loading.text('Loading…').addClass('is-loading');
        $resultLabel.text('Loading…');
        ajaxRequest({
            url: ROUTES.appointments.reportsData,
            method: 'GET',
            dataType: 'json',
            data: $form.serialize() + '&page=' + encodeURIComponent(page) + '&page_size=' + pageSize,
            success: function (response) {
                if (!response || !response.status) {
                    setErrors(response && response.errors ? response.errors : {search: (response && response.msg) || 'Unable to load the report.'});
                    $loading.text('Report unavailable');
                    return;
                }
                const data = response.data || {};
                Object.keys(data.summary || {}).forEach(key => $('[data-summary="' + key + '"]').text(data.summary[key]));
                drawRows(data.rows || []);
                drawPagination(data.pagination || {});
                const total = Number((data.pagination || {}).total || 0);
                $loading.text(total ? 'Updated' : 'No results').removeClass('is-loading');
                $resultLabel.text(total + ' appointment' + (total === 1 ? '' : 's'));
            },
            error: function () {
                $tbody.html('<tr><td colspan="7"><div class="ri-empty-state admin-report-empty"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><h3>Report unavailable</h3><p>We could not load this report. Try again.</p><button type="button" class="btn btn-sm btn-outline-secondary" id="retryAppointmentReport">Retry</button></div></td></tr>');
                $loading.text('Request failed').removeClass('is-loading');
                $resultLabel.text('Request failed');
                showError('Unable to load the appointment report.');
            },
            complete: function () { $loading.removeClass('is-loading'); }
        });
    }

    $form.on('submit', function (event) {
        event.preventDefault();
        if (validateDates()) loadReport(1);
    });
    $('#resetAppointmentReport').on('click', function () {
        $form[0].reset();
        clearErrors();
        if ($.fn.selectpicker) $form.find('.btSelect').selectpicker('refresh');
        loadReport(1);
    });
    $(document).on('click', '.report-page-button:not(:disabled)', function () { loadReport($(this).data('page')); });
    $(document).on('click', '#retryAppointmentReport', function () { loadReport(1); });
    $('#printAppointmentReport').on('click', function () { window.print(); });
    loadReport(1);
}(jQuery));
