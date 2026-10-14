<?php foreach ($appointments as $appointment): ?>
    <?php $item = $appointmentContext($appointment); ?>
    <?php $queuePosition = $item['queuePosition']; ?>
    <article class="doctor-appointment-card<?= $item['consultationDateToday'] ? ' doctor-appointment-today' : '' ?>" role="listitem"<?= $item['consultationDateToday'] ? ' aria-label="Today’s appointment"' : '' ?>>
        <div class="doctor-appointment-card-main">
            <div class="doctor-appointment-card-patient doctor-appointment-group" aria-label="Patient information">
                <span class="doctor-appointment-group-kicker">Patient</span>
                <div class="doctor-appointment-avatar" aria-hidden="true"><?= esc($item['patientInitial']) ?></div>
                <div class="doctor-appointment-patient-details">
                    <h3 class="doctor-appointment-patient-name" title="<?= esc($item['patientName']) ?>"><?= esc($item['patientName']) ?></h3>
                    <div class="doctor-appointment-patient-meta">
                        <span title="Patient no: <?= esc($item['appointment']['registration_number'] ?? '—') ?>">Patient no: <?= esc($item['appointment']['registration_number'] ?? '—') ?></span>
                        <span title="<?= esc($item['patientMobile']) ?>"><?= esc($item['patientMobile']) ?></span>
                    </div>
                </div>
            </div>
            <div class="doctor-appointment-card-details doctor-appointment-card-appointment doctor-appointment-group" aria-label="Appointment information">
                <span class="doctor-appointment-group-kicker">Appointment</span>
                <div class="doctor-appointment-date-time">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <strong><?= esc($item['appointmentDateDisplay']) ?></strong>
                    <span><?= esc($item['appointmentTimeDisplay']) ?></span>
                </div>
            </div>
            <div class="doctor-appointment-card-service doctor-appointment-group" aria-label="Service and queue information">
                <span class="doctor-appointment-group-kicker">Service &amp; queue</span>
                <div class="doctor-appointment-service">
                    <span class="doctor-appointment-label">Service</span>
                    <strong title="<?= esc($item['serviceName']) ?>"><?= esc($item['serviceName']) ?></strong>
                    <span class="text-muted" title="<?= esc($item['appointmentType']) ?>"><?= esc($item['appointmentType']) ?></span>
                </div>
                <span class="badge badge-light doctor-appointment-queue" title="Queue <?= $queuePosition !== null && $queuePosition !== '' ? '#' . (int) $queuePosition : '—' ?>">Queue <?= $queuePosition !== null && $queuePosition !== '' ? '#' . (int) $queuePosition : '—' ?></span>
            </div>
            <div class="doctor-appointment-card-status doctor-appointment-group" aria-label="Appointment status">
                <span class="doctor-appointment-group-kicker">Status</span>
                <span class="doctor-appointment-label">Status</span>
                <span class="badge badge-light doctor-status-badge doctor-status-<?= esc(str_replace('_', '-', $item['status'])) ?>"><?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?></span>
                <?php if ($item['consultationDateToday']): ?>
                    <span class="doctor-appointment-today-indicator" title="Today’s appointment" aria-label="Today’s appointment"><i class="bi bi-circle-fill mr-1" aria-hidden="true"></i>Today</span>
                <?php endif; ?>
                <?php if ($item['consultationDatePassed']): ?>
                    <span class="doctor-appointment-past-indicator" title="Past appointment: consultation cannot be started for a date before today."><i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past appointment</span>
                <?php endif; ?>
            </div>
        </div>
        <?= view(DOCTOR_VIEW . 'pages/appointments_actions', array_merge($item, compact('canConsult', 'canEdit', 'nextStatuses', 'canView', 'canFollowUp', 'canPrescription', 'canBilling'))) ?>
    </article>
<?php endforeach; ?>
