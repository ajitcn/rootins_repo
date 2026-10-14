<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $scopeLabel = $clinicId > 0 ? 'Assigned clinic' : 'All permitted clinics'; ?>

<main class="dashboard-container p-3">
    <div class="mb-4">
        <h4 class="mb-1">Operational overview</h4>
        <div class="text-muted small"><?= esc($scopeLabel) ?> · Today: <?= esc(date('d M Y')) ?></div>
    </div>

    <div class="row mb-4">
        <?php foreach ([
            ['label' => 'Active patients', 'value' => $activePatients],
            ['label' => 'Appointments today', 'value' => $appointmentsToday],
            ['label' => 'Completed today', 'value' => $completedToday],
            ['label' => 'Cancelled today', 'value' => $cancelledToday],
            ['label' => 'Consultations today', 'value' => $consultationsToday],
            ['label' => 'Prescription review queue', 'value' => $pendingReviews],
        ] as $metric): ?>
            <div class="col-12 col-sm-6 col-lg-4 mb-3">
                <div class="tblr-card p-3 h-100">
                    <div class="stat-title"><?= esc($metric['label']) ?></div>
                    <div class="stat-value"><?= (int) $metric['value'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="tblr-card">
        <div class="tblr-card-header"><h6 class="tblr-card-title">Upcoming appointments</h6></div>
        <div class="table-responsive">
            <table class="table table-tabler mb-0">
                <thead><tr><th>Appointment</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if ($appointments === []): ?>
                        <tr><td colspan="5" class="text-center text-muted">No upcoming appointments.</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <?php $patientName = trim(implode(' ', array_filter([
                                $appointment['patient_first_name'] ?? '',
                                $appointment['patient_middle_name'] ?? '',
                                $appointment['patient_last_name'] ?? '',
                            ], static fn ($part) => $part !== null && $part !== ''))); ?>
                            <tr>
                                <td><?= esc($appointment['appointment_number'] ?? '') ?></td>
                                <td><?= esc($patientName) ?></td>
                                <td><?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?></td>
                                <td><?= esc(rootins_format_date($appointment['appointment_date'] ?? '')) ?> <?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></td>
                                <td><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['status'] ?? '')))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
