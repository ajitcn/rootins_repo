<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $prescription['patient_first_name'] ?? '', $prescription['patient_middle_name'] ?? '', $prescription['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$doctorName = trim(implode(' ', array_filter([
    $prescription['doctor_first_name'] ?? '', $prescription['doctor_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>
<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Patient Management', 'page' => 'Prescription Details']) ?>
    <div class="action-btn-container">
        <a href="<?= base_url('admin/prescriptions') ?>" class="btn btn-sm theme-btn"><i class="bi bi-arrow-left"></i> Back to Prescriptions</a>
        <?php if ($prescription['status'] === 'completed'): ?>
            <a href="<?= base_url('admin/prescriptions/pdf/' . (int) $prescription['id']) ?>" class="btn btn-sm theme-btn" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Print / View PDF</a>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Prescription <?= esc($prescription['prescription_number']) ?></h5></div>
        <div class="card-body">
            <div class="row">
                <?php foreach ([
                    'Consultation' => $prescription['consultation_number'],
                    'Patient' => $patientName . ' (' . $prescription['registration_number'] . ')',
                    'Doctor' => $doctorName,
                    'Clinic' => $prescription['clinic_name'],
                    'Date' => $prescription['prescription_date'],
                    'Status' => ucwords(str_replace('_', ' ', $prescription['status'])),
                ] as $label => $value): ?>
                    <div class="col-md-6 mb-3"><strong><?= esc($label) ?></strong><div><?= esc($value) ?></div></div>
                <?php endforeach; ?>
                <div class="col-12 mb-3"><strong>Notes</strong><div><?= nl2br(esc($prescription['notes'] ?: '—')) ?></div></div>
            </div>
            <h6>Prescription Items</h6>
            <div class="table-responsive"><table class="table table-bordered table-sm"><thead class="thead-light"><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Quantity</th><th>Instructions</th><th>Notes</th></tr></thead><tbody>
                <?php foreach ($items as $item): ?>
                    <tr><td><?= esc($item['medicine_name']) ?></td><td><?= esc($item['dosage'] ?: '—') ?></td><td><?= esc($item['frequency'] ?: '—') ?></td><td><?= esc(trim(($item['duration'] ?: '') . ' ' . ($item['duration_unit'] ?: '')) ?: '—') ?></td><td><?= esc($item['quantity'] ?? '—') ?></td><td><?= nl2br(esc($item['instructions'] ?: '—')) ?></td><td><?= nl2br(esc($item['notes'] ?: '—')) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>
