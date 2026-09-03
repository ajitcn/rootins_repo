<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>

<main class="dashboard-container" aria-labelledby="patient-dashboard-title">
    <div class="page-heading">
        <div><div class="page-eyebrow">Patient portal</div><h1 id="patient-dashboard-title" class="h3 mb-1">Welcome, <?= esc($patientName) ?></h1>
        <div class="text-muted">
            Registration: <?= esc($patient['registration_number'] ?? '') ?>
            · Clinic: <?= esc($patient['clinic_name'] ?? '') ?>
        </div>
        </div><span class="badge badge-primary"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Secure clinic portal</span>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Upcoming appointments</h5></div>
                <div class="card-body">
                    <?php if ($appointments === []): ?>
                        <p class="text-muted mb-0">No upcoming appointments.</p>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="font-weight-bold"><?= esc($appointment['appointment_date'] ?? '') ?> <?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></div>
                                <div><?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?></div>
                                <small class="text-muted"><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? '')))) ?> · <?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['status'] ?? '')))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Visit history</h5></div>
                <div class="card-body">
                    <?php if ($visits === []): ?>
                        <p class="text-muted mb-0">No visits found.</p>
                    <?php else: ?>
                        <?php foreach ($visits as $visit): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="font-weight-bold"><?= esc($visit['consultation_date'] ?? '') ?></div>
                                <div><?= esc(trim(($visit['doctor_first_name'] ?? '') . ' ' . ($visit['doctor_last_name'] ?? ''))) ?></div>
                                <small class="text-muted"><?= esc(ucwords(str_replace('_', ' ', (string) ($visit['status'] ?? '')))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Prescriptions</h5></div>
                <div class="card-body">
                    <?php if ($prescriptions === []): ?>
                        <p class="text-muted mb-0">No prescriptions found.</p>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="font-weight-bold"><?= esc($prescription['prescription_date'] ?? '') ?></div>
                                <div><?= esc($prescription['prescription_number'] ?? '') ?></div>
                                <small class="text-muted"><?= esc(ucwords(str_replace('_', ' ', (string) ($prescription['status'] ?? '')))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Invoices & payment history</h5></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead><tbody><?php foreach ($invoices ?? [] as $invoice): ?><tr><td><?= esc($invoice['invoice_number']) ?></td><td><?= esc($invoice['created_at']) ?></td><td><?= esc($invoice['currency'] . ' ' . $invoice['total']) ?></td><td><?= esc($invoice['status']) ?></td><td><?= esc($invoice['payment_status']) ?></td></tr><?php endforeach; ?><?php if (($invoices ?? []) === []): ?><tr><td colspan="5" class="text-center text-muted">No invoices found.</td></tr><?php endif; ?></tbody></table></div></div>
</main>
<?= $this->endSection() ?>
