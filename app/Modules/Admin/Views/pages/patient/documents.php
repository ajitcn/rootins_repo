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
        'page'       => 'Patient Documents',
    ]) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/patients/view/' . (int) $patient['id']) ?>" class="btn btn-sm theme-btn">
            <i class="bi bi-arrow-left"></i> Back to Patient Details
        </a>
        <button type="button" class="btn btn-sm btn-primary" id="openPatientDocumentModal">
            <i class="bi bi-upload"></i> Upload Document
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
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th width="60">#</th>
                    <th>Document Type</th>
                    <th>Title</th>
                    <th>File</th>
                    <th>Uploaded On</th>
                    <th width="180">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $key => $document): ?>
                    <tr>
                        <td><?= $key + 1 ?></td>
                        <td><?= esc($document['document_type'] ?? '') ?></td>
                        <td><?= esc($document['title'] ?? '') ?></td>
                        <td><?= esc($document['file_name'] ?? '') ?></td>
                        <td><?= esc($document['created_at'] ?? '') ?></td>
                        <td>
                            <a href="<?= base_url('admin/patients/' . (int) $patient['id'] . '/documents/download/' . (int) $document['id']) ?>"
                               class="btn btn-sm btn-info"
                               target="_blank"
                               rel="noopener"
                               title="View or download document">
                                <i class="bi bi-download"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-sm btn-primary edit-patient-document"
                                    data-id="<?= (int) $document['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-danger delete-patient-document"
                                    data-id="<?= (int) $document['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal fade" id="patientDocumentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Patient Document</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?= form_open('', 'id="patientDocumentForm" enctype="multipart/form-data" autocomplete="off"') ?>
                <input type="hidden" name="id" id="patient_document_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="patient_document_type">Document Type <span class="text-danger">*</span></label>
                        <input type="text" name="document_type" id="patient_document_type" class="form-control" maxlength="100">
                        <div class="validation-error" id="document_typeErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="patient_document_title">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="patient_document_title" class="form-control" maxlength="255">
                        <div class="validation-error" id="titleErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="patient_document_file">Document <span class="text-danger" id="patientDocumentRequired">*</span></label>
                        <input type="file" name="document" id="patient_document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="form-text text-muted">Allowed: PDF, JPG, JPEG, PNG, DOC, DOCX. Maximum size: 10 MB.</small>
                        <div class="validation-error" id="documentErr"></div>
                        <div id="patientCurrentDocument" class="mt-2" style="display:none;">
                            <small class="text-muted">Current Document:</small>
                            <span id="patientCurrentDocumentName"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary save">Upload Document</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
    window.patientDocumentId = <?= (int) $patient['id'] ?>;
</script>
<script src="<?= base_url('assets/js/admin/patient/patient_document.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
