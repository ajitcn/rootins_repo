(function ($) {
    'use strict';
    if (window.rootinsPaymentStatusInlineBound) return;

    var modalNode = document.getElementById('paymentStatusModal');

    function nativeCloseModal() {
        if (!modalNode) return;
        modalNode.classList.remove('show');
        modalNode.style.display = 'none';
        modalNode.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.payment-status-modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        document.body.classList.remove('modal-open', 'payment-status-modal-open');
        document.body.style.removeProperty('padding-right');
    }

    function nativeOpenModal() {
        if (!modalNode) return;
        document.querySelectorAll('.payment-status-modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show payment-status-modal-backdrop';
        document.body.appendChild(backdrop);
        if (modalNode.parentNode !== document.body) document.body.appendChild(modalNode);
        modalNode.style.display = 'block';
        modalNode.classList.add('show');
        modalNode.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open', 'payment-status-modal-open');
    }

    if (!$) {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-payment-status-reset="1"]');
            var closer = event.target.closest('#paymentStatusModal [data-dismiss="modal"]');
            if (trigger) { event.preventDefault(); nativeOpenModal(); }
            if (closer) { event.preventDefault(); nativeCloseModal(); }
        });
        return;
    }

    var modal = $('#paymentStatusModal');

    function cleanupModalArtifacts() {
        window.setTimeout(function () {
            if (!modal.hasClass('show')) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open payment-status-modal-open').css('padding-right', '');
                return;
            }
            var backdrops = $('.modal-backdrop');
            if (backdrops.length > 1) backdrops.slice(0, -1).remove();
            $('body').addClass('payment-status-modal-open');
        }, 0);
    }

    if (modal.length && modal.parent()[0] !== document.body) modal.detach().appendTo('body');
    modal.on('show.bs.modal shown.bs.modal hide.bs.modal hidden.bs.modal', cleanupModalArtifacts);

    function clearErrors() {
        $('#paymentStatusForm .validation-error').empty();
        $('#paymentStatusForm .is-invalid').removeClass('is-invalid');
    }

    function resetForm() {
        $('#paymentStatusForm')[0].reset();
        $('#paymentStatusId').val('');
        $('#paymentStatusSort').val('0');
        $('#paymentStatusModalTitle').text('Add payment status');
        clearErrors();
    }

    $(document).off('click.paymentStatusReset', '[data-payment-status-reset="1"]').on('click.paymentStatusReset', '[data-payment-status-reset="1"]', function (event) {
        event.preventDefault();
        resetForm();
        if ($.fn.modal) {
            modal.modal('show');
        } else {
            nativeOpenModal();
        }
    });
    $(document).off('click.paymentStatusEdit', '.js-payment-status-edit').on('click.paymentStatusEdit', '.js-payment-status-edit', function () {
        var row = $(this).data('payment-status') || {};
        resetForm();
        $('#paymentStatusId').val(row.id || '');
        $('#paymentStatusName').val(row.status_name || '');
        $('#paymentStatusCode').val(row.status_code || '');
        $('#paymentStatusDescription').val(row.description || '');
        $('#paymentStatusSort').val(row.sort_order || 0);
        $('#paymentStatusState').val(String(row.status || 0));
        $('#paymentStatusModalTitle').text('Edit payment status');
        if ($.fn.modal) modal.modal('show'); else nativeOpenModal();
    });
    $('#paymentStatusForm').off('submit.paymentStatus').on('submit.paymentStatus', function (event) {
        event.preventDefault();
        clearErrors();
        var form = this;
        var submit = $(form).find('button[type="submit"]');
        if (submit.prop('disabled')) return;
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: form.action,
            type: 'POST',
            data: $(form).serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg || 'Payment status saved.');
                    resetForm();
                    if ($.fn.modal) modal.modal('hide'); else nativeCloseModal();
                    reload_page(500);
                }
                else { showError(response.msg); if (response.errors) bindFormError('#paymentStatusForm', response.errors); }
            },
            complete: function () { submit.prop('disabled', false).removeAttr('aria-busy'); }
        });
    });
    $(document).on('submit', '.js-payment-status-action', function (event) {
        event.preventDefault();
        var form = this;
        if (form.action.indexOf('/archive/') !== -1 && !window.confirm('Archive this payment status? Existing records will keep their code.')) return;
        var button = $(form).find('button[type="submit"]');
        if (button.prop('disabled')) return;
        button.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: form.action,
            type: 'POST',
            data: $(form).serialize(),
            success: function (response) { if (response.status) reload_page(500); else showError(response.msg); },
            complete: function () { button.prop('disabled', false).removeAttr('aria-busy'); }
        });
    });
}(window.jQuery));
