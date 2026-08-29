<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $consultation['patient_first_name'] ?? '',
    $consultation['patient_middle_name'] ?? '',
    $consultation['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$doctorName = trim(implode(' ', array_filter([
    $consultation['doctor_first_name'] ?? '',
    $consultation['doctor_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'Consultation Details',
    ]) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/consultations') ?>" class="btn btn-sm theme-btn">
            <i class="bi bi-arrow-left"></i> Back to Consultations
        </a>
        <?php if ($canCreatePrescription): ?>
            <a href="<?= base_url('admin/prescriptions') ?>?consultation_id=<?= (int) $consultation['id'] ?>" class="btn btn-sm theme-btn">
                <i class="bi bi-prescription2"></i> Create Prescription
            </a>
        <?php endif; ?>
        <?php if ($canEdit && $consultation['status'] !== 'completed'): ?>
            <a href="<?= base_url('admin/consultations') ?>?edit_id=<?= (int) $consultation['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil-square"></i> Edit Consultation
            </a>
        <?php endif; ?>
        <?php if ($canDelete && $consultation['status'] !== 'completed'): ?>
            <button type="button" class="btn btn-sm btn-danger delete-consultation" data-id="<?= (int) $consultation['id'] ?>">
                <i class="bi bi-trash"></i> Delete Consultation
            </button>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Consultation <?= esc($consultation['consultation_number']) ?></h5></div>
        <div class="card-body">
            <div class="row">
                <?php foreach ([
                    'Patient' => $patientName . ' (' . ($consultation['registration_number'] ?? '') . ')',
                    'Doctor'  => $doctorName . ' (' . ($consultation['doctor_code'] ?? '') . ')',
                    'Clinic'  => $consultation['clinic_name'] ?? '',
                    'Appointment' => $consultation['appointment_number'] ?? '—',
                    'Date' => $consultation['consultation_date'] ?? '',
                    'Time' => substr((string) ($consultation['consultation_time'] ?? ''), 0, 5) ?: '—',
                    'Status' => ucwords(str_replace('_', ' ', $consultation['status'] ?? '')),
                    'Follow-up Date' => $consultation['follow_up_date'] ?: '—',
                ] as $label => $value): ?>
                    <div class="col-md-6 mb-3"><strong><?= esc($label) ?></strong><div><?= esc($value) ?></div></div>
                <?php endforeach; ?>
                <?php foreach ([
                    'Chief Complaint' => 'chief_complaint',
                    'Clinical Notes'  => 'clinical_notes',
                    'Diagnosis'       => 'diagnosis',
                    'Treatment Plan'  => 'treatment_plan',
                ] as $label => $field): ?>
                    <div class="col-md-6 mb-3"><strong><?= esc($label) ?></strong><div><?= nl2br(esc($consultation[$field] ?? '—')) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<?= form_open('', 'id="consultationCsrfForm" class="d-none"') ?><?= form_close() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/consultation/consultation.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
