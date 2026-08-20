<div class="modal fade" id="doctorCommissionModal" tabindex="-1">

    <div class="modal-dialog modal-md">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="doctorCommissionModalLabel">
                    Add Doctor Commission
                </h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>


            <?= form_open('', 'id="doctorCommissionForm" autocomplete="off"') ?>

                <input type="hidden" name="id" id="doctor_commission_id">


                <div class="modal-body">

                    <div class="row">

                        <!-- Doctor -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label class="form-label">
                                    Doctor <span class="text-danger">*</span>
                                </label>

                                <select name="doctor_id" id="doctor_id" class="form-control btSelect" data-live-search="true" title="Select Doctor">

                                    <?php foreach ($doctors as $doctor): ?>

                                        <option value="<?= $doctor['id'] ?>">
                                            Dr.
                                            <?= esc(
                                                trim(
                                                    $doctor['first_name'] . ' ' .
                                                    $doctor['last_name']
                                                )
                                            ) ?>
                                        </option>

                                        <?php endforeach; ?>

                                </select>

                                <div class="validation-error" id="doctor_idErr"></div>

                            </div>

                        </div>


                        <!-- Commission For -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Commission For
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="commission_for" id="commission_for" class="form-control btSelect" title="Select Commission For">

                                    <option value="CONSULTATION" selected>
                                        Consultation
                                    </option>

                                    <!-- <option value="SERVICE">
                                        Service
                                    </option>

                                    <option value="PROCEDURE">
                                        Procedure
                                    </option> -->

                                </select>

                                <div class="validation-error" id="commission_forErr"></div>

                            </div>

                        </div>


                        <!-- Commission Type -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Commission Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="commission_type" id="commission_type" class="form-control btSelect" title="Select Commission Type">

                                    <option value="PERCENTAGE">
                                        Percentage
                                    </option>

                                    <option value="FIXED">
                                        Fixed Amount
                                    </option>

                                </select>

                                <div class="validation-error" id="commission_typeErr"></div>

                            </div>

                        </div>


                        <!-- Reference -->
                        <!-- <div class="col-md-12" id="commissionReferenceWrapper">

                            <div class="form-group">

                                <label class="form-label" id="commissionReferenceLabel">
                                    Service / Procedure
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="reference_id" id="reference_id" class="form-control btSelect" data-live-search="true" title="Select Service / Procedure">

                                    <option value="">
                                        Select Service / Procedure
                                    </option>

                                </select>

                                <div class="validation-error" id="reference_idErr"></div>

                            </div>

                        </div> -->


                        <!-- Commission Value -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Commission Value
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    

                                    <input type="number" name="commission_value" id="commission_value" class="form-control" min="0" step="0.01" placeholder="Enter commission">

                                </div>

                                <div class="validation-error" id="commission_valueErr"></div>

                            </div>

                        </div>


                        <!-- Effective From -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Effective From
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" name="effective_from" id="effective_from" class="form-control datepicker" placeholder="dd-mm-yyyy">

                                <div class="validation-error" id="effective_fromErr"></div>

                            </div>

                        </div> 

                        <!-- Status -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Status
                                </label>

                                <select name="status" id="doctor_commission_status" class="form-control btSelect" title="Select Status">

                                    <option value="1" selected>
                                        Active
                                    </option>

                                    <option value="0">
                                        Inactive
                                    </option>

                                </select>

                                <div class="validation-error" id="statusErr"></div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button type="submit" class="btn theme-btn save">

                        Save Commission

                    </button>

                </div>

                <?= form_close() ?>

        </div>

    </div>

</div>