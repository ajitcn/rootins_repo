window.DoctorPatientLocation = {
    init: function (createSelector, editSelector, countries, patient) {
        const optionMarkup = (items, placeholder) => {
            const options = (items || []).filter(item => String(item.phone_code || '').trim() !== '');
            return '<option value="">' + placeholder + '</option>' + options.map(function (item) {
                const code = String(item.phone_code || '').replace(/^\+/, '');
                return '<option value="' + Number(item.id) + '" data-phone-code="' + $('<div>').text(code).html() + '">' + $('<div>').text(item.name + ' (+' + code + ')').html() + '</option>';
            }).join('');
        };
        const fields = function (prefix) {
            return '<div class="form-row location-fields">'
                + '<div class="form-group col-md-6"><label for="' + prefix + '-country">Country</label><select id="' + prefix + '-country" class="form-control btSelect" data-live-search="true" name="country_id">' + optionMarkup(countries, 'Select country') + '</select><input class="form-control mt-2 location-manual d-none" name="country_name" placeholder="Or enter country" maxlength="191"></div>'
                + '<div class="form-group col-md-6"><label for="' + prefix + '-state">State/Division</label><select id="' + prefix + '-state" class="form-control btSelect" data-live-search="true" name="state_id"><option value="">Select state/division</option></select><input class="form-control mt-2 location-manual d-none" name="state_name" placeholder="Or enter state/division" maxlength="191"></div>'
                + '<div class="form-group col-md-6"><label for="' + prefix + '-city">City</label><select id="' + prefix + '-city" class="form-control btSelect" data-live-search="true" name="city_id"><option value="">Select city</option></select><input class="form-control mt-2 location-manual d-none" name="city_name" placeholder="Or enter city" maxlength="191"></div>'
                + '<div class="form-group col-md-6"><label for="' + prefix + '-locality">Locality</label><select id="' + prefix + '-locality" class="form-control btSelect" data-live-search="true" name="locality_id"><option value="">Select locality</option></select><input class="form-control mt-2 location-manual d-none" name="locality_name" placeholder="Or enter locality" maxlength="191"></div>'
                + '</div>';
        };
        const wire = function (form, prefix, values) {
            if (!form.length || form.find('.location-fields').length) return;
            const body = form.find('.modal-body').first();
            if (body.length) body.prepend(fields(prefix));
            else form.prepend(fields(prefix));
            const root = form.find('.location-fields').first();
            const mobile = form.find('[name="mobile_number"]').first();
            if (mobile.length && !form.find('[name="country_code"]').length) {
                const mobileGroup = mobile.closest('.form-group');
                let contactRow = mobileGroup.parent('.contact-number-row');
                if (!contactRow.length) {
                    mobileGroup.wrap('<div class="col-12 col-md-6 contact-number-row-wrapper px-0"></div>');
                    contactRow = mobileGroup.parent('.contact-number-row-wrapper').wrap('<div class="form-row contact-number-row"></div>').parent('.contact-number-row');
                }
                const codeMarkup = '<div class="form-group col-md-6 country-code-field"><label for="' + prefix + '-phone-code">Country code <span class="text-danger">*</span></label>'
                    + '<select id="' + prefix + '-phone-code" class="form-control btSelect" data-live-search="true" name="country_code" required>' + optionMarkup(countries, 'Select country code') + '</select>'
                    + '<div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input country-code-manual-toggle" id="' + prefix + '-phone-code-manual" data-select="#' + prefix + '-phone-code" data-input="#' + prefix + '-phone-code-input"><label class="custom-control-label small" for="' + prefix + '-phone-code-manual">Enter code manually</label></div>'
                    + '<input id="' + prefix + '-phone-code-input" class="form-control mt-2 d-none" name="country_code" placeholder="Example: +91" inputmode="tel" maxlength="10" disabled>'
                    + '<small class="form-text text-muted">Select a country or enter a valid code.</small><div class="validation-error" id="country_codeErr"></div></div>';
                contactRow.prepend(codeMarkup);
                mobileGroup.removeClass('col-md-4 col-md-6').addClass('mobile-number-field col-12');
            }
            root.find('select[name$="_id"]').each(function () {
                const select = $(this);
                const input = select.siblings('input[name$="_name"]');
                input.attr('id', select.attr('id') + '-name');
                const toggleId = select.attr('id') + '-manual';
                $('<div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="' + toggleId + '" data-select="#' + select.attr('id') + '" data-input="#' + input.attr('id') + '"><label class="custom-control-label small" for="' + toggleId + '">Enter manually</label></div>').insertAfter(select);
            });
            form.find('[name="mobile_number"]').val(values.mobile_number || '');
            form.find('[name="country_code"]').first().val(values.country_code_id || '').selectpicker('refresh');
            root.find('#' + prefix + '-country').val(values.country_id || '').selectpicker('refresh');
            bindLocationManualToggles(root);
            bindCountryCodeToggles(form);
            root.find('#' + prefix + '-country').on('change', function () {
                root.find('#' + prefix + '-state, #' + prefix + '-city, #' + prefix + '-locality').html('<option value="">Select value</option>').selectpicker('refresh');
                if ($(this).val()) loadStates($(this).val(), '#' + prefix + '-state');
            });
            root.find('#' + prefix + '-state').on('change', function () {
                root.find('#' + prefix + '-city, #' + prefix + '-locality').html('<option value="">Select value</option>').selectpicker('refresh');
                if ($(this).val()) loadCities($(this).val(), '#' + prefix + '-city');
            });
            root.find('#' + prefix + '-city').on('change', function () {
                root.find('#' + prefix + '-locality').html('<option value="">Select locality</option>').selectpicker('refresh');
                if ($(this).val()) loadLocalities($(this).val(), '#' + prefix + '-locality');
            });
            if (values.country_id) loadStates(values.country_id, '#' + prefix + '-state', function () {
                root.find('#' + prefix + '-state').val(values.state_id || '').selectpicker('refresh');
                if (values.state_id) loadCities(values.state_id, '#' + prefix + '-city', function () {
                    root.find('#' + prefix + '-city').val(values.city_id || '').selectpicker('refresh');
                    if (values.city_id) loadLocalities(values.city_id, '#' + prefix + '-locality', function () {
                        root.find('#' + prefix + '-locality').val(values.locality_id || '').selectpicker('refresh');
                    });
                });
            });
        };
        $(function () {
            wire($(createSelector), 'new-patient-location', {});
            wire($(editSelector), 'edit-patient-location', patient || {});
        });
    }
};
