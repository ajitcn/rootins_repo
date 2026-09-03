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


function bindFormError(target, msg) {

    if (
        typeof msg === 'object' &&
        msg !== null &&
        !Array.isArray(msg)
    ) {

        $.each(msg, function(key, error) {
            $(target+ ' #'+ key + 'Err').text(error);
        });

    }
}

function clearFormErrors() {
    $('.validation-error').empty();
}



/**
 *
 * preloader on html
 *
 */

function show_loader() {
  $('.preloader').show();
}



function hide_loader() {
  $('.preloader').hide();
}


$(document).ready(function(){
  hide_loader();
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
    const defaults = {

        method: 'POST',

        dataType: 'json',

        cache: false,

        beforeSend: function () {
            show_loader();
        },

        success: function () {
             hide_loader();
        },

        error: function (xhr, status, error) {


            let message = 'Something went wrong.';

            switch (xhr.status) {

                case 0:
                    message = 'Unable to connect to the server.';
                    break;

                case 400:
                    message = xhr.responseJSON?.msg ?? 'Bad Request.';
                    break;

                case 401:
                    message = 'Your session has expired. Please login again.';
                    window.location.href = BASE_URL + 'system/login';
                    return;

                case 403:
                    message = 'Access denied.';
                    break;

                case 404:
                    message = 'Requested resource not found.';
                    break;

                case 422:
                    message = xhr.responseJSON?.msg ?? 'Validation failed.';
                    break;

                case 500:
                    message = xhr.responseJSON?.msg ?? 'Internal Server Error.';
                    break;

                default:
                    message = xhr.responseJSON?.msg ?? error;
            }

            showError(message);
            hide_loader();

        },

        complete: function () {
            hide_loader();
        }

    };

    $.ajax($.extend(true, {}, defaults, options));
}





/*================================
=            Location            =
================================*/
function bindLocationManualToggles(root = document)
{
    $(root).find('.location-manual-toggle').off('change.location').on('change.location', function () {
        const toggle = $(this);
        const select = $(toggle.data('select'));
        const input = $(toggle.data('input'));
        const manual = toggle.is(':checked');
        select.prop('disabled', manual).val('').selectpicker('refresh');
        input.toggleClass('d-none', ! manual).prop('required', manual);
        if (! manual) input.val('');
    });
}

function bindCountryCodeToggles(root = document)
{
    $(root).find('.country-code-manual-toggle').off('change.countryCode').on('change.countryCode', function () {
        const toggle = $(this);
        const select = $(toggle.data('select'));
        const input = $(toggle.data('input'));
        const manual = toggle.is(':checked');
        select.prop('disabled', manual).val('').selectpicker('refresh');
        input.toggleClass('d-none', ! manual).prop('disabled', ! manual).prop('required', manual);
        if (! manual) input.val('');
    });
    $(root).find('select[name="country_code"]').off('changed.countryCode').on('changed.countryCode', function () {
        const code = $(this).find(':selected').data('phone-code');
        if (code) $(this).siblings('.bootstrap-select').find('.filter-option-inner-inner').text('+' + code);
    }).trigger('changed.countryCode');
}

function resetLocationControls(root = document)
{
    const scope = $(root);
    scope.find('.location-manual-toggle').prop('checked', false);
    scope.find('.location-manual').addClass('d-none').prop('required', false).val('');
    scope.find('.location-manual-toggle').each(function () {
        $($(this).data('select')).prop('disabled', false).selectpicker('refresh');
    });
}

function loadStates(countryId, stateSelector, callback =null)
{
      
    $(stateSelector).html('<option value="">Loading...</option>');

    ajaxRequest({

        url: ROUTES.location.states + countryId,

        method: 'GET',

        success: function (response) {

            //$(stateSelector).selectpicker('destroy').html(response.data).selectpicker();
            $(stateSelector).html(response.data);
            $(stateSelector).selectpicker('refresh');

            if (typeof callback === 'function') {
                callback();
            }
            

        }

    }); 
}


function loadCities(stateId, citySelector, callback =null)
{ 
    $(citySelector).html('<option value="">Loading...</option>');

    ajaxRequest({

        url: BASE_URL + 'location/cities/' + stateId,

        method: 'GET',

        success: function (response) {

            //$(citySelector).selectpicker('destroy').html(response.data).selectpicker();
            $(citySelector).html(response.data);
            $(citySelector).selectpicker('refresh');

            if (typeof callback === 'function') {
                callback();
            }

        }

    });

    
}

function loadLocalities(cityId, localitySelector, callback = null)
{
    $(localitySelector).html('<option value="">Loading...</option>').selectpicker('refresh');
    ajaxRequest({
        url: BASE_URL + 'location/localities/' + cityId,
        method: 'GET',
        success: function (response) {
            $(localitySelector).html(response.data || '<option value="">No localities found</option>').selectpicker('refresh');
            if (typeof callback === 'function') callback();
        }
    });
}


/*=====  End of Location  ======*/
