<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$doctorName = trim(implode(' ', array_filter([$doctor['first_name'] ?? '', $doctor['last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
$statusLabels = ['scheduled'=>'Scheduled','confirmed'=>'Confirmed','checked_in'=>'Checked in','in_queue'=>'In queue','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No show'];
?>
<main class="dashboard-container doctor-dashboard-page" aria-labelledby="doctor-dashboard-title">
    <section class="doctor-dashboard-hero">
        <div><div class="doctor-dashboard-eyebrow"><i class="bi bi-sun" aria-hidden="true"></i> <?= esc(date('D, d M Y')) ?></div><h1 id="doctor-dashboard-title">Good day, Dr. <?= esc($doctorName ?: 'Doctor') ?></h1><p>Your clinical day at a glance. Start with the patients and consultations that need your attention.</p></div>
        <a class="btn btn-primary doctor-dashboard-primary-action" href="<?= base_url('doctor/clinical') ?>"><i class="bi bi-clipboard2-pulse mr-1" aria-hidden="true"></i> Open clinical workspace</a>
    </section>
    <section aria-labelledby="doctor-pulse-title">
        <div class="doctor-section-heading"><div><h2 id="doctor-pulse-title">Today’s pulse</h2><span>Doctor-scoped activity for today</span></div><a href="<?= base_url('doctor/appointments') ?>" class="doctor-inline-link">View appointments <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
        <div class="doctor-kpi-grid">
            <a class="doctor-kpi doctor-kpi-blue" href="<?= base_url('doctor/appointments') ?>"><span class="doctor-kpi-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span><span><strong><?= (int) $appointmentCount ?></strong><small>Appointments today</small><em><?= (int) $todayPendingCount ?> still to attend</em></span></a>
            <a class="doctor-kpi doctor-kpi-violet" href="<?= base_url('doctor/clinical') ?>"><span class="doctor-kpi-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span><span><strong><?= (int) $pendingConsultationCount ?></strong><small>Consultations to complete</small><em><?= (int) $consultationCount ?> started today</em></span></a>
            <a class="doctor-kpi doctor-kpi-cyan" href="<?= base_url('doctor/appointments') ?>"><span class="doctor-kpi-icon"><i class="bi bi-camera-video" aria-hidden="true"></i></span><span><strong><?= (int) $todayVideoCount ?></strong><small>Video consultations</small><em>Scheduled today</em></span></a>
            <a class="doctor-kpi doctor-kpi-green" href="<?= base_url('doctor/prescriptions') ?>"><span class="doctor-kpi-icon"><i class="bi bi-prescription2" aria-hidden="true"></i></span><span><strong><?= (int) $completedPrescriptionCount ?></strong><small>Prescriptions completed</small><em>Created today</em></span></a>
        </div>
    </section>
    <div class="doctor-dashboard-grid">
        <section class="doctor-dashboard-panel doctor-schedule-panel" aria-labelledby="doctor-schedule-title">
            <div class="doctor-panel-heading"><div><h2 id="doctor-schedule-title">Today’s schedule</h2><span>Next patients assigned to you</span></div><a href="<?= base_url('doctor/appointments') ?>" class="btn btn-sm btn-outline-primary">View all</a></div>
            <div class="doctor-status-strip" aria-label="Today appointment status"><?php foreach (['scheduled','confirmed','checked_in','in_queue','completed'] as $status): ?><span class="doctor-status-pill doctor-status-<?= esc($status) ?>"><b><?= (int) ($todayStatusCounts[$status] ?? 0) ?></b> <?= esc($statusLabels[$status]) ?></span><?php endforeach; ?></div>
            <?php if ($appointments === []): ?><div class="doctor-empty-state"><i class="bi bi-calendar2-x" aria-hidden="true"></i><strong>No upcoming appointments</strong><span>Your assigned schedule is clear for now.</span></div><?php else: ?>
                <div class="doctor-appointment-list">
                <?php foreach ($appointments as $appointment): ?>
                    <?php $patientName = trim(implode(' ', array_filter([$appointment['patient_first_name'] ?? '', $appointment['patient_middle_name'] ?? '', $appointment['patient_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== ''))); $patientContact = service('telephoneContact')->formatDisplay($appointment['patient_telephone_country_code'] ?? null, $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null, $appointment['patient_legacy_country_code'] ?? null); $appointmentStatus = (string) ($appointment['status'] ?? ''); $type = ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? ''))); ?>
                    <a class="doctor-appointment-row" href="<?= base_url('doctor/appointments/' . (int) ($appointment['id'] ?? 0)) ?>"><span class="doctor-appointment-time"><strong><?= esc(rootins_format_time($appointment['appointment_time'] ?? '')) ?></strong><small><?= esc(rootins_format_date($appointment['appointment_date'] ?? '')) ?></small></span><span class="doctor-appointment-patient"><strong><?= esc($patientName ?: 'Patient') ?></strong><small><?= esc($appointment['registration_number'] ?? '') ?><?= $patientContact !== '' ? ' · ' . esc($patientContact) : '' ?></small></span><span class="doctor-appointment-meta"><span class="doctor-type-chip"><i class="bi <?= strtolower((string) ($appointment['appointment_type'] ?? '')) === 'video' ? 'bi-camera-video' : 'bi-person' ?>" aria-hidden="true"></i> <?= esc($type ?: 'Visit') ?></span><span class="doctor-row-status doctor-row-status-<?= esc($appointmentStatus) ?>"><?= esc($statusLabels[$appointmentStatus] ?? ucwords(str_replace('_', ' ', $appointmentStatus))) ?></span></span><i class="bi bi-chevron-right doctor-row-arrow" aria-hidden="true"></i></a>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </section>
        <aside class="doctor-dashboard-side">
            <section class="doctor-dashboard-panel doctor-action-panel" aria-labelledby="doctor-actions-title"><div class="doctor-panel-heading"><div><h2 id="doctor-actions-title">Action center</h2><span>Keep your work moving</span></div><span class="doctor-panel-badge">3 shortcuts</span></div>
                <a class="doctor-action-link" href="<?= base_url('doctor/clinical') ?>"><span class="doctor-action-icon doctor-action-icon-blue"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span><span><strong>Continue clinical work</strong><small>Open consultation workspace</small></span><span class="doctor-action-label">Start here</span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                <a class="doctor-action-link" href="<?= base_url('doctor/follow-ups') ?>"><span class="doctor-action-icon doctor-action-icon-amber"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span><span><strong>Follow-ups due today</strong><small><?= (int) $followUpsDueToday ?> patient follow-up<?= $followUpsDueToday === 1 ? '' : 's' ?></small></span><span class="doctor-action-label doctor-action-label-amber"><?= (int) $followUpsDueToday ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                <a class="doctor-action-link" href="<?= base_url('doctor/prescriptions') ?>"><span class="doctor-action-icon doctor-action-icon-green"><i class="bi bi-prescription2" aria-hidden="true"></i></span><span><strong>Review prescriptions</strong><small><?= (int) $prescriptionCount ?> created today</small></span><span class="doctor-action-label doctor-action-label-green"><?= (int) $prescriptionCount ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            </section>
            <section class="doctor-dashboard-panel doctor-secondary-panel" aria-labelledby="doctor-overview-title"><div class="doctor-panel-heading"><div><h2 id="doctor-overview-title">My workload</h2><span>Quick access to your records</span></div></div><div class="doctor-workload-grid"><a href="<?= base_url('doctor/patients') ?>"><span class="doctor-workload-icon doctor-workload-blue"><i class="bi bi-people" aria-hidden="true"></i></span><span><strong><?= (int) $patientCount ?></strong><small>Patients</small></span></a><a href="<?= base_url('doctor/follow-ups') ?>"><span class="doctor-workload-icon doctor-workload-amber"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span><span><strong><?= (int) $followUpCount ?></strong><small>Open follow-ups</small></span></a><a href="<?= base_url('doctor/billing') ?>"><span class="doctor-workload-icon doctor-workload-green"><i class="bi bi-receipt" aria-hidden="true"></i></span><span><strong><?= (int) $billingCount ?></strong><small>Invoices</small></span></a></div></section>
        </aside>
    </div>
</main>
<?= $this->endSection() ?>
