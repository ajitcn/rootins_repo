<div class="doctor-prescription-table-wrap" role="region" aria-label="Prescription list">
    <table class="doctor-prescription-table doctor-prescription-list-table">
        <caption class="sr-only">Prescriptions with patient, prescription date, medicine count, status, and actions.</caption>
        <colgroup><col class="prescription-col-patient"><col class="prescription-col-date"><col class="prescription-col-medicines"><col class="prescription-col-status"><col class="prescription-col-actions"></colgroup>
        <thead><tr><th scope="col">Patient</th><th scope="col">Prescription date</th><th scope="col">Medicine count</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($prescriptions as $prescription): ?>
            <?php $item = $prescriptionContext($prescription); ?>
            <tr>
                <td data-label="Patient"><div class="doctor-prescription-list-patient"><div class="doctor-prescription-avatar" aria-hidden="true"><?= esc($item['initial']) ?></div><div class="doctor-prescription-patient-copy"><strong title="<?= esc($item['patientName']) ?>"><?= esc($item['patientName']) ?></strong><span title="Patient no: <?= esc($item['patientReference']) ?>">Patient no: <?= esc($item['patientReference']) ?></span><span title="<?= esc($item['mobile']) ?>"><?= esc($item['mobile']) ?></span></div></div></td>
                <td data-label="Prescription date"><strong><?= esc($item['date']) ?></strong><span class="doctor-prescription-list-number" title="<?= esc($item['prescriptionNumber']) ?>"><?= esc($item['prescriptionNumber']) ?></span></td>
                <td data-label="Medicine count"><span class="doctor-prescription-count"><i class="fa-solid fa-pills mr-1" aria-hidden="true"></i><?= (int) $item['medicineCount'] ?></span></td>
                <td data-label="Status"><span class="badge doctor-prescription-status-badge doctor-prescription-status-<?= esc($item['statusClass']) ?>" title="<?= esc($item['statusLabel']) ?>"><?= esc($item['statusLabel']) ?></span></td>
                <td data-label="Actions" class="doctor-prescription-list-actions"><?= view(DOCTOR_VIEW . 'pages/prescriptions_actions', $item + ['doctorCanViewPatients' => $doctorCanViewPatients, 'doctorCanViewClinical' => $doctorCanViewClinical]) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
