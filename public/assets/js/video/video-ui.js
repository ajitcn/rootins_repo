(function (window, $) {
    'use strict';
    if (!$) return;

    $(document).on('click.rootinsVideoLayout', '.js-video-layout-toggle', function () {
        var stage = $(this).closest('.doctor-video-panel, #patient-video-page').find('.doctor-video-stage, .patient-video-stage').first();
        var localMain = stage.toggleClass('is-local-main').hasClass('is-local-main');
        stage.find('[data-video-layout]').attr('data-video-layout', localMain ? 'local-main' : 'remote-main');
        var label = localMain ? 'Show doctor' : 'Show me';
        var labelNode = $(this).find('span').last();
        if (labelNode.length) labelNode.text(label);
        else $(this).contents().filter(function () { return this.nodeType === 3; }).remove().end().append(document.createTextNode(label));
        $(this).attr('aria-pressed', localMain ? 'true' : 'false');
    });

    $(document).on('click.rootinsVideoFullscreen', '.js-video-fullscreen', function () {
        var stage = $(this).closest('#patient-video-page').find('#patient-video-stage')[0];
        if (!stage) return;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function () {});
        } else if (stage.requestFullscreen) {
            stage.requestFullscreen().catch(function () {});
        }
    });
}(window, window.jQuery));
