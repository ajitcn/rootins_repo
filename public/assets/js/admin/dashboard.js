(function ($) {
    'use strict';
    var form = $('#admin-dashboard-filter');
    if (!form.length) return;
    var feedback = $('.admin-dashboard-feedback'), range = $('#dashboard-range'), from = $('#dashboard-from'), to = $('#dashboard-to');
    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function setLoading(loading) { form.toggleClass('is-loading', loading); form.find('button').prop('disabled', loading); form.find('.admin-dashboard-apply-label').toggleClass('d-none', loading); form.find('.admin-dashboard-spinner').toggleClass('d-none', !loading); }
    function payload() { return { range: range.val() || 'last_7_days', from: from.val() || '', to: to.val() || '' }; }
    function toggleCustomDates() { $('.admin-dashboard-custom-date').toggle(range.val() === 'custom'); }
    function render(data) {
        $.each(data.metrics || {}, function (key, value) { var node = $('[data-metric="' + key + '"]'); if (key === 'revenue') node.text('₹' + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })); else node.text(Number(value || 0).toLocaleString('en-IN')); if (key === 'audit_alerts') node.toggleClass('text-danger', Number(value || 0) > 0); });
        $.each(data.summaries || {}, function (key, value) { $('[data-summary="' + key + '"]').text(Number(value || 0).toLocaleString('en-IN')); });
        var days = (data.charts || {}).appointments_by_day || [], max = Math.max.apply(null, [1].concat(days.map(function (row) { return Number(row.total || 0); }))), chart = $('[data-dashboard-chart="appointments_by_day"]');
        chart.empty(); if (!days.length) chart.html('<div class="dashboard-empty">No appointments in this range.</div>'); else $.each(days, function (_, row) { var date = new Date(row.day + 'T00:00:00'); chart.append('<div class="dashboard-bar-item"><span>' + esc(date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })) + '</span><div class="dashboard-bar-track"><i style="height:' + Math.round(Number(row.total || 0) / max * 100) + '%" title="' + Number(row.total || 0) + ' appointments"></i></div><strong>' + Number(row.total || 0) + '</strong></div>'); });
        var body = $('[data-dashboard-activity]').empty(), rows = (data.tables || {}).recent_activity || []; if (!rows.length) body.html('<tr><td colspan="4" class="dashboard-empty">No recent activity in this range.</td></tr>'); else $.each(rows, function (_, row) { body.append('<tr><td>' + esc(row.occurred_at) + '</td><td>' + esc(row.action) + '</td><td>' + esc(row.route || '—') + '</td><td>' + esc(row.clinic_id || '—') + '</td></tr>'); });
        var selected = data.range || {}; range.val(selected.key || 'last_7_days'); from.val(selected.from || ''); to.val(selected.to || ''); toggleCustomDates(); $('.admin-dashboard-range-label').text((selected.label || '') + ': ' + (selected.from_display || '') + ' – ' + (selected.to_display || ''));
    }
    form.on('submit', function (event) { event.preventDefault(); setLoading(true); feedback.addClass('d-none').text(''); $.ajax({ url: form.data('data-url'), method: 'GET', data: payload(), dataType: 'json', cache: false }).done(function (response) { if (!response || response.status !== true || !response.data) { feedback.removeClass('d-none alert-success').addClass('alert alert-danger').text(response.msg || 'Dashboard data could not be loaded.'); return; } render(response.data); var query = $.param(payload()); window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '')); }).fail(function () { feedback.removeClass('d-none alert-success').addClass('alert alert-danger').text('Dashboard data could not be loaded. Please try again.'); }).always(function () { setLoading(false); }); });
    range.on('change', function () { toggleCustomDates(); if ($(this).val() !== 'custom') form.trigger('submit'); });
    toggleCustomDates();
})(jQuery);
