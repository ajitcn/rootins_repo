<div class="modal fade" id="consultationFeeModal" tabindex="-1">

    <div class="modal-dialog modal-md">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="consultationFeeModalLabel">
                    Add Consultation Fee
                </h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>


            <?= form_open('', 'id="consultationFeeForm" autocomplete="off"')?>

                <input type="hidden" name="id" id="consultation_fee_id">


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


                        <!-- Consultation Type -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    Consultation Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="consultation_type" id="consultation_type" class="form-control btSelect" title="Select Type">


                                    <option value="NEW">
                                        New
                                    </option>

                                    <option value="FOLLOWUP">
                                        Follow-up
                                    </option>

                                    <option value="ALL">
                                        All
                                    </option>

                                </select>
                                <div class="validation-error" id="consultation_typeErr"></div>
                            </div>
                        </div>


                        <!-- Consultation Mode -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    Consultation Mode
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="consultation_mode" id="consultation_mode" class="form-control btSelect" title="Select Mode">



                                    <option value="OFFLINE">
                                        Offline
                                    </option>

                                    <option value="ONLINE">
                                        Online
                                    </option>

                                    <option value="HOME_VISIT">
                                        Home Visit
                                    </option>

                                </select>
                                <div class="validation-error" id="consultation_modeErr"></div>
                            </div>
                        </div>


                        <!-- Fee -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    Consultation Fee
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                    ₹
                                </span>

                                    <input type="number" name="fee" id="consultation_fee" class="form-control" min="0" step="0.01" placeholder="Enter fee">

                                </div>
                                <div class="validation-error" id="feeErr"></div>

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

                                <select name="status" id="consultation_fee_status" class="form-control btSelect" title="Select Status">

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

                        Save Fee

                    </button>

                </div>

                <?= form_close()?>

        </div>

    </div>

</div>