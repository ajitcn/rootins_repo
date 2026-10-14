<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $filters = $filters ?? []; ?>
<main class="dashboard-container admin-clinical-page">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Clinical Records', 'page' => 'Consultations']) ?>

    <div class="ri-page-header action-btn-container">
        <div class="ri-page-header-copy"><p class="ri-eyebrow mb-1">Clinical Records</p><h1 class="h3 mb-1">Consultation history</h1><p class="text-muted mb-0">Review patient context, visit timing, clinical notes, and follow-up plans.</p></div>
        <div class="ri-page-header-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#consultationFilterModal"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><?php if ($canCreate): ?><button type="button" class="btn btn-sm theme-btn" id="openConsultationModal"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New consultation</button><?php endif; ?></div>
    </div>

    <?= display_session_msg() ?>

    <section class="ri-list-card clinical-record-list-card">
        <div class="ri-list-card-header"><div><h2 class="h6 mb-1">Patient visits</h2><p class="text-muted small mb-0">Grouped by patient, care team, visit context, and status.</p></div><span class="ri-list-count"><?= esc((string) count($consultations)) ?> on this page</span></div>
        <div class="table-responsive admin-clinical-table-wrap">
            <table class="table table-hover admin-clinical-table" id="consultations-table">
                <thead><tr><th class="clinical-col-patient">Patient</th><th class="clinical-col-doctor">Doctor</th><th class="clinical-col-visit">Visit</th><th class="clinical-col-status">Status</th><th class="clinical-col-actions text-right">Actions</th></tr></thead>
                <tbody>
                <?php if ($consultations === []): ?><tr><td colspan="5"><div class="ri-empty-state"><i class="bi bi-journal-medical" aria-hidden="true"></i><h3>No consultations found</h3><p>Try changing the search or filters, or create a new consultation.</p><?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= esc(current_url()) ?>">Clear filters</a><?php endif; ?></div></td></tr><?php endif; ?>
                <?php foreach ($consultations as $consultation): ?>
                    <?php
                    $patientName = trim(implode(' ', array_filter([$consultation['patient_first_name'] ?? '', $consultation['patient_middle_name'] ?? '', $consultation['patient_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
                    $doctorName = trim(implode(' ', array_filter([$consultation['doctor_first_name'] ?? '', $consultation['doctor_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
                    $statusTone = match ($consultation['status'] ?? '') {
                        'completed' => 'green', 'cancelled' => 'red', 'in_progress' => 'blue', 'draft' => 'amber', default => 'charcoal',
                    };
                    $sourceTone = match ($consultation['patient_source'] ?? '') { 'shopify' => 'purple', 'self' => 'charcoal', default => 'gray' };
                    $appointmentDate = rootins_format_date($consultation['appointment_date'] ?? '');
                    $appointmentTime = substr((string) ($consultation['appointment_time'] ?? ''), 0, 5);
                    ?>
                    <tr>
                        <td data-label="Patient"><div class="ri-stack-cell"><strong class="clinical-primary"><?= esc($patientName ?: 'Unknown patient') ?></strong><span class="clinical-secondary"><?= esc($consultation['registration_number'] ?? 'Registration unavailable') ?></span><span class="clinical-secondary"><?= esc($consultation['patient_mobile'] ?? 'Mobile unavailable') ?></span><span class="badge ri-status ri-status--<?= esc($sourceTone) ?> clinical-source-badge"><?= esc(patient_source_label($consultation['patient_source'] ?? null)) ?></span></div></td>
                        <td data-label="Doctor"><div class="ri-stack-cell"><strong class="clinical-primary">Dr. <?= esc($doctorName ?: 'Unassigned') ?></strong><span class="clinical-secondary"><?= esc($consultation['doctor_code'] ?? 'Code unavailable') ?></span><span class="clinical-secondary"><?= esc($consultation['clinic_name'] ?? 'Clinic unavailable') ?></span></div></td>
                        <td data-label="Visit"><div class="ri-stack-cell"><strong class="clinical-date"><?= esc(rootins_format_date($consultation['consultation_date'] ?? '')) ?></strong><span class="clinical-secondary"><?= esc(substr((string) ($consultation['consultation_time'] ?? ''), 0, 5) ?: 'Time not recorded') ?></span><span class="clinical-secondary"><?= esc($consultation['consultation_number'] ?? '') ?><?= $appointmentDate ? ' · ' . esc($appointmentDate) : '' ?><?= $appointmentTime ? ' ' . esc($appointmentTime) : '' ?></span><?php if (!empty($consultation['service_name'])): ?><span class="clinical-secondary"><?= esc($consultation['service_name']) ?><?= !empty($consultation['queue_position']) ? ' · Queue ' . esc((string) $consultation['queue_position']) : '' ?></span><?php endif; ?></div></td>
                        <td data-label="Status"><div class="ri-stack-cell"><span class="badge ri-status ri-status--<?= esc($statusTone) ?>"><?= esc(ucwords(str_replace('_', ' ', $consultation['status'] ?? 'Unknown'))) ?></span><?php if (!empty($consultation['follow_up_date'])): ?><span class="clinical-secondary">Follow-up <?= esc(rootins_format_date($consultation['follow_up_date'])) ?></span><?php endif; ?></div></td>
                        <td data-label="Actions" class="clinical-actions-cell text-right"><div class="dropdown ri-actions-wrapper"><button type="button" class="btn btn-sm ri-more-actions-toggle dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="ri-actions-icon" aria-hidden="true"></span>More Actions</button><div class="dropdown-menu dropdown-menu-right ri-actions-menu"><a class="dropdown-item" href="<?= esc(base_url('admin/consultations/view/' . (int) $consultation['id'])) ?>"><i class="bi bi-eye" aria-hidden="true"></i>View details</a><?php if ($canEdit && ($consultation['status'] ?? '') !== 'completed'): ?><button type="button" class="dropdown-item edit-consultation" data-id="<?= (int) $consultation['id'] ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i>Edit consultation</button><?php endif; ?><?php if ($canDelete && ($consultation['status'] ?? '') !== 'completed'): ?><button type="button" class="dropdown-item text-danger delete-consultation" data-id="<?= (int) $consultation['id'] ?>"><i class="bi bi-trash" aria-hidden="true"></i>Delete consultation</button><?php endif; ?><div class="dropdown-divider"></div><span class="dropdown-item-text clinical-menu-meta">Created <?= esc(rootins_format_date(substr((string) ($consultation['created_at'] ?? ''), 0, 10)) ?: 'Not recorded') ?></span></div></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'consultations', 'filters' => $filters]) ?>
</main>

<?= view(ADMIN_VIEW . 'parts/clinical_filter', ['prefix' => 'consultation', 'title' => 'Filter consultations', 'statuses' => ['draft', 'in_progress', 'completed', 'cancelled'], 'clinics' => $clinics, 'doctors' => $doctors, 'filters' => $filters]) ?>
<?= form_open('', 'id="consultationCsrfForm" class="d-none"') ?><?= form_close() ?>

<?php if ($canCreate || $canEdit): ?>
<div class="modal fade" id="consultationModal" tabindex="-1" role="dialog" aria-labelledby="consultationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable ri-scroll-modal" role="document"><div class="modal-content">
        <div class="modal-header"><div><p class="ri-eyebrow mb-1">Clinical Records</p><h2 class="modal-title h5 mb-0" id="consultationModalTitle">Add Consultation</h2></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <?= form_open('', 'id="consultationForm" autocomplete="off"') ?><input type="hidden" name="id" id="consultation_id">
        <div class="modal-body">
            <div class="clinical-form-section"><div class="clinical-form-section-heading"><h3>Visit context</h3><p>Select the patient, care team, and optional appointment.</p></div><div class="form-row">
                <div class="form-group col-md-6"><label for="consultation_patient_id">Patient <span class="text-danger">*</span></label><select name="patient_id" id="consultation_patient_id" class="form-control btSelect" data-live-search="true" title="Select Patient"><option value="">Select Patient</option><?php foreach ($patients as $patient): ?><?php $name = trim(implode(' ', array_filter([$patient['first_name'] ?? '', $patient['middle_name'] ?? '', $patient['last_name'] ?? '']))); ?><option value="<?= (int) $patient['id'] ?>"><?= esc($patient['registration_number'] . ' - ' . $name) ?></option><?php endforeach; ?></select><div class="validation-error" id="patient_idErr"></div></div>
                <div class="form-group col-md-6"><label for="consultation_doctor_id">Doctor <span class="text-danger">*</span></label><select name="doctor_id" id="consultation_doctor_id" class="form-control btSelect" data-live-search="true" title="Select Doctor"><option value="">Select Doctor</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" data-clinic-id="<?= (int) $doctor['clinic_id'] ?>">Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name']) . ' (' . $doctor['doctor_code'] . ')') ?></option><?php endforeach; ?></select><div class="validation-error" id="doctor_idErr"></div></div>
                <div class="form-group col-md-6"><label for="consultation_clinic_id">Clinic <span class="text-danger">*</span></label><select name="clinic_id" id="consultation_clinic_id" class="form-control btSelect" data-live-search="true" title="Select Clinic"><option value="">Select Clinic</option><?php foreach ($clinics as $clinic): ?><option value="<?= (int) $clinic['id'] ?>"><?= esc($clinic['clinic_name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="clinic_idErr"></div></div>
                <div class="form-group col-md-6"><label for="consultation_appointment_id">Appointment</label><select name="appointment_id" id="consultation_appointment_id" class="form-control btSelect" data-live-search="true" title="Optional appointment"><option value="">No linked appointment</option><?php foreach ($appointments as $appointment): ?><option value="<?= (int) $appointment['id'] ?>" data-patient-id="<?= (int) $appointment['patient_id'] ?>" data-doctor-id="<?= (int) $appointment['doctor_id'] ?>" data-clinic-id="<?= (int) $appointment['clinic_id'] ?>"><?= esc($appointment['appointment_number'] . ' - ' . rootins_format_date($appointment['appointment_date']) . ' ' . substr((string) $appointment['appointment_time'], 0, 5)) ?></option><?php endforeach; ?></select><div class="validation-error" id="appointment_idErr"></div></div>
            </div></div>
            <div class="clinical-form-section"><div class="clinical-form-section-heading"><h3>Visit timing and status</h3><p>Dates are entered in dd-mm-yyyy and normalized on the server.</p></div><div class="form-row">
                <div class="form-group col-md-6"><label for="consultation_date">Consultation date <span class="text-danger">*</span></label><input type="text" name="consultation_date" id="consultation_date" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off"><div class="validation-error" id="consultation_dateErr"></div></div>
                <div class="form-group col-md-3"><label for="consultation_time">Time</label><input type="time" name="consultation_time" id="consultation_time" class="form-control"><div class="validation-error" id="consultation_timeErr"></div></div>
                <div class="form-group col-md-3"><label for="consultation_status">Status <span class="text-danger">*</span></label><select name="status" id="consultation_status" class="form-control btSelect" title="Select Status"><option value="draft">Draft</option><option value="in_progress">In Progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select><div class="validation-error" id="statusErr"></div></div>
            </div></div>
            <div class="clinical-form-section"><div class="clinical-form-section-heading"><h3>Clinical record</h3><p>Keep the narrative readable; line breaks are preserved in details and print views.</p></div><div class="form-row"><?php foreach (['chief_complaint' => 'Chief complaint', 'clinical_notes' => 'Clinical notes', 'diagnosis' => 'Diagnoses', 'treatment_plan' => 'Advice and treatment plan', 'diagnostic_tests' => 'Diagnostic tests', 'diet_plan' => 'Diet plan'] as $field => $label): ?><div class="form-group col-md-6"><label for="consultation_<?= $field ?>"><?= $label ?></label><textarea name="<?= $field ?>" id="consultation_<?= $field ?>" class="form-control" rows="4"></textarea><div class="validation-error" id="<?= $field ?>Err"></div></div><?php endforeach; ?><div class="form-group col-md-6"><label for="follow_up_date">Follow-up date</label><input type="text" name="follow_up_date" id="follow_up_date" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off"><div class="validation-error" id="follow_up_dateErr"></div></div></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn theme-btn save">Save consultation</button></div>
        <?= form_close() ?>
    </div></div>
</div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/consultation/consultation.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
