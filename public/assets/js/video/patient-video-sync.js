(function (window, $) {
    'use strict';
    if (!$) return;

    var page = $('#patient-video-page');
    var appointmentId = Number(page.data('appointment-id') || 0);
    if (!page.length || !appointmentId) return;

    var statusUrl = page.data('status-url');
    var button = $('#patient-video-join');
    var status = $('#patient-video-status');
    var message = $('#patient-video-message');
    var redirectUrl = page.data('redirect-url');
    var timer = null;
    var ended = false;

    function label(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function setStatus(value) { status.text('Status: ' + label(value)); }

    function stopForEnded() {
        if (ended) return;
        ended = true;
        setStatus('ended');
        message.text('The doctor ended this consultation.');
        button.prop('disabled', true).html('<i class="bi bi-check-circle mr-1"></i>Consultation ended');
        $('#patient-video-mute, #patient-video-camera, .js-video-layout-toggle, .js-video-fullscreen').prop('disabled', true);
        if (window.RootinsTwilioRoom) window.RootinsTwilioRoom.disconnect();
        if (redirectUrl) {
            window.setTimeout(function () { window.location.assign(redirectUrl); }, 3500);
        }
    }

    function sync() {
        if (!statusUrl || ended) return;
        $.getJSON(statusUrl).done(function (response) {
            var session = response && response.data ? response.data.session : null;
            if (!session) return;
            setStatus(session.status);
            if (session.status === 'ended') {
                stopForEnded();
            } else if (['waiting', 'active'].indexOf(session.status) !== -1 && !button.prop('disabled')) {
                message.text('Your doctor has started the consultation. You can join now.');
            }
        });
    }

    timer = window.setInterval(sync, 2500);
    sync();
    $(window).on('beforeunload.patientVideoSync', function () { if (timer) window.clearInterval(timer); });
}(window, window.jQuery));
