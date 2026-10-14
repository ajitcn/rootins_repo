<?php
$appointmentId = (int) ($appointment['id'] ?? 0);
$patientId = (int) ($appointment['patient_id'] ?? 0);
$appointmentLabel = (string) ($appointment['appointment_number'] ?? ($appointmentId > 0 ? $appointmentId : 'appointment'));
$canConsultNow = $canConsult && ! $consultationDatePassed && in_array($status, ['scheduled', 'confirmed', 'checked_in', 'in_queue'], true);
?>
<div class="doctor-appointment-actions">
    <div class="doctor-appointment-action-group">
        <button class="btn btn-sm btn-light doctor-appointment-metadata" type="button" data-toggle="tooltip" data-placement="top" title="Created: <?= esc((string) ($appointment['created_at'] ?? '—')) ?> | Updated: <?= esc((string) ($appointment['updated_at'] ?? '—')) ?>" aria-label="Created <?= esc((string) ($appointment['created_at'] ?? 'not available')) ?>; Updated <?= esc((string) ($appointment['updated_at'] ?? 'not available')) ?>">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        </button>
        <?php
        $hasSecondaryActions = ($canEdit && (isset($nextStatuses[$status]) || ! in_array($status, ['completed', 'cancelled', 'no_show'], true)))
            || ($canConsult && ! $consultationDatePassed && in_array($status, ['scheduled', 'confirmed', 'checked_in', 'in_queue'], true))
            || ($canConsult && ! $consultationDatePassed && in_array($status, ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'in_consultation'], true) && ($appointment['appointment_type'] ?? '') === 'video')
            || ($canFollowUp && ! empty($appointment['consultation_id']))
            || ($canPrescription && ! empty($appointment['prescription_id']))
            || $canBilling;
?>
        <?php if ($hasSecondaryActions || $canView): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="More Actions for appointment <?= esc($appointmentLabel) ?>" title="More Actions">
                    <i class="fa-solid fa-ellipsis-vertical ri-actions-icon mr-1" aria-hidden="true"></i>More Actions
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                <?php if ($canConsultNow): ?>
                    <a class="dropdown-item doctor-appointment-consult" href="<?= base_url('doctor/clinical?appointment_id=' . $appointmentId) ?>" title="Open consultation for this appointment" aria-label="Consult appointment <?= esc($appointmentLabel) ?>"><i class="bi bi-clipboard2-pulse mr-2" aria-hidden="true"></i>Consult</a>
                <?php endif; ?>
                <?php if ($canView): ?>
                    <?php if ($patientId > 0): ?><a class="dropdown-item" href="<?= base_url('doctor/patients/' . $patientId) ?>"><i class="bi bi-person-vcard mr-2" aria-hidden="true"></i>View Patient</a><?php endif; ?>
                    <?php if ($appointmentId > 0): ?><a class="dropdown-item" href="<?= base_url('doctor/appointments/' . $appointmentId) ?>"><i class="bi bi-eye mr-1" aria-hidden="true"></i>View Appointment</a><?php endif; ?>
                <?php endif; ?>
                <?php if ($canEdit && isset($nextStatuses[$status])): ?>
                    <?php foreach ($nextStatuses[$status] as $nextStatus => $label): ?>
                        <button class="dropdown-item js-appointment-status" type="button" data-id="<?= $appointmentId ?>" data-status="<?= esc($nextStatus) ?>"><i class="bi bi-arrow-right-circle mr-2" aria-hidden="true"></i><?= esc($label) ?></button>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($canEdit && ! in_array($status, ['completed', 'cancelled', 'no_show'], true)): ?>
                    <button class="dropdown-item js-reschedule" type="button" data-id="<?= $appointmentId ?>"><i class="bi bi-pencil-square mr-2" aria-hidden="true"></i>Edit / Reschedule</button>
                    <button class="dropdown-item text-danger js-cancel" type="button" data-id="<?= $appointmentId ?>"><i class="bi bi-x-circle mr-2" aria-hidden="true"></i>Cancel</button>
                <?php endif; ?>
                <?php if ($canConsultNow && ($appointment['appointment_type'] ?? '') === 'video'): ?>
                    <button class="dropdown-item js-video-consultation" type="button" data-video-url="<?= base_url('doctor/video/' . $appointmentId) ?>" data-appointment-number="<?= esc($appointmentLabel) ?>"><i class="bi bi-camera-video mr-2" aria-hidden="true"></i>Video Consultation</button>
                <?php endif; ?>
                <?php if ($canFollowUp && ! empty($appointment['consultation_id'])): ?>
                    <a class="dropdown-item" href="<?= base_url('doctor/follow-ups?consultation_id=' . (int) $appointment['consultation_id'] . '&appointment_id=' . $appointmentId) ?>"><i class="bi bi-calendar2-plus mr-2" aria-hidden="true"></i>Create Follow-up</a>
                <?php endif; ?>
                <?php if ($canPrescription && ! empty($appointment['prescription_id'])): ?>
                    <a class="dropdown-item" href="<?= base_url('doctor/prescriptions?prescription_id=' . (int) $appointment['prescription_id']) ?>"><i class="bi bi-file-medical mr-2" aria-hidden="true"></i>View Prescription</a>
                <?php endif; ?>
                <?php if ($canBilling): ?>
                    <a class="dropdown-item" href="<?= base_url('doctor/billing?appointment_id=' . $appointmentId) ?>"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>View Billing</a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
