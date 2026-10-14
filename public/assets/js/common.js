function showSuccess(message)
{
    Swal.fire({icon: 'success', title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3200, timerProgressBar: true});
}

function showError(message)
{
    Swal.fire({icon: 'error', title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 4200, timerProgressBar: true});
}

function resetForm(form)
{
    $(form)[0].reset();
    $(form).find('.btSelect').selectpicker('refresh');
}

function reload_page(time) {
  setTimeout(function(){
    window.location.reload();
  }, time);
}


function formFieldsByName(form, name) {
    let fields = $(form).find(':input').filter(function () {
        return $(this).attr('name') === name;
    });

    fields.filter('[data-datepicker-hidden-for]').each(function () {
        const displayId = $(this).attr('data-datepicker-hidden-for');
        if (displayId) {
            fields = fields.add($(form).find('#' + displayId));
        }
    });

    // The backend reports both country-code modes under country_code. When
    // the manual input is disabled, surface that error on the active select.
    if (name === 'country_code' && !fields.filter(':enabled').length) {
        fields = fields.add($(form).find(':input[name="telephone_country_code_id"]:enabled'));
    }

    return fields;
}

function clearFieldError(form, name) {
    const fields = formFieldsByName(form, name);
    const error = $(form).find('.validation-error').filter(function () {
        return $(this).attr('id') === name + 'Err' || $(this).data('error-for') === name;
    });
    const errorId = error.attr('id');

    error.empty();
    fields.removeClass('is-invalid').removeAttr('aria-invalid');
    if (errorId) {
        fields.each(function () {
            const describedBy = String($(this).attr('aria-describedby') || '')
                .split(/\s+/)
                .filter(function (id) { return id && id !== errorId; });
            if (describedBy.length) {
                $(this).attr('aria-describedby', describedBy.join(' '));
            } else {
                $(this).removeAttr('aria-describedby');
            }
        });
    }
    fields.closest('.bootstrap-select').removeClass('is-invalid');
}

function validationFields(form) {
    return $(form).find('input, select, textarea').filter(function () {
        const type = String($(this).attr('type') || '').toLowerCase();
        return type !== 'hidden' && type !== 'submit' && type !== 'button' && type !== 'reset';
    });
}

function ensureFieldErrorContainer(form, name, field) {
    const exactId = name + 'Err';
    let error = $(form).find('.validation-error').filter(function () {
        return $(this).attr('id') === exactId || $(this).data('error-for') === name;
    }).first();

    const control = $(field);
    const bootstrapWrapper = control.closest('.bootstrap-select');
    const inputGroup = control.closest('.input-group');
    const anchor = bootstrapWrapper.length ? bootstrapWrapper : inputGroup.length ? inputGroup : control;

    if (!error.length) {
        error = anchor.next('.validation-error').first();
    }

    if (!error.length) {
        error = $('<div>', {
            class: 'validation-error validation-error-generated',
            id: exactId
        });
        anchor.after(error);
    } else if (!error.attr('id')) {
        error.attr('id', exactId);
    }

    return error;
}

function ensureValidationContainers(form) {
    validationFields(form).each(function () {
        const name = $(this).attr('name');
        if (name) {
            ensureFieldErrorContainer(form, name, this);
        }
    });
}

function initializeValidationContainers(root) {
    $(root).find('form').each(function () {
        ensureValidationContainers(this);
    });
}

function bindFormError(target, msg) {
    const form = $(target);
    let firstInvalid = $();

    if (typeof msg !== 'object' || msg === null || Array.isArray(msg)) {
        return;
    }

    ensureValidationContainers(form);

    $.each(msg, function (key, message) {
        const name = String(key);
        const fields = formFieldsByName(form, name);
        const error = fields.length ? ensureFieldErrorContainer(form, name, fields.first()) : $();
        const errorId = error.attr('id');

        if (error.length) {
            error.text(message);
        }
        if (!fields.length) {
            return;
        }

        fields.addClass('is-invalid').attr('aria-invalid', 'true');
        if (errorId) {
            fields.each(function () {
                const describedBy = String($(this).attr('aria-describedby') || '')
                    .split(/\s+/)
                    .filter(Boolean);
                if (describedBy.indexOf(errorId) === -1) {
                    describedBy.push(errorId);
                }
                $(this).attr('aria-describedby', describedBy.join(' '));
            });
        }
        fields.closest('.bootstrap-select').addClass('is-invalid');

        if (!firstInvalid.length) {
            firstInvalid = fields.filter(':enabled:visible').first();
            if (!firstInvalid.length) {
                firstInvalid = fields.filter(':enabled').first();
            }
            if (!firstInvalid.length) {
                firstInvalid = fields.first();
            }
        }
    });

    if (firstInvalid.length) {
        const focusTarget = firstInvalid.closest('.bootstrap-select').find('button').first().length
            ? firstInvalid.closest('.bootstrap-select').find('button').first()
            : firstInvalid;
        const modalBody = focusTarget.closest('.modal-body');
        if (modalBody.length) {
            const top = focusTarget.offset().top - modalBody.offset().top + modalBody.scrollTop() - 24;
            modalBody.stop(true).animate({scrollTop: Math.max(0, top)}, 180);
        } else {
            focusTarget[0].scrollIntoView({behavior: 'smooth', block: 'center'});
        }
        window.setTimeout(function () { focusTarget.trigger('focus'); }, 200);
    }
}

function handleFormAjaxError(target, xhr, fallbackMessage, requestStatus) {
    const response = xhr.responseJSON || {};
    let message = response.msg || fallbackMessage || 'Something went wrong.';

    if (xhr.status === 0 && requestStatus === 'timeout') {
        message = 'The request timed out. Please try again.';
    } else if (xhr.status === 0 && requestStatus === 'abort') {
        message = 'The request was cancelled.';
    }

    switch (xhr.status) {
        case 0:
            if (requestStatus !== 'timeout' && requestStatus !== 'abort') {
                message = 'Unable to connect to the server.';
            }
            break;
        case 401:
            message = 'Your session has expired. Please login again.';
            window.setTimeout(function () { window.location.href = BASE_URL + 'system/login'; }, 300);
            break;
        case 403:
            message = 'Access denied.';
            break;
        case 419:
            message = 'Your security token has expired. Refresh the page and try again.';
            break;
        case 422:
            message = response.msg || 'Please correct the highlighted fields.';
            break;
        case 409:
            message = response.msg || 'The request conflicts with the current record.';
            break;
        case 500:
            message = response.msg || fallbackMessage || 'Something went wrong.';
            break;
    }

    if (response.errors) {
        bindFormError(target, response.errors);
    }
    showError(message);
}

function clearFormErrors(target) {
    const form = target ? $(target) : $(document);
    if (target) {
        ensureValidationContainers(form);
    }
    form.find('.validation-error').empty();
    form.find(':input.is-invalid').each(function () {
        clearFieldError(form, $(this).attr('name') || $(this).attr('data-validation-name'));
    });
}

$(document)
    .off('input.rootinsFieldErrors change.rootinsFieldErrors', 'form :input')
    .on('input.rootinsFieldErrors change.rootinsFieldErrors', 'form :input', function () {
        const name = $(this).attr('name') || $(this).attr('data-validation-name');
        if (name) {
            clearFieldError($(this).closest('form'), name);
        }
    });

$(function () {
    initializeValidationContainers(document);
});

$(document).on('shown.bs.modal.rootinsValidation', '.modal', function () {
    initializeValidationContainers(this);
});

/* Shared Doctor modal behavior for same-page workflows. */
window.RootinsModal = (function () {
    const focusable = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function focusInitial(modal) {
        const dialog = $(modal);
        if (!dialog.length || dialog.find(document.activeElement).length) {
            return;
        }
        const target = dialog.find('[data-modal-autofocus], [autofocus], ' + focusable)
            .filter(':visible')
            .first();
        if (target.length) {
            window.setTimeout(function () {
                target.trigger('focus');
            }, 0);
        }
    }

    function trapFocus(event) {
        if (event.key !== 'Tab') {
            return;
        }
        const modal = $(this);
        const controls = modal.find(focusable).filter(':visible');
        if (!controls.length) {
            event.preventDefault();
            return;
        }
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            $(last).trigger('focus');
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            $(first).trigger('focus');
        }
    }

    $(document)
        .off('show.bs.modal.rootinsModal', '.modal')
        .on('show.bs.modal.rootinsModal', '.modal', function (event) {
            const opener = event.relatedTarget && $(event.relatedTarget).length
                ? $(event.relatedTarget)
                : $(document.activeElement);
            $(this).data('rootins-modal-opener', opener && opener.length ? opener : null);
            $('body').addClass('rootins-modal-lock');
        })
        .off('shown.bs.modal.rootinsModal', '.modal')
        .on('shown.bs.modal.rootinsModal', '.modal', function () {
            focusInitial(this);
        })
        .off('keydown.rootinsModal', '.modal')
        .on('keydown.rootinsModal', '.modal', trapFocus)
        .off('hidden.bs.modal.rootinsModal', '.modal')
        .on('hidden.bs.modal.rootinsModal', '.modal', function () {
            const opener = $(this).data('rootins-modal-opener');
            if (!$('.modal.show').length) {
                $('body').removeClass('rootins-modal-lock');
            }
            if (opener && opener.length && opener.is(':visible') && !opener.is(':disabled')) {
                window.setTimeout(function () {
                    opener.trigger('focus');
                }, 0);
            }
        });

    return {focus: focusInitial};
}());



/**
 *
 * preloader on html
 *
 */

window.RootinsPreloader = (function () {
    let activeRequests = 0;
    let fallbackTimer = null;

    function render(visible) {
        $('.preloader').toggle(visible);
        $('body').toggleClass('rootins-loading', visible);
    }

    function armFallback() {
        window.clearTimeout(fallbackTimer);
        fallbackTimer = window.setTimeout(function () {
            activeRequests = 0;
            render(false);
        }, 30000);
    }

    return {
        show: function () {
            activeRequests += 1;
            render(true);
            armFallback();
        },
        hide: function () {
            activeRequests = Math.max(0, activeRequests - 1);
            if (activeRequests === 0) {
                window.clearTimeout(fallbackTimer);
                fallbackTimer = null;
                render(false);
            }
        },
        reset: function () {
            activeRequests = 0;
            window.clearTimeout(fallbackTimer);
            fallbackTimer = null;
            render(false);
        }
    };
}());

/*
 * Capture form controls before module submit handlers disable or relabel them.
 * ajaxRequest() consumes this state and restores it from one completion path,
 * including validation, permission, timeout, abort, and parse failures.
 */
const rootinsPendingFormSubmits = [];

document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form || form.nodeName !== 'FORM') {
        return;
    }

    const buttons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')).map(function (button) {
        return {
            element: button,
            disabled: button.disabled,
            ariaBusy: button.getAttribute('aria-busy'),
            html: button.innerHTML,
            className: button.className
        };
    });

    rootinsPendingFormSubmits.push({form: form, buttons: buttons, claimed: false, at: Date.now()});
    window.setTimeout(function () {
        const index = rootinsPendingFormSubmits.findIndex(function (entry) {
            return entry.form === form && entry.at + 5000 < Date.now();
        });
        if (index !== -1) {
            rootinsPendingFormSubmits.splice(index, 1);
        }
    }, 5100);
}, true);

function rootinsRequestForm(options) {
    if (options && options.form) {
        const form = $(options.form).first();
        return form.length ? {
            form: form[0],
            buttons: form.data('rootins-submit-snapshot') || Array.from(form.find(':submit')).map(function (button) {
                return {
                    element: button,
                    disabled: button.disabled,
                    ariaBusy: button.getAttribute('aria-busy'),
                    html: button.innerHTML,
                    className: button.className
                };
            })
        } : null;
    }

    for (let index = rootinsPendingFormSubmits.length - 1; index >= 0; index -= 1) {
        const entry = rootinsPendingFormSubmits[index];
        if (!entry.claimed && entry.at + 1500 >= Date.now()) {
            entry.claimed = true;
            return entry;
        }
    }

    return null;
}

function rootinsRestoreFormState(state) {
    if (!state || !state.form) {
        return;
    }

    state.buttons.forEach(function (snapshot) {
        const button = $(snapshot.element);
        if (!button.length) {
            return;
        }
        button.prop('disabled', snapshot.disabled)
            .html(snapshot.html)
            .attr('class', snapshot.className);
        if (snapshot.ariaBusy === null) {
            button.removeAttr('aria-busy');
        } else {
            button.attr('aria-busy', snapshot.ariaBusy);
        }
    });

    $(state.form).removeData('rootins-native-submitting');
    $(state.form).removeData('rootins-submit-snapshot');
}

function rootinsCleanupAjaxRequest(formState, loaderVisible) {
    try {
        rootinsRestoreFormState(formState);
    } finally {
        if (loaderVisible) {
            hide_loader();
        }
        if (formState && formState.form) {
            $(formState.form).find(':submit').removeClass('loading is-loading').removeAttr('aria-busy');
        }
    }
}

function show_loader() {
    window.RootinsPreloader.show();
}

function hide_loader() {
    window.RootinsPreloader.hide();
}

$(document).ready(function () {
    window.RootinsPreloader.reset();
});

$(window).on('pageshow.rootinsPreloader load.rootinsPreloader', function () {
    window.RootinsPreloader.reset();
});

$(document).on('submit.rootinsPreloader', 'form:not([data-no-global-loader])', function (event) {
    if (event.isDefaultPrevented()) {
        return;
    }

    const form = $(this);
    if (form.data('rootins-native-submitting')) {
        event.preventDefault();
        return;
    }

    form.data('rootins-native-submitting', true);
    form.find(':submit').prop('disabled', true).attr('aria-busy', 'true');
    show_loader();
});

/**
 *-------------------------------------------------------
 * Common AJAX Request
 *-------------------------------------------------------
 *
 * Example:
 *
 * ajaxRequest({
 *     url: BASE_URL + 'admin/clinic/save',
 *     method: 'POST',
 *     data: $('#clinicForm').serialize(),
 *
 *     success: function(response){
 *         showSuccess(response.msg);
 *     }
 * });
 *
 */

function ajaxRequest(options)
{
    options = options || {};
    const userBeforeSend = options.beforeSend;
    const userComplete = options.complete;
    const requestForm = rootinsRequestForm(options);
    let loaderVisible = false;
    let formState = null;
    if (requestForm) {
        formState = {
            form: requestForm.form,
            buttons: requestForm.buttons
        };
    }
    const defaults = {
        method: 'POST',
        dataType: 'json',
        cache: false,
        timeout: 30000,
        beforeSend: function (xhr, settings) {
            show_loader();
            loaderVisible = true;
            if (formState && /^(POST|PUT|PATCH|DELETE)$/i.test(settings.type || settings.method || '')) {
                clearFormErrors(formState.form);
                $(formState.form).find(':submit').prop('disabled', true).attr('aria-busy', 'true');
            }
            if (typeof userBeforeSend === 'function') {
                try {
                    const result = userBeforeSend.call(this, xhr, settings);
                    if (result === false) {
                        loaderVisible = false;
                        rootinsCleanupAjaxRequest(formState, true);
                    }
                    return result;
                } catch (error) {
                    if (loaderVisible) {
                        rootinsCleanupAjaxRequest(formState, true);
                        loaderVisible = false;
                    }
                    throw error;
                }
            }
            return undefined;
        },
        error: function (xhr, status, error) {
            handleFormAjaxError(formState ? formState.form : null, xhr, error || 'Something went wrong.', status);
        },
        complete: function (xhr, status) {
            try {
                if (typeof userComplete === 'function') {
                    userComplete.call(this, xhr, status);
                }
            } finally {
                rootinsCleanupAjaxRequest(formState, loaderVisible);
                loaderVisible = false;
            }
        }
    };

    const request = $.extend(true, {}, defaults, options);
    request.beforeSend = defaults.beforeSend;
    request.complete = defaults.complete;
    return $.ajax(request);
}

function rootinsFetch(url, options) {
    options = options || {};
    const requestForm = rootinsRequestForm(options);
    const formState = requestForm ? {form: requestForm.form, buttons: requestForm.buttons} : null;
    const requestOptions = Object.assign({}, options);
    const timeout = Number(requestOptions.timeout || 30000);
    const controller = new AbortController();
    const callerSignal = requestOptions.signal;
    let timer = null;

    delete requestOptions.form;
    delete requestOptions.timeout;
    requestOptions.signal = controller.signal;
    if (callerSignal) {
        if (callerSignal.aborted) {
            controller.abort();
        } else {
            callerSignal.addEventListener('abort', function () { controller.abort(); }, {once: true});
        }
    }

    show_loader();
    timer = window.setTimeout(function () { controller.abort(); }, timeout);

    return window.fetch(url, requestOptions).finally(function () {
        window.clearTimeout(timer);
        rootinsRestoreFormState(formState);
        hide_loader();
    });
}





/*================================
=            Location            =
================================*/
function bindLocationManualToggles(root = document)
{
    function applyLocationManualMode(toggle, focus = false) {
        const select = $(toggle.data('select'));
        const input = $(toggle.data('input'));
        const manual = toggle.is(':checked');
        select.prop('disabled', manual).val('');
        setLocationSelectState(select, manual);
        const selectDisplay = select.closest('.bootstrap-select').length
            ? select.closest('.bootstrap-select')
            : select;

        selectDisplay.toggleClass('d-none', manual);
        input.toggleClass('d-none', ! manual)
            .prop('disabled', ! manual)
            .prop('required', manual);
        if (! manual) {
            input.val('');
        }
        if (manual && focus && input.length) {
            window.setTimeout(function () {
                input.trigger('focus');
            }, 0);
        }
    }

    $(document)
        .off('change.location', '.location-manual-toggle')
        .on('change.location', '.location-manual-toggle', function () {
            applyLocationManualMode($(this), true);
        });

    $(root).find('.location-manual-toggle').each(function () {
        applyLocationManualMode($(this));
    });
}

function normalizeCountryCode(value)
{
    return String(value || '').trim().replace(/^\+/, '');
}

function isValidCountryCode(value)
{
    return /^[1-9][0-9]{0,3}$/.test(normalizeCountryCode(value));
}

function applyCountryCodeMode(toggle)
{
    const select = $(toggle.data('select'));
    const input = $(toggle.data('input'));
    const nameInput = $(toggle.data('nameInput') || toggle.data('name-input') || '');
    const manualFields = input.closest('.country-code-manual-fields');
    const selectDisplay = select.closest('.bootstrap-select').length
        ? select.closest('.bootstrap-select')
        : select;
    const manual = toggle.is(':checked');

    select.prop('disabled', manual);
    if (manual) select.val('');
    selectDisplay.toggleClass('d-none', manual);
    input.attr('pattern', '\\+?[0-9][0-9]{0,3}')
        .attr('maxlength', '5')
        .attr('title', 'Enter 1 to 4 digits, optionally prefixed with +.')
        .toggleClass('d-none', ! manual)
        .prop('disabled', ! manual)
        .prop('required', manual);
    nameInput
        .toggleClass('d-none', ! manual)
        .prop('disabled', ! manual)
        .prop('required', manual);
    manualFields.toggleClass('d-none', ! manual);
    if (! manual) input.val('');
    if (! manual) nameInput.val('');
    if (selectDisplay.hasClass('bootstrap-select')) {
        select.selectpicker('refresh');
    }
    if (manual && input.length) {
        window.setTimeout(function () { input.trigger('focus'); }, 0);
    }
}

function bindCountryCodeToggles(root = document)
{
    $(document).off('change.countryCode', '.country-code-manual-toggle').on('change.countryCode', '.country-code-manual-toggle', function () {
        applyCountryCodeMode($(this));
    });
    $(root).find('.country-code-manual-toggle').each(function () {
        applyCountryCodeMode($(this));
    });
    $(root).find('select[name="country_code"], select[name="telephone_country_code_id"]').off('changed.countryCode').on('changed.countryCode', function () {
        const code = $(this).find(':selected').data('phone-code');
        if (code) $(this).siblings('.bootstrap-select').find('.filter-option-inner-inner').text('+' + code);
    }).trigger('changed.countryCode');
}

function resetLocationControls(root = document)
{
    const scope = $(root);
    scope.find('.location-manual-toggle').prop('checked', false);
    scope.find('.location-manual')
        .addClass('d-none')
        .prop('disabled', true)
        .prop('required', false)
        .val('');
    scope.find('.location-manual-toggle').each(function () {
        const select = $($(this).data('select'));
        setLocationSelectState(select, false);
        const selectDisplay = select.closest('.bootstrap-select').length
            ? select.closest('.bootstrap-select')
            : select;
        selectDisplay.removeClass('d-none');
    });
}

function locationSelectIsManual(select)
{
    const element = $(select);
    const selector = element.attr('id') ? '#' + element.attr('id') : '';
    return selector !== '' && $('.location-manual-toggle').filter(function () {
        return String($(this).data('select')) === selector;
    }).is(':checked');
}

function setLocationSelectState(select, disabled)
{
    const element = $(select);
    if (! element.length) {
        return;
    }
    const shouldDisable = Boolean(disabled) || locationSelectIsManual(element);
    element.prop('disabled', shouldDisable);
    if (! element.hasClass('selectpicker')) {
        element.selectpicker({
            noneSelectedText: 'Select an option',
            liveSearchPlaceholder: 'Type to search...'
        });
    }
    if (shouldDisable) {
        element.selectpicker('disable');
    } else {
        element.selectpicker('enable');
    }
    element.selectpicker('refresh');
}

function locationSelectPlaceholder(label, message = '')
{
    return '<option value="">' + (message || 'Select ' + label) + '</option>';
}

function resetLocationSelect(selector, label, disabled = false, message = '')
{
    const select = $(selector);
    if (! select.length) {
        return;
    }
    const requestToken = (Number(select.data('location-request-token')) || 0) + 1;
    select.data('location-request-token', requestToken)
        .html(locationSelectPlaceholder(label, message))
        .val('');
    setLocationSelectState(select, disabled);
}

function populateLocationSelect(selector, label, response)
{
    const select = $(selector);
    if (! select.length) {
        return false;
    }

    const options = response && response.status !== false ? String(response.data || '').trim() : '';
    const hasOptions = options !== '';
    select.html(locationSelectPlaceholder(label) + (hasOptions ? options : locationSelectPlaceholder(label, 'No ' + label + ' options found')))
        .val('');
    setLocationSelectState(select, false);
    return hasOptions;
}

function loadLocationOptions(parentId, selector, label, url, callback = null)
{
    const select = $(selector);
    if (! select.length) {
        return;
    }

    const requestToken = (Number(select.data('location-request-token')) || 0) + 1;
    select.data('location-request-token', requestToken)
        .html(locationSelectPlaceholder(label, 'Loading ' + label + '...'))
        .val('');
    setLocationSelectState(select, true);

    if (! parentId) {
        resetLocationSelect(selector, label, false);
        return;
    }

    ajaxRequest({
        url: url + parentId,
        method: 'GET',
        success: function (response) {
            if (Number(select.data('location-request-token')) !== requestToken) {
                return;
            }
            populateLocationSelect(selector, label, response);
            if (typeof callback === 'function') {
                callback();
            }
        },
        error: function () {
            if (Number(select.data('location-request-token')) !== requestToken) {
                return;
            }
            resetLocationSelect(selector, label, false, 'Unable to load ' + label + ' options');
        }
    });
}

function loadStates(countryId, stateSelector, callback = null)
{
    loadLocationOptions(countryId, stateSelector, 'state', ROUTES.location.states, callback);
}

function loadCities(stateId, citySelector, callback = null)
{
    loadLocationOptions(stateId, citySelector, 'city', BASE_URL + 'location/cities/', callback);
}

function loadLocalities(cityId, localitySelector, callback = null)
{
    loadLocationOptions(cityId, localitySelector, 'locality', BASE_URL + 'location/localities/', callback);
}


$(function () {
    bindCountryCodeToggles(document);
});


/*=====  End of Location  ======*/
