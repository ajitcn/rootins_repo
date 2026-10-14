(function (window, document) {
    'use strict';

    if (window.rootinsCrmFollowupModalLifecycleBound) return;
    window.rootinsCrmFollowupModalLifecycleBound = true;

    function cleanup(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open', 'rootins-modal-lock');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        }
        modal.querySelectorAll('[data-followup-panel]').forEach(function (panel) {
            panel.classList.add('d-none');
        });
        modal.querySelectorAll('[data-detail]').forEach(function (field) {
            field.textContent = field.dataset.detail === 'remarks' ? 'No remarks added' : '—';
        });
        var activity = modal.querySelector('[data-followup-activity]');
        if (activity) {
            activity.innerHTML = '<div class="crm-empty"><span>Select a follow-up to view activity.</span></div>';
        }
    }

    function bindBackdropClicks(modal) {
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            if (backdrop.dataset.crmCloseBound === '1') return;
            backdrop.dataset.crmCloseBound = '1';
            backdrop.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
                cleanup(modal || document.querySelector('.crm-modal.show'));
            }, true);
        });
    }

    function openFromTrigger(event) {
        var trigger = event.target.closest('.js-crm-followup-details');
        var modal = document.querySelector('#crmFollowUpDetailsModal');
        if (!trigger || !modal) return;
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open', 'rootins-modal-lock');
        document.body.style.overflow = 'hidden';
        window.setTimeout(function () {
            if (!document.querySelector('.modal-backdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show crm-modal-backdrop';
                document.body.appendChild(backdrop);
            }
            bindBackdropClicks(modal);
        }, 0);
    }

    function closeFromEvent(event) {
        openFromTrigger(event);
        var dismiss = event.target.closest('[data-dismiss="modal"]');
        var modal = event.target.closest('.crm-modal') || (event.target.closest('.modal-backdrop') && document.querySelector('.crm-modal.show'));
        var backdropClick = event.target.closest('.modal-backdrop') || (modal && event.target === modal && modal.dataset.backdrop !== 'static');
        if (!modal || (!dismiss && !backdropClick)) return;
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
        cleanup(modal);
    }

    document.addEventListener('click', closeFromEvent, true);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var modal = document.querySelector('.crm-modal.show');
        if (!modal || modal.dataset.keyboard === 'false') return;
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
        cleanup(modal);
    }, true);
}(window, document));
