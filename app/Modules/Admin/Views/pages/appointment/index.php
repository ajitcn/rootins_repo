<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<?php
$filters = $filters ?? [];
$permissions = session('permissions') ?? [];
$canCreate = $canCreate ?? in_array('appointment.create', $permissions, true);
$canEdit = $canEdit ?? in_array('appointment.edit', $permissions, true);
$canDelete = $canDelete ?? in_array('appointment.delete', $permissions, true);
$canConsult = $canConsult ?? in_array('consultation.view', $permissions, true) && in_array('consultation.create', $permissions, true);
$canBilling = $canBilling ?? in_array('billing.view', $permissions, true);
?>
<main class="dashboard-container admin-appointment-page">
    <?= view('templates/_breadcrumb', [
        'module' => 'admin',
        'menu_title' => 'Appointment Management',
        'page' => 'Appointments',
    ]) ?>

    <div class="ri-page-header action-btn-container d-flex flex-wrap justify-content-between align-items-center mb-3" id="appointments-title">
        <div class="ri-page-header-copy"><p class="text-muted text-uppercase small mb-1">Appointment Management</p><h1 class="h4 mb-1">Appointments</h1><p class="text-muted mb-0">Patient, doctor, schedule, and current appointment status.</p></div>
        <div class="ri-page-header-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-toggle="modal" data-target="#appointmentFilterModal">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filters
            </button>
            <?php if ($canCreate): ?><button type="button" class="btn btn-sm theme-btn" id="openAppointmentModal">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> New Appointment
            </button><?php endif; ?>
        </div>
    </div>

    <?= display_session_msg() ?>

    <div class="admin-appointment-table-wrap table-responsive" role="region" aria-labelledby="appointments-table-caption" tabindex="0">
        <table class="table table-bordered table-hover admin-appointment-table" id="appointments-table">
            <caption id="appointments-table-caption" class="sr-only">Appointments grouped by patient, doctor, appointment schedule, status, and available actions.</caption>
            <colgroup>
                <col class="admin-appointment-col-patient">
                <col class="admin-appointment-col-doctor">
                <col class="admin-appointment-col-appointment">
                <col class="admin-appointment-col-status">
                <col class="admin-appointment-col-actions">
            </colgroup>
            <thead class="thead-light">
                <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Doctor</th>
                    <th scope="col">Appointment</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($appointments === []): ?>
                    <tr><td colspan="5" class="admin-appointment-empty text-center text-muted py-5"><i class="bi bi-calendar-x d-block mb-2" aria-hidden="true"></i><strong class="d-block mb-1">No appointments found</strong>No appointments match the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($appointments as $appointment): ?>
                    <?php
                    $patientName = trim(implode(' ', array_filter([
                        $appointment['patient_first_name'] ?? '',
                        $appointment['patient_middle_name'] ?? '',
                        $appointment['patient_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $doctorName = trim(implode(' ', array_filter([
                        $appointment['doctor_first_name'] ?? '',
                        $appointment['doctor_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $patientMobile = trim((string) ($appointment['patient_mobile'] ?? ''));
                    $doctorCode = trim((string) ($appointment['doctor_code'] ?? ''));
                    $serviceName = trim((string) ($appointment['service_name'] ?? '')) ?: 'Service not specified';
                    $statusKey = (string) ($appointment['status'] ?? '');
                    $statusTone = ['scheduled' => 'blue', 'confirmed' => 'blue', 'checked_in' => 'amber', 'in_queue' => 'orange', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'gray'][$statusKey] ?? 'charcoal';
                    $statusLabel = ucwords(str_replace('_', ' ', $statusKey));
                    $appointmentNumber = (string) ($appointment['appointment_number'] ?? 'Appointment');
                    $isClosed = in_array($statusKey, ['completed', 'cancelled', 'no_show'], true);
                    $canReschedule = $canEdit && ! $isClosed;
                    $canCancel = $canEdit && in_array($statusKey, ['scheduled', 'confirmed', 'checked_in', 'in_queue'], true);
                    $queuePosition = ($appointment['queue_position'] ?? null) === null || ($appointment['queue_position'] ?? '') === '' ? null : (int) $appointment['queue_position'];
                    ?>
                    <tr class="admin-appointment-row" data-appointment-id="<?= (int) $appointment['id'] ?>">
                        <td class="admin-appointment-cell admin-appointment-cell-patient" data-label="Patient">
                            <strong class="admin-appointment-primary" title="<?= esc($patientName) ?>"><?= esc($patientName) ?></strong>
                            <span class="admin-appointment-meta" title="Patient number: <?= esc((string) ($appointment['registration_number'] ?? 'Not provided')) ?>"><span class="admin-appointment-meta-label">Patient no.</span> <?= esc($appointment['registration_number'] ?? 'Not provided') ?></span>
                            <span class="admin-appointment-meta" title="<?= esc($patientMobile !== '' ? $patientMobile : 'Mobile unavailable') ?>"><i class="bi bi-telephone mr-1" aria-hidden="true"></i><?= esc($patientMobile !== '' ? $patientMobile : 'Mobile unavailable') ?></span>
                        </td>
                        <td class="admin-appointment-cell admin-appointment-cell-doctor" data-label="Doctor">
                            <strong class="admin-appointment-primary" title="<?= esc($doctorName) ?>"><?= esc($doctorName) ?></strong>
                            <span class="admin-appointment-meta"><span class="admin-appointment-meta-label">Code</span> <?= esc($doctorCode !== '' ? $doctorCode : 'Not provided') ?></span>
                        </td>
                        <td class="admin-appointment-cell admin-appointment-cell-appointment" data-label="Appointment">
                            <div class="admin-appointment-date-time">
                                <span class="admin-appointment-number" title="<?= esc($appointmentNumber) ?>"><?= esc($appointmentNumber) ?></span>
                                <time class="admin-appointment-date" datetime="<?= esc($appointment['appointment_date'] ?? '') ?>"><?= esc(rootins_format_date($appointment['appointment_date'] ?? '')) ?></time>
                                <time class="admin-appointment-time" datetime="<?= esc(($appointment['appointment_date'] ?? '') . 'T' . ($appointment['appointment_time'] ?? '')) ?>"><?= esc(rootins_format_time($appointment['appointment_time'] ?? '')) ?></time>
                            </div>
                            <span class="admin-appointment-meta admin-appointment-service" title="<?= esc($serviceName) ?>"><i class="bi bi-briefcase mr-1" aria-hidden="true"></i><?= esc($serviceName) ?></span>
                            <span class="badge badge-light admin-appointment-queue" title="<?= $queuePosition !== null ? 'Queue #' . $queuePosition : 'No queue position' ?>">Queue <?= $queuePosition !== null ? '#' . $queuePosition : '—' ?></span>
                        </td>
                        <td class="admin-appointment-cell admin-appointment-cell-status" data-label="Status">
                            <span class="badge ri-status ri-status--<?= esc($statusTone) ?>">
                                <?= esc($statusLabel) ?>
                            </span>
                            <?php if (! empty($appointment['consultation_eligible'])): ?>
                                <span class="admin-appointment-indicator admin-appointment-indicator--eligible" title="Consultation can be started for this appointment"><i class="bi bi-check-circle mr-1" aria-hidden="true"></i>Consult eligible</span>
                            <?php elseif (! empty($appointment['consultation_date_passed']) && ! $isClosed): ?>
                                <span class="admin-appointment-indicator admin-appointment-indicator--past" title="<?= esc($appointment['consultation_unavailable_reason'] ?? 'Past appointment') ?>"><i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past appointment</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-appointment-actions text-right" data-label="Actions">
                            <div class="dropdown ri-actions-wrapper">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle ri-more-actions-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="More Actions for appointment <?= esc($appointmentNumber) ?>">
                                    <i class="fa-solid fa-ellipsis-vertical ri-actions-icon mr-1" aria-hidden="true"></i>More Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right ri-actions-menu" role="menu">
                            <a href="<?= base_url('admin/appointments/view/' . (int) $appointment['id']) ?>"
                               class="dropdown-item" role="menuitem" aria-label="View appointment <?= esc($appointmentNumber) ?>"><i class="bi bi-eye mr-2" aria-hidden="true"></i>View appointment
                            </a>
                            <?php if ($canEdit): ?><button type="button" class="dropdown-item edit-appointment" data-appointment-id="<?= (int) $appointment['id'] ?>" data-id="<?= (int) $appointment['id'] ?>" data-toggle="modal" data-target="#appointmentModal" aria-label="Edit appointment <?= esc($appointmentNumber) ?>"><i class="bi bi-pencil-square mr-2" aria-hidden="true"></i>Edit appointment</button><?php endif; ?>
                            <?php if ($canReschedule): ?><button type="button" class="dropdown-item reschedule-appointment" data-appointment-id="<?= (int) $appointment['id'] ?>" data-id="<?= (int) $appointment['id'] ?>" data-toggle="modal" data-target="#appointmentRescheduleModal" aria-label="Reschedule appointment <?= esc($appointmentNumber) ?>"><i class="bi bi-calendar2-week mr-2" aria-hidden="true"></i>Reschedule appointment</button><?php endif; ?>
                            <?php if ($canCancel): ?><button type="button" class="dropdown-item text-danger cancel-appointment" data-id="<?= (int) $appointment['id'] ?>" data-appointment-number="<?= esc($appointmentNumber) ?>" aria-label="Cancel appointment <?= esc($appointmentNumber) ?>"><i class="bi bi-x-circle mr-2" aria-hidden="true"></i>Cancel appointment</button><?php endif; ?>
                            <?php if ($canConsult && ! empty($appointment['consultation_eligible'])): ?><a href="<?= base_url('admin/consultations') . '?appointment_id=' . (int) $appointment['id'] ?>" class="dropdown-item" role="menuitem"><i class="bi bi-clipboard2-pulse mr-2" aria-hidden="true"></i>Consult</a><?php endif; ?>
                            <?php if ($canBilling): ?><a href="<?= base_url('admin/billing') . '?appointment_id=' . (int) $appointment['id'] ?>" class="dropdown-item" role="menuitem"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>Billing / payment</a><?php endif; ?>
                            <?php if ($canDelete): ?><button type="button" class="dropdown-item text-danger delete-appointment" data-id="<?= (int) $appointment['id'] ?>" data-appointment-number="<?= esc($appointmentNumber) ?>" aria-label="Delete appointment <?= esc($appointmentNumber) ?>"><i class="bi bi-trash mr-2" aria-hidden="true"></i>Delete</button><?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', [
        'pager' => $pager ?? null,
        'group' => 'appointments',
        'filters' => $filters,
    ]) ?>
</main>

<div class="modal fade" id="appointmentFilterModal" tabindex="-1" role="dialog" aria-labelledby="appointmentFilterTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="appointmentFilterTitle">Filter appointments</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form method="get" action="<?= current_url() ?>">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="appointmentFilterSearch">Search</label>
                            <input type="search" class="form-control" id="appointmentFilterSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Appointment no., patient, doctor, clinic">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="appointmentFilterStatus">Status</label>
                            <select class="form-control btSelect" id="appointmentFilterStatus" name="status" title="All statuses">
                                <option value="">All statuses</option>
                                <?php foreach (['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'] as $status): ?>
                                    <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="appointmentFilterType">Type</label>
                            <select class="form-control btSelect" id="appointmentFilterType" name="appointment_type" title="All types">
                                <option value="">All types</option>
                                <?php foreach (['in_person', 'video', 'phone'] as $type): ?>
                                    <option value="<?= esc($type) ?>" <?= ($filters['appointment_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="appointmentFilterClinic">Clinic</label>
                            <select class="form-control btSelect" id="appointmentFilterClinic" name="clinic_id" title="All clinics">
                                <option value="">All clinics</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= (int) $clinic['id'] ?>" <?= (string) ($filters['clinic_id'] ?? '') === (string) $clinic['id'] ? 'selected' : '' ?>><?= esc($clinic['clinic_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="appointmentFilterDoctor">Doctor</label>
                            <select class="form-control btSelect" id="appointmentFilterDoctor" name="doctor_id" title="All doctors">
                                <option value="">All doctors</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <?php $doctorLabel = trim($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    <option value="<?= (int) $doctor['id'] ?>" <?= (string) ($filters['doctor_id'] ?? '') === (string) $doctor['id'] ? 'selected' : '' ?>>Dr. <?= esc($doctorLabel . ' (' . $doctor['doctor_code'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="appointmentFilterFrom">Date from</label>
                            <input type="text" class="form-control datepicker" id="appointmentFilterFrom" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="appointmentFilterTo">Date to</label>
                            <input type="text" class="form-control datepicker" id="appointmentFilterTo" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= current_url() ?>" class="btn btn-outline-secondary">Clear</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable ri-scroll-modal" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Appointment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?= form_open('', 'id="appointmentForm" autocomplete="off"') ?>
                <input type="hidden" name="id" id="appointment_id">
                <div class="modal-body">
                    <div id="appointmentLoading" class="alert alert-info d-none" role="status">Loading appointment…</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="appointment_patient_id">Patient <span class="text-danger">*</span></label>
                                <select name="patient_id" id="appointment_patient_id" class="form-control btSelect" data-live-search="true" title="Select Patient">
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <?php $name = trim(implode(' ', array_filter([$patient['first_name'] ?? '', $patient['middle_name'] ?? '', $patient['last_name'] ?? '']))); ?>
                                        <option value="<?= (int) $patient['id'] ?>">
                                            <?= esc($patient['registration_number'] . ' - ' . $name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="patient_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="appointment_doctor_id">Doctor <span class="text-danger">*</span></label>
                                <select name="doctor_id" id="appointment_doctor_id" class="form-control btSelect" data-live-search="true" title="Select Doctor">
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= (int) $doctor['id'] ?>">
                                            Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name']) . ' (' . $doctor['doctor_code'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="doctor_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="appointment_clinic_id">Clinic <span class="text-danger">*</span></label>
                                <select name="clinic_id" id="appointment_clinic_id" class="form-control btSelect" data-live-search="true" title="Select Clinic">
                                    <option value="">Select Clinic</option>
                                    <?php foreach ($clinics as $clinic): ?>
                                        <option value="<?= (int) $clinic['id'] ?>"><?= esc($clinic['clinic_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="clinic_idErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="appointment_service_name">Service</label>
                                <input type="text" id="appointment_service_name" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="appointment_date">Date <span class="text-danger">*</span></label>
                            <input type="text" name="appointment_date" id="appointment_date" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
                                <div class="validation-error" id="appointment_dateErr"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="appointment_time">Time <span class="text-danger">*</span></label>
                                <input type="time" name="appointment_time" id="appointment_time" class="form-control" step="900" list="appointmentTimeSlots" required autocomplete="off" aria-describedby="appointmentTimeHelp appointment_timeErr">
                                <datalist id="appointmentTimeSlots"></datalist>
                                <small id="appointmentTimeHelp" class="form-text text-muted">Select a doctor and date to load available 15-minute slots.</small>
                                <div class="validation-error" id="appointment_timeErr"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="appointment_end_time">End time</label>
                                <input type="text" id="appointment_end_time" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="duration_minutes">Duration (minutes) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="1" max="999" value="15">
                                <div class="validation-error" id="duration_minutesErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="appointment_type">Type <span class="text-danger">*</span></label>
                                <select name="appointment_type" id="appointment_type" class="form-control btSelect" title="Select Type">
                                    <option value="in_person">In Person</option>
                                    <option value="video">Video</option>
                                    <option value="phone">Phone</option>
                                </select>
                                <div class="validation-error" id="appointment_typeErr"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="appointment_status">Status <span class="text-danger">*</span></label>
                                <select name="status" id="appointment_status" class="form-control btSelect" title="Select Status">
                                    <option value="scheduled">Scheduled</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="checked_in">Checked In</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no_show">No Show</option>
                                </select>
                                <div class="validation-error" id="statusErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reason_for_visit">Reason for Visit</label>
                                <textarea name="reason_for_visit" id="reason_for_visit" class="form-control" rows="3"></textarea>
                                <div class="validation-error" id="reason_for_visitErr"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="appointment_notes">Notes</label>
                                <textarea name="notes" id="appointment_notes" class="form-control" rows="3"></textarea>
                                <div class="validation-error" id="notesErr"></div>
                            </div>
                        </div>
                        <div class="col-12" id="cancellationFields" style="display:none;">
                            <div class="border rounded p-3">
                                <strong class="d-block mb-2">Cancellation Details</strong>
                                <div class="form-group mb-0">
                                    <label for="cancellation_reason">Cancellation Reason</label>
                                    <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="2"></textarea>
                                    <div class="validation-error" id="cancellation_reasonErr"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary save">Save Appointment</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<div class="modal fade appointment-reschedule-modal" id="appointmentRescheduleModal" tabindex="-1" role="dialog" aria-labelledby="appointmentRescheduleTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable ri-scroll-modal" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentRescheduleTitle">Reschedule Appointment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <?= form_open('', 'id="appointmentRescheduleForm" autocomplete="off"') ?>
                <input type="hidden" name="patient_id" id="reschedule_patient_id">
                <input type="hidden" name="doctor_id" id="reschedule_doctor_id">
                <input type="hidden" name="clinic_id" id="reschedule_clinic_id">
                <input type="hidden" name="duration_minutes" id="reschedule_duration_minutes">
                <input type="hidden" name="appointment_type" id="reschedule_appointment_type">
                <input type="hidden" name="status" id="reschedule_status">
                <input type="hidden" name="reason_for_visit" id="reschedule_reason_for_visit">
                <input type="hidden" name="notes" id="reschedule_notes">
                <input type="hidden" name="cancellation_reason" id="reschedule_cancellation_reason">
                <div class="modal-body">
                    <div id="appointmentRescheduleLoading" class="alert alert-info d-none" role="status">Loading appointment…</div>
                    <div id="appointmentRescheduleErrors" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label for="reschedule_patient_name">Patient</label><input type="text" class="form-control" id="reschedule_patient_name" readonly></div>
                        <div class="form-group col-md-6"><label for="reschedule_doctor_name">Current doctor</label><input type="text" class="form-control" id="reschedule_doctor_name" readonly></div>
                        <div class="form-group col-md-6"><label for="reschedule_current_datetime">Current date/time</label><input type="text" class="form-control" id="reschedule_current_datetime" readonly></div>
                        <div class="form-group col-md-6"><label for="reschedule_current_end_time">Current end time</label><input type="text" class="form-control" id="reschedule_current_end_time" readonly></div>
                        <div class="form-group col-md-6"><label for="reschedule_new_date">New date <span class="text-danger">*</span></label><input type="text" name="appointment_date" class="form-control datepicker" id="reschedule_new_date" placeholder="dd-mm-yyyy" autocomplete="off"><div class="validation-error" id="reschedule_appointment_dateErr"></div></div>
                        <div class="form-group col-md-6"><label for="reschedule_new_start_time">New start time <span class="text-danger">*</span></label><input type="text" name="appointment_time" class="form-control" id="reschedule_new_start_time" placeholder="hh:mm AM/PM" inputmode="text" autocomplete="off"><div class="validation-error" id="reschedule_appointment_timeErr"></div></div>
                        <div class="form-group col-md-6"><label for="reschedule_new_end_time">New end time</label><input type="text" class="form-control" id="reschedule_new_end_time" readonly></div>
                        <div class="form-group col-md-6"><label for="reschedule_reason">Reason / remarks</label><textarea name="reschedule_remarks" class="form-control" id="reschedule_reason" rows="3" placeholder="Reason for rescheduling"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary reschedule-save">Save reschedule</button></div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
