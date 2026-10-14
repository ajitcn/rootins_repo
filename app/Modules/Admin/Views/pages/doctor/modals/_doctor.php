<div class="modal fade" id="doctorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <?= form_open('', ['id' => 'doctorForm', 'autocomplete' => 'off']) ?>

                <input type="hidden" name="doctor_id" id="doctor_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="doctorModalTitle">
                    Add Doctor
                </h5>

                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <div class="row">

                        <!-- Clinic -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Clinic <span class="text-danger">*</span></label>

                                <select class="form-control btSelect" data-live-search="true" title="Select Clinic" name="clinic_id" id="clinic_id">

                                    <?php foreach($clinics as $clinic): ?>
                                        <option value="<?= $clinic['id'] ?>">
                                            <?= esc($clinic['clinic_name']) ?>
                                        </option>
                                        <?php endforeach; ?>

                                </select>
                                <div class="validation-error" id="clinic_idErr"></div>
                            </div>
                        </div>

                        <!-- Specialization -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Specialization <span class="text-danger">*</span></label>

                                <select class="form-control btSelect" 
                                multiple
                                data-live-search="true" title="Select Specialization" name="specialization_ids[]" id="specialization_ids">

                                    <?php foreach($specializations as $row): ?>
                                        <option value="<?= $row['id'] ?>">
                                            <?= esc($row['specialization_name']) ?>
                                        </option>
                                        <?php endforeach; ?>

                                </select>
                                <div class="validation-error" id="specialization_idsErr"></div>
                            </div>
                        </div>

                        <!-- Doctor Code -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Doctor Code <span class="text-danger">*</span></label>

                                <input type="text" class="form-control" name="doctor_code" id="doctor_code">
                                <div class="validation-error" id="doctor_codeErr"></div>
                            </div>
                        </div>

                        <!-- First Name -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>

                                <input type="text" class="form-control" name="first_name" id="first_name">
                                <div class="validation-error" id="first_nameErr"></div>
                            </div>
                        </div>

                        <!-- Last Name -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Last Name</label>

                                <input type="text" class="form-control" name="last_name" id="last_name">
                                <div class="validation-error" id="last_nameErr"></div>
                            </div>
                        </div>

                        <!-- Gender -->
                        <div class="col-md-4">
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

                        <!-- DOB -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date of Birth</label>

                                <input type="text" class="form-control datepicker" name="dob" id="dob" placeholder="dd-mm-yyyy">
                                <div class="validation-error" id="dobErr"></div>
                            </div>
                        </div>

                        <!-- Experience -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Experience (Years)</label>

                                <input type="number" class="form-control" name="experience" id="experience">
                                <div class="validation-error" id="experienceErr"></div>
                            </div>
                        </div>

                        <!-- Qualification -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Qualification</label>

                                <input type="text" class="form-control" name="qualification" id="qualification">
                                <div class="validation-error" id="qualificationErr"></div>
                            </div>
                        </div>

                        <!-- Registration -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Registration Number</label>

                                <input type="text" class="form-control" name="registration_number" id="registration_number">
                                <div class="validation-error" id="registration_numberErr"></div>
                            </div>
                        </div>

                        <?= view('templates/_telephone_contact', [
                            'idPrefix' => 'doctor',
                            'mobileName' => 'mobile',
                            'mobileId' => 'doctor-mobile',
                            'telephoneCodes' => $telephoneCodes ?? [],
                            'mobileRequired' => true,
                        ]) ?>

                        <!-- Email -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>

                                <input type="email" class="form-control" name="email" id="email">
                                <div class="validation-error" id="emailErr"></div>
                            </div>
                        </div>

                        <!-- Consultation Duration -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Consultation Duration (Minutes)</label>

                                <input type="number" class="form-control" name="consultation_duration" id="consultation_duration" value="15">
                                <div class="validation-error" id="consultation_durationErr"></div>
                            </div>
                        </div>

                        <!-- Display Order -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Display Order</label>

                                <input type="number" class="form-control" name="display_order" id="display_order" value="0">
                                <div class="validation-error" id="display_orderErr"></div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status</label>

                                <select class="form-control" name="status" id="status">

                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>

                                </select>
                                <div class="validation-error" id="statusErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    City <span class="text-danger">*</span>
                                </label>

                                <select
                                    class="form-control btSelect"
                                    data-live-search="true"
                                    title="Select City"
                                    name="city_id"
                                    id="city_id">

                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?= $city['id'] ?>">
                                            <?= esc($city['name']) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                                <div class="validation-error" id="city_idErr"></div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Address</label>

                                <textarea class="form-control" rows="2" name="address" id="address"></textarea>
                                <div class="validation-error" id="addressErr"></div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Notes</label>

                                <textarea class="form-control" rows="3" name="notes" id="notes"></textarea>
                                <div class="validation-error" id="notesErr"></div>
                            </div>
                        </div>

                        <!-- Online Booking -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch mt-4">

                                    <input type="checkbox" class="custom-control-input" id="online_booking" name="online_booking" value="1" checked>

                                    <label class="custom-control-label" for="online_booking">
                                        Allow Online Booking
                                    </label>

                                </div>
                                <div class="validation-error" id="online_bookingErr"></div>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
 
                    <button type="submit" class="btn theme-btn">
                        Save Doctor
                    </button>

                </div>

                <?= form_close() ?>

        </div>
    </div>
</div>
