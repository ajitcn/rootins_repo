$(function () {
    const state = {
        date: new Date(),
        view: 'month',
        events: []
    };

    const pad = value => String(value).padStart(2, '0');
    const dateKey = date => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    const startOfWeek = date => {
        const result = new Date(date);
        result.setHours(0, 0, 0, 0);
        result.setDate(result.getDate() - result.getDay());
        return result;
    };
    const addDays = (date, days) => {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    };
    const displayDate = date => date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    const displayType = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());

    function eventsFor(date) {
        return state.events.filter(event => event.appointment_date === dateKey(date));
    }

    function eventMarkup(event) {
        const statusClass = event.status === 'cancelled' ? 'danger' : 'info';
        return '<button type="button" class="appointment-calendar-event btn btn-light btn-sm text-left w-100 mb-1" data-id="' +
            Number(event.id) + '">' +
            '<strong class="d-block">' + $('<div>').text(event.appointment_number).html() + '</strong>' +
            '<span class="d-block">' + $('<div>').text(event.patient_name).html() + '</span>' +
            '<small class="d-block text-muted">' + $('<div>').text(event.doctor_name + ' · ' + event.clinic_name).html() + '</small>' +
            '<small class="d-block">' + $('<div>').text(event.appointment_time + ' · ' + displayType(event.appointment_type)).html() +
            ' <span class="badge badge-' + statusClass + '">' + $('<div>').text(displayType(event.status)).html() + '</span></small>' +
            '</button>';
    }

    function renderDay(date) {
        return '<div class="p-2" style="min-height:420px;"><h6>' + displayDate(date) + '</h6>' +
            eventsFor(date).map(eventMarkup).join('') + '</div>';
    }

    function renderWeek(date) {
        const first = startOfWeek(date);
        let html = '<div class="row no-gutters">';
        for (let i = 0; i < 7; i++) {
            const day = addDays(first, i);
            html += '<div class="col border-right p-2" style="min-width:150px; min-height:420px;">' +
                '<h6>' + day.toLocaleDateString(undefined, { weekday: 'short' }) + '<br><small>' + displayDate(day) + '</small></h6>' +
                eventsFor(day).map(eventMarkup).join('') + '</div>';
        }
        return html + '</div>';
    }

    function renderMonth(date) {
        const first = new Date(date.getFullYear(), date.getMonth(), 1);
        const gridStart = startOfWeek(first);
        let html = '<div class="row no-gutters">';
        for (let i = 0; i < 42; i++) {
            const day = addDays(gridStart, i);
            const outside = day.getMonth() !== date.getMonth() ? ' text-muted bg-light' : '';
            html += '<div class="col-12 col-sm-6 col-lg border-right border-bottom p-2' + outside + '" style="min-width:130px; min-height:130px;">' +
                '<div class="small font-weight-bold mb-1">' + day.getDate() + '</div>' +
                eventsFor(day).map(eventMarkup).join('') + '</div>';
        }
        return html + '</div>';
    }

    function render() {
        const date = state.date;
        if (state.view === 'day') {
            $('#appointmentCalendarTitle').text(displayDate(date));
            $('#appointmentCalendar').html(renderDay(date));
        } else if (state.view === 'week') {
            const first = startOfWeek(date);
            $('#appointmentCalendarTitle').text(displayDate(first) + ' - ' + displayDate(addDays(first, 6)));
            $('#appointmentCalendar').html(renderWeek(date));
        } else {
            $('#appointmentCalendarTitle').text(date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
            $('#appointmentCalendar').html(renderMonth(date));
        }
        $('.appointment-calendar-view').removeClass('btn-primary').addClass('btn-outline-primary');
        $('.appointment-calendar-view[data-view="' + state.view + '"]').removeClass('btn-outline-primary').addClass('btn-primary');
    }

    function loadEvents() {
        ajaxRequest({
            url: ROUTES.appointments.calendarData,
            method: 'GET',
            success: function (response) {
                if (!response.status) {
                    showError(response.msg);
                    return;
                }
                state.events = response.data || [];
                render();
            }
        });
    }

    $('.appointment-calendar-view').on('click', function () {
        state.view = $(this).data('view');
        render();
    });
    $('#appointmentCalendarToday').on('click', function () {
        state.date = new Date();
        render();
    });
    $('#appointmentCalendarPrevious').on('click', function () {
        if (state.view === 'month') {
            state.date.setMonth(state.date.getMonth() - 1);
        } else {
            state.date.setDate(state.date.getDate() - (state.view === 'day' ? 1 : 7));
        }
        render();
    });
    $('#appointmentCalendarNext').on('click', function () {
        if (state.view === 'month') {
            state.date.setMonth(state.date.getMonth() + 1);
        } else {
            state.date.setDate(state.date.getDate() + (state.view === 'day' ? 1 : 7));
        }
        render();
    });
    $('#appointmentCalendar').on('click', '.appointment-calendar-event', function () {
        window.location.href = ROUTES.appointments.view + Number($(this).data('id'));
    });

    loadEvents();
});
