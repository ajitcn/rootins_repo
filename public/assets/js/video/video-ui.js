(function (window, document) {
    'use strict';

    function stageFor(button) {
        var scope = button.closest('.doctor-video-panel, #patient-video-page');
        return scope ? scope.querySelector('.doctor-video-stage, .patient-video-stage') : null;
    }

    function layoutFor(stage) {
        if (!stage) return null;
        return stage.querySelector('[data-video-layout]') || stage;
    }

    function setButtonLabel(button, label) {
        var span = button.querySelector('span');
        if (span) {
            span.textContent = label;
            return;
        }
        Array.prototype.slice.call(button.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) node.remove();
        });
        button.appendChild(document.createTextNode(label));
    }

    function setLayout(button) {
        var stage = stageFor(button);
        var layout = layoutFor(stage);
        if (!stage || !layout) return;
        var localMain = !layout.classList.contains('is-local-main');
        layout.classList.toggle('is-local-main', localMain);
        stage.classList.toggle('is-local-main', localMain);
        layout.setAttribute('data-video-layout', localMain ? 'local-main' : 'remote-main');
        button.setAttribute('aria-pressed', localMain ? 'true' : 'false');
        button.setAttribute('data-layout-state', localMain ? 'local-main' : 'remote-main');
        setButtonLabel(button, localMain ? 'Show doctor' : 'Show me');
    }

    function setFullscreenLabel(button, active) {
        var label = active ? 'Restore view' : 'Fullscreen';
        var icon = button.querySelector('i');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        if (icon) {
            icon.classList.remove('bi-arrows-angle-contract', 'bi-arrows-fullscreen');
            icon.classList.add(active ? 'bi-arrows-angle-contract' : 'bi-arrows-fullscreen');
        }
        setButtonLabel(button, label);
    }

    document.addEventListener('click', function (event) {
        var layoutButton = event.target.closest ? event.target.closest('.js-video-layout-toggle') : null;
        if (layoutButton) {
            event.preventDefault();
            event.stopPropagation();
            setLayout(layoutButton);
            return;
        }

        var fullscreenButton = event.target.closest ? event.target.closest('.js-video-fullscreen') : null;
        if (!fullscreenButton) return;
        var scope = fullscreenButton.closest('#patient-video-page');
        var stage = scope ? scope.querySelector('#patient-video-stage') : null;
        if (!stage) return;
        event.preventDefault();
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function () {});
        } else if (stage.requestFullscreen) {
            stage.requestFullscreen().catch(function () {});
        }
    }, true);

    document.addEventListener('fullscreenchange', function () {
        var active = !!document.fullscreenElement;
        document.querySelectorAll('.js-video-fullscreen').forEach(function (button) { setFullscreenLabel(button, active); });
    });
}(window, document));
