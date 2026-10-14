$(function () {
    const page = $('.admin-appointment-calendar-page');
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const statusLabels = { scheduled: 'Scheduled', confirmed: 'Confirmed', checked_in: 'Checked In', in_queue: 'In Queue', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No Show' };
    const state = {
        date: String(page.attr('data-calendar-today') || '').trim() || '1970-01-01',
        today: String(page.attr('data-calendar-today') || '').trim() || '1970-01-01',
        timezone: String(page.attr('data-calendar-timezone') || 'Asia/Kolkata'),
        view: 'month',
        events: [],
        filters: {},
        requestId: 0
    };

    const pad = value => String(value).padStart(2, '0');
    const escapeHtml = value => $('<div>').text(value == null ? '' : String(value)).html();
    const displayType = value => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
    const statusTone = status => ({ scheduled: 'blue', confirmed: 'blue', checked_in: 'amber', in_queue: 'orange', completed: 'green', cancelled: 'red', no_show: 'gray' })[status] || 'charcoal';
    const statusLabel = status => statusLabels[status] || displayType(status) || 'Unknown';

    function isoParts(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
        return match ? {year: Number(match[1]), month: Number(match[2]), day: Number(match[3])} : null;
    }

    function isoDate(year, month, day) {
        const date = new Date(Date.UTC(year, month - 1, day));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() + 1 !== month || date.getUTCDate() !== day) return null;
        return year + '-' + pad(month) + '-' + pad(day);
    }

    function dateFromIso(value) {
        const parts = isoParts(value);
        return parts ? new Date(Date.UTC(parts.year, parts.month - 1, parts.day)) : null;
    }

    function isoFromDate(date) {
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function addDays(value, days) {
        const date = dateFromIso(value);
        if (!date) return value;
        date.setUTCDate(date.getUTCDate() + days);
        return isoFromDate(date);
    }

    function addMonths(value, months) {
        const parts = isoParts(value);
        if (!parts) return value;
        const date = new Date(Date.UTC(parts.year, parts.month - 1 + months, 1));
        const lastDay = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
        return isoDate(date.getUTCFullYear(), date.getUTCMonth() + 1, Math.min(parts.day, lastDay));
    }

    function startOfWeek(value) {
        const date = dateFromIso(value);
        if (!date) return value;
        return addDays(value, -date.getUTCDay());
    }

    function displayDate(value) {
        const parts = isoParts(value);
        return parts ? pad(parts.day) + '-' + pad(parts.month) + '-' + parts.year : 'Not provided';
    }

    function displayMonth(value) {
        const parts = isoParts(value);
        return parts ? monthNames[parts.month - 1] + ' ' + parts.year : 'Calendar';
    }

    function displayWeekday(value) {
        const date = dateFromIso(value);
        return date ? weekdayNames[date.getUTCDay()] : '';
    }

    function parseDisplayDate(value) {
        const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec(String(value || '').trim());
        return match ? isoDate(Number(match[3]), Number(match[2]), Number(match[1])) : null;
    }

    function eventsFor(value) {
        return state.events.filter(event => String(event.appointment_date || '') === value);
    }

    function visibleDates() {
        if (state.view === 'day') return [state.date];
        if (state.view === 'week') return Array.from({length: 7}, (_, index) => addDays(startOfWeek(state.date), index));
        const first = isoParts(state.date);
        const firstOfMonth = isoDate(first.year, first.month, 1);
        return Array.from({length: 42}, (_, index) => addDays(startOfWeek(firstOfMonth), index));
    }

    function eventSummary(event) {
        const service = String(event.service_name || '').trim() || displayType(event.appointment_type) || 'Service not specified';
        const queue = event.queue_position === null || event.queue_position === undefined || event.queue_position === '' ? 'Queue —' : 'Queue #' + Number(event.queue_position);
        return [event.patient_name || 'Patient not provided', event.registration_number || 'Patient number unavailable', event.doctor_name || 'Doctor not provided', event.appointment_time || 'Time unavailable', service, queue, statusLabel(event.status)].join(' · ');
    }

    function eventMarkup(event) {
        const service = String(event.service_name || '').trim() || displayType(event.appointment_type) || 'Service not specified';
        const queue = event.queue_position === null || event.queue_position === undefined || event.queue_position === '' ? 'Queue —' : 'Queue #' + Number(event.queue_position);
        const registration = String(event.registration_number || '').trim() || 'Patient number unavailable';
        return '<button type="button" class="appointment-calendar-event" data-id="' + Number(event.id) + '" title="' + escapeHtml(eventSummary(event)) + '" aria-label="' + escapeHtml(eventSummary(event)) + '">' +
            '<span class="appointment-calendar-event-time">' + escapeHtml(event.appointment_time || '—') + '</span>' +
            '<strong class="appointment-calendar-event-patient">' + escapeHtml(event.patient_name || 'Patient not provided') + '</strong>' +
            '<span class="appointment-calendar-event-meta">' + escapeHtml(registration) + '</span>' +
            '<span class="appointment-calendar-event-meta appointment-calendar-event-doctor">' + escapeHtml(event.doctor_name || 'Doctor not provided') + '</span>' +
            '<span class="appointment-calendar-event-service">' + escapeHtml(service) + '</span>' +
            '<span class="appointment-calendar-event-queue">' + escapeHtml(queue) + '</span>' +
            '<span class="badge ri-status ri-status--' + statusTone(event.status) + '">' + escapeHtml(statusLabel(event.status)) + '</span>' +
            '</button>';
    }

    function renderDay() {
        const today = state.date === state.today ? ' is-today' : '';
        return '<div class="appointment-calendar-day-grid"><div class="appointment-calendar-day-cell' + today + '"><div class="appointment-calendar-cell-heading"><span>' + escapeHtml(displayWeekday(state.date)) + '</span><time datetime="' + escapeHtml(state.date) + '">' + escapeHtml(displayDate(state.date)) + '</time></div>' + eventsFor(state.date).map(eventMarkup).join('') + '</div></div>';
    }

    function renderWeek() {
        const first = startOfWeek(state.date);
        let html = '<div class="appointment-calendar-scroll"><div class="appointment-calendar-week-grid">';
        for (let index = 0; index < 7; index++) {
            const day = addDays(first, index);
            html += '<div class="appointment-calendar-week-cell' + (day === state.today ? ' is-today' : '') + '"><div class="appointment-calendar-cell-heading"><span>' + escapeHtml(displayWeekday(day)) + '</span><time datetime="' + escapeHtml(day) + '">' + escapeHtml(displayDate(day)) + '</time></div>' + eventsFor(day).map(eventMarkup).join('') + '</div>';
        }
        return html + '</div></div>';
    }

    function renderMonth() {
        const first = isoParts(state.date);
        const firstOfMonth = isoDate(first.year, first.month, 1);
        const gridStart = startOfWeek(firstOfMonth);
        let html = '<div class="appointment-calendar-scroll"><div class="appointment-calendar-month-grid"><div class="appointment-calendar-weekdays">';
        weekdayNames.forEach(day => { html += '<div>' + escapeHtml(day) + '</div>'; });
        html += '</div><div class="appointment-calendar-month-cells">';
        for (let index = 0; index < 42; index++) {
            const day = addDays(gridStart, index);
            const outside = isoParts(day).month !== first.month ? ' is-outside-month' : '';
            html += '<div class="appointment-calendar-month-cell' + outside + (day === state.today ? ' is-today' : '') + '"><div class="appointment-calendar-cell-heading"><span>' + escapeHtml(displayWeekday(day)) + '</span><time datetime="' + escapeHtml(day) + '">' + escapeHtml(displayDate(day)) + '</time></div>' + eventsFor(day).map(eventMarkup).join('') + '</div>';
        }
        return html + '</div></div></div>';
    }

    function setLoading(loading) {
        $('#appointmentCalendarLoading').prop('hidden', !loading);
        $('#appointmentCalendar').attr('aria-busy', loading ? 'true' : 'false');
    }

    function render() {
        let html = '';
        if (state.view === 'day') {
            $('#appointmentCalendarTitle').text(displayDate(state.date));
            html = renderDay();
        } else if (state.view === 'week') {
            const first = startOfWeek(state.date);
            $('#appointmentCalendarTitle').text(displayDate(first) + ' – ' + displayDate(addDays(first, 6)));
            html = renderWeek();
        } else {
            $('#appointmentCalendarTitle').text(displayMonth(state.date));
            html = renderMonth();
        }
        $('#appointmentCalendar').html(html);
        $('.appointment-calendar-view').removeClass('is-active').attr('aria-pressed', 'false');
        $('.appointment-calendar-view[data-view="' + state.view + '"]').addClass('is-active').attr('aria-pressed', 'true');
        const count = visibleDates().reduce((total, date) => total + eventsFor(date).length, 0);
        $('#appointmentCalendarResultSummary').text(state.events.length + (state.events.length === 1 ? ' appointment loaded' : ' appointments loaded') + (count !== state.events.length ? ' · ' + count + ' in this view' : ''));
        $('#appointmentCalendarEmpty').prop('hidden', count > 0);
        $('#appointmentCalendarLiveStatus').text(count ? count + ' appointments displayed.' : 'No appointments found for this view.');
    }

    function readFilters() {
        const dateFromText = $('#appointmentCalendarDateFrom').val();
        const dateToText = $('#appointmentCalendarDateTo').val();
        const dateFrom = parseDisplayDate(dateFromText);
        const dateTo = parseDisplayDate(dateToText);
        $('#appointmentCalendarDateFrom, #appointmentCalendarDateTo').removeClass('is-invalid').removeAttr('aria-invalid');
        if (dateFromText && !dateFrom) {
            $('#appointmentCalendarDateFrom').addClass('is-invalid').attr('aria-invalid', 'true').focus();
            showError('Enter the start date as dd-mm-yyyy.');
            return null;
        }
        if (dateToText && !dateTo) {
            $('#appointmentCalendarDateTo').addClass('is-invalid').attr('aria-invalid', 'true').focus();
            showError('Enter the end date as dd-mm-yyyy.');
            return null;
        }
        if (dateFrom && dateTo && dateFrom > dateTo) {
            $('#appointmentCalendarDateTo').addClass('is-invalid').attr('aria-invalid', 'true').focus();
            showError('The end date must be on or after the start date.');
            return null;
        }
        return {
            search: String($('#appointmentCalendarSearch').val() || '').trim(),
            status: $('#appointmentCalendarStatus').val() || '',
            appointment_type: $('#appointmentCalendarType').val() || '',
            clinic_id: $('#appointmentCalendarClinic').val() || '',
            doctor_id: $('#appointmentCalendarDoctor').val() || '',
            date_from: dateFrom || '',
            date_to: dateTo || ''
        };
    }

    function loadEvents() {
        const filters = readFilters();
        if (!filters) return;
        state.filters = filters;
        const requestId = ++state.requestId;
        setLoading(true);
        ajaxRequest({
            url: ROUTES.appointments.calendarData,
            method: 'GET',
            data: filters,
            success: function (response) {
                if (requestId !== state.requestId) return;
                if (!response.status) {
                    state.events = [];
                    render();
                    showError(response.msg || 'Unable to load appointments.');
                    return;
                }
                state.events = Array.isArray(response.data) ? response.data : [];
                render();
            },
            complete: function () {
                if (requestId === state.requestId) setLoading(false);
            }
        });
    }

    function showEventDetails(event) {
        const service = String(event.service_name || '').trim() || displayType(event.appointment_type) || 'Service not specified';
        const queue = event.queue_position === null || event.queue_position === undefined || event.queue_position === '' ? 'Not assigned' : 'Queue #' + Number(event.queue_position);
        const rows = [
            ['Patient', event.patient_name || 'Not provided'],
            ['Patient number', event.registration_number || 'Not provided'],
            ['Mobile', event.patient_mobile || 'Not provided'],
            ['Doctor', event.doctor_name || 'Not provided'],
            ['Doctor code', event.doctor_code || 'Not provided'],
            ['Clinic', event.clinic_name || 'Not provided'],
            ['Date', displayDate(event.appointment_date)],
            ['Time', event.appointment_time || 'Not provided'],
            ['Service', service],
            ['Queue', queue],
            ['Appointment type', displayType(event.appointment_type) || 'Not provided']
        ];
        let html = '<div class="appointment-calendar-detail-status"><span class="badge ri-status ri-status--' + statusTone(event.status) + '">' + escapeHtml(statusLabel(event.status)) + '</span></div><dl class="appointment-calendar-detail-grid">';
        rows.forEach(row => { html += '<div><dt>' + escapeHtml(row[0]) + '</dt><dd>' + escapeHtml(row[1]) + '</dd></div>'; });
        html += '</dl>';
        $('#appointmentCalendarDetailsTitle').text(event.appointment_number || 'Appointment details');
        $('#appointmentCalendarDetailsBody').html(html);
        $('#appointmentCalendarOpenDetails').attr('href', ROUTES.appointments.view + Number(event.id));
        $('#appointmentCalendarDetailsModal').modal('show');
    }

    $('.appointment-calendar-view').on('click', function () {
        state.view = String($(this).data('view') || 'month');
        render();
    });
    $('#appointmentCalendarToday').on('click', function () {
        state.date = state.today;
        render();
    });
    $('#appointmentCalendarPrevious').on('click', function () {
        state.date = state.view === 'month' ? addMonths(state.date, -1) : addDays(state.date, state.view === 'day' ? -1 : -7);
        render();
    });
    $('#appointmentCalendarNext').on('click', function () {
        state.date = state.view === 'month' ? addMonths(state.date, 1) : addDays(state.date, state.view === 'day' ? 1 : 7);
        render();
    });
    $('#appointmentCalendarDate').on('change', function () {
        const value = parseDisplayDate($(this).val());
        if (!value) {
            $(this).addClass('is-invalid').attr('aria-invalid', 'true');
            showError('Enter the date as dd-mm-yyyy.');
            return;
        }
        $(this).removeClass('is-invalid').removeAttr('aria-invalid');
        state.date = value;
        render();
    });
    $('#appointmentCalendarFilters').on('submit', function (event) {
        event.preventDefault();
        loadEvents();
    });
    $('#appointmentCalendarReset').on('click', function () {
        $('#appointmentCalendarFilters')[0].reset();
        $('#appointmentCalendarDateFrom, #appointmentCalendarDateTo').removeClass('is-invalid').removeAttr('aria-invalid');
        state.date = state.today;
        $('#appointmentCalendarDate').val(displayDate(state.date));
        loadEvents();
    });
    $('#appointmentCalendar').on('click', '.appointment-calendar-event', function () {
        const event = state.events.find(item => Number(item.id) === Number($(this).data('id')));
        if (event) showEventDetails(event);
    });

    if ($.fn.datepicker) {
        const calendarDateInputs = $('#appointmentCalendarDate, #appointmentCalendarDateFrom, #appointmentCalendarDateTo');
        calendarDateInputs.datepicker({format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true});
        calendarDateInputs.on('show', function () {
            // Bootstrap datepicker appends its menu to body; keep it above the calendar toolbar.
            $('.datepicker-dropdown').css('z-index', '1090');
        });
    }
    $('#appointmentCalendarDate').val(displayDate(state.date));
    loadEvents();
});
