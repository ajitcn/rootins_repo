<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$filters = $filters ?? [];
$lookupOptions = $lookupOptions ?? ['forms' => [], 'strengths' => [], 'units' => [], 'manufacturers' => [], 'statuses' => []];
$prescriptionOptionSets = $prescriptionOptionSets ?? ['duration' => [], 'frequency' => [], 'dose' => [], 'intake' => []];
?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Masters & Configuration', 'page' => 'Medicine Master']) ?>

    <div class="action-btn-container d-flex flex-wrap justify-content-between align-items-center mb-3"><h1 class="h4 mb-2 mb-md-0">Medicine Master</h1><div><button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-toggle="modal" data-target="#medicineFilterModal"><i class="bi bi-funnel" aria-hidden="true"></i> Filters</button><?php if ($canManageLookups): ?><a class="btn btn-sm btn-outline-secondary mr-2" href="<?= base_url('admin/medicine-lookups') ?>"><i class="bi bi-list-check" aria-hidden="true"></i> Manage options</a><?php endif; ?><?php if ($canCreate): ?><button type="button" class="btn btn-sm theme-btn" id="openMedicineModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Medicine</button><?php endif; ?></div></div>

    <?= display_session_msg() ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="medicines-table">
            <thead class="thead-light"><tr><th>Medicine Name</th><th>Generic Name</th><th>Code</th><th>Dosage Form</th><th>Strength</th><th>Unit</th><th>Unit Price</th><th>Manufacturer</th><th>Status</th><th width="120">Action</th></tr></thead>
            <tbody>
                <?php if ($medicines === []): ?><tr><td colspan="10" class="text-center text-muted py-4">No medicines match the selected filters.</td></tr><?php endif; ?>
                <?php foreach ($medicines as $medicine): ?>
                    <tr>
                        <td><strong><?= esc($medicine['medicine_name']) ?></strong></td>
                        <td><?= esc($medicine['generic_name'] ?: '—') ?></td>
                        <td><?= esc($medicine['medicine_code'] ?: '—') ?></td>
                        <td><?= esc($medicine['dosage_form'] ?: '—') ?></td>
                        <td><?= esc($medicine['strength'] ?: '—') ?></td>
                        <td><?= esc($medicine['unit'] ?: '—') ?></td>
                        <td><?= esc(number_format((float) ($medicine['unit_price'] ?? 0), 2)) ?></td>
                        <td><?= esc($medicine['manufacturer'] ?: '—') ?></td>
                        <td><span class="badge <?= $medicine['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= esc(ucwords(str_replace('_', ' ', $medicine['status']))) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="tooltip" title="Created: <?= esc($medicine['created_at'] ?? '—') ?> | Updated: <?= esc($medicine['updated_at'] ?? '—') ?>" aria-label="Show created and updated dates"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></button>
                            <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-primary edit-medicine" data-id="<?= (int) $medicine['id'] ?>" aria-label="Edit <?= esc($medicine['medicine_name']) ?>">Edit</button><?php endif; ?>
                            <?php if ($canDelete): ?><button type="button" class="btn btn-sm btn-outline-danger delete-medicine" data-id="<?= (int) $medicine['id'] ?>" aria-label="Delete <?= esc($medicine['medicine_name']) ?>">Delete</button><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'medicines', 'filters' => $filters]) ?>
</main>

<div class="modal fade" id="medicineFilterModal" tabindex="-1" role="dialog" aria-labelledby="medicineFilterTitle" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form method="get" action="<?= current_url() ?>"><div class="modal-header"><h2 class="modal-title h5" id="medicineFilterTitle">Filter medicines</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="form-group"><label for="medicineFilterSearch">Search</label><input type="search" class="form-control" id="medicineFilterSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Name, generic name, code, or manufacturer"></div><div class="form-group"><label for="medicineFilterStatus">Status</label><select class="form-control btSelect" id="medicineFilterStatus" name="status" title="All statuses"><option value="">All statuses</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div><div class="modal-footer"><a href="<?= current_url() ?>" class="btn btn-outline-secondary mr-auto">Clear</a><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn theme-btn">Apply filters</button></div></form></div></div></div>

<?= form_open('', 'id="medicineCsrfForm" class="d-none"') ?><?= form_close() ?>
<?php if ($canCreate || $canEdit): ?>
<div class="modal fade ri-scroll-modal" id="medicineModal" tabindex="-1" role="dialog" aria-labelledby="medicineModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="medicineModalTitle">Add Medicine</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close medicine dialog"><span aria-hidden="true">&times;</span></button></div>
        <?= form_open('', 'id="medicineForm" autocomplete="off"') ?>
            <input type="hidden" name="id" id="medicine_id">
            <div class="modal-body"><div class="row">
                <div class="col-md-6 form-group"><label>Medicine Name <span class="text-danger">*</span></label><input name="medicine_name" class="form-control" maxlength="255"><div class="validation-error" id="medicine_nameErr"></div></div>
                <div class="col-md-6 form-group"><label>Generic Name</label><input name="generic_name" class="form-control" maxlength="255"><div class="validation-error" id="generic_nameErr"></div></div>
                <div class="col-md-4 form-group"><label>Medicine Code</label><input name="medicine_code" class="form-control" maxlength="50"><div class="validation-error" id="medicine_codeErr"></div></div>
                <div class="col-md-4 form-group"><label for="medicine_form_id">Form/type</label><select id="medicine_form_id" name="form_id" class="form-control btSelect" title="Select form/type"><option value="">Select form/type</option><?php foreach ($lookupOptions['forms'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="form_idErr"></div></div>
                <div class="col-md-4 form-group"><label for="medicine_strength_id">Strength</label><select id="medicine_strength_id" name="strength_id" class="form-control btSelect" title="Select strength"><option value="">Select strength</option><?php foreach ($lookupOptions['strengths'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="strength_idErr"></div></div>
                <div class="col-md-4 form-group"><label for="medicine_unit_price">Unit price</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="medicine_unit_price" name="unit_price" class="form-control" inputmode="decimal" placeholder="0.00" value="0.00" aria-describedby="unit_priceHelp unit_priceErr"></div><small id="unit_priceHelp" class="form-text text-muted">Use up to two decimals. Zero is allowed for no-charge medicines.</small><div class="validation-error" id="unit_priceErr"></div></div>
                <div class="col-md-4 form-group"><label for="medicine_unit_id">Unit</label><select id="medicine_unit_id" name="unit_id" class="form-control btSelect" title="Select unit"><option value="">Select unit</option><?php foreach ($lookupOptions['units'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="unit_idErr"></div></div>
                <div class="col-md-6 form-group"><label for="medicine_manufacturer_id">Manufacturer</label><select id="medicine_manufacturer_id" name="manufacturer_id" class="form-control btSelect" title="Select manufacturer"><option value="">Select manufacturer</option><?php foreach ($lookupOptions['manufacturers'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="manufacturer_idErr"></div></div>
                <div class="col-md-6 form-group"><label for="medicine_status_id">Status <span class="text-danger">*</span></label><select id="medicine_status_id" name="status_id" class="form-control btSelect" required><option value="">Select status</option><?php foreach ($lookupOptions['statuses'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="status_idErr"></div></div>
                <div class="col-md-3 form-group"><label for="medicine_prescription_duration_id">Default duration</label><select id="medicine_prescription_duration_id" name="prescription_duration_id" class="form-control btSelect" title="Select default duration" aria-describedby="prescription_duration_idErr"><option value="">Select duration</option><?php foreach ($prescriptionOptionSets['duration'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="prescription_duration_idErr"></div></div>
                <div class="col-md-3 form-group"><label for="medicine_prescription_frequency_id">Default frequency</label><select id="medicine_prescription_frequency_id" name="prescription_frequency_id" class="form-control btSelect" title="Select default frequency" aria-describedby="prescription_frequency_idErr"><option value="">Select frequency</option><?php foreach ($prescriptionOptionSets['frequency'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="prescription_frequency_idErr"></div></div>
                <div class="col-md-3 form-group"><label for="medicine_prescription_dose_id">Default dose</label><select id="medicine_prescription_dose_id" name="prescription_dose_id" class="form-control btSelect" title="Select default dose" aria-describedby="prescription_dose_idErr"><option value="">Select dose</option><?php foreach ($prescriptionOptionSets['dose'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="prescription_dose_idErr"></div></div>
                <div class="col-md-3 form-group"><label for="medicine_prescription_intake_id">Default intake</label><select id="medicine_prescription_intake_id" name="prescription_intake_id" class="form-control btSelect" title="Select default intake" aria-describedby="prescription_intake_idErr"><option value="">Select intake</option><?php foreach ($prescriptionOptionSets['intake'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="prescription_intake_idErr"></div></div>
                <div class="col-12 form-group"><label for="medicine_prescription_note">Default prescription note</label><textarea id="medicine_prescription_note" name="prescription_note" class="form-control" rows="2" maxlength="2000" aria-describedby="prescription_noteErr"></textarea><div class="validation-error" id="prescription_noteErr"></div></div>
                <div class="col-12 form-group"><label>Description</label><textarea name="description" class="form-control" rows="4"></textarea><div class="validation-error" id="descriptionErr"></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary save">Save Medicine</button></div>
        <?= form_close() ?>
    </div></div>
</div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/medicine/medicine.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
