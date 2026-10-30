<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$filters = $filters ?? [];
$appointments = $appointments ?? [];
$services = $services ?? [];
$statusSummary = $statusSummary ?? [];
$canCreate = in_array('appointment.create', session('permissions') ?? [], true);
$canView = in_array('appointment.view', session('permissions') ?? [], true);
$canEdit = in_array('appointment.edit', session('permissions') ?? [], true);
$canConsult = in_array('consultation.edit', session('permissions') ?? [], true);
$canFollowUp = in_array('consultation.edit', session('permissions') ?? [], true);
$canPrescription = in_array('prescription.view', session('permissions') ?? [], true);
$canBilling = in_array('billing.view', session('permissions') ?? [], true);
$displayDate = static function ($value): string {
    $value = (string) ($value ?? '');
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false ? $date->format('d-m-Y') : $value;
};
$nextStatuses = [
    'scheduled' => ['confirmed' => 'Confirm', 'checked_in' => 'Check in', 'no_show' => 'No-show'],
    'confirmed' => ['checked_in' => 'Check in', 'no_show' => 'No-show'],
    'checked_in' => ['in_queue' => 'Add to queue', 'no_show' => 'No-show'],
    'in_queue' => ['completed' => 'Complete', 'no_show' => 'No-show'],
];
$filterErrors = $filters['filter_errors'] ?? [];
$requestedAppointmentView = (string) ($filters['view'] ?? 'list');
$appointmentView = in_array($requestedAppointmentView, ['list', 'grid'], true) ? $requestedAppointmentView : 'list';
$appointmentContext = static function (array $appointment) use ($displayDate): array {
    $status = (string) ($appointment['status'] ?? '');
    $patientName = trim(($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''));
    $patientMobile = service('telephoneContact')->formatDisplay($appointment['patient_telephone_country_code'] ?? null, $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null, $appointment['patient_legacy_country_code'] ?? null);

    return [
        'appointment' => $appointment,
        'appointmentId' => (int) ($appointment['id'] ?? 0),
        'patientId' => (int) ($appointment['patient_id'] ?? 0),
        'serviceName' => trim((string) ($appointment['service_name'] ?? '')) !== '' ? (string) $appointment['service_name'] : '—',
        'queuePosition' => $appointment['queue_position'] ?? null,
        'status' => $status,
        'consultationDatePassed' => (bool) ($appointment['consultation_date_passed'] ?? false),
        'consultationDateToday' => (bool) ($appointment['consultation_date_today'] ?? false),
        'patientName' => $patientName !== '' ? $patientName : 'Unnamed patient',
        'patientInitial' => strtoupper(substr($patientName !== '' ? $patientName : '?', 0, 1)),
        'patientMobile' => $patientMobile !== '' ? $patientMobile : 'Mobile not recorded',
        'appointmentType' => ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? ''))),
        'appointmentDateDisplay' => $displayDate($appointment['appointment_date'] ?? ''),
        'appointmentTimeDisplay' => rootins_format_time($appointment['appointment_time'] ?? ''),
    ];
};
$hasActiveFilters = trim((string) ($filters['search'] ?? '')) !== ''
    || (int) ($filters['service_id'] ?? 0) > 0
    || (int) ($filters['queue_number'] ?? 0) > 0
    || trim((string) ($filters['appointment_type'] ?? '')) !== ''
    || trim((string) ($filters['status'] ?? '')) !== ''
    || trim((string) ($filters['date_from'] ?? '')) !== ''
    || trim((string) ($filters['date_to'] ?? '')) !== '';
?>

<main class="dashboard-container doctor-appointments-page" aria-labelledby="doctor-appointments-title">
    <header class="doctor-appointments-header">
    <div class="page-heading mb-0">
        <div>
            <div class="page-eyebrow">Schedule / patient queue</div>
            <h1 id="doctor-appointments-title">Appointments</h1>
            <p class="text-muted mb-0">Create and manage appointments within your clinic scope.</p>
        </div>
        <div class="doctor-appointments-header-actions">
            <div class="doctor-appointments-total" role="status" aria-live="polite">
                <strong><?= (int) ($pagination['total'] ?? count($appointments)) ?></strong>
                <span>appointments in scope</span>
            </div>
            <?php if ($canCreate): ?>
                <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#doctor-appointment-create-modal" aria-controls="doctor-appointment-create-modal">
                    <i class="bi bi-calendar-plus mr-1" aria-hidden="true"></i> New Appointment
                </button>
            <?php endif; ?>
        </div>
    </div>
    </header>

    <section class="card doctor-appointments-toolbar-card" aria-labelledby="doctor-appointments-toolbar-title">
        <div class="card-body">
            <h2 class="sr-only" id="doctor-appointments-toolbar-title">Appointment search and filters</h2>
            <form class="doctor-appointments-toolbar-form" method="get" action="<?= current_url() ?>">
                <div class="doctor-appointments-search form-group mb-0">
                    <label for="doctor-appointment-toolbar-search">Search appointments</label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                        </div>
                        <input id="doctor-appointment-toolbar-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control" type="search" placeholder="Search patient name, patient number, mobile, service, queue, or status" autocomplete="off">
                    </div>
                    <div class="validation-error" id="appointment_searchErr"></div>
                </div>
                <div class="doctor-appointments-toolbar-actions">
                    <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#doctor-appointment-filter-modal" aria-controls="doctor-appointment-filter-modal">
                        <i class="bi bi-funnel mr-1" aria-hidden="true"></i> Filters
                    </button>
                    <a class="btn btn-light" href="<?= current_url() ?>">
                        <i class="bi bi-arrow-counterclockwise mr-1" aria-hidden="true"></i> Clear filters
                    </a>
                    <a href="<?= base_url('doctor/clinical') ?>" class="btn btn-light">
                        <i class="bi bi-list-check mr-1" aria-hidden="true"></i> Today’s queue
                    </a>
                    <div class="btn-group doctor-appointments-view-toggle" role="group" aria-label="Appointment view">
                        <button type="button" class="btn btn-outline-secondary <?= $appointmentView === 'list' ? 'active' : '' ?>" data-appointment-view="list" aria-pressed="<?= $appointmentView === 'list' ? 'true' : 'false' ?>" title="List View">
                            <i class="fa-solid fa-list" aria-hidden="true"></i><span class="ml-1">List</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary <?= $appointmentView === 'grid' ? 'active' : '' ?>" data-appointment-view="grid" aria-pressed="<?= $appointmentView === 'grid' ? 'true' : 'false' ?>" title="Grid View">
                            <i class="fa-solid fa-grip" aria-hidden="true"></i><span class="ml-1">Grid</span>
                        </button>
                    </div>
                </div>
                <div class="doctor-appointments-page-size form-group mb-0">
                    <label for="doctor-appointments-toolbar-page-size">Rows</label>
                    <select class="custom-select custom-select-sm" id="doctor-appointments-toolbar-page-size" name="page_size" onchange="this.form.submit()">
                        <?php foreach ([10, 15, 25, 50] as $size): ?>
                            <option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="appointment_type" value="<?= esc($filters['appointment_type'] ?? '') ?>">
                <input type="hidden" name="status" value="<?= esc($filters['status'] ?? '') ?>">
                <input type="hidden" name="service_id" value="<?= (int) ($filters['service_id'] ?? 0) ?>">
                <input type="hidden" name="queue_number" value="<?= (int) ($filters['queue_number'] ?? 0) ?>">
                <input type="hidden" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>">
                <input type="hidden" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>">
                <input type="hidden" name="page" value="<?= max(1, (int) ($filters['page'] ?? 1)) ?>" data-appointment-page-input>
                <input type="hidden" name="view" value="<?= esc($appointmentView) ?>" data-appointment-view-input>
            </form>
        </div>
    </section>

    <section class="doctor-appointments-status-summary" aria-label="Appointment status summary">
        <?php foreach ([
            'today' => ['Today', 'bi-calendar-day'],
            'pending' => ['Pending', 'bi-hourglass-split'],
            'confirmed' => ['Confirmed', 'bi-check2-circle'],
            'completed' => ['Completed', 'bi-check2-all'],
            'cancelled' => ['Cancelled', 'bi-x-circle'],
        ] as $key => [$label, $icon]): ?>
            <div class="doctor-appointments-status-item doctor-appointments-status-<?= esc($key) ?>">
                <span class="doctor-appointments-status-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span>
                <span class="doctor-appointments-status-copy"><small><?= esc($label) ?></small><strong><?= (int) ($statusSummary[$key] ?? 0) ?></strong></span>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($canCreate): ?>
    <div class="modal fade ri-scroll-modal" id="doctor-appointment-create-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-appointment-create-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-appointment-create-title">New Appointment</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close appointment form"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="doctor-appointment-create-form" novalidate>
                    <div class="modal-body">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-patient-search">Patient <span class="text-danger" aria-hidden="true">*</span></label>
                                <div class="doctor-patient-autocomplete position-relative">
                                    <input id="appointment-patient-search" type="search" class="form-control" placeholder="Type at least 2 characters" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="appointment-patient-results" aria-expanded="false" aria-describedby="patient_idErr" required>
                                    <input type="hidden" id="appointment-patient-id" name="patient_id" value="">
                                    <div id="appointment-patient-results" class="doctor-patient-autocomplete-menu list-group" role="listbox" hidden></div>
                                </div>
                                <div class="validation-error" id="patient_idErr" aria-live="polite"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-service">Service <span class="text-danger" aria-hidden="true">*</span></label>
                                <select id="appointment-service" name="service_ids[]" class="form-control btSelect" data-live-search="true" data-actions-box="true" data-selected-text-format="count > 2" title="Select one or more services" multiple required>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= (int) $service['id'] ?>"><?= esc($service['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="appointment-service-legacy" name="service_name" value="">
                                <div class="validation-error" id="service_idsErr" data-error-for="service_ids[]"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-type">Consultation type <span class="text-danger" aria-hidden="true">*</span></label>
                                <select id="appointment-type" name="appointment_type" class="form-control btSelect" title="Select consultation type" required>
                                    <option value="in_person">In person</option>
                                    <option value="video">Video</option>
                                    <option value="phone">Telephone</option>
                                </select>
                                <div class="validation-error" id="appointment_typeErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-date">Date <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="appointment-date" name="appointment_date" type="text" class="form-control datepicker" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" required>
                                <div class="validation-error" id="appointment_dateErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-time">Time <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="appointment-time" name="appointment_time" type="time" class="form-control" step="900" list="appointment-time-options" autocomplete="off" aria-describedby="appointment-time-help appointment_timeErr" required disabled>
                                <datalist id="appointment-time-options"></datalist>
                                <small id="appointment-time-help" class="form-text text-muted">Select a date to load available 15-minute times.</small>
                                <div class="validation-error" id="appointment_timeErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-status">Status <span class="text-danger" aria-hidden="true">*</span></label>
                                <select id="appointment-status" name="appointment_status" class="form-control btSelect" title="Select appointment status" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                                <div class="validation-error" id="appointment_statusErr"></div>
                            </div>
                            <div class="form-group col-12">
                                <label for="appointment-reason">Reason for visit <span class="text-muted">(optional)</span></label>
                                <textarea id="appointment-reason" name="reason_for_visit" class="form-control" rows="2" maxlength="1000"></textarea>
                                <div class="validation-error" id="reason_for_visitErr"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit" id="doctor-appointment-create-submit">Create appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="card doctor-appointments-content-card" aria-labelledby="doctor-appointment-register-title">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <h2 class="h5 mb-1" id="doctor-appointment-register-title">Appointment register</h2>
                    <small class="text-muted" role="status" aria-live="polite">
                        <?= (int) ($pagination['total'] ?? count($appointments)) ?> appointment(s) in scope
                        <span class="badge badge-light ml-1"><?= $hasActiveFilters ? 'Filters active' : 'All appointments' ?></span>
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body p-0 doctor-appointment-content-body">
        <?php if ($appointments !== []): ?>
            <div class="doctor-appointment-records" data-appointment-view-container data-view="<?= esc($appointmentView) ?>" role="<?= $appointmentView === 'grid' ? 'list' : 'region' ?>" aria-label="Appointments">
                <?= view(DOCTOR_VIEW . 'pages/appointments_' . $appointmentView, [
                    'appointments' => $appointments,
                    'appointmentContext' => $appointmentContext,
                    'canConsult' => $canConsult,
                    'canEdit' => $canEdit,
                    'nextStatuses' => $nextStatuses,
                    'canView' => $canView,
                    'canFollowUp' => $canFollowUp,
                    'canPrescription' => $canPrescription,
                    'canBilling' => $canBilling,
                ]) ?>
            </div>
        <?php else: ?>
            <div class="empty-state" role="status" aria-live="polite">
                <i class="bi bi-calendar-x d-block h3 mb-2" aria-hidden="true"></i>
                <strong>No appointments match the current filters.</strong>
                <small class="d-block mt-1">Use New Appointment or adjust Filters to get started.</small>
            </div>
        <?php endif; ?>
        </div>
        <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters, 'hidePageSize' => true]) ?>
    </section>
</main>

<div class="modal fade ri-scroll-modal" id="doctor-appointment-filter-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-appointment-filter-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="get" action="<?= current_url() ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-appointment-filter-title">Filter appointments</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close filters"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="search" value="<?= esc($filters['search'] ?? '') ?>">
                    <input type="hidden" name="view" value="<?= esc($appointmentView) ?>" data-appointment-view-input>
                    <div class="form-group">
                        <label for="doctor-appointment-filter-type">Consultation type</label>
                        <select id="doctor-appointment-filter-type" name="appointment_type" class="form-control btSelect" title="All consultation types">
                            <option value="">All consultation types</option>
                            <?php foreach (['in_person' => 'In person', 'video' => 'Video', 'phone' => 'Telephone'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= ($filters['appointment_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="validation-error" id="doctor_appointment_filter_appointment_typeErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-appointment-filter-service">Service</label>
                        <select id="doctor-appointment-filter-service" name="service_id" class="form-control btSelect" data-live-search="true" title="All services">
                            <option value="">All services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int) $service['id'] ?>" <?= (int) ($filters['service_id'] ?? 0) === (int) $service['id'] ? 'selected' : '' ?>><?= esc($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="validation-error" id="service_idErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-appointment-filter-status">Status</label>
                        <select id="doctor-appointment-filter-status" name="status" class="form-control btSelect" title="All statuses">
                            <option value="">All statuses</option>
                            <?php foreach (['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'] as $status): ?>
                                <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="validation-error" id="doctor_appointment_filter_statusErr"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="doctor-appointment-filter-from">Date from</label>
                            <input id="doctor-appointment-filter-from" name="date_from" type="text" class="form-control datepicker" data-submit-display-date="true" placeholder="dd-mm-yyyy" value="<?= esc($filters['date_from'] ?? '') ?>" autocomplete="off">
                            <div class="validation-error" id="date_fromErr"><?= esc($filterErrors['date_from'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="doctor-appointment-filter-to">Date to</label>
                            <input id="doctor-appointment-filter-to" name="date_to" type="text" class="form-control datepicker" data-submit-display-date="true" placeholder="dd-mm-yyyy" value="<?= esc($filters['date_to'] ?? '') ?>" autocomplete="off">
                            <div class="validation-error" id="date_toErr"><?= esc($filterErrors['date_to'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-appointment-filter-queue">Queue number</label>
                        <input id="doctor-appointment-filter-queue" name="queue_number" type="number" min="1" step="1" class="form-control" value="<?= (int) ($filters['queue_number'] ?? 0) ?: '' ?>" placeholder="Any queue number" inputmode="numeric">
                        <div class="validation-error" id="queue_numberErr"></div>
                    </div>
                    <input type="hidden" name="page_size" value="<?= esc((string) ($filters['page_size'] ?? 15)) ?>">
                </div>
                <div class="modal-footer">
                    <a class="btn btn-light" href="<?= current_url() ?>">Clear</a>
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade ri-scroll-modal" id="doctor-reschedule-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-reschedule-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form id="doctor-reschedule-form" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-reschedule-title">Reschedule appointment</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close reschedule form"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" id="reschedule-appointment-id" name="appointment_id">
                    <div class="validation-error" id="appointment_idErr"></div>
                    <div class="form-group">
                        <label for="reschedule-date">New date (dd-mm-yyyy): <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="reschedule-date" name="appointment_date" type="text" class="form-control datepicker" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" required>
                        <div class="validation-error" id="reschedule_appointment_dateErr" data-error-for="appointment_date"></div>
                    </div>
                    <div class="form-group">
                        <label for="reschedule-time">New time <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="reschedule-time" name="appointment_time" type="text" class="form-control" placeholder="hh:mm AM/PM" inputmode="text" autocomplete="off" required>
                        <div class="validation-error" id="reschedule_appointment_timeErr" data-error-for="appointment_time"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="doctor-reschedule-submit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade ri-scroll-modal" id="doctor-video-consultation-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-video-consultation-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="doctor-video-consultation-title">Video consultation</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close video consultation"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="doctor-video-consultation-body">
                <div class="text-center text-muted py-4" role="status">Loading video consultation…</div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://sdk.twilio.com/js/video/releases/2.29.0/twilio-video.min.js"></script>
<script src="<?= base_url('assets/js/video/twilio-room.js?v=20261030.5') ?>"></script>
<script>
$(function () {
    const createForm = $('#doctor-appointment-create-form');
    const patientSearchInput = $('#appointment-patient-search');
    const patientIdInput = $('#appointment-patient-id');
    const patientResults = $('#appointment-patient-results');
    const serviceSelect = $('#appointment-service');
    const rescheduleForm = $('#doctor-reschedule-form');
    const csrf = function () { return $('input[name="csrf_rtn"]').first().val(); };
    let patientSearchTimer = null;
    let doctorVideoRequest = null;
    let appointmentSlotsRequest = null;
    let appointmentSlotValues = [];

    $(document).on('click.doctorVideoConsultation', '.js-video-consultation', function () {
        const button = $(this);
        const url = button.data('video-url');
        const modal = $('#doctor-video-consultation-modal');
        const body = $('#doctor-video-consultation-body');
        if (!url || !modal.length || !body.length) return;
        button.closest('.dropdown').find('[data-toggle="dropdown"]').dropdown('hide');
        body.html('<div class="text-center text-muted py-4" role="status">Loading video consultation…</div>');
        modal.modal('show');
        doctorVideoRequest = ajaxRequest({
            url: url + (url.indexOf('?') === -1 ? '?' : '&') + 'fragment=1',
            method: 'GET',
            dataType: 'html',
            timeout: 30000,
            success: function (html) { body.html(html); body.find('[title]').tooltip(); },
            error: function (xhr, status) {
                body.html('<div class="alert alert-danger mb-0" role="alert">' + (status === 'timeout' ? 'Video consultation loading timed out.' : 'Video consultation could not be loaded safely.') + '</div>');
            },
            complete: function () { }
        });
    });

    $(document).on('hidden.bs.modal.doctorVideoConsultation', '#doctor-video-consultation-modal', function () {
        if (doctorVideoRequest && doctorVideoRequest.readyState !== 4) doctorVideoRequest.abort();
    });

    $(document).on('click.doctorVideoStart', '.js-video-start', function () {
        const button = $(this);
        const url = button.data('start-url');
        if (!url) return;
        button.prop('disabled', true);
        doctorVideoRequest = ajaxRequest({
            url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (!response.status) { showError(response.msg || 'Video consultation could not be started.'); return; }
                const panel = button.closest('.doctor-video-panel');
                const badge = panel.find('.doctor-video-status-badge');
                badge.removeClass(function (index, classes) { return (classes.match(/(^|\\s)doctor-video-status-\\S+/g) || []).join(' '); }).addClass('doctor-video-status-waiting');
                panel.find('.doctor-video-status').text('Waiting');
                panel.find('.doctor-video-stage-title').text('Ready to join');
                panel.find('.doctor-video-connection').text('The secure room is waiting for you to join.');
                panel.find('.js-video-join').prop('disabled', false);
                button.remove();
                window.setTimeout(function () { panel.find('.js-video-join').trigger('click'); }, 150);
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Video consultation start timed out.' : 'Video consultation could not be started.'); },
            complete: function () { if (button.closest('body').length && !button.data('started')) button.prop('disabled', false); }
        });
    });

    $(document).on('click.doctorVideoJoin', '.js-video-join', function () {
        const button = $(this);
        const url = button.data('token-url');
        if (!url) return;
        button.prop('disabled', true);
        doctorVideoRequest = ajaxRequest({
            url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (!response.status) { showError(response.msg || 'Video service is not available yet.'); return; }
                const panel = button.closest('.doctor-video-panel');
                const badge = panel.find('.doctor-video-status-badge');
                badge.removeClass(function (index, classes) { return (classes.match(/(^|\\s)doctor-video-status-\\S+/g) || []).join(' '); }).addClass('doctor-video-status-active');
                panel.find('.doctor-video-status').text('Active');
                panel.find('.doctor-video-stage').addClass('is-connected');
                panel.find('.doctor-video-stage-title').text('Connected');
                panel.find('.doctor-video-connection').text('You are connected to the secure consultation room.');
                const stage = panel.find('.doctor-video-stage')[0];
                if (stage) {
                    stage.addEventListener('rootins:video:reconnecting', function () { panel.find('.doctor-video-connection').text('Connection interrupted. Reconnecting automatically…'); });
                    stage.addEventListener('rootins:video:reconnected', function () { panel.find('.doctor-video-connection').text('You are connected to the secure consultation room.'); });
                    stage.addEventListener('rootins:video:disconnected', function () { panel.find('.doctor-video-connection').text('The video connection ended. You can join again if the consultation is still active.'); });
                }
                if (window.RootinsTwilioRoom) window.RootinsTwilioRoom.connect(response.data.token, panel[0]).catch(function (error) {
                    const name = error && error.name ? error.name : '';
                    const message = name === 'NotReadableError'
                        ? 'The camera or microphone is busy. Close Microsoft Phone Link, Teams, Camera, or any other app using these devices, then try Join again.'
                        : (name === 'NotAllowedError' || name === 'PermissionDeniedError'
                            ? 'Camera or microphone access was denied. Allow access in the browser address-bar settings, then try Join again.'
                            : (error && error.message ? 'Video connection failed: ' + error.message : 'Camera or microphone permission or connection failed.'));
                    showError(message);
                });
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Video connection timed out.' : 'Video service is not available yet.'); },
            complete: function () { button.prop('disabled', false); }
        });
    });

    $(document).on('click.doctorVideoEnd', '.js-video-end', function () {
        const button = $(this);
        const url = button.data('end-url');
        if (!url || !window.confirm('End this video consultation?')) return;
        button.prop('disabled', true);
        doctorVideoRequest = ajaxRequest({
            url: url, method: 'POST', dataType: 'json', data: { csrf_rtn: csrf() }, timeout: 30000,
            success: function (response) {
                if (response.status) {
                    const panel = button.closest('.doctor-video-panel');
                    const badge = panel.find('.doctor-video-status-badge');
                    badge.removeClass(function (index, classes) { return (classes.match(/(^|\\s)doctor-video-status-\\S+/g) || []).join(' '); }).addClass('doctor-video-status-ended');
                    panel.find('.doctor-video-status').text('Ended');
                    panel.find('.doctor-video-stage').removeClass('is-connected');
                    panel.find('.doctor-video-stage-title').text('Consultation ended');
                    panel.find('.doctor-video-connection').text('This consultation has ended.');
                    panel.find('.js-video-end, .js-video-join, .js-video-mute, .js-video-camera').prop('disabled', true);
                    if (window.RootinsTwilioRoom) window.RootinsTwilioRoom.disconnect();
                    button.data('ended', true);
                } else showError(response.msg || 'Video consultation could not be ended.');
            },
            error: function (xhr, status) { if (status !== 'abort') showError(status === 'timeout' ? 'Ending consultation timed out.' : 'Video consultation could not be ended.'); },
            complete: function () { if (!button.data('ended')) button.prop('disabled', false); }
        });
    });

    $(document).on('click.doctorVideoDevice', '.js-video-mute, .js-video-camera', function () {
        const button = $(this);
        const enabled = button.attr('aria-pressed') !== 'true';
        button.attr('aria-pressed', enabled ? 'true' : 'false').toggleClass('active', enabled);
        const label = enabled ? (button.hasClass('js-video-mute') ? 'Unmute microphone' : 'Enable camera') : (button.hasClass('js-video-mute') ? 'Mute microphone' : 'Disable camera');
        button.attr('aria-label', label).attr('title', label).find('.sr-only').text(label);
        if (window.RootinsTwilioRoom) {
            if (button.hasClass('js-video-mute')) enabled ? window.RootinsTwilioRoom.mute() : window.RootinsTwilioRoom.unmute();
            if (button.hasClass('js-video-camera')) enabled ? window.RootinsTwilioRoom.cameraOff() : window.RootinsTwilioRoom.cameraOn();
        }
    });
    let patientSearchRequest = 0;
    let patientSearchXhr = null;
    let selectedPatient = null;

    $('.btSelect').selectpicker();
    $('[data-toggle="tooltip"]').tooltip();

    function hidePatientResults() {
        patientResults.empty().prop('hidden', true);
        patientSearchInput.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
    }

    function renderPatientStatus(message, loading) {
        patientResults.empty().append($('<div>', {
            class: 'list-group-item small text-muted',
            role: 'status',
            text: message
        })).prop('hidden', false);
        patientSearchInput.attr('aria-expanded', 'true').removeAttr('aria-activedescendant');
        if (loading) patientResults.find('[role="status"]').prepend($('<span>', {class: 'spinner-border spinner-border-sm mr-2', role: 'status', 'aria-hidden': 'true'}));
    }

    function renderPatients(items) {
        patientResults.empty();
        if (!items || !items.length) {
            renderPatientStatus('No patients found in your clinic scope.', false);
            return;
        }
        items.forEach(function (item, index) {
            const result = $('<button>', {
                type: 'button',
                class: 'list-group-item list-group-item-action text-left doctor-patient-result',
                role: 'option',
                id: 'doctor-patient-result-' + item.id,
                tabindex: '-1'
            }).attr('data-index', index).attr('data-patient-id', item.id);
            $('<strong>', {class: 'd-block', text: item.name || 'Unnamed patient'}).appendTo(result);
            $('<span>', {class: 'small text-muted', text: [item.mobile, 'ID ' + item.id, item.registration_number ? 'Reg. ' + item.registration_number : ''].filter(Boolean).join(' · ')}).appendTo(result);
            patientResults.append(result);
        });
        patientResults.data('items', items).data('active-index', -1).prop('hidden', false);
        patientSearchInput.attr('aria-expanded', 'true');
    }

    function setPatientError(message) {
        const error = $('#patient_idErr');
        error.text(message || '');
        patientSearchInput.toggleClass('is-invalid', Boolean(message)).attr('aria-invalid', message ? 'true' : 'false');
    }

    function choosePatient(item) {
        selectedPatient = item;
        patientIdInput.val(item.id);
        patientSearchInput.val(item.label || item.name || '');
        setPatientError('');
        hidePatientResults();
    }

    function setActivePatient(index) {
        const items = patientResults.data('items') || [];
        if (!items.length) return;
        const next = (index + items.length) % items.length;
        patientResults.data('active-index', next);
        patientResults.find('.doctor-patient-result').removeClass('active').eq(next).addClass('active');
        patientSearchInput.attr('aria-activedescendant', 'doctor-patient-result-' + items[next].id);
    }

    function searchPatients(search) {
        const requestId = ++patientSearchRequest;
        if (patientSearchXhr && patientSearchXhr.readyState !== 4) patientSearchXhr.abort();
        renderPatientStatus('Searching patients…', true);
        patientSearchXhr = ajaxRequest({
            url: '<?= base_url('doctor/appointments/patients/search') ?>',
            method: 'GET',
            data: { q: String(search || '').trim(), page: 1, page_size: 20 },
            success: function (response) {
                if (requestId !== patientSearchRequest) return;
                if (response.status) {
                    renderPatients(response.data && response.data.items ? response.data.items : []);
                    return;
                }
                renderPatientStatus(response.msg || 'Patients could not be loaded.', false);
            },
            error: function (xhr, status) {
                if (requestId !== patientSearchRequest || status === 'abort') return;
                renderPatientStatus('Patient search is unavailable. Try again.', false);
            }
        });
    }

    $(document).on('shown.bs.modal', '#doctor-appointment-create-modal', function () {
        initSharedDatepickers(this);
        hidePatientResults();
        patientSearchInput.trigger('focus');
    });

    $(document).on('shown.bs.modal', '#doctor-appointment-filter-modal', function () {
        initSharedDatepickers(this);
        $('.btSelect', this).selectpicker('refresh');
        $('#doctor-appointment-filter-type', this).trigger('focus');
    });

    $(document).on('input', '#appointment-patient-search', function () {
        const search = String(this.value || '').trim();
        selectedPatient = null;
        patientIdInput.val('');
        setPatientError('');
        window.clearTimeout(patientSearchTimer);
        if (search.length < 2) {
            hidePatientResults();
            return;
        }
        patientSearchTimer = window.setTimeout(function () { searchPatients(search); }, 280);
    });

    $(document).on('click', '.doctor-patient-result', function () {
        const items = patientResults.data('items') || [];
        choosePatient(items[Number($(this).data('index'))]);
    });

    $(document).on('keydown', '#appointment-patient-search', function (event) {
        const items = patientResults.data('items') || [];
        const active = Number(patientResults.data('active-index'));
        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            setActivePatient(active + 1);
        } else if (event.key === 'ArrowUp' && items.length) {
            event.preventDefault();
            setActivePatient(active <= 0 ? items.length - 1 : active - 1);
        } else if (event.key === 'Enter') {
            if (active >= 0 && items[active]) {
                event.preventDefault();
                choosePatient(items[active]);
            } else if (!patientIdInput.val()) {
                event.preventDefault();
                setPatientError('Select a patient from the search results.');
            }
        } else if (event.key === 'Escape') {
            hidePatientResults();
        }
    });

    $(document).on('mousedown', function (event) {
        if (!$(event.target).closest('.doctor-patient-autocomplete').length) {
            hidePatientResults();
        }
    });

    serviceSelect.on('changed.bs.select change', function () {
        const names = serviceSelect.find('option:selected').map(function () { return $(this).text().trim(); }).get();
        $('#appointment-service-legacy').val(names.join(', '));
    });

    function clearAppointmentTime(message) {
        const input = $('#appointment-time');
        appointmentSlotValues = [];
        input.val('').removeAttr('min max').prop('disabled', true).removeClass('is-invalid').attr('aria-invalid', 'false');
        $('#appointment-time-options').empty();
        $('#appointment-time-help').text(message || 'Select a date to load available 15-minute times.');
        $('#appointment_timeErr').text('');
    }

    function loadAppointmentSlots() {
        const date = String($('#appointment-date').val() || '').trim();
        clearAppointmentTime(date ? 'Loading available 15-minute times…' : 'Select a date to load available 15-minute times.');
        if (!date) return;
        if (appointmentSlotsRequest && appointmentSlotsRequest.readyState !== 4) appointmentSlotsRequest.abort();
        appointmentSlotsRequest = ajaxRequest({
            url: '<?= base_url('doctor/appointments/available-slots') ?>',
            method: 'GET',
            dataType: 'json',
            data: { appointment_date: date },
            success: function (response) {
                if (!response.status) {
                    $('#appointment-time-help').text(response.msg || 'Times could not be loaded.');
                    return;
                }
                const data = response.data || {};
                appointmentSlotValues = (data.slots || []).map(function (slot) { return String(slot.value || '').slice(0, 5); });
                const options = (data.slots || []).map(function (slot) {
                    return $('<option>').attr('value', String(slot.value || '').slice(0, 5)).attr('label', slot.label || slot.value || '');
                });
                $('#appointment-time-options').empty().append(options);
                const input = $('#appointment-time');
                if (appointmentSlotValues.length) {
                    input.attr({min: data.min || appointmentSlotValues[0], max: data.max || appointmentSlotValues[appointmentSlotValues.length - 1]}).prop('disabled', false);
                    $('#appointment-time-help').text('Available times are shown in 15-minute intervals.');
                } else {
                    $('#appointment-time-help').text(data.msg || 'No appointment times are available for this date.');
                }
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                $('#appointment-time-help').text('Times could not be loaded. Try selecting the date again.');
            }
        });
    }

    $(document).on('change.doctorAppointmentSlots changeDate.doctorAppointmentSlots input.doctorAppointmentSlots', '#appointment-date', loadAppointmentSlots);
    $(document).on('change.doctorAppointmentSlots input.doctorAppointmentSlots', '#appointment-time', function () {
        const value = String(this.value || '').trim();
        const valid = /^([01]\d|2[0-3]):[0-5]\d$/.test(value) && Number(value.slice(3)) % 15 === 0 && appointmentSlotValues.indexOf(value) !== -1;
        $('#appointment_timeErr').text(value && !valid ? 'Select an available time in 15-minute increments.' : '');
        $(this).toggleClass('is-invalid', Boolean(value && !valid)).attr('aria-invalid', value && !valid ? 'true' : 'false');
    });

    $(document).on('hidden.bs.modal.doctorAppointmentSlots', '#doctor-appointment-create-modal', function () {
        if (appointmentSlotsRequest && appointmentSlotsRequest.readyState !== 4) appointmentSlotsRequest.abort();
        clearAppointmentTime();
    });

    $(document).on('shown.bs.modal.doctorAppointmentSlots', '#doctor-appointment-create-modal', function () {
        if ($('#appointment-date').val()) loadAppointmentSlots();
        else clearAppointmentTime();
    });

    createForm.on('submit', function (event) {
        event.preventDefault();
        const submit = $('#doctor-appointment-create-submit');
        if (!submit.length || submit.prop('disabled')) return;
        clearFormErrors(createForm);
        if (!patientIdInput.val()) {
            setPatientError('Select a patient from the search results.');
            return;
        }
        const selectedTime = String($('#appointment-time').val() || '').trim();
        const validTime = /^([01]\d|2[0-3]):[0-5]\d$/.test(selectedTime) && Number(selectedTime.slice(3)) % 15 === 0 && appointmentSlotValues.indexOf(selectedTime) !== -1;
        if (!validTime) {
            $('#appointment_timeErr').text('Select an available time in 15-minute increments.');
            $('#appointment-time').addClass('is-invalid').attr('aria-invalid', 'true').trigger('focus');
            return;
        }
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: '<?= base_url('doctor/appointments/create') ?>',
            method: 'POST',
            data: createForm.serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                    return;
                }
                if (response.errors) bindFormError(createForm, response.errors);
                showError(response.msg || 'Please correct the highlighted fields.');
            },
            error: function (xhr) {
                handleFormAjaxError(createForm, xhr, 'Appointment could not be created safely.');
            },
            complete: function () {
                submit.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });

    $(document).on('click', '.js-reschedule', function () {
        const id = $(this).data('id');
        clearFormErrors(rescheduleForm);
        rescheduleForm[0].reset();
        $('#reschedule-appointment-id').val(id);
        $('#doctor-reschedule-modal').modal('show');
    });

    $('#doctor-reschedule-modal').on('shown.bs.modal', function () {
        initSharedDatepickers(this);
        $('#reschedule-date').trigger('focus');
    });

    rescheduleForm.on('submit', function (event) {
        event.preventDefault();
        const submit = $('#doctor-reschedule-submit');
        if (submit.prop('disabled')) return;
        const id = $('#reschedule-appointment-id').val();
        clearFormErrors(rescheduleForm);
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: '<?= base_url('doctor/appointments/reschedule') ?>/' + encodeURIComponent(id),
            method: 'POST',
            data: rescheduleForm.serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                    return;
                }
                if (response.errors) bindFormError(rescheduleForm, response.errors);
                showError(response.msg || 'Please correct the highlighted fields.');
            },
            error: function (xhr) {
                handleFormAjaxError(rescheduleForm, xhr, 'Appointment could not be rescheduled safely.');
            },
            complete: function () {
                submit.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });

    $(document).on('click', '.js-cancel', function () {
        const id = $(this).data('id');
        if (!window.confirm('Cancel this appointment?')) return;
        ajaxRequest({
            url: '<?= base_url('doctor/appointments/cancel') ?>/' + encodeURIComponent(id),
            method: 'POST',
            data: { csrf_rtn: csrf(), cancellation_reason: 'Cancelled by doctor' },
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                    return;
                }
                showError(response.msg || 'Appointment could not be cancelled.');
            },
            error: function (xhr) {
                handleFormAjaxError(null, xhr, 'Appointment could not be cancelled safely.');
            }
        });
    });

    $(document).on('click', '.js-appointment-status', function () {
        const button = $(this);
        if (button.prop('disabled')) return;
        button.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: '<?= base_url('doctor/appointments/queue') ?>/' + encodeURIComponent(button.data('id')),
            method: 'POST',
            data: { csrf_rtn: csrf(), status: button.data('status') },
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                    return;
                }
                showError(response.msg || 'Appointment status could not be updated.');
            },
            error: function (xhr) {
                handleFormAjaxError(null, xhr, 'Appointment status could not be updated safely.');
            },
            complete: function () {
                button.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });

    let appointmentViewRequest = null;

    function setAppointmentViewToggle(view) {
        $('[data-appointment-view]').each(function () {
            const isActive = this.getAttribute('data-appointment-view') === view;
            $(this).toggleClass('active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
        });
        $('[data-appointment-view-input]').val(view);
    }

    $(document).on('click.doctorAppointmentsView', '[data-appointment-view]', function (event) {
        event.preventDefault();
        const view = this.getAttribute('data-appointment-view');
        if (view !== 'list' && view !== 'grid') {
            return;
        }
        const container = $('[data-appointment-view-container]').first();
        const form = $('.doctor-appointments-toolbar-form').first();
        const previousView = container.attr('data-view') || 'list';
        if (!container.length || !form.length || view === previousView && !appointmentViewRequest) return;
        if (appointmentViewRequest && appointmentViewRequest.readyState !== 4) appointmentViewRequest.abort();

        const url = new URL(form.attr('action'), window.location.href);
        const params = new URLSearchParams(form.serialize());
        params.set('view', view);
        url.search = params.toString();
        setAppointmentViewToggle(view);
        $('.doctor-appointment-content-body').attr('aria-busy', 'true');
        $('[data-appointment-view]').prop('disabled', true);

        appointmentViewRequest = ajaxRequest({
            url: url.toString(),
            method: 'GET',
            dataType: 'html',
            timeout: 30000,
            success: function (html) {
                const parsed = $('<div>').html(html);
                const nextContent = parsed.find('.doctor-appointments-content-card').first();
                const nextFooter = nextContent.find('.card-footer').first();
                const nextRecords = nextContent.find('[data-appointment-view-container]').first();
                const nextEmptyState = nextContent.find('.empty-state').first();
                if (!nextContent.length || !nextFooter.length || (!nextRecords.length && !nextEmptyState.length)) {
                    showError('Appointment view could not be loaded safely.');
                    setAppointmentViewToggle(previousView);
                    return;
                }
                $('.doctor-appointments-content-card').replaceWith(nextContent);
                $('[data-appointment-view-container]').attr('data-view', view);
                window.history.pushState({}, '', url.toString());
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                setAppointmentViewToggle(previousView);
                showError(status === 'timeout' ? 'Appointment view loading timed out.' : 'Appointment view could not be loaded.');
            },
            complete: function () {
                $('.doctor-appointment-content-body').removeAttr('aria-busy');
                $('[data-appointment-view]').prop('disabled', false);
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
