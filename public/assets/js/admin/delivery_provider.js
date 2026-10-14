(function ($) {
    'use strict';
    const form = $('#deliveryProviderForm');
    const modal = $('#deliveryProviderModal');
    form.attr('data-no-global-loader', 'true');
    $('.js-provider-action').attr('data-no-global-loader', 'true');
    if ($('#providerTemplate').length && !$('#providerTemplateHelp').length) $('#providerTemplate').after('<small id="providerTemplateHelp" class="form-text text-muted">Use a valid HTTP/HTTPS tracking page. Tracking placeholders are optional.</small>');

    function clearProviderErrors() { clearFormErrors(form[0]); form.find('.validation-error').empty(); }
    function resetForm() { if (!form.length) return; form[0].reset(); $('#providerId').val(''); $('#providerSort').val(0); clearProviderErrors(); }

    function saveProvider(event) {
        event.preventDefault();
        const submit = form.find('[type="submit"]');
        if (!submit.length || submit.prop('disabled')) return;
        const snapshot = {disabled: submit.prop('disabled'), html: submit.html(), className: submit.attr('class') || '', ariaBusy: submit.attr('aria-busy')};
        clearProviderErrors(); form.data('rootins-submit-snapshot', [{element: submit[0], disabled: snapshot.disabled, ariaBusy: snapshot.ariaBusy, html: snapshot.html, className: snapshot.className}]); submit.prop('disabled', true).attr('aria-busy', 'true').addClass('loading is-loading');
        ajaxRequest({url: form.attr('action'), method: 'POST', data: form.serialize(), dataType: 'json', form: form[0]})
            .done(function (response) { if (response && response.status) { modal.modal('hide'); window.location.reload(); return; } bindFormError(form[0], (response && response.errors) || {}); showError((response && response.msg) || 'Provider could not be saved.'); })
            .fail(function (xhr, status) { handleFormAjaxError(form[0], xhr, 'Provider could not be saved.', status); if (xhr.responseJSON && xhr.responseJSON.errors) bindFormError(form[0], xhr.responseJSON.errors); });
    }

    function providerAction(event) {
        event.preventDefault(); const actionForm = $(this); const submit = actionForm.find('[type="submit"]');
        if (!submit.length || submit.prop('disabled')) return;
        const snapshot = {disabled: submit.prop('disabled'), html: submit.html(), className: submit.attr('class') || '', ariaBusy: submit.attr('aria-busy')};
        actionForm.data('rootins-submit-snapshot', [{element: submit[0], disabled: snapshot.disabled, ariaBusy: snapshot.ariaBusy, html: snapshot.html, className: snapshot.className}]); submit.prop('disabled', true).attr('aria-busy', 'true').addClass('loading is-loading');
        ajaxRequest({url: actionForm.attr('action'), method: 'POST', data: actionForm.serialize(), dataType: 'json', form: actionForm[0]})
            .done(function (response) { if (response && response.status) window.location.reload(); else showError((response && response.msg) || 'Provider action failed.'); })
            .fail(function (xhr, status) { handleFormAjaxError(actionForm[0], xhr, 'Provider action failed.', status); });
    }

    $(document).on('click', '[data-provider-reset]', resetForm);
    $(document).on('click', '.js-provider-edit', function () { const row = $(this).data('provider') || {}; resetForm(); $('#providerId').val(row.id || ''); $('#providerName').val(row.provider_name || ''); $('#providerCode').val(row.provider_code || ''); $('#providerTemplate').val(row.tracking_url_template || ''); $('#providerSupport').val(row.support_contact || ''); $('#providerSort').val(row.sort_order || 0); $('#providerStatus').val(String(row.status == null ? 1 : row.status)); modal.modal('show'); });
    $(document).on('submit', '#deliveryProviderForm', saveProvider);
    $(document).on('submit', '.js-provider-action', providerAction);
    modal.on('hidden.bs.modal', function () { clearProviderErrors(); form.removeData('rootins-native-submitting'); });
}(jQuery));
