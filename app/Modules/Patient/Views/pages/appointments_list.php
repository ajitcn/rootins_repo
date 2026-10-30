<?php
$statusBadge = static function (string $status): string {
    return match ($status) {
        'confirmed' => 'badge-success',
        'checked_in', 'in_queue' => 'badge-warning',
        'in_consultation' => 'badge-info',
        'completed' => 'badge-dark',
        'cancelled', 'no_show' => 'badge-danger',
        default => 'badge-primary',
    };
};
?>
<div class="patient-appointments-list" role="list" aria-label="Your appointments">
    <?php foreach ($appointments as $appointment): ?>
        <?php $editable = in_array($appointment['status'], ['scheduled', 'confirmed'], true) && (string) $appointment['appointment_date'] >= $today; ?>
        <article class="patient-appointment-row" role="listitem">
            <div class="patient-appointment-row-main">
                <div class="patient-appointment-row-identity">
                    <span class="patient-appointment-row-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                    <div>
                        <span class="patient-appointment-row-kicker">Appointment</span>
                        <strong class="patient-appointment-row-number" title="<?= esc($appointment['appointment_number'] ?? '') ?>"><?= esc($appointment['appointment_number'] ?? '—') ?></strong>
                        <small class="patient-appointment-row-created">Created <?= esc($formatDate($appointment['created_at'] ?? null)) ?></small>
                    </div>
                </div>
                <div class="patient-appointment-row-doctor">
                    <span class="patient-appointment-row-label">Doctor</span>
                    <strong>Dr. <?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?></strong>
                    <small><?= esc($appointment['specialization'] ?: 'Specialization not recorded') ?></small>
                </div>
                <div class="patient-appointment-row-service">
                    <span class="patient-appointment-row-label">Service</span>
                    <strong><?= esc($appointment['service_name'] ?: $label((string) $appointment['appointment_type'])) ?></strong>
                    <small><?= esc($label((string) ($appointment['appointment_type'] ?? ''))) ?></small>
                </div>
                <div class="patient-appointment-row-date">
                    <span class="patient-appointment-row-label">Date &amp; time</span>
                    <strong><?= esc($formatDate($appointment['appointment_date'] ?? null)) ?></strong>
                    <small><i class="bi bi-clock mr-1" aria-hidden="true"></i><?= esc(rootins_format_time($appointment['appointment_time'] ?? '') ?: '—') ?></small>
                </div>
                <div class="patient-appointment-row-queue">
                    <span class="patient-appointment-row-label">Queue</span>
                    <strong><?= esc((string) ($appointment['queue_position'] ?? '—')) ?></strong>
                </div>
                <div class="patient-appointment-row-status">
                    <span class="patient-appointment-row-label">Status</span>
                    <span class="badge <?= $statusBadge((string) $appointment['status']) ?> patient-status-badge"><?= esc($label((string) $appointment['status'])) ?></span>
                </div>
            </div>
            <div class="patient-appointment-row-actions">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots-vertical mr-1" aria-hidden="true"></i>Actions</button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <button class="dropdown-item patient-appointment-view" type="button" data-id="<?= (int) $appointment['id'] ?>" data-toggle="modal" data-target="#patient-appointment-detail-modal"><i class="bi bi-eye mr-2" aria-hidden="true"></i>View appointment</button>
                        <?php if ($editable): ?>
                            <button class="dropdown-item patient-appointment-reschedule" type="button" data-id="<?= (int) $appointment['id'] ?>" data-date-display="<?= esc($formatDate($appointment['appointment_date'] ?? null)) ?>" data-time="<?= esc(rootins_format_time($appointment['appointment_time'] ?? '')) ?>"><i class="bi bi-calendar2-event mr-2" aria-hidden="true"></i>Reschedule</button>
                            <button class="dropdown-item text-danger patient-appointment-cancel" type="button" data-id="<?= (int) $appointment['id'] ?>"><i class="bi bi-x-circle mr-2" aria-hidden="true"></i>Cancel appointment</button>
                        <?php endif; ?>
                        <?php if ((int) ($appointment['prescription_id'] ?? 0) > 0): ?><a class="dropdown-item" href="<?= base_url('patient/prescriptions?prescription_id=' . (int) $appointment['prescription_id']) ?>"><i class="bi bi-file-medical mr-2" aria-hidden="true"></i>View prescription</a><?php endif; ?>
                        <?php if ((int) ($appointment['invoice_id'] ?? 0) > 0): ?><a class="dropdown-item" href="<?= base_url('patient/invoices?invoice_id=' . (int) $appointment['invoice_id']) ?>"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>View invoice / payment</a><?php endif; ?>
                        <?php if ((int) ($appointment['order_id'] ?? 0) > 0): ?><a class="dropdown-item" href="<?= base_url('patient/orders?order_id=' . (int) $appointment['order_id']) ?>"><i class="bi bi-box-seam mr-2" aria-hidden="true"></i>View order / delivery</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($appointments === []): ?>
        <div class="patient-empty-state"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>No appointments found</strong><span>Try clearing filters or book a new appointment.</span></div>
    <?php endif; ?>
</div>
