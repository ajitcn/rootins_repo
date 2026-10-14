<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="doctor-dashboard-title">
    <?php
    $doctorName = trim(implode(' ', array_filter([
        $doctor['first_name'] ?? '',
        $doctor['last_name'] ?? '',
    ], static fn ($part) => $part !== null && $part !== '')));
?>

    <div class="page-heading">
        <div><div class="page-eyebrow">Doctor workspace</div>
        <h1 id="doctor-dashboard-title" class="h3 mb-1">Welcome, Dr. <?= esc($doctorName) ?></h1>
        <div class="text-muted">Doctor code: <?= esc($doctor['doctor_code'] ?? '') ?></div>
        </div><a class="btn btn-primary" href="<?= base_url('doctor/clinical') ?>"><i class="bi bi-clipboard2-pulse mr-1" aria-hidden="true"></i> Open clinical workspace</a>
    </div>

    <div class="row mb-4">
        <?php foreach ([
            ['Today\'s appointments', $appointmentCount, 'doctor/appointments', 'bi-calendar-check'],
            ['Today\'s consultations', $consultationCount, 'doctor/clinical', 'bi-clipboard2-pulse'],
            ['Today\'s prescriptions', $prescriptionCount, 'doctor/prescriptions', 'bi-prescription2'],
            ['Patients', $patientCount, 'doctor/patients', 'bi-people'],
            ['Follow-ups', $followUpCount, 'doctor/follow-ups', 'bi-calendar2-check'],
            ['Invoices', $billingCount, 'doctor/billing', 'bi-receipt'],
        ] as [$label, $count, $url, $icon]): ?>
            <div class="col-12 col-sm-6 col-xl-4 mb-3">
                <a class="card ri-stat-card h-100 text-decoration-none" href="<?= base_url($url) ?>" aria-label="<?= esc($label) ?>">
                    <div class="card-body">
                        <div class="text-muted small"><?= esc($label) ?></div>
                        <div class="ri-stat-value mt-2"><?= (int) $count ?></div><span class="ri-stat-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 mb-0">Upcoming assigned appointments</h2><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/appointments') ?>">View all</a></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Appointment</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No upcoming appointments.</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <?php $patientName = trim(implode(' ', array_filter([
                                $appointment['patient_first_name'] ?? '',
                                $appointment['patient_middle_name'] ?? '',
                                $appointment['patient_last_name'] ?? '',
                            ], static fn ($part) => $part !== null && $part !== ''))); ?>
                            <?php $patientContact = service('telephoneContact')->formatDisplay($appointment['patient_telephone_country_code'] ?? null, $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null, $appointment['patient_legacy_country_code'] ?? null); ?>
                            <tr>
                                <td><a href="<?= base_url('doctor/appointments/' . (int) ($appointment['id'] ?? 0)) ?>"><?= esc($appointment['appointment_number'] ?? '') ?></a></td>
                                <td><?= esc($patientName) ?> <span class="text-muted small"><?= esc($appointment['registration_number'] ?? '') ?></span><br><small class="text-muted"><?= esc($patientContact) ?></small></td>
                                <td><?= esc(rootins_format_date($appointment['appointment_date'] ?? '')) ?></td>
                                <td><?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></td>
                                <td><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? '')))) ?></td>
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
