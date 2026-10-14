(function ($) {
    'use strict';
    var toolbar = $('.pharmacy-dashboard-filters');
    var refresh = $('#pharmacyDashboardRefresh');
    if (toolbar.length && refresh.length) {
        toolbar.append(refresh.detach().addClass('pharmacy-dashboard-refresh'));
        toolbar.append($('<a>', { class: 'btn btn-light pharmacy-dashboard-clear', href: window.location.pathname, text: 'Clear' }));
    }
    $('#pharmacyDashboardRefresh').on('click', function () { window.location.reload(); });
    $('.pharmacy-dashboard-filters').on('submit', function (event) {
        var from = $('#dashboardFrom').val().trim();
        var to = $('#dashboardTo').val().trim();
        var parse = function (value) { var parts = value.split('-'); return parts.length === 3 ? new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0])) : null; };
        var fromDate = parse(from);
        var toDate = parse(to);
        $('.pharmacy-dashboard-date-error').remove();
        if (fromDate && toDate && toDate < fromDate) {
            event.preventDefault();
            $('#dashboardTo').after($('<small>', { class: 'pharmacy-dashboard-date-error text-danger d-block mt-1', text: 'To date cannot be earlier than From date.' }));
            $('#dashboardTo').addClass('is-invalid').trigger('focus');
        }
    });
}(jQuery));
