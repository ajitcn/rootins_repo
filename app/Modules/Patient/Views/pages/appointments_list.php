<?php
$statusBadge = static function (string $status): string {
    return in_array($status, ['confirmed', 'checked_in', 'in_queue', 'in_consultation'], true)
        ? 'badge-success'
        : ($status === 'cancelled' || $status === 'no_show' ? 'badge-secondary' : 'badge-light');
};
?>
<div class="table-responsive patient-appointments-table-wrap">
    <table class="table patient-appointments-table mb-0">
        <caption class="sr-only">Your appointments</caption>
        <thead>
            <tr>
                <th scope="col">Appointment</th>
                <th scope="col">Doctor</th>
                <th scope="col">Service</th>
                <th scope="col">Date / time</th>
                <th scope="col">Queue / type</th>
                <th scope="col">Status</th>
                <th scope="col" class="patient-appointments-actions-heading">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <?php $editable = in_array($appointment['status'], ['scheduled', 'confirmed'], true) && (string) $appointment['appointment_date'] >= $today; ?>
                <tr>
                    <td data-label="Appointment">
                        <strong class="text-truncate d-block" title="<?= esc($appointment['appointment_number'] ?? '') ?>"><?= esc($appointment['appointment_number'] ?? '—') ?></strong>
                        <small class="text-muted"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i><?= esc($formatDate($appointment['created_at'] ?? null)) ?></small>
                    </td>
                    <td data-label="Doctor">
                        <strong class="text-truncate d-block" title="<?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?>">Dr. <?= esc(trim(($appointment['doctor_first_name'] ?? '') . ' ' . ($appointment['doctor_last_name'] ?? ''))) ?></strong>
                        <small class="text-muted d-block text-truncate" title="<?= esc($appointment['specialization'] ?? '') ?>"><?= esc($appointment['specialization'] ?: 'Specialization not recorded') ?></small>
                    </td>
                    <td data-label="Service"><span class="text-truncate d-block" title="<?= esc($appointment['service_name'] ?: $appointment['appointment_type']) ?>"><?= esc($appointment['service_name'] ?: $label((string) $appointment['appointment_type'])) ?></span></td>
                    <td data-label="Date / time"><strong><?= esc($formatDate($appointment['appointment_date'] ?? null)) ?></strong><small class="text-muted d-block"><?= esc(rootins_format_time($appointment['appointment_time'] ?? '') ?: '—') ?></small></td>
                    <td data-label="Queue / type"><span class="d-block">Queue <?= esc((string) ($appointment['queue_position'] ?? '—')) ?></span><small class="text-muted"><?= esc($label((string) ($appointment['appointment_type'] ?? ''))) ?></small></td>
                    <td data-label="Status"><span class="badge <?= $statusBadge((string) $appointment['status']) ?> patient-status-badge"><?= esc($label((string) $appointment['status'])) ?></span></td>
                    <td data-label="Actions" class="patient-appointments-actions">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More Actions</button>
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
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?><tr><td colspan="7"><div class="patient-empty-state"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>No appointments found</strong><span>Try clearing filters or book a new appointment.</span></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
