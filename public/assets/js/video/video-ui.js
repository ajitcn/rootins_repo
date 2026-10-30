(function (window, $) {
    'use strict';
    if (!$) return;

    $(document).on('click.rootinsVideoLayout', '.js-video-layout-toggle', function () {
        var stage = $(this).closest('.doctor-video-panel, #patient-video-page').find('.doctor-video-stage, .patient-video-stage').first();
        var layout = stage.find('[data-video-layout]').first();
        if (!layout.length) layout = stage;
        var localMain = !layout.hasClass('is-local-main');
        layout.toggleClass('is-local-main', localMain).attr('data-video-layout', localMain ? 'local-main' : 'remote-main');
        stage.toggleClass('is-local-main', localMain);
        var label = localMain ? 'Show doctor' : 'Show me';
        var labelNode = $(this).find('span').last();
        if (labelNode.length) {
            labelNode.text(label);
        } else {
            $(this).contents().filter(function () { return this.nodeType === 3; }).remove();
            $(this).append(document.createTextNode(label));
        }
        $(this).attr('aria-pressed', localMain ? 'true' : 'false');
    });

    function setFullscreenLabel(button, active) {
        var label = active ? 'Restore view' : 'Fullscreen';
        var icon = active ? 'bi-arrows-angle-contract' : 'bi-arrows-fullscreen';
        button.attr('aria-label', label).attr('title', label);
        button.find('i').removeClass('bi-arrows-angle-contract bi-arrows-fullscreen').addClass(icon);
        var text = button.find('span').last();
        if (text.length) {
            text.text(label);
        } else {
            button.contents().filter(function () { return this.nodeType === 3; }).remove();
            button.append(document.createTextNode(label));
        }
    }

    $(document).on('click.rootinsVideoFullscreen', '.js-video-fullscreen', function () {
        var button = $(this), stage = button.closest('#patient-video-page').find('#patient-video-stage')[0];
        if (!stage) return;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function () {});
        } else if (stage.requestFullscreen) {
            stage.requestFullscreen().catch(function () {});
        }
    });

    document.addEventListener('fullscreenchange', function () {
        var active = !!document.fullscreenElement;
        $('.js-video-fullscreen').each(function () { setFullscreenLabel($(this), active); });
    });
}(window, window.jQuery));
