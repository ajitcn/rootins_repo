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
                                <div class="validation-error" id="genderErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_birth" id="date_of_birth">
                                <div class="validation-error" id="date_of_birthErr"></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-3">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                            <label>Country Code <span class="text-danger">*</span></label>
                            <select class="form-control btSelect" data-live-search="true" title="Select Country Code" name="country_code" id="country_code">
                                <option value="">Select Country Code</option>
                                <?php foreach ($countries as $country): ?>
                                    <?php if (! empty($country['phone_code'])): ?>
                                        <option value="<?= esc($country['phone_code']) ?>">
                                            <?= esc($country['name']) ?> (<?= esc($country['phone_code']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
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
                                <div class="validation-error" id="country_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>State</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select State" name="state_id" id="patient_state_id">
                                    <option value="">Select State</option>
                                </select>
                                <div class="validation-error" id="state_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>City</label>
                                <select class="form-control btSelect" data-live-search="true" title="Select City" name="city_id" id="patient_city_id">
                                    <option value="">Select City</option>
                                </select>
                                <div class="validation-error" id="city_idErr"></div>
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
