<div class="modal fade" id="doctorCommissionModal" tabindex="-1" aria-labelledby="doctorCommissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="doctorCommissionModalLabel">Add Doctor Commission</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>

            <?= form_open('', 'id="doctorCommissionForm" autocomplete="off"') ?>
                <input type="hidden" name="id" id="doctor_commission_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label" for="doctor_id">Doctor <span class="text-danger">*</span></label>
                                <select name="doctor_id" id="doctor_id" class="form-control btSelect" data-live-search="true" title="Select Doctor">
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= (int) $doctor['id'] ?>">Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="doctor_idErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="commission_for">Commission For <span class="text-danger">*</span></label>
                                <select name="commission_for" id="commission_for" class="form-control btSelect" title="Select Commission For">
                                    <option value="CONSULTATION" selected>Consultation</option>
                                </select>
                                <div class="validation-error" id="commission_forErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="commission_type">Commission Type <span class="text-danger">*</span></label>
                                <select name="commission_type" id="commission_type" class="form-control btSelect" title="Select Commission Type">
                                    <option value="PERCENTAGE">Percentage</option>
                                    <option value="FIXED">Fixed Amount</option>
                                </select>
                                <div class="validation-error" id="commission_typeErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="commission_value">Commission Value <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="commissionValuePrefix" aria-hidden="true">%</span>
                                    <input type="number" name="commission_value" id="commission_value" class="form-control" min="0.01" step="0.01" inputmode="decimal" placeholder="Enter commission" aria-describedby="commission_valueErr">
                                </div>
                                <div class="validation-error" id="commission_valueErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="effective_from">Effective From <span class="text-danger">*</span></label>
                                <input type="text" name="effective_from" id="effective_from" class="form-control datepicker" placeholder="dd-mm-yyyy" data-submit-display-date="true" autocomplete="off" aria-describedby="effective_fromErr">
                                <div class="validation-error" id="effective_fromErr"></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="doctor_commission_status">Status</label>
                                <select name="status" id="doctor_commission_status" class="form-control btSelect" title="Select Status">
                                    <option value="1" selected>Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <div class="validation-error" id="statusErr"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary save"><i class="bi bi-check2 mr-1" aria-hidden="true"></i>Save Commission</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
