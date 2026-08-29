<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Masters & Configuration', 'page' => 'Medicine Master']) ?>

    <?php if ($canCreate): ?>
        <div class="action-btn-container"><button type="button" class="btn btn-sm theme-btn" id="openMedicineModal">+ New Medicine</button></div>
    <?php endif; ?>

    <?= display_session_msg() ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable">
            <thead class="thead-light"><tr><th>Medicine Name</th><th>Generic Name</th><th>Code</th><th>Dosage Form</th><th>Strength</th><th>Manufacturer</th><th>Status</th><th width="120">Action</th></tr></thead>
            <tbody>
                <?php foreach ($medicines as $medicine): ?>
                    <tr>
                        <td><strong><?= esc($medicine['medicine_name']) ?></strong></td>
                        <td><?= esc($medicine['generic_name'] ?: '—') ?></td>
                        <td><?= esc($medicine['medicine_code'] ?: '—') ?></td>
                        <td><?= esc($medicine['dosage_form'] ?: '—') ?></td>
                        <td><?= esc($medicine['strength'] ?: '—') ?></td>
                        <td><?= esc($medicine['manufacturer'] ?: '—') ?></td>
                        <td><span class="badge <?= $medicine['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= esc(ucwords(str_replace('_', ' ', $medicine['status']))) ?></span></td>
                        <td>
                            <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-primary edit-medicine" data-id="<?= (int) $medicine['id'] ?>" title="Edit Medicine"><i class="bi bi-pencil-square"></i></button><?php endif; ?>
                            <?php if ($canDelete): ?><button type="button" class="btn btn-sm btn-danger delete-medicine" data-id="<?= (int) $medicine['id'] ?>" title="Delete Medicine"><i class="bi bi-trash"></i></button><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?= form_open('', 'id="medicineCsrfForm" class="d-none"') ?><?= form_close() ?>
<?php if ($canCreate || $canEdit): ?>
<div class="modal fade" id="medicineModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add Medicine</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
        <?= form_open('', 'id="medicineForm" autocomplete="off"') ?>
            <input type="hidden" name="id" id="medicine_id">
            <div class="modal-body"><div class="row">
                <div class="col-md-6 form-group"><label>Medicine Name <span class="text-danger">*</span></label><input name="medicine_name" class="form-control" maxlength="255"><div class="validation-error" id="medicine_nameErr"></div></div>
                <div class="col-md-6 form-group"><label>Generic Name</label><input name="generic_name" class="form-control" maxlength="255"><div class="validation-error" id="generic_nameErr"></div></div>
                <div class="col-md-4 form-group"><label>Medicine Code</label><input name="medicine_code" class="form-control" maxlength="50"><div class="validation-error" id="medicine_codeErr"></div></div>
                <div class="col-md-4 form-group"><label>Dosage Form</label><input name="dosage_form" class="form-control" maxlength="100"><div class="validation-error" id="dosage_formErr"></div></div>
                <div class="col-md-4 form-group"><label>Strength</label><input name="strength" class="form-control" maxlength="100"><div class="validation-error" id="strengthErr"></div></div>
                <div class="col-md-6 form-group"><label>Manufacturer</label><input name="manufacturer" class="form-control" maxlength="255"><div class="validation-error" id="manufacturerErr"></div></div>
                <div class="col-md-6 form-group"><label>Status <span class="text-danger">*</span></label><select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select><div class="validation-error" id="statusErr"></div></div>
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
