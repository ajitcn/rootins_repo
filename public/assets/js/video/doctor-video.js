(function (window, $) {
    'use strict';
    if (!$) return;

    function csrf() { return $('input[name="csrf_rtn"]').first().val() || ''; }
    function connectionError(error) {
        var name = error && error.name ? error.name : '';
        if (name === 'NotReadableError') return 'The camera or microphone is busy. Close Microsoft Phone Link, Teams, Camera, or another app using these devices, then try Join again.';
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Camera or microphone access was denied. Allow browser access, then try Join again.';
        return error && error.message ? 'Video connection failed: ' + error.message : 'Camera or microphone permission or connection failed.';
    }

    $(document).on('click.rootinsDoctorVideoStart', '.js-video-start', function () {
        var button = $(this), url = button.data('start-url');
        if (!url) return;
        button.prop('disabled', true);
        ajaxRequest({ url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (!response.status) { showError(response.msg || 'Video consultation could not be started.'); return; }
                var panel = button.closest('.doctor-video-panel');
                panel.find('.doctor-video-status-badge').removeClass(function (i, c) { return (c.match(/(^|\s)doctor-video-status-\S+/g) || []).join(' '); }).addClass('doctor-video-status-waiting');
                panel.find('.doctor-video-status').text('Waiting');
                panel.find('.js-video-join').prop('disabled', false).trigger('click');
                button.remove();
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Video consultation start timed out.' : 'Video consultation could not be started.'); },
            complete: function () { if (button.closest('body').length) button.prop('disabled', false); }
        });
    });

    $(document).on('click.rootinsDoctorVideoJoin', '.js-video-join', function () {
        var button = $(this), url = button.data('token-url');
        if (!url) return;
        button.prop('disabled', true);
        ajaxRequest({ url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (!response.status || !response.data || !response.data.token) { showError(response.msg || 'Video service is not available yet.'); return; }
                var panel = button.closest('.doctor-video-panel'), stage = panel.find('.doctor-video-stage')[0];
                panel.find('.doctor-video-status-badge').removeClass(function (i, c) { return (c.match(/(^|\s)doctor-video-status-\S+/g) || []).join(' '); }).addClass('doctor-video-status-active');
                panel.find('.doctor-video-status').text('Active');
                panel.find('.doctor-video-stage').addClass('is-connected');
                panel.find('.js-video-join, .js-video-mute, .js-video-camera, .js-video-end').prop('disabled', false);
                if (stage) {
                    stage.addEventListener('rootins:video:reconnecting', function () { panel.find('.doctor-video-connection').text('Connection interrupted. Reconnecting automatically…'); });
                    stage.addEventListener('rootins:video:reconnected', function () { panel.find('.doctor-video-connection').text('You are connected to the secure consultation room.'); });
                }
                window.RootinsTwilioRoom.connect(response.data.token, panel[0]).catch(function (error) { showError(connectionError(error)); });
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Video connection timed out.' : 'Video service is not available yet.'); },
            complete: function () { button.prop('disabled', false); }
        });
    });

    $(document).on('click.rootinsDoctorVideoEnd', '.js-video-end', function () {
        var button = $(this), url = button.data('end-url');
        if (!url || !window.confirm('End this video consultation?')) return;
        button.prop('disabled', true);
        ajaxRequest({ url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (!response.status) { showError(response.msg || 'Video consultation could not be ended.'); return; }
                var panel = button.closest('.doctor-video-panel');
                panel.find('.doctor-video-status-badge').removeClass(function (i, c) { return (c.match(/(^|\s)doctor-video-status-\S+/g) || []).join(' '); }).addClass('doctor-video-status-ended');
                panel.find('.doctor-video-status').text('Ended');
                panel.find('.doctor-video-connection').text('This consultation has ended.');
                panel.find('.js-video-end, .js-video-join, .js-video-mute, .js-video-camera').prop('disabled', true);
                window.RootinsTwilioRoom.disconnect();
                button.data('ended', true);
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Ending consultation timed out.' : 'Video consultation could not be ended.'); },
            complete: function () { if (!button.data('ended')) button.prop('disabled', false); }
        });
    });

    $(document).on('click.rootinsDoctorVideoDevice', '.js-video-mute, .js-video-camera', function () {
        var button = $(this), enabled = button.attr('aria-pressed') !== 'true';
        button.attr('aria-pressed', enabled ? 'true' : 'false').toggleClass('active', enabled);
        if (button.hasClass('js-video-mute')) enabled ? window.RootinsTwilioRoom.mute() : window.RootinsTwilioRoom.unmute();
        if (button.hasClass('js-video-camera')) enabled ? window.RootinsTwilioRoom.cameraOff() : window.RootinsTwilioRoom.cameraOn();
    });
}(window, window.jQuery));
