<?php
$p = $prescription ?? [];
$lines = $items ?? [];
$fullName = static fn (string $prefix): string => trim(implode(' ', array_filter([
    $p[$prefix . 'first_name'] ?? '',
    $p[$prefix . 'middle_name'] ?? '',
    $p[$prefix . 'last_name'] ?? '',
], static fn ($value): bool => trim((string) $value) !== '')));
$text = static fn ($value): string => trim((string) $value);
$date = static function ($value): string {
    $value = trim((string) $value);
    return $value === '' ? '' : (rootins_format_date($value) ?: $value);
};
$doctorName = $text($p['doctor_name'] ?? '') ?: $fullName('doctor_');
$patientName = $text($p['patient_name'] ?? '') ?: $fullName('patient_');
$patientAge = $text($p['patient_age'] ?? '');
$patientGender = $text($p['patient_gender'] ?? $p['gender'] ?? '');
$followUp = trim(implode(' · ', array_filter([
    $date($p['follow_up_date'] ?? ''),
    $text($p['follow_up_time'] ?? ''),
    $text($p['follow_up_remarks'] ?? ''),
])));
?>
<article class="rootins-prescription-document" data-prescription-reference="<?= esc($text($p['prescription_number'] ?? ''), 'attr') ?>">
    <header class="rootins-prescription-letterhead">
        <div class="rootins-prescription-brand"><img src="<?= esc(base_url('assets/img/logo.png')) ?>" alt="ROOTINS logo"><div><strong>ROOTINS</strong><span>Healthcare and Pharmacy</span></div></div>
        <div class="rootins-prescription-clinic"><strong><?= esc($text($p['clinic_name'] ?? '') ?: 'ROOTINS Clinic') ?></strong><?php if ($text($p['clinic_address'] ?? '') !== ''): ?><span><?= nl2br(esc($p['clinic_address'])) ?></span><?php endif; ?><span><?= esc(implode(' | ', array_filter([$text($p['clinic_mobile'] ?? ''), $text($p['clinic_email'] ?? '')]))) ?></span></div>
    </header>
    <div class="rootins-prescription-divider"></div>

    <section class="rootins-prescription-doctor-patient">
        <div class="rootins-prescription-panel"><h2>Doctor</h2><strong><?= esc($doctorName) ?></strong><?php if ($text($p['qualification'] ?? '') !== ''): ?><span><?= esc($p['qualification']) ?></span><?php endif; ?><?php if ($text($p['doctor_specializations'] ?? '') !== ''): ?><span><?= esc($p['doctor_specializations']) ?></span><?php endif; ?><?php if ($text($p['doctor_registration_number'] ?? '') !== ''): ?><span>Registration no. <?= esc($p['doctor_registration_number']) ?></span><?php endif; ?><?php if ($date($p['consultation_date'] ?? '') !== ''): ?><span>Consultation: <?= esc($date($p['consultation_date'])) ?></span><?php endif; ?></div>
        <div class="rootins-prescription-panel"><h2>Patient</h2><strong><?= esc($patientName) ?></strong><?php if ($text($p['registration_number'] ?? '') !== ''): ?><span>Reference: <?= esc($p['registration_number']) ?></span><?php endif; ?><span><?= esc(trim(implode(' | ', array_filter([$patientAge !== '' ? $patientAge . ' years' : '', $patientGender !== '' ? ucwords($patientGender) : '', $text($p['patient_mobile'] ?? $p['patient_mobile_display'] ?? '')])))) ?></span><?php if ($text($p['patient_address'] ?? '') !== ''): ?><span><?= nl2br(esc($p['patient_address'])) ?></span><?php endif; ?></div>
    </section>

    <section class="rootins-prescription-identity"><div class="rootins-prescription-rx">Rx</div><div><h1><?= esc($text($p['prescription_number'] ?? 'Prescription')) ?></h1><span>Prescription</span></div><dl><?php foreach ([['Date', $date($p['prescription_date'] ?? '')], ['Valid until', $date($p['valid_until'] ?? '')], ['Reference', trim(implode(' / ', array_filter([$text($p['appointment_number'] ?? ''), $text($p['consultation_number'] ?? '')])))]] as [$label, $value]): ?><?php if ($value !== ''): ?><div><dt><?= esc($label) ?></dt><dd><?= esc($value) ?></dd></div><?php endif; ?><?php endforeach; ?></dl></section>

    <?php foreach ([['Diagnosis / complaint', $p['diagnosis'] ?? $p['complaint'] ?? ''], ['Doctor instructions', $p['instructions'] ?? ''], ['Notes', $p['notes'] ?? ''], ['Follow-up', $followUp], ['Important advice', $p['important_advice'] ?? '']] as [$title, $value]): ?><?php if ($text($value) !== ''): ?><section class="rootins-prescription-clinical-section"><h2><?= esc($title) ?></h2><p><?= nl2br(esc($value)) ?></p></section><?php endif; ?><?php endforeach; ?>

    <section class="rootins-prescription-medicines"><h2>Medicines</h2><div class="table-responsive"><table><thead><tr><th>No.</th><th>Medicine</th><th>Strength/Form</th><th>Dose</th><th>Route</th><th>Frequency</th><th>Duration</th><th>Qty</th><th>Instructions</th></tr></thead><tbody><?php if ($lines === []): ?><tr><td colspan="9" class="text-muted">No prescription medicines.</td></tr><?php else: ?><?php foreach ($lines as $index => $line): ?><tr><td><?= (int) $index + 1 ?></td><td><strong><?= esc($text($line['medicine_catalog_name'] ?? $line['medicine_name'] ?? '')) ?></strong></td><td><?= esc(trim($text($line['strength'] ?? $line['medicine_strength'] ?? '') . ' ' . $text($line['dosage_form'] ?? $line['medicine_form'] ?? $line['form'] ?? ''))) ?></td><td><?= esc($text($line['dosage'] ?? '')) ?></td><td><?= esc($text($line['route'] ?? '')) ?></td><td><?= esc($text($line['frequency'] ?? '')) ?></td><td><?= esc(trim($text($line['duration'] ?? '') . ' ' . $text($line['duration_unit'] ?? ''))) ?></td><td><?= esc($text($line['quantity'] ?? '')) ?></td><td><?= nl2br(esc($text($line['instructions'] ?? ''))) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>

    <footer class="rootins-prescription-footer"><div class="rootins-prescription-signature"><span></span><strong>Doctor signature</strong><small>Digitally generated prescription</small></div><div>Confidential medical document<br><?= esc($text($p['prescription_number'] ?? '')) ?></div></footer>
</article>
