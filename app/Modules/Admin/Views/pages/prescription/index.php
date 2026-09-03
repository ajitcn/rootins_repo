<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $filters = $filters ?? []; ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Clinical Records',
        'page'       => 'Prescriptions',
    ]) ?>

    <div class="action-btn-container d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-2 mb-md-0">Prescriptions</h1>
        <div><button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-toggle="modal" data-target="#prescriptionFilterModal"><i class="bi bi-funnel" aria-hidden="true"></i> Filters</button>
        <?php if ($canCreate): ?><button type="button" class="btn btn-sm theme-btn" id="openPrescriptionModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Prescription</button><?php endif; ?></div>
    </div>

    <?= display_session_msg() ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="prescriptions-table">
            <thead class="thead-light">
                <tr>
                    <th>Prescription No.</th><th>Consultation</th><th>Patient</th><th>Doctor</th>
                    <th>Clinic</th><th>Date</th><th>Status</th><th width="140">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($prescriptions === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No prescriptions match the selected filters.</td></tr><?php endif; ?>
                <?php foreach ($prescriptions as $prescription): ?>
                    <?php
                    $patientName = trim(implode(' ', array_filter([
                        $prescription['patient_first_name'] ?? '', $prescription['patient_middle_name'] ?? '', $prescription['patient_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $doctorName = trim(implode(' ', array_filter([
                        $prescription['doctor_first_name'] ?? '', $prescription['doctor_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $statusClass = $prescription['status'] === 'completed' ? 'badge-success' : 'badge-info';
                    ?>
                    <tr>
                        <td><strong><?= esc($prescription['prescription_number']) ?></strong></td>
                        <td><?= esc($prescription['consultation_number']) ?></td>
                        <td><strong><?= esc($patientName) ?></strong><br><small class="text-muted"><?= esc($prescription['registration_number']) ?></small></td>
                        <td><?= esc($doctorName) ?></td>
                        <td><?= esc($prescription['clinic_name']) ?></td>
                        <td><?= esc($prescription['prescription_date']) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $prescription['status']))) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="tooltip" title="Created: <?= esc($prescription['created_at'] ?? '—') ?> | Updated: <?= esc($prescription['updated_at'] ?? '—') ?>" aria-label="Show created and updated dates"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></button>
                            <a href="<?= base_url('admin/prescriptions/view/' . (int) $prescription['id']) ?>" class="btn btn-sm btn-outline-primary" aria-label="View prescription <?= esc($prescription['prescription_number']) ?>">View</a>
                            <?php if ($canEdit && $prescription['status'] !== 'completed'): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-prescription" data-id="<?= (int) $prescription['id'] ?>" aria-label="Edit prescription <?= esc($prescription['prescription_number']) ?>">Edit</button>
                            <?php endif; ?>
                            <?php if ($canDelete && $prescription['status'] !== 'completed'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-prescription" data-id="<?= (int) $prescription['id'] ?>" aria-label="Delete prescription <?= esc($prescription['prescription_number']) ?>">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'prescriptions', 'filters' => $filters]) ?>
</main>

<?= view(ADMIN_VIEW . 'parts/clinical_filter', ['prefix' => 'prescription', 'title' => 'Filter prescriptions', 'statuses' => ['draft', 'completed'], 'clinics' => $clinics ?? [], 'doctors' => $doctors ?? [], 'filters' => $filters]) ?>
<?= form_open('', 'id="prescriptionCsrfForm" class="d-none"') ?><?= form_close() ?>

<?php if ($canCreate || $canEdit): ?>
    <div class="modal fade" id="prescriptionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Prescription</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?= form_open('', 'id="prescriptionForm" autocomplete="off" data-medicine-lookup="' . ($canMedicineLookup ? '1' : '0') . '"') ?>
                    <input type="hidden" name="id" id="prescription_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="prescription_consultation_id">Consultation <span class="text-danger">*</span></label>
                                    <select name="consultation_id" id="prescription_consultation_id" class="form-control btSelect" data-live-search="true" title="Select Consultation">
                                        <option value="">Select Consultation</option>
                                        <?php foreach ($consultations as $consultation): ?>
                                            <?php $patientName = trim(($consultation['patient_first_name'] ?? '') . ' ' . ($consultation['patient_last_name'] ?? '')); ?>
                                            <option value="<?= (int) $consultation['id'] ?>">
                                                <?= esc($consultation['consultation_number'] . ' - ' . $patientName . ' - ' . $consultation['clinic_name'] . ' (' . $consultation['consultation_date'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-error" id="consultation_idErr"></div>
                                    <small class="form-text text-muted">Patient, doctor, and clinic are taken from this consultation.</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="prescription_date">Date <span class="text-danger">*</span></label>
                                    <input type="text" name="prescription_date" id="prescription_date" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
                                    <div class="validation-error" id="prescription_dateErr"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="prescription_status">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="prescription_status" class="form-control btSelect">
                                        <option value="draft">Draft</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                    <div class="validation-error" id="statusErr"></div>
                                </div>
                            </div>
                            <div class="col-12"><div class="alert alert-light border" id="consultationSummary">Select a consultation to see its linked patient, doctor, and clinic.</div></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Prescription Items</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addPrescriptionItem"><i class="bi bi-plus"></i> Add Item</button>
                        </div>
                        <div class="validation-error mb-2" id="itemsErr"></div>
                        <div id="prescriptionItemRows">
                            <div class="prescription-item-row border rounded p-3 mb-2" data-index="0">
                                <div class="row align-items-end">
                                    <div class="col-md-3 position-relative"><label>Medicine Name <span class="text-danger">*</span></label><?php if ($canMedicineLookup): ?><input type="search" class="form-control medicine-search" placeholder="Search medicine master" autocomplete="off"><div class="medicine-suggestions list-group position-absolute w-100 shadow-sm bg-white" style="z-index: 1051;"></div><?php endif; ?><input name="items[0][medicine_name]" class="form-control" maxlength="255"><div class="validation-error item-error-medicine_name"></div></div>
                                    <div class="col-md-2"><label>Dosage</label><input name="items[0][dosage]" class="form-control" maxlength="100"><div class="validation-error item-error-dosage"></div></div>
                                    <div class="col-md-2"><label>Frequency</label><input name="items[0][frequency]" class="form-control" maxlength="100"><div class="validation-error item-error-frequency"></div></div>
                                    <div class="col-md-1"><label>Duration</label><input name="items[0][duration]" class="form-control" maxlength="50"><div class="validation-error item-error-duration"></div></div>
                                    <div class="col-md-2"><label>Duration Unit</label><input name="items[0][duration_unit]" class="form-control" maxlength="30"><div class="validation-error item-error-duration_unit"></div></div>
                                    <div class="col-md-1"><label>Quantity</label><input type="number" step="0.01" min="0" name="items[0][quantity]" class="form-control"><div class="validation-error item-error-quantity"></div></div>
                                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-prescription-item" title="Remove item"><i class="bi bi-trash"></i></button></div>
                                    <div class="col-md-6 mt-2"><label>Instructions</label><textarea name="items[0][instructions]" class="form-control" rows="2"></textarea></div>
                                    <div class="col-md-6 mt-2"><label>Notes</label><textarea name="items[0][notes]" class="form-control" rows="2"></textarea></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-3"><label for="prescription_notes">Prescription Notes</label><textarea name="notes" id="prescription_notes" class="form-control" rows="3"></textarea><div class="validation-error" id="notesErr"></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary save">Save Prescription</button></div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/prescription/prescription.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
