<div class="doctor-prescription-grid" role="list" aria-label="Prescription cards">
    <?php foreach ($prescriptions as $prescription): ?>
        <?php $item = $prescriptionContext($prescription); ?>
        <article class="doctor-prescription-card" role="listitem">
            <div class="doctor-prescription-card-main">
                <div class="doctor-prescription-card-patient"><div class="doctor-prescription-avatar" aria-hidden="true"><?= esc($item['initial']) ?></div><div class="doctor-prescription-patient-copy"><h3 class="doctor-prescription-patient-name" title="<?= esc($item['patientName']) ?>"><?= esc($item['patientName']) ?></h3><span title="Patient no: <?= esc($item['patientReference']) ?>">Patient no: <?= esc($item['patientReference']) ?></span><span title="<?= esc($item['mobile']) ?>"><?= esc($item['mobile']) ?></span></div></div>
                <div class="doctor-prescription-card-details"><span class="doctor-prescription-card-label">Prescription date</span><strong><?= esc($item['date']) ?></strong><span class="doctor-prescription-list-number" title="<?= esc($item['prescriptionNumber']) ?>"><?= esc($item['prescriptionNumber']) ?></span></div>
                <div class="doctor-prescription-card-medicines"><span class="doctor-prescription-card-label">Medicines</span><strong><?= (int) $item['medicineCount'] ?></strong></div>
                <div class="doctor-prescription-card-status"><span class="doctor-prescription-card-label">Status</span><span class="badge doctor-prescription-status-badge doctor-prescription-status-<?= esc($item['statusClass']) ?>" title="<?= esc($item['statusLabel']) ?>"><?= esc($item['statusLabel']) ?></span></div>
                <div class="doctor-prescription-card-status"><span class="doctor-prescription-card-label">Cart Sync</span><span class="badge doctor-prescription-cart-sync-badge doctor-prescription-cart-sync-<?= esc($item['cartSyncStatusClass']) ?>" title="<?= esc($item['cartSyncStatusLabel']) ?>"><?= esc($item['cartSyncStatusLabel']) ?></span></div>
            </div>
            <div class="doctor-prescription-card-actions"><?= view(DOCTOR_VIEW . 'pages/prescriptions_actions', $item + ['doctorCanViewPatients' => $doctorCanViewPatients, 'doctorCanViewClinical' => $doctorCanViewClinical]) ?></div>
        </article>
    <?php endforeach; ?>
</div>
