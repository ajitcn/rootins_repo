<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $patientName = trim(implode(' ', array_filter([
    $appointment['patient_first_name'] ?? '',
    $appointment['patient_middle_name'] ?? '',
    $appointment['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== ''))); ?>

<main class="dashboard-container" aria-labelledby="doctor-appointment-title">
    <div class="page-heading">
        <div><a href="<?= base_url('doctor') ?>" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>Back to dashboard</a>
        <div class="page-eyebrow">Appointment / assigned patient</div><h1 id="doctor-appointment-title" class="h3 mb-1">Appointment <?= esc($appointment['appointment_number'] ?? '') ?></h1>
        <div class="text-muted">Assigned patient appointment details</div></div>
        <span class="badge badge-primary"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Clinic scoped</span>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Appointment</h2></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Patient</strong><div><?= esc($patientName) ?> <span class="text-muted small"><?= esc($appointment['registration_number'] ?? '') ?></span></div></div>
                <div class="col-md-6 mb-3"><strong>Date and time</strong><div><?= esc($appointment['appointment_date'] ?? '') ?> <?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Type</strong><div><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? '')))) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Status</strong><div><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['status'] ?? '')))) ?></div></div>
                <div class="col-12"><strong>Reason for visit</strong><div><?= nl2br(esc($appointment['reason_for_visit'] ?? '')) ?: '<span class="text-muted">Not provided.</span>' ?></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Consultation</h5></div><div class="card-body">
            <?php if ($consultation === null): ?><span class="text-muted">No consultation linked.</span><?php else: ?>
                <div><?= esc($consultation['consultation_number'] ?? '') ?></div><div><?= esc($consultation['consultation_date'] ?? '') ?></div><div><?= esc(ucwords(str_replace('_', ' ', (string) ($consultation['status'] ?? '')))) ?></div>
            <?php endif; ?>
        </div></div></div>
        <div class="col-md-6 mb-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Prescription</h5></div><div class="card-body">
            <?php if ($prescription === null): ?><span class="text-muted">No prescription linked.</span><?php else: ?>
                <div><?= esc($prescription['prescription_number'] ?? '') ?></div><div><?= esc($prescription['prescription_date'] ?? '') ?></div><div><?= esc(ucwords(str_replace('_', ' ', (string) ($prescription['status'] ?? '')))) ?></div>
            <?php endif; ?>
        </div></div></div>
    </div>
</main>
<?= $this->endSection() ?>
