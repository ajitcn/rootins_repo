<div class="modal fade" id="doctorScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <?= form_open('', ['id' => 'doctorScheduleForm' , 'autocomplete' => 'off']) ?>

                <input type="hidden" name="schedule_id" id="schedule_id">

                <div class="modal-header">
                    <h5 class="modal-title">
                    Add New Schedule
                </h5>

                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Doctor <span class="text-danger">*</span></label>

                                <select class="form-control btSelect" data-live-search="true" name="doctor_id" title="Select Doctor" id="doctor_id">

                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>">
                                            <?= esc($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>

                                </select>
                                <div class="validation-error" id="doctor_idErr"></div>

                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Day <span class="text-danger">*</span></label>

                                <select class="form-control btSelect" 
                                    multiple 
                                    data-live-search="true" 
                                    title="Select Day(s)"
                                    name="day_of_weeks[]" 
                                    id="day_of_weeks"
                                >
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                                <div class="validation-error" id="day_of_weeksErr"></div>

                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Shift Name</label>

                                <input type="text" class="form-control" name="shift_name" id="shift_name" placeholder="Morning / Evening">
                                <div class="validation-error" id="shift_nameErr"></div>

                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>

                                <select class="form-control btSelect" name="status" id="status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <div class="validation-error" id="statusErr"></div>

                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Time <span class="text-danger">*</span></label>

                                <input type="time" class="form-control" name="start_time" id="start_time">
                                <div class="validation-error" id="start_timeErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Time <span class="text-danger">*</span></label>

                                <input type="time" class="form-control" name="end_time" id="end_time">
                                <div class="validation-error" id="end_timeErr"></div>
                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Slot Duration</label>

                                <select class="form-control btSelect" name="slot_duration" id="slot_duration">
                                    <option value="10">10 Minutes</option>
                                    <option value="15" selected>15 Minutes</option>
                                    <option value="20">20 Minutes</option>
                                    <option value="30">30 Minutes</option>
                                    <option value="45">45 Minutes</option>
                                    <option value="60">60 Minutes</option>
                                </select>
                                <div class="validation-error" id="slot_durationErr"></div>

                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Maximum Patients</label>

                                <input type="number" class="form-control" name="max_patients" id="max_patients" min="0" value="0">
                                <div class="validation-error" id="max_patientsErr"></div>

                                <small class="text-muted">0 = Unlimited</small>

                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer"> 
                    <button type="submit" class="btn theme-btn save">
                        Save Doctor
                    </button>

                </div>

                <?= form_close() ?>

        </div>
    </div>
</div>