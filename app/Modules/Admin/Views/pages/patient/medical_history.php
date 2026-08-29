<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>

<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'Medical History',
    ]) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/patients/view/' . (int) $patient['id']) ?>" class="btn btn-sm theme-btn">
            <i class="bi bi-arrow-left"></i> Back to Patient Details
        </a>
        <button type="button" class="btn btn-sm btn-primary" id="openMedicalHistoryModal">
            <i class="bi bi-plus-circle"></i> Add History
        </button>
    </div>

    <?= display_session_msg() ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Patient</strong>
                    <div><?= esc($patientName) ?></div>
                </div>
                <div class="col-md-6">
                    <strong>Registration Number</strong>
                    <div><?= esc($patient['registration_number'] ?? '') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable">
            <thead class="thead-light">
                <tr>
                    <th width="60">#</th>
                    <th>History Date</th>
                    <th>Title</th>
                    <th>Details</th>
                    <th>Created On</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($histories as $key => $history): ?>
                    <tr>
                        <td><?= $key + 1 ?></td>
                        <td><?= esc($history['history_date'] ?? '') ?></td>
                        <td><?= esc($history['title'] ?? '') ?></td>
                        <td><?= nl2br(esc($history['details'] ?? '')) ?></td>
                        <td><?= esc($history['created_at'] ?? '') ?></td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary edit-medical-history"
                                    data-id="<?= (int) $history['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal fade" id="medicalHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Medical History</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="medicalHistoryForm">
                <?= csrf_field() ?>
                <input type="hidden" name="history_id" id="medical_history_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="medical_history_title">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="medical_history_title">
                        <div class="validation-error" id="titleErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="medical_history_date">History Date</label>
                        <input type="date" class="form-control" name="history_date" id="medical_history_date">
                        <div class="validation-error" id="history_dateErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="medical_history_details">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="details" id="medical_history_details" rows="5"></textarea>
                        <div class="validation-error" id="detailsErr"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save History</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
    window.patientMedicalHistoryId = <?= (int) $patient['id'] ?>;
</script>
<script src="<?= base_url('assets/js/admin/patient/patient_medical_history.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
