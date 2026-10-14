window.DoctorPatientLocation = {
    init: function (createSelector, editSelector, countries, patient, telephoneCodes) {
        const countryOptionMarkup = (items, placeholder) => {
            const options = (items || []).map(function (item) {
                return '<option value="' + Number(item.id) + '">' + $('<div>').text(item.name).html() + '</option>';
            }).join('');
            return '<option value="">' + placeholder + '</option>' + options;
        };
        const telephoneCodeOptionMarkup = (items, placeholder) => {
            const options = (items || []).filter(item => String(item.normalized_code || item.phone_code || '').trim() !== '');
            return '<option value="">' + placeholder + '</option>' + options.map(function (item) {
                const code = String(item.normalized_code || item.phone_code || '').replace(/^\+/, '');
                return '<option value="' + Number(item.id) + '" data-phone-code="' + $('<div>').text(code).html() + '">' + $('<div>').text(item.name + ' (+' + code + ')').html() + '</option>';
            }).join('');
        };
        telephoneCodes = telephoneCodes || countries;
        const fields = function (prefix) {
            return '<div class="form-group col-md-4 col-12 doctor-patient-location-field location-fields"><label for="' + prefix + '-country">Country</label><select id="' + prefix + '-country" class="form-control btSelect" data-live-search="true" name="country_id">' + countryOptionMarkup(countries, 'Select country') + '</select><div class="validation-error" id="country_idErr"></div><input class="form-control mt-2 location-manual d-none" name="country_name" placeholder="Or enter country" maxlength="191"><div class="validation-error" id="country_nameErr"></div></div>'
                + '<div class="form-group col-md-4 col-12 doctor-patient-location-field"><label for="' + prefix + '-state">State/Division</label><select id="' + prefix + '-state" class="form-control btSelect" data-live-search="true" name="state_id"><option value="">Select state/division</option></select><div class="validation-error" id="state_idErr"></div><input class="form-control mt-2 location-manual d-none" name="state_name" placeholder="Or enter state/division" maxlength="191"><div class="validation-error" id="state_nameErr"></div></div>'
                + '<div class="form-group col-md-4 col-12 doctor-patient-location-field"><label for="' + prefix + '-city">City</label><select id="' + prefix + '-city" class="form-control btSelect" data-live-search="true" name="city_id"><option value="">Select city</option></select><div class="validation-error" id="city_idErr"></div><input class="form-control mt-2 location-manual d-none" name="city_name" placeholder="Or enter city" maxlength="191"><div class="validation-error" id="city_nameErr"></div></div>'
                + '<div class="form-group col-md-4 col-12 doctor-patient-location-field"><label for="' + prefix + '-locality">Locality</label><select id="' + prefix + '-locality" class="form-control btSelect" data-live-search="true" name="locality_id"><option value="">Select locality</option></select><div class="validation-error" id="locality_idErr"></div><input class="form-control mt-2 location-manual d-none" name="locality_name" placeholder="Or enter locality" maxlength="191"><div class="validation-error" id="locality_nameErr"></div></div>';
        };
        const wire = function (form, prefix, values) {
            if (!form.length || form.find('.doctor-patient-location-field').length) return;
            const body = form.find('.modal-body').first();
            if (body.length) {
                const locationSlot = body.find('.doctor-patient-location-slot').first();
                if (locationSlot.length) locationSlot.after(fields(prefix));
                else {
                    const contactSection = body.find('.doctor-patient-contact-section').first();
                    if (contactSection.length) contactSection.after(fields(prefix));
                    else {
                        const personalRow = body.find('.form-row').first();
                        if (personalRow.length) personalRow.after(fields(prefix));
                        else body.append(fields(prefix));
                    }
                }
            } else {
                form.prepend(fields(prefix));
            }
            const root = form;
            const stateSelector = '#' + prefix + '-state';
            const citySelector = '#' + prefix + '-city';
            const localitySelector = '#' + prefix + '-locality';
            const clearDependentLocations = function () {
                resetLocationSelect(stateSelector, 'state');
                resetLocationSelect(citySelector, 'city');
                resetLocationSelect(localitySelector, 'locality');
            };
            const prepareSelect = function (selector) {
                const select = root.find(selector);
                if (select.length && !select.hasClass('selectpicker')) {
                    select.selectpicker({
                        noneSelectedText: 'Select an option',
                        liveSearchPlaceholder: 'Type to search...'
                    });
                }
                return select;
            };
            const selectRestoredValue = function (selector, value) {
                const select = prepareSelect(selector);
                const wanted = String(value || '');
                const available = wanted !== '' && select.find('option').filter(function () {
                    return String($(this).val()) === wanted;
                }).length > 0;
                select.val(available ? wanted : '');
                setLocationSelectState(select, false);
                return available;
            };
            const mobile = form.find('[name="mobile_number"]').first();
            if (mobile.length && !form.find('[name="country_code"]').length) {
                const mobileGroup = mobile.closest('.form-group');
                const inputGroup = mobileGroup.find('.contact-number-input-group').first();
                if (inputGroup.length) {
                    const codeMarkup = '<div class="input-group-prepend country-code-field"><select id="' + prefix + '-phone-code" class="form-control btSelect" data-live-search="true" aria-label="Country code" name="telephone_country_code_id" required>' + telephoneCodeOptionMarkup(telephoneCodes, 'Country code') + '</select></div>';
                    const codeError = mobileGroup.find('#country_codeErr').length ? '' : '<div class="validation-error" id="country_codeErr"></div>';
                    const metaMarkup = '<div class="country-code-meta"><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input country-code-manual-toggle" id="' + prefix + '-phone-code-manual" name="country_code_manual" value="1" data-select="#' + prefix + '-phone-code" data-input="#' + prefix + '-phone-code-input" data-name-input="#' + prefix + '-phone-country-name"><label class="custom-control-label small" for="' + prefix + '-phone-code-manual">Enter code manually</label></div><div class="country-code-manual-fields d-none"><label class="small mb-1" for="' + prefix + '-phone-country-name">Country name <span class="text-danger" aria-hidden="true">*</span></label><input id="' + prefix + '-phone-country-name" class="form-control country-code-manual-name" name="telephone_country_name" placeholder="Country name" maxlength="191" disabled required><label class="small mb-1 mt-2" for="' + prefix + '-phone-code-input">Telephone country code <span class="text-danger" aria-hidden="true">*</span></label><input id="' + prefix + '-phone-code-input" class="form-control country-code-manual-input d-none" name="country_code" placeholder="Example: +91" aria-label="Manual telephone country code" inputmode="tel" maxlength="5" pattern="\\+?[0-9][0-9]{0,3}" title="Enter 1 to 4 digits, optionally prefixed with +." disabled required></div>' + codeError + '</div>';
                    inputGroup.prepend(codeMarkup);
                    mobileGroup.find('.contact-number-meta').append(metaMarkup);
                    mobileGroup.find('.country-code-manual-name').after('<div class="validation-error" id="telephone_country_nameErr"></div>');
                    bindCountryCodeToggles(mobileGroup);
                } else {
                    let contactRow = mobileGroup.parent('.contact-number-row');
                    if (!contactRow.length) {
                        mobileGroup.wrap('<div class="form-row contact-number-row col-12 px-0"></div>');
                        contactRow = mobileGroup.parent('.contact-number-row');
                    }
                    const codeMarkup = '<div class="form-group col-md-4 col-12 country-code-field"><label for="' + prefix + '-phone-code">Country code <span class="text-danger">*</span></label>'
                        + '<select id="' + prefix + '-phone-code" class="form-control btSelect" data-live-search="true" name="telephone_country_code_id" required>' + telephoneCodeOptionMarkup(telephoneCodes, 'Select country code') + '</select>'
                        + '<div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input country-code-manual-toggle" id="' + prefix + '-phone-code-manual" name="country_code_manual" value="1" data-select="#' + prefix + '-phone-code" data-input="#' + prefix + '-phone-code-input" data-name-input="#' + prefix + '-phone-country-name"><label class="custom-control-label small" for="' + prefix + '-phone-code-manual">Enter code manually</label></div>'
                        + '<div class="country-code-manual-fields d-none"><label class="small mb-1" for="' + prefix + '-phone-country-name">Country name <span class="text-danger" aria-hidden="true">*</span></label><input id="' + prefix + '-phone-country-name" class="form-control country-code-manual-name" name="telephone_country_name" placeholder="Country name" maxlength="191" disabled required><label class="small mb-1 mt-2" for="' + prefix + '-phone-code-input">Telephone country code <span class="text-danger" aria-hidden="true">*</span></label><input id="' + prefix + '-phone-code-input" class="form-control country-code-manual-input d-none" name="country_code" placeholder="Example: +91" inputmode="tel" maxlength="5" pattern="\\+?[0-9][0-9]{0,3}" title="Enter 1 to 4 digits, optionally prefixed with +." disabled required></div>'
                        + '<div class="validation-error" id="country_codeErr"></div></div>';
                    contactRow.prepend(codeMarkup);
                    contactRow.find('.country-code-manual-name').after('<div class="validation-error" id="telephone_country_nameErr"></div>');
                    mobileGroup.removeClass('col-md-4 col-md-6 col-12').addClass('mobile-number-field col-md-4 col-12');
                }
            }
            form.find('.doctor-patient-location-field select[name$="_id"]').each(function () {
                const select = $(this);
                const input = select.siblings('input[name$="_name"]');
                input.attr('id', select.attr('id') + '-name');
                const toggleId = select.attr('id') + '-manual';
                const selectError = select.nextAll('.validation-error').first();
                const toggle = $('<div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="' + toggleId + '" data-select="#' + select.attr('id') + '" data-input="#' + input.attr('id') + '"><label class="custom-control-label small" for="' + toggleId + '">Enter manually</label></div>');
                toggle.insertAfter(selectError.length ? selectError : select);
            });
            form.find('[name="mobile_number"]').val(values.mobile_number || '');
            const selectedTelephoneCode = values.telephone_country_code_id || '';
            form.find('[name="telephone_country_code_id"]').first().val(selectedTelephoneCode).selectpicker('refresh');
            form.find('[name="telephone_country_name"]').first().val(values.telephone_country_name || '');
            form.find('[name="country_code"]').first().val(values.country_code || '');
            if (values.country_code_manual || (! selectedTelephoneCode && values.country_code)) {
                form.find('.country-code-manual-toggle').prop('checked', true);
            }
            form.find('.country-code-manual-toggle').each(function () { applyCountryCodeMode($(this)); });
            clearDependentLocations();
            const countrySelector = '#' + prefix + '-country';
            const locationValues = {
                country: values.country_id,
                state: values.state_id,
                city: values.city_id,
                locality: values.locality_id
            };
            ['country', 'state', 'city', 'locality'].forEach(function (field) {
                root.find('[name="' + field + '_name"]').val(values[field + '_name'] || '');
            });
            root.find('.location-manual-toggle').each(function () {
                const toggle = $(this);
                const selector = String(toggle.data('select') || '');
                const field = selector.replace(/^#/, '').replace(new RegExp('^' + prefix + '-'), '').replace(/-([a-z])/g, function (_, letter) {
                    return '_' + letter;
                });
                const manualInput = root.find(toggle.data('input'));
                const savedManualValue = String(values[field + '_name'] || '').trim();
                toggle.prop('checked', !locationValues[field] && savedManualValue !== '' && manualInput.val() === savedManualValue);
            });
            selectRestoredValue(countrySelector, locationValues.country);
            bindLocationManualToggles(root);
            bindCountryCodeToggles(form);
            let locationRestoreToken = 0;
            root.find('#' + prefix + '-country').off('change.doctorLocation').on('change.doctorLocation', function () {
                locationRestoreToken++;
                clearDependentLocations();
                if ($(this).val()) loadStates($(this).val(), stateSelector);
            });
            root.find(stateSelector).off('change.doctorLocation').on('change.doctorLocation', function () {
                locationRestoreToken++;
                resetLocationSelect(citySelector, 'city');
                resetLocationSelect(localitySelector, 'locality');
                if ($(this).val()) loadCities($(this).val(), citySelector);
            });
            root.find(citySelector).off('change.doctorLocation').on('change.doctorLocation', function () {
                locationRestoreToken++;
                resetLocationSelect(localitySelector, 'locality');
                if ($(this).val()) loadLocalities($(this).val(), localitySelector);
            });
            const restoreToken = ++locationRestoreToken;
            if (selectRestoredValue(countrySelector, locationValues.country)) {
                loadStates(locationValues.country, stateSelector, function () {
                    if (restoreToken !== locationRestoreToken) return;
                    if (locationSelectIsManual(stateSelector) || !selectRestoredValue(stateSelector, locationValues.state)) return;
                    loadCities(locationValues.state, citySelector, function () {
                        if (restoreToken !== locationRestoreToken) return;
                        if (locationSelectIsManual(citySelector) || !selectRestoredValue(citySelector, locationValues.city)) return;
                        loadLocalities(locationValues.city, localitySelector, function () {
                            if (restoreToken !== locationRestoreToken) return;
                            if (!locationSelectIsManual(localitySelector)) selectRestoredValue(localitySelector, locationValues.locality);
                        });
                    });
                });
            }
        };
        $(function () {
            wire($(createSelector), 'new-patient-location', {});
            wire($(editSelector), 'edit-patient-location', patient || {});
        });
    }
};
