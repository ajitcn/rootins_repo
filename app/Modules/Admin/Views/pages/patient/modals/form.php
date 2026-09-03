<div class="modal fade" id="patientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <?= form_open('', ['id' => 'patientForm', 'autocomplete' => 'off']) ?>
                <input type="hidden" name="patient_id" id="patient_id">

                <div class="modal-header">
                    <h5 class="modal-title">Add Patient</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <h6 class="mb-3">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" id="first_name">
                                <div class="validation-error" id="first_nameErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" id="middle_name">
                                <div class="validation-error" id="middle_nameErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" id="last_name">
                                <div class="validation-error" id="last_nameErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender</label>
                                <select class="form-control" name="gender" id="gender">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="patient_country_manual" data-select="#patient_country_id" data-input="#patient_country_name"><label class="custom-control-label small" for="patient_country_manual">Enter country manually</label></div>
                                <input type="text" class="form-control mt-2 location-manual d-none" name="country_name" id="patient_country_name" placeholder="Country name" maxlength="191" aria-describedby="patient_country_hint">
                                <small id="patient_country_hint" class="form-text text-muted">Select a suggestion or enter a normalized name.</small>
                                <div class="validation-error" id="genderErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Date of Birth <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="date_of_birth" id="date_of_birth" placeholder="dd-mm-yyyy" autocomplete="off">
                                <div class="validation-error" id="date_of_birthErr"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-3">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                            <label>Country Code <span class="text-danger">*</span></label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Country Code" name="country_code" id="country_code" required>
                                <option value="">Select Country Code</option>
                                <?php foreach ($countries as $country): ?>
                                    <?php if (! empty($country['phone_code'])): ?>
                                        <option value="<?= (int) $country['id'] ?>" data-phone-code="<?= esc(ltrim((string) $country['phone_code'], '+')) ?>">
                                            <?= esc($country['name']) ?> (<?= esc($country['phone_code']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input country-code-manual-toggle" id="patient_country_code_manual_toggle" data-select="#country_code" data-input="#patient_country_code_manual"><label class="custom-control-label small" for="patient_country_code_manual_toggle">Enter code manually</label></div>
                            <input type="text" class="form-control mt-2 d-none" name="country_code" id="patient_country_code_manual" placeholder="Example: +91" inputmode="tel" maxlength="10" disabled aria-describedby="patient-country-code-hint">
                            <small id="patient-country-code-hint" class="form-text text-muted">Select a country to use its telephone code.</small>
                                <div class="validation-error" id="country_codeErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                            <label>Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="mobile_number" id="mobile_number">
                                <div class="validation-error" id="mobile_numberErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="email">
                                <div class="validation-error" id="emailErr"></div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea class="form-control" rows="2" name="address" id="address"></textarea>
                                <div class="validation-error" id="addressErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" id="postal_code">
                                <div class="validation-error" id="postal_codeErr"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-3">Clinic</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Clinic <span class="text-danger">*</span></label>
                                <select class="form-control btSelect" data-live-search="true" title="Select Clinic" name="clinic_id" id="patient_clinic_id">
                                    <option value="">Select Clinic</option>
                                    <?php foreach ($clinics as $clinic): ?>
                                        <option value="<?= (int) $clinic['id'] ?>"><?= esc($clinic['clinic_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="patient_state_manual" data-select="#patient_state_id" data-input="#patient_state_name"><label class="custom-control-label small" for="patient_state_manual">Enter manually</label></div>
                                <input type="text" class="form-control mt-2 location-manual d-none" name="state_name" id="patient_state_name" placeholder="State or division name" maxlength="191">
                                <div class="validation-error" id="clinic_idErr"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-3">Location</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Country</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select Country" name="country_id" id="patient_country_id">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= (int) $country['id'] ?>"><?= esc($country['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" name="country_name" id="patient_country_name" placeholder="Or enter country manually" maxlength="191">
                                <div class="validation-error" id="country_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>State</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select State" name="state_id" id="patient_state_id">
                                    <option value="">Select State</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="state_name" id="patient_state_name" placeholder="Or enter state/division manually" maxlength="191">
                                <div class="validation-error" id="state_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>City</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select City" name="city_id" id="patient_city_id">
                                    <option value="">Select City</option>
                                </select>
                                <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="patient_city_manual" data-select="#patient_city_id" data-input="#patient_city_name"><label class="custom-control-label small" for="patient_city_manual">Enter manually</label></div>
                                <input type="text" class="form-control mt-2 location-manual d-none" name="city_name" id="patient_city_name" placeholder="City name" maxlength="191">
                                <div class="validation-error" id="city_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="patient_locality_id">Locality</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select Locality" name="locality_id" id="patient_locality_id">
                                    <option value="">Select Locality</option>
                                </select>
                                <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input location-manual-toggle" id="patient_locality_manual" data-select="#patient_locality_id" data-input="#patient_locality_name"><label class="custom-control-label small" for="patient_locality_manual">Enter manually</label></div>
                                <input type="text" class="form-control mt-2 location-manual d-none" name="locality_name" id="patient_locality_name" placeholder="Locality or neighborhood" maxlength="191">
                                <div class="validation-error" id="locality_idErr"></div>
                                <div class="validation-error" id="locality_nameErr"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-3">Status</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status" id="patient_status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <div class="validation-error" id="statusErr"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn theme-btn save">
                        <i class="bi bi-check-circle"></i> Save Patient
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
