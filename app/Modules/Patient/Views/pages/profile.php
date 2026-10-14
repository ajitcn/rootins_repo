<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$displayDateOfBirth = !empty($patient['date_of_birth']) && strtotime((string) $patient['date_of_birth']) !== false
    ? date('d-m-Y', strtotime((string) $patient['date_of_birth']))
    : '';
?>

<main class="patient-profile-shell" aria-labelledby="patient-profile-title">
    <header class="patient-page-header">
        <div>
            <div class="patient-eyebrow">Patient portal / profile</div>
            <h1 id="patient-profile-title">My profile</h1>
            <p>Keep your contact and personal details up to date for your clinic.</p>
        </div>
        <span class="patient-profile-reference"><i class="bi bi-person-badge mr-1" aria-hidden="true"></i><?= esc($patient['registration_number'] ?? 'Patient number not recorded') ?></span>
    </header>

    <form id="patient-profile-form" class="patient-profile-card" method="post" action="<?= base_url('patient/profile') ?>" novalidate>
        <?= csrf_field() ?>
        <section class="patient-profile-section" aria-labelledby="patient-personal-details-title">
            <div class="patient-form-section-heading">
                <div><span class="patient-eyebrow">Identity</span><h2 id="patient-personal-details-title">Personal details</h2></div>
                <span class="text-muted small">Patient number is read-only</span>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="patient-profile-registration-number">Patient number</label>
                    <input id="patient-profile-registration-number" class="form-control" value="<?= esc($patient['registration_number'] ?? '') ?>" readonly>
                </div>
                <div class="form-group col-md-4">
                    <label for="patient-profile-first-name">First name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="patient-profile-first-name" class="form-control" name="first_name" value="<?= esc($patient['first_name'] ?? '') ?>" maxlength="100" required>
                    <div class="validation-error" id="first_nameErr"></div>
                </div>
                <div class="form-group col-md-4">
                    <label for="patient-profile-last-name">Last name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="patient-profile-last-name" class="form-control" name="last_name" value="<?= esc($patient['last_name'] ?? '') ?>" maxlength="100" required>
                    <div class="validation-error" id="last_nameErr"></div>
                </div>
                <div class="form-group col-md-4">
                    <label for="patient-profile-gender">Gender</label>
                    <select id="patient-profile-gender" class="form-control" name="gender">
                        <option value="">Prefer not to say</option>
                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= ($patient['gender'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="validation-error" id="genderErr"></div>
                </div>
                <div class="form-group col-md-4">
                    <label for="patient-profile-date-of-birth">Date of birth</label>
                    <input id="patient-profile-date-of-birth" class="form-control datepicker" type="text" name="date_of_birth" data-submit-display-date="true" value="<?= esc($displayDateOfBirth) ?>" placeholder="dd-mm-yyyy" autocomplete="off">
                    <div class="validation-error" id="date_of_birthErr"></div>
                </div>
            </div>
        </section>

        <section class="patient-profile-section" aria-labelledby="patient-contact-details-title">
            <div class="patient-form-section-heading">
                <div><span class="patient-eyebrow">Reach you</span><h2 id="patient-contact-details-title">Contact details</h2></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="patient-profile-email">Email</label>
                    <input id="patient-profile-email" class="form-control" type="email" name="email" value="<?= esc($patient['email'] ?? '') ?>" maxlength="255">
                    <div class="validation-error" id="emailErr"></div>
                </div>
                <div class="form-group col-md-8">
                    <label for="patient-profile-mobile">Mobile number</label>
                    <div class="input-group patient-phone-group">
                        <select id="patient-profile-telephone-code" class="form-control btSelect" data-live-search="true" name="telephone_country_code_id" title="Select country code">
                            <option value="">Code</option>
                            <?php foreach ($telephoneCodes as $telephoneCode): ?>
                                <option value="<?= (int) $telephoneCode['id'] ?>" data-phone-code="<?= esc((string) $telephoneCode['normalized_code']) ?>" <?= (int) ($patient['telephone_country_code_id'] ?? 0) === (int) $telephoneCode['id'] ? 'selected' : '' ?>>+<?= esc((string) $telephoneCode['normalized_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input id="patient-profile-mobile" class="form-control" name="mobile_number" value="<?= esc($patient['mobile_number'] ?? '') ?>" inputmode="tel" maxlength="20">
                    </div>
                    <div class="validation-error" id="country_codeErr"></div>
                    <div class="validation-error" id="mobile_numberErr"></div>
                    <div class="custom-control custom-switch mt-2">
                        <input id="patient-profile-manual-code-toggle" class="custom-control-input country-code-manual-toggle" type="checkbox" name="country_code_manual" value="1" data-select="#patient-profile-telephone-code" data-input="#patient-profile-manual-code" data-name-input="#patient-profile-telephone-name">
                        <label class="custom-control-label small" for="patient-profile-manual-code-toggle">Enter country code manually</label>
                    </div>
                    <div class="country-code-manual-fields d-none mt-2">
                        <div class="form-row">
                            <div class="col-md-6">
                                <label class="small" for="patient-profile-manual-code">Country code</label>
                                <input id="patient-profile-manual-code" class="form-control country-code-manual-input" name="country_code" value="<?= esc($patient['country_code'] ?? '') ?>" placeholder="+91" inputmode="tel" maxlength="5" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="small" for="patient-profile-telephone-name">Country name</label>
                                <input id="patient-profile-telephone-name" class="form-control" name="telephone_country_name" value="" placeholder="Country name" maxlength="191" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="patient-profile-section" aria-labelledby="patient-location-details-title">
            <div class="patient-form-section-heading">
                <div><span class="patient-eyebrow">Where you live</span><h2 id="patient-location-details-title">Location</h2></div>
            </div>
            <div class="form-row">
                <?php foreach ([
                    ['country_id', 'Country', 'patient-profile-country', $patient['country_id'] ?? ''],
                    ['state_id', 'State / Division', 'patient-profile-state', $patient['state_id'] ?? ''],
                    ['city_id', 'City', 'patient-profile-city', $patient['city_id'] ?? ''],
                    ['locality_id', 'Locality', 'patient-profile-locality', $patient['locality_id'] ?? ''],
                ] as [$field, $title, $id, $selected]): ?>
                    <div class="form-group col-md-4">
                        <label for="<?= esc($id) ?>"><?= esc($title) ?></label>
                        <select id="<?= esc($id) ?>" class="form-control btSelect" data-live-search="true" name="<?= esc($field) ?>" data-saved-value="<?= esc((string) $selected) ?>">
                            <option value="">Select <?= esc(strtolower($title)) ?></option>
                            <?php if ($field === 'country_id'): ?>
                                <?php foreach ($countries as $country): ?><option value="<?= (int) $country['id'] ?>" <?= (int) $selected === (int) $country['id'] ? 'selected' : '' ?>><?= esc($country['name']) ?></option><?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="validation-error" id="<?= esc($field) ?>Err"></div>
                        <div class="custom-control custom-switch mt-2">
                            <input id="<?= esc($id) ?>-manual-toggle" class="custom-control-input location-manual-toggle" type="checkbox" data-select="#<?= esc($id) ?>" data-input="#<?= esc($id) ?>-manual">
                            <label class="custom-control-label small" for="<?= esc($id) ?>-manual-toggle">Enter manually</label>
                        </div>
                        <input id="<?= esc($id) ?>-manual" class="form-control location-manual d-none mt-2" name="<?= esc(str_replace('_id', '_name', $field)) ?>" placeholder="Enter <?= esc(strtolower($title)) ?>" maxlength="191" disabled>
                    </div>
                <?php endforeach; ?>
                <div class="form-group col-md-4">
                    <label for="patient-profile-postal-code">Postal code</label>
                    <input id="patient-profile-postal-code" class="form-control" name="postal_code" value="<?= esc($patient['postal_code'] ?? '') ?>" maxlength="20">
                    <div class="validation-error" id="postal_codeErr"></div>
                </div>
                <div class="form-group col-12">
                    <label for="patient-profile-address">Address</label>
                    <textarea id="patient-profile-address" class="form-control" name="address" rows="4" maxlength="2000"><?= esc($patient['address'] ?? '') ?></textarea>
                    <div class="validation-error" id="addressErr"></div>
                </div>
            </div>
        </section>

        <footer class="patient-profile-footer">
            <span class="text-muted small"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Your profile is visible only within your permitted clinic.</span>
            <button id="patient-profile-save" class="btn btn-primary" type="submit"><i class="bi bi-check2 mr-1" aria-hidden="true"></i>Save changes</button>
        </footer>
    </form>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        var form = $('#patient-profile-form');
        var country = $('#patient-profile-country');
        var state = $('#patient-profile-state');
        var city = $('#patient-profile-city');
        var locality = $('#patient-profile-locality');
        var saved = {
            country: country.data('saved-value') || country.val() || '',
            state: state.data('saved-value') || '',
            city: city.data('saved-value') || '',
            locality: locality.data('saved-value') || '',
        };
        var previous = {
            country: saved.country,
            state: saved.state,
            city: saved.city,
        };
        var saving = false;

        function restoreValue(select, value) {
            var option = select.find('option').filter(function () {
                return String($(this).val()) === String(value);
            }).first();

            if (!option.length) {
                select.val('').selectpicker('refresh');
                return false;
            }

            select.val(option.val()).selectpicker('refresh');
            return true;
        }

        function restoreSavedLocation(level) {
            if (level === 'country') {
                if (!restoreValue(country, saved.country)) {
                    return;
                }

                previous.country = saved.country;
                loadStates(saved.country, '#patient-profile-state', function () {
                    restoreSavedLocation('state');
                });
                return;
            }

            if (level === 'state') {
                if (!restoreValue(state, saved.state)) {
                    return;
                }

                previous.state = saved.state;
                loadCities(saved.state, '#patient-profile-city', function () {
                    restoreSavedLocation('city');
                });
                return;
            }

            if (level === 'city') {
                if (!restoreValue(city, saved.city)) {
                    return;
                }

                previous.city = saved.city;
                loadLocalities(saved.city, '#patient-profile-locality', function () {
                    restoreSavedLocation('locality');
                });
                return;
            }

            restoreValue(locality, saved.locality);
        }

        bindCountryCodeToggles(form);
        bindLocationManualToggles(form);
        restoreValue(country, saved.country);
        form.on('change.patientProfileManual', '.location-manual-toggle', function () {
            var toggle = $(this);

            if (toggle.is(':checked')) {
                return;
            }

            window.setTimeout(function () {
                var select = $(toggle.data('select'));
                var selector = select.attr('id') || '';

                if (selector === 'patient-profile-country') {
                    restoreSavedLocation('country');
                } else if (selector === 'patient-profile-state') {
                    restoreSavedLocation('state');
                } else if (selector === 'patient-profile-city') {
                    restoreSavedLocation('city');
                } else if (selector === 'patient-profile-locality') {
                    restoreSavedLocation('locality');
                }
            }, 0);
        });
        $('.datepicker').datepicker({format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto', container: '#patient-profile-form'});

        loadStates(saved.country, '#patient-profile-state', function () {
            restoreSavedLocation('state');
        });

        country.on('changed.bs.select change', function () {
            var value = String($(this).val() || '');

            if (value === String(previous.country || '')) {
                return;
            }

            previous.country = value;
            saved = {country: value, state: '', city: '', locality: ''};
            resetLocationSelect('#patient-profile-city', 'city', true);
            resetLocationSelect('#patient-profile-locality', 'locality', true);
            resetLocationSelect('#patient-profile-state', 'state', false);
            loadStates(value, '#patient-profile-state');
        });
        state.on('changed.bs.select change', function () {
            var value = String($(this).val() || '');

            if (value === String(previous.state || '')) {
                return;
            }

            previous.state = value;
            saved.state = value;
            saved.city = '';
            saved.locality = '';
            resetLocationSelect('#patient-profile-locality', 'locality', true);
            resetLocationSelect('#patient-profile-city', 'city', false);
            loadCities(value, '#patient-profile-city');
        });
        city.on('changed.bs.select change', function () {
            var value = String($(this).val() || '');

            if (value === String(previous.city || '')) {
                return;
            }

            previous.city = value;
            saved.city = value;
            saved.locality = '';
            resetLocationSelect('#patient-profile-locality', 'locality', false);
            loadLocalities(value, '#patient-profile-locality');
        });

        form.on('submit', function (event) {
            event.preventDefault();
            if (saving) return;
            clearFormErrors(form);
            saving = true;
            $('#patient-profile-save').prop('disabled', true).attr('aria-busy', 'true');
            ajaxRequest({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                success: function (response) {
                    if (response.status) {
                        showSuccess(response.msg);
                    } else {
                        bindFormError(form, response.errors || {});
                        showError(response.msg);
                    }
                },
                error: function (xhr) {
                    handleFormAjaxError(form, xhr, 'Profile could not be updated safely.');
                },
                complete: function () {
                    saving = false;
                    $('#patient-profile-save').prop('disabled', false).removeAttr('aria-busy');
                },
            });
        });
    });
</script>
<?= $this->endSection() ?>
