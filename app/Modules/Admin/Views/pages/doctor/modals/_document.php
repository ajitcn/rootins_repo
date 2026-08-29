<div class="modal fade" id="doctorDocumentModal" tabindex="-1">

    <div class="modal-dialog modal-md">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="doctorDocumentModalLabel">
                    Add Doctor Document
                </h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>


            <?= form_open(
                '',
                'id="doctorDocumentForm" enctype="multipart/form-data" autocomplete="off"'
            ) ?>

                <input type="hidden" name="id" id="doctor_document_id">


                <div class="modal-body">

                    <div class="row">

                        <!-- Doctor -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label class="form-label">
                                    Doctor
                                    <span class="text-danger">*</span>
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


                        <!-- Document Type -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Document Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="document_type" id="document_type" class="form-control btSelect" title="Select Document Type">

                                   
                                    <option value="REGISTRATION">
                                        Registration Certificate
                                    </option>

                                    <option value="DEGREE">
                                        Degree / Qualification
                                    </option>

                                    <option value="LICENSE">
                                        Medical License
                                    </option>

                                    <option value="ID_PROOF">
                                        ID Proof
                                    </option>

                                    <option value="EXPERIENCE">
                                        Experience Certificate
                                    </option>

                                    <option value="OTHER">
                                        Other
                                    </option>

                                </select>

                                <div class="validation-error" id="document_typeErr"></div>

                            </div>

                        </div>


                        <!-- Document Name -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Document Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" name="document_name" id="document_name" class="form-control" maxlength="255" placeholder="Enter document name">

                                <div class="validation-error" id="document_nameErr"></div>

                            </div>

                        </div>


                        <!-- Document File -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label class="form-label">
                                    Document
                                    <span class="text-danger" id="documentRequired">*</span>
                                </label>

                                <input type="file" name="document" id="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">

                                <small class="form-text text-muted">
                                    Allowed: PDF, JPG, JPEG, PNG, DOC, DOCX.
                                    Maximum size: 10 MB.
                                </small>

                                <div class="validation-error" id="documentErr"></div>

                                <div id="currentDocument" class="mt-2" style="display:none;">

                                    <small class="text-muted">
                                        Current Document:
                                    </small>

                                    <span id="currentDocumentName"></span>

                                </div>

                            </div>

                        </div>


                        <!-- Remarks -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label class="form-label">
                                    Remarks
                                </label>

                                <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Enter remarks"></textarea>

                                <div class="validation-error" id="remarksErr"></div>

                            </div>

                        </div>


                        <!-- Status -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label class="form-label">
                                    Status
                                </label>

                                <select name="status" id="doctor_document_status" class="form-control btSelect" title="Select Status">

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

                        Save Document

                    </button>

                </div>

                <?= form_close() ?>

        </div>

    </div>

</div>