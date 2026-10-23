(function ($) {
    'use strict';
    function csrfHeaders() { var token = $('input[name="csrf_rtn"]').first().val() || ''; return { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, Accept: 'application/json' }; }
    function handle(form) {
        var $form = $(form), $button = $form.find('[type="submit"]'), label = $button.html();
        if ($button.prop('disabled')) return;
        $button.prop('disabled', true).attr('aria-busy', 'true');
        $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), dataType: 'json', headers: csrfHeaders() }).done(function (response) {
            if (response.status) { if (typeof showSuccess === 'function') showSuccess(response.msg || 'Updated.'); window.setTimeout(function () { window.location.reload(); }, 250); return; }
            if (typeof showError === 'function') showError(response.msg || 'The operation could not be completed.');
        }).fail(function (xhr) { if (typeof showError === 'function') showError((xhr.responseJSON && xhr.responseJSON.msg) || 'The operation could not be completed safely.'); }).always(function () { $button.prop('disabled', false).removeAttr('aria-busy').html(label); });
    }
    $(document).on('submit', '.shopify-order-action-form, .shopify-prescription-review-form', function (event) { event.preventDefault(); handle(this); });
}(window.jQuery));
