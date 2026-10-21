<div class="table-responsive doctor-appointment-table-wrap" role="region" aria-label="Appointment list">
    <table class="table doctor-appointment-table">
        <caption class="sr-only">Doctor appointments with patient, appointment, service, queue, status, and available actions.</caption>
        <colgroup>
            <col class="doctor-appointment-col-patient">
            <col class="doctor-appointment-col-appointment">
            <col class="doctor-appointment-col-service">
            <col class="doctor-appointment-col-queue">
            <col class="doctor-appointment-col-status">
            <col class="doctor-appointment-col-actions">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">Patient</th>
                <th scope="col">Appointment</th>
                <th scope="col">Service</th>
                <th scope="col">Queue</th>
                <th scope="col">Status</th>
                <th scope="col" class="doctor-appointment-table-actions-heading">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($appointments as $appointment): ?>
            <?php $item = $appointmentContext($appointment); ?>
            <?php $queuePosition = $item['queuePosition']; ?>
            <tr class="doctor-appointment-table-row<?= $item['consultationDateToday'] ? ' doctor-appointment-today' : '' ?>"<?= $item['consultationDateToday'] ? ' aria-label="Today’s appointment"' : '' ?>>
                <td class="doctor-appointment-table-patient" data-label="Patient">
                    <div class="doctor-appointment-table-patient-content">
                        <div class="doctor-appointment-avatar" aria-hidden="true"><?= esc($item['patientInitial']) ?></div>
                        <div class="doctor-appointment-patient-details">
                            <strong class="doctor-appointment-patient-name" title="<?= esc($item['patientName']) ?>"><?= esc($item['patientName']) ?></strong>
                            <span class="doctor-appointment-patient-meta-value" title="Patient no: <?= esc($item['appointment']['registration_number'] ?? '—') ?>">Patient no: <?= esc($item['appointment']['registration_number'] ?? '—') ?></span>
                            <span class="doctor-appointment-patient-meta-value" title="<?= esc($item['patientMobile']) ?>"><?= esc($item['patientMobile']) ?></span>
                        </div>
                    </div>
                </td>
                <td class="doctor-appointment-table-appointment" data-label="Appointment">
                    <div class="doctor-appointment-table-date-time">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <strong><?= esc($item['appointmentDateDisplay']) ?></strong>
                        <span><?= esc($item['appointmentTimeDisplay']) ?></span>
                    </div>
                </td>
                <td class="doctor-appointment-table-service" data-label="Service">
                    <strong title="<?= esc($item['serviceName']) ?>"><?= esc($item['serviceName']) ?></strong>
                    <span title="<?= esc($item['appointmentType']) ?>"><?= esc($item['appointmentType']) ?></span>
                </td>
                <td class="doctor-appointment-table-queue" data-label="Queue">
                    <span class="badge badge-light doctor-appointment-queue" title="Queue <?= $queuePosition !== null && $queuePosition !== '' ? '#' . (int) $queuePosition : '—' ?>">Queue <?= $queuePosition !== null && $queuePosition !== '' ? '#' . (int) $queuePosition : '—' ?></span>
                </td>
                <td class="doctor-appointment-table-status" data-label="Status">
                    <div class="doctor-appointment-table-status-content">
                        <span class="badge badge-light doctor-status-badge doctor-status-<?= esc(str_replace('_', '-', $item['status'])) ?>" title="<?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?>"><?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?></span>
                        <?php if ($item['consultationDateToday']): ?>
                            <span class="doctor-appointment-today-indicator" title="Today’s appointment" aria-label="Today’s appointment"><i class="bi bi-circle-fill mr-1" aria-hidden="true"></i>Today</span>
                        <?php endif; ?>
                        <?php if ($item['consultationDatePassed']): ?>
                            <span class="doctor-appointment-past-indicator" title="Past appointment: consultation cannot be started for a date before today."><i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past appointment</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="doctor-appointment-table-actions" data-label="Actions">
                    <?= view(DOCTOR_VIEW . 'pages/appointments_actions', array_merge($item, compact('canConsult', 'canEdit', 'nextStatuses', 'canView', 'canFollowUp', 'canPrescription', 'canBilling'))) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
