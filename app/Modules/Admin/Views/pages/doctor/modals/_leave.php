<div class="modal fade" id="doctorLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <?= form_open('', ['id' => 'doctorLeaveForm' , 'autocomplete' => 'off']) ?>
 

                <div class="modal-header">
                    <h5 class="modal-title">
                        Add Doctor Leave
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

                                <select class="form-control btSelect" data-live-search="true" name="doctor_id" id="doctor_id" title="Select Doctor">

                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>">
                                            <?= esc($doctor['first_name'].' '.$doctor['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>

                                </select>
                                <div class="validation-error" id="doctor_idErr"></div>

                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Leave Type <span class="text-danger">*</span></label>

                                <select class="form-control btSelect" name="leave_type" id="leave_type" title="Select Leave Type">

                                    <option value="Casual Leave">Casual Leave</option>
                                    <option value="Sick Leave">Sick Leave</option>
                                    <option value="Emergency Leave">Emergency Leave</option>
                                    <option value="Vacation">Vacation</option>
                                    <option value="Conference">Conference</option>
                                    <option value="Other">Other</option>

                                </select>
                                <div class="validation-error" id="leave_typeErr"></div>

                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>

                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" placeholder="dd-mm-yyyy">
                                <div class="validation-error" id="start_dateErr"></div>

                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Date <span class="text-danger">*</span></label>

                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" placeholder="dd-mm-yyyy">
                                <div class="validation-error" id="end_dateErr"></div>

                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Reason</label>

                                <textarea class="form-control" rows="4" name="reason" id="reason" placeholder="Enter leave reason (optional)"></textarea>
                                <div class="validation-error" id="reasonErr"></div>

                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>

                                <select class="form-control btSelect" title="Select Status" name="status" id="status">

                                    <option value="1">Approved</option>
                                    <option value="0">Cancelled</option>
                                    <option value="2">Pending</option>

                                </select>
                                <div class="validation-error" id="statusErr"></div>

                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="submit" class="btn theme-btn save">
                        Save Leave
                    </button>

                </div>

                <?= form_close() ?>

        </div>
    </div>
</div>





<div class="modal fade" id="doctorLeaveUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <?= form_open('admin/doctor-leaves/update-status', '') ?>

                <input type="hidden" name="leave_id" id="leave_id">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Update Leave Status
                    </h5>

                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body"> 

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>

                                <select class="form-control btSelect" name="status" title="Select Status"  id="status">

                                    <option value="1">Approved</option>
                                    <option value="0">Cancelled</option>
                                    <option value="2">Pending</option>

                                </select>

                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="submit" class="btn theme-btn">
                        Update Status
                    </button>

                </div>

                <?= form_close() ?>

        </div>
    </div>
</div>