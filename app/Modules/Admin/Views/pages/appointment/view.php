<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $appointment['patient_first_name'] ?? '',
    $appointment['patient_middle_name'] ?? '',
    $appointment['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$doctorName = trim(implode(' ', array_filter([
    $appointment['doctor_first_name'] ?? '',
    $appointment['doctor_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$patientMobile = trim((string) ($appointment['patient_mobile'] ?? '')) ?: 'Not provided';
$patientEmail = trim((string) ($appointment['patient_email'] ?? '')) ?: 'Not provided';
?>
<main class="dashboard-container admin-appointment-page">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Appointment Management', 'page' => 'Appointment Details']) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/appointments/calendar') ?>" class="btn btn-sm theme-btn"><i class="bi bi-arrow-left"></i> Back to Calendar</a>
        <a href="<?= base_url('admin/appointments') ?>" class="btn btn-sm btn-outline-secondary">List View</a>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Appointment <?= esc($appointment['appointment_number']) ?></h5></div>
        <div class="card-body">
            <div class="row">
                <?php foreach ([
                    'Patient' => $patientName . ' (' . ($appointment['registration_number'] ?? '') . ')',
                    'Patient mobile' => $patientMobile,
                    'Patient email' => $patientEmail,
                    'Doctor' => $doctorName . ' (' . ($appointment['doctor_code'] ?? '') . ')',
                    'Clinic' => $appointment['clinic_name'] ?? '',
                    'Date' => rootins_format_date($appointment['appointment_date'] ?? ''),
                    'Time' => substr((string) ($appointment['appointment_time'] ?? ''), 0, 5),
                    'Duration' => ($appointment['duration_minutes'] ?? '') . ' minutes',
                    'Appointment Type' => ucwords(str_replace('_', ' ', $appointment['appointment_type'] ?? '')),
                    'Status' => ucwords(str_replace('_', ' ', $appointment['status'] ?? '')),
                ] as $label => $value): ?>
                    <div class="col-md-6 mb-3"><strong><?= esc($label) ?></strong><div><?= esc($value) ?></div></div>
                <?php endforeach; ?>
                <div class="col-12 mb-3"><strong>Patient Source</strong><div><span class="badge <?= esc(patient_source_badge_class($appointment['patient_source_type'] ?? null)) ?>"><?= esc(patient_source_label($appointment['patient_source_type'] ?? null)) ?></span></div></div><div class="col-12 mb-3"><strong>Reason for Visit</strong><div><?= nl2br(esc($appointment['reason_for_visit'] ?: '—')) ?></div></div>
                <div class="col-12 mb-3"><strong>Notes</strong><div><?= nl2br(esc($appointment['notes'] ?: '—')) ?></div></div>
                <?php if ($appointment['status'] === 'cancelled'): ?>
                    <div class="col-12 mb-3"><strong>Cancellation Reason</strong><div><?= nl2br(esc($appointment['cancellation_reason'] ?: '—')) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
