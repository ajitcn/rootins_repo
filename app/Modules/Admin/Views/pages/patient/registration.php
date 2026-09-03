<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'Patient Registration',
    ]) ?>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Patient Registration</h5>
        </div>

        <div class="card-body">
            <?= form_open('', ['id' => 'patientRegistrationForm', 'autocomplete' => 'off']) ?>
                <div class="alert alert-info">
                    The patient registration number will be generated after successful registration.
                </div>

                <div id="patientRegistrationResult" class="alert alert-success d-none">
                    Patient registration number:
                    <strong id="patientRegistrationNumber"></strong>
                </div>

                <h6 class="mb-3">Personal Information</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_first_name">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="registration_first_name">
                            <div class="validation-error" id="first_nameErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_middle_name">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="registration_middle_name">
                            <div class="validation-error" id="middle_nameErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_last_name">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="registration_last_name">
                            <div class="validation-error" id="last_nameErr"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="registration_gender">Gender</label>
                            <select class="form-control" name="gender" id="registration_gender">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                                </select>
                            <div class="validation-error" id="genderErr"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="registration_date_of_birth">Date of Birth <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" name="date_of_birth" id="registration_date_of_birth" placeholder="dd-mm-yyyy" autocomplete="off">
                            <div class="validation-error" id="date_of_birthErr"></div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3 mt-3">Contact Information</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_country_code">Country Code <span class="text-danger">*</span></label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Country Code" name="country_code" id="registration_country_code" required>
                                <option value="">Select Country Code</option>
                                <?php foreach ($countries as $country): ?>
                                    <?php if (! empty($country['phone_code'])): ?>
                                        <option value="<?= (int) $country['id'] ?>" data-phone-code="<?= esc(ltrim((string) $country['phone_code'], '+')) ?>">
                                            <?= esc($country['name']) ?> (<?= esc($country['phone_code']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input country-code-manual-toggle" id="registration_country_code_manual_toggle" data-select="#registration_country_code" data-input="#registration_country_code_manual"><label class="custom-control-label small" for="registration_country_code_manual_toggle">Enter code manually</label></div>
                            <input type="text" class="form-control mt-2 d-none" name="country_code" id="registration_country_code_manual" placeholder="Example: +91" inputmode="tel" maxlength="10" disabled aria-describedby="registration-country-code-hint">
                            <small id="registration-country-code-hint" class="form-text text-muted">Select a country to use its telephone code.</small>
                            <div class="validation-error" id="country_codeErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_mobile_number">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mobile_number" id="registration_mobile_number">
                            <div class="validation-error" id="mobile_numberErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="registration_email">
                            <div class="validation-error" id="emailErr"></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="registration_address">Address</label>
                            <textarea class="form-control" rows="2" name="address" id="registration_address"></textarea>
                            <div class="validation-error" id="addressErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_postal_code">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" id="registration_postal_code">
                            <div class="validation-error" id="postal_codeErr"></div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3 mt-3">Initial Clinic Assignment</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="registration_clinic_id">Clinic <span class="text-danger">*</span></label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Clinic" name="clinic_id" id="registration_clinic_id">
                                <option value="">Select Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= (int) $clinic['id'] ?>"><?= esc($clinic['clinic_name']) ?></option>
                                <?php endforeach; ?>
                                </select>
                            <div class="validation-error" id="clinic_idErr"></div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3 mt-3">Location</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_country_id">Country</label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Country" name="country_id" id="registration_country_id">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= (int) $country['id'] ?>"><?= esc($country['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="registration_country_manual" data-select="#registration_country_id" data-input="#registration_country_name"><label class="custom-control-label small" for="registration_country_manual">Enter country manually</label></div>
                            <input type="text" class="form-control mt-2 location-manual d-none" name="country_name" id="registration_country_name" placeholder="Country name" maxlength="191">
                            <div class="validation-error" id="country_idErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_state_id">State</label>
                            <select class="form-control btSelect" data-live-search="true" title="Select State" name="state_id" id="registration_state_id">
                                <option value="">Select State</option>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="registration_state_manual" data-select="#registration_state_id" data-input="#registration_state_name"><label class="custom-control-label small" for="registration_state_manual">Enter manually</label></div>
                            <input type="text" class="form-control mt-2 location-manual d-none" name="state_name" id="registration_state_name" placeholder="State or division name" maxlength="191">
                            <div class="validation-error" id="state_idErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_city_id">City</label>
                            <select class="form-control btSelect" data-live-search="true" title="Select City" name="city_id" id="registration_city_id">
                                <option value="">Select City</option>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="registration_city_manual" data-select="#registration_city_id" data-input="#registration_city_name"><label class="custom-control-label small" for="registration_city_manual">Enter manually</label></div>
                            <input type="text" class="form-control mt-2 location-manual d-none" name="city_name" id="registration_city_name" placeholder="City name" maxlength="191">
                            <div class="validation-error" id="city_idErr"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="registration_locality_id">Locality</label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Locality" name="locality_id" id="registration_locality_id">
                                <option value="">Select Locality</option>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="registration_locality_manual" data-select="#registration_locality_id" data-input="#registration_locality_name"><label class="custom-control-label small" for="registration_locality_manual">Enter manually</label></div>
                            <input type="text" class="form-control mt-2 location-manual d-none" name="locality_name" id="registration_locality_name" placeholder="Locality or neighborhood" maxlength="191">
                            <div class="validation-error" id="locality_idErr"></div>
                            <div class="validation-error" id="locality_nameErr"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn theme-btn">
                        <i class="bi bi-check-circle"></i> Register Patient
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</main>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/patient/patient_registration.js') ?>"></script>
<?= $this->endSection() ?>
