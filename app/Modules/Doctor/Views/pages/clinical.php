<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$permissions = session('permissions') ?? [];
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
$displayDate = static function ($value): string {
    $value = (string) ($value ?? '');
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false ? $date->format('d-m-Y') : $value;
};
$displayTime = static function ($value): string {
    return substr((string) ($value ?? ''), 0, 5);
};
$selectedAppointment = $selectedAppointment ?? null;
$patientName = static function (array $appointment): string {
    return trim(implode(' ', array_filter([
        $appointment['patient_first_name'] ?? '',
        $appointment['patient_middle_name'] ?? '',
        $appointment['patient_last_name'] ?? '',
    ], static fn ($part) => $part !== null && $part !== '')));
};
$patientContact = static function (array $appointment): string {
    return service('telephoneContact')->formatDisplay(
        $appointment['patient_telephone_country_code'] ?? null,
        $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null,
        $appointment['patient_legacy_country_code'] ?? null,
    );
};
$patientAge = static function (array $appointment): string {
    $dob = trim((string) ($appointment['patient_date_of_birth'] ?? ''));

    if ($dob === '') {
        return 'Not recorded';
    }

    try {
        return (string) (new \DateTimeImmutable($dob))->diff(new \DateTimeImmutable('today'))->y . ' years';
    } catch (\Throwable) {
        return 'Not recorded';
    }
};
$patientLocation = static function (array $appointment): string {
    $parts = array_filter([
        $appointment['patient_locality_name'] ?? '',
        $appointment['patient_city_name'] ?? '',
        $appointment['patient_state_name'] ?? '',
        $appointment['patient_country_name'] ?? '',
    ], static fn ($part) => trim((string) $part) !== '');

    return $parts === [] ? 'Not recorded' : implode(', ', $parts);
};
$selectedQueueNumber = (int) ($selectedAppointment['queue_position'] ?? 0);
$clinic = $clinic ?? [];
$prescriptionOptionSets = $prescriptionOptionSets ?? ['duration' => [], 'frequency' => [], 'dose' => [], 'intake' => [], 'status' => []];
$medicineLookupOptions = $medicineLookupOptions ?? ['forms' => [], 'units' => [], 'manufacturers' => [], 'statuses' => []];
$followUpTypes = $followUpTypes ?? [];
$prescriptionStatusDefault = (string) ($prescriptionStatusDefault ?? '');
$optionId = static function (array $options, string $value): string {
    foreach ($options as $option) {
        if ((string) ($option['name'] ?? '') === $value || (string) ($option['code'] ?? '') === $value) {
            return (string) ($option['id'] ?? '');
        }
    }

    return '';
};
$defaultPrescriptionOptionId = static function (array $options, array $codes): string {
    foreach ($options as $option) {
        if (in_array((string) ($option['code'] ?? ''), $codes, true)) {
            return (string) ($option['id'] ?? '');
        }
    }

    return '';
};
$defaultIntakeOptionId = $defaultPrescriptionOptionId($prescriptionOptionSets['intake'], ['after_food']);
?>
<main class="dashboard-container" aria-labelledby="clinical-title" data-selected-appointment-id="<?= $selectedAppointment !== null ? (int) $selectedAppointment['id'] : 0 ?>" data-selected-patient-id="<?= $selectedAppointment !== null ? (int) $selectedAppointment['patient_id'] : 0 ?>">
    <div class="page-heading">
        <div>
            <h1 id="clinical-title" class="h3 mb-0">OPD Consultation</h1>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-end">
            <button id="clinical-queue-trigger" class="btn btn-sm btn-primary mr-2 mb-2 mb-md-0" type="button" data-toggle="modal" data-target="#clinical-opd-queue-modal" aria-controls="clinical-opd-queue-modal">
                <i class="bi bi-calendar2-check mr-1" aria-hidden="true"></i>Today’s OPD <i class="bi bi-search ml-1" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <?php
    $pastVisitsCount = (int) ($historyPagination['total'] ?? count($history ?? []));
$pastVisitsUrl = base_url('doctor/clinical/past-visits');
$selectedPatientId = $selectedAppointment !== null ? (int) $selectedAppointment['patient_id'] : 0;
$paymentHistoryUrl = base_url('doctor/billing');

if ($selectedPatientId > 0) {
    $paymentHistoryUrl .= '?patient_id=' . $selectedPatientId;
}
$clinicalActionContext = [
    'appointment_id' => $selectedAppointment !== null ? (int) $selectedAppointment['id'] : 0,
    'patient_id' => $selectedPatientId,
    'patient_name' => $selectedAppointment !== null ? $patientName($selectedAppointment) : '',
    'patient_age' => $selectedAppointment !== null ? $patientAge($selectedAppointment) : '',
    'registration_number' => $selectedAppointment['registration_number'] ?? '',
    'doctor_name' => trim((string) ($doctor['first_name'] ?? '') . ' ' . (string) ($doctor['last_name'] ?? '')),
    'appointment_number' => $selectedAppointment['appointment_number'] ?? '',
    'consultation_number' => $selectedAppointment['consultation_number'] ?? '',
    'clinic_name' => $clinic['clinic_name'] ?? '',
    'clinic_logo_url' => ! empty($clinic['logo']) ? base_url((string) $clinic['logo']) : '',
    'clinic_address' => $clinic['address'] ?? '',
    'clinic_mobile' => $clinic['mobile_display'] ?? '',
    'mobile' => $selectedAppointment !== null ? $patientContact($selectedAppointment) : '',
    'appointment_date' => $selectedAppointment !== null ? $displayDate($selectedAppointment['appointment_date'] ?? '') : '',
    'appointment_time' => $selectedAppointment !== null ? $displayTime($selectedAppointment['appointment_time'] ?? '') : '',
    'appointment_type' => $selectedAppointment['appointment_type'] ?? '',
    'service_name' => $selectedAppointment['service_name'] ?? '',
    'appointment_status' => $selectedAppointment['status'] ?? '',
    'consultation' => [
        'chief_complaint' => $selectedAppointment['chief_complaint'] ?? '',
        'diagnosis' => $selectedAppointment['diagnosis'] ?? '',
        'treatment_plan' => $selectedAppointment['treatment_plan'] ?? '',
        'diagnostic_tests' => $selectedAppointment['diagnostic_tests'] ?? '',
        'diet_plan' => $selectedAppointment['diet_plan'] ?? '',
        'clinical_notes' => $selectedAppointment['clinical_notes'] ?? '',
    ],
    'prescription' => $selectedPrescription === null ? null : [
        'number' => $selectedPrescription['prescription_number'] ?? '',
        'date' => $displayDate($selectedPrescription['prescription_date'] ?? ''),
        'items' => array_map(static fn (array $item): array => [
            'medicine_id' => (int) ($item['medicine_id'] ?? 0),
            'medicine_name' => $item['medicine_name'] ?? '',
            'dosage' => $item['dosage'] ?? '',
            'frequency' => $item['frequency'] ?? '',
            'duration' => $item['duration'] ?? '',
            'duration_unit' => $item['duration_unit'] ?? '',
            'instructions' => $item['instructions'] ?? '',
            'notes' => $item['notes'] ?? '',
        ], $selectedPrescriptionItems),
    ],
    'past_visits_url' => $pastVisitsUrl,
    'payment_history_url' => $paymentHistoryUrl,
];
?>
    <div class="clinical-opd-toolbar" role="toolbar" aria-label="OPD consultation actions">
        <?php if ($selectedAppointment === null): ?>
            <span class="small text-muted" role="status" aria-live="polite">Select an appointment from Today’s OPD to enable consultation actions.</span>
        <?php else: ?>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="past-visits" aria-controls="clinical-action-modal" aria-label="View past visits<?= $selectedAppointment !== null ? ' for selected patient' : '' ?>">
            <i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past Visits
            <span class="badge badge-light ml-1"><?= $pastVisitsCount ?></span>
        </button>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="consultation" aria-controls="clinical-action-modal" aria-label="Open consultation form">
            <i class="bi bi-clipboard2-pulse mr-1" aria-hidden="true"></i>Consultation Form
        </button>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="print-prescription" aria-controls="clinical-action-modal" aria-label="Print selected prescription">
            <i class="bi bi-printer mr-1" aria-hidden="true"></i>Print Prescription
        </button>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="new-payment" aria-controls="clinical-action-modal" aria-label="Open new payment form">
            <i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New Payment
        </button>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="payment-history" aria-controls="clinical-action-modal" aria-label="View payment history">
            <i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Payment History
        </button>
        <button class="btn btn-sm btn-primary" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="images" aria-controls="clinical-action-modal" aria-label="Open patient images">
            <i class="bi bi-image mr-1" aria-hidden="true"></i>Images
        </button>
        <?php endif; ?>
    </div>

    <div id="clinical-feedback" class="sr-only" role="status" aria-live="polite"></div>

    <div class="modal fade ri-scroll-modal" id="clinical-action-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="clinical-action-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="clinical-action-modal-title">Clinical action</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close clinical action" data-modal-autofocus><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="clinical-action-modal-body" aria-live="polite">
                    <div class="clinical-action-loading text-muted" role="status">
                        <span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading…
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                    <button class="btn btn-primary d-none" type="button" id="clinical-action-modal-primary"></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade ri-scroll-modal" id="clinical-opd-queue-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="clinical-opd-queue-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="clinical-opd-queue-title">Today’s OPD / Queue</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close today’s OPD queue"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <label for="clinical-opd-search">Search today’s appointments</label>
                    <input id="clinical-opd-search" class="form-control" type="search" placeholder="Patient, mobile, registration, or appointment number" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="clinical-opd-results" aria-expanded="false">
                    <div id="clinical-opd-results" class="list-group mt-3" role="listbox" aria-live="polite">
                        <div class="list-group-item text-muted" role="status">Open the queue to load today’s appointments.</div>
                    </div>
                    <nav id="clinical-opd-pagination" class="d-flex align-items-center justify-content-between mt-3" aria-label="Today’s appointment pages" hidden>
                        <button id="clinical-opd-prev" class="btn btn-sm btn-outline-secondary" type="button">Previous</button>
                        <span id="clinical-opd-page-status" class="small text-muted" aria-live="polite"></span>
                        <button id="clinical-opd-next" class="btn btn-sm btn-outline-secondary" type="button">Next</button>
                    </nav>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade ri-scroll-modal" id="clinical-medicine-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="clinical-medicine-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="clinical-medicine-modal-title">Add Medicine</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close add medicine dialog"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="clinical-medicine-feedback" class="sr-only" role="status" aria-live="polite"></div>
                    <form id="clinical-medicine-form" method="post" action="<?= base_url('admin/medicines/save') ?>" novalidate autocomplete="off">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="clinical-medicine-name">Medicine name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="clinical-medicine-name" class="form-control" name="medicine_name" type="text" maxlength="255" required aria-required="true" aria-describedby="medicine_nameErr">
                                <div class="validation-error" id="medicine_nameErr"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="clinical-medicine-generic">Generic name</label>
                                <input id="clinical-medicine-generic" class="form-control" name="generic_name" type="text" maxlength="255" aria-describedby="generic_nameErr">
                                <div class="validation-error" id="generic_nameErr"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="clinical-medicine-strength">Strength</label>
                                <input id="clinical-medicine-strength" class="form-control" name="strength" type="text" maxlength="100" aria-describedby="strengthErr">
                                <div class="validation-error" id="strengthErr"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="clinical-medicine-form-type">Form/type</label>
                                <select id="clinical-medicine-form-type" class="form-control btSelect" name="form_id" title="Select form/type" aria-describedby="form_idErr">
                                    <option value="">Select form/type</option>
                                    <?php foreach ($medicineLookupOptions['forms'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="form_idErr"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="clinical-medicine-unit">Unit</label>
                                <select id="clinical-medicine-unit" class="form-control btSelect" name="unit_id" title="Select unit" aria-describedby="unit_idErr">
                                    <option value="">Select unit</option>
                                    <?php foreach ($medicineLookupOptions['units'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="unit_idErr"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="clinical-medicine-manufacturer">Manufacturer</label>
                                <select id="clinical-medicine-manufacturer" class="form-control btSelect" name="manufacturer_id" title="Select manufacturer" aria-describedby="manufacturer_idErr">
                                    <option value="">Select manufacturer</option>
                                    <?php foreach ($medicineLookupOptions['manufacturers'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="manufacturer_idErr"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="clinical-medicine-status">Status <span class="text-danger" aria-hidden="true">*</span></label>
                                <select id="clinical-medicine-status" class="form-control btSelect" name="status_id" required aria-required="true" aria-describedby="status_idErr">
                                    <option value="">Select status</option>
                                    <?php foreach ($medicineLookupOptions['statuses'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="status_idErr"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="clinical-medicine-default-duration">Default duration</label>
                                <select id="clinical-medicine-default-duration" class="form-control btSelect" name="prescription_duration_id" title="Select default duration" aria-describedby="prescription_duration_idErr">
                                    <option value="">Select duration</option>
                                    <?php foreach ($prescriptionOptionSets['duration'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="prescription_duration_idErr"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="clinical-medicine-default-frequency">Default frequency</label>
                                <select id="clinical-medicine-default-frequency" class="form-control btSelect" name="prescription_frequency_id" title="Select default frequency" aria-describedby="prescription_frequency_idErr">
                                    <option value="">Select frequency</option>
                                    <?php foreach ($prescriptionOptionSets['frequency'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="prescription_frequency_idErr"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="clinical-medicine-default-dose">Default dose</label>
                                <select id="clinical-medicine-default-dose" class="form-control btSelect" name="prescription_dose_id" title="Select default dose" aria-describedby="prescription_dose_idErr">
                                    <option value="">Select dose</option>
                                    <?php foreach ($prescriptionOptionSets['dose'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="prescription_dose_idErr"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="clinical-medicine-default-intake">Default intake</label>
                                <select id="clinical-medicine-default-intake" class="form-control btSelect" name="prescription_intake_id" title="Select default intake" aria-describedby="prescription_intake_idErr">
                                    <option value="">Select intake</option>
                                    <?php foreach ($prescriptionOptionSets['intake'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="prescription_intake_idErr"></div>
                            </div>
                            <div class="col-12 form-group">
                                <label for="clinical-medicine-default-note">Default prescription note</label>
                                <textarea id="clinical-medicine-default-note" class="form-control" name="prescription_note" rows="2" maxlength="2000" aria-describedby="prescription_noteErr"></textarea>
                                <div class="validation-error" id="prescription_noteErr"></div>
                            </div>
                            <div class="col-12 form-group">
                                <label for="clinical-medicine-description">Description</label>
                                <textarea id="clinical-medicine-description" class="form-control" name="description" rows="4" aria-describedby="descriptionErr"></textarea>
                                <div class="validation-error" id="descriptionErr"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="clinical-medicine-save" form="clinical-medicine-form">Create Medicine</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selectedAppointment !== null): ?>
    <div id="clinical-patient-specific-content" class="clinical-workspace" data-clinical-patient-specific>
    <form id="clinical-workspace-form" class="clinical-workspace-form" method="post" action="<?= base_url('doctor/clinical/save') ?>" novalidate data-appointment-id="<?= $selectedAppointment !== null ? (int) $selectedAppointment['id'] : 0 ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="appointment_id" value="<?= $selectedAppointment !== null ? (int) $selectedAppointment['id'] : 0 ?>">
        <input type="hidden" name="consultation_id" value="<?= (int) ($selectedAppointment['consultation_id'] ?? 0) ?>">
        <input type="hidden" name="prescription_status" id="clinical-prescription-status" value="<?= esc($prescriptionStatusDefault) ?>">
        <input type="hidden" name="prescription_present" id="clinical-prescription-present" value="<?= $selectedPrescription !== null ? '1' : '0' ?>">
        <div class="clinical-main-grid">
    <aside id="clinical-pane-patient-summary" class="clinical-patient-rail" aria-label="Patient information">
        <div class="card clinical-patient-info-card mb-3">
            <div class="card-header clinical-patient-card-header">
                <h2 class="h5 mb-0"><i class="bi bi-person-circle mr-1" aria-hidden="true"></i>Patient Info</h2>
                <?php if ($selectedAppointment !== null): ?><strong class="clinical-patient-card-name"><?= esc($patientName($selectedAppointment)) ?></strong><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($selectedAppointment !== null): ?>
                    <dl class="clinical-info-list mb-0">
                        <div><dt><i class="bi bi-card-text mr-1" aria-hidden="true"></i>Patient ID</dt><dd><?= esc($selectedAppointment['registration_number'] ?: ('#' . (int) $selectedAppointment['patient_id'])) ?></dd></div>
                        <div><dt><i class="bi bi-diagram-3 mr-1" aria-hidden="true"></i>Source</dt><dd><span class="badge <?= esc(patient_source_badge_class($selectedAppointment['patient_source_type'] ?? null)) ?>"><?= esc(patient_source_label($selectedAppointment['patient_source_type'] ?? null)) ?></span></dd></div>
                        <div><dt><i class="bi bi-calendar3 mr-1" aria-hidden="true"></i>Age</dt><dd><?= esc($patientAge($selectedAppointment)) ?></dd></div>
                        <div><dt><i class="bi bi-phone mr-1" aria-hidden="true"></i>Mobile</dt><dd><?= esc($patientContact($selectedAppointment)) ?></dd></div>
                        <div><dt><i class="bi bi-geo-alt mr-1" aria-hidden="true"></i>Location</dt><dd><?= esc($patientLocation($selectedAppointment)) ?></dd></div>
                        <div><dt><i class="bi bi-calendar2-event mr-1" aria-hidden="true"></i>Appointment</dt><dd><?= esc(trim(($selectedAppointment['appointment_date'] ?? '') . ' ' . ($selectedAppointment['appointment_time'] ?? ''))) ?></dd></div>
                        <div><dt><i class="bi bi-circle-fill mr-1" aria-hidden="true"></i>Status</dt><dd><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', $selectedAppointment['status']))) ?></span></dd></div>
                    </dl>
                <?php else: ?>
                    <div class="empty-state py-3" role="status">Select a patient from Today’s OPD.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header clinical-timeline-header d-flex align-items-center justify-content-between">
                <h2 class="h5 mb-0">Visit Timeline</h2>
                <a class="btn btn-sm btn-warning" href="<?= $selectedAppointment !== null ? base_url('doctor/patients/' . (int) $selectedAppointment['patient_id']) : base_url('doctor/patients') ?>">
                    <i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past Visits
                </a>
            </div>
            <div class="list-group list-group-flush" aria-live="polite">
                <?php foreach ($history as $item): ?>
                    <div class="list-group-item small">
                        Visited: <?= esc($displayDate($item['consultation_date'])) ?>
                        <span class="text-muted">| <?= esc(ucwords(str_replace('_', ' ', (string) $item['status']))) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($history === []): ?><div class="list-group-item text-muted small" role="status">No past visits recorded.</div><?php endif; ?>
            </div>
        </div>
    </aside>

    <div class="clinical-workflow-content">
            <?php if ($selectedAppointment === null): ?><div class="empty-state p-4" role="status">Select an appointment before recording consultation notes.</div>
            <?php elseif (! $can('consultation.edit')): ?><div class="alert alert-warning" role="alert">You do not have permission to edit consultations.</div>
            <?php else: ?>
                <?php $appointmentId = (int) $selectedAppointment['id']; ?>
                <div class="consultation-form" data-appointment-id="<?= $appointmentId ?>">
                    <div class="row g-2">
                    <div class="col-md-6 col-12">
                    <div id="clinical-consultation-pane-chief-complaints" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Chief Complaints</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="complaint-<?= $appointmentId ?>">Chief complaint</label>
                                <textarea id="complaint-<?= $appointmentId ?>" class="form-control" name="chief_complaint"><?= esc($selectedAppointment['chief_complaint'] ?? '') ?></textarea>
                                <div class="validation-error" id="chief_complaint_<?= $appointmentId ?>Err"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="col-md-6 col-12">
                    <div id="clinical-consultation-pane-diagnosis" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Diagnosis</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="diagnosis-<?= $appointmentId ?>">Diagnosis</label>
                                <textarea id="diagnosis-<?= $appointmentId ?>" class="form-control" name="diagnosis"><?= esc($selectedAppointment['diagnosis'] ?? '') ?></textarea>
                                <div class="validation-error" id="diagnosis_<?= $appointmentId ?>Err" data-error-for="diagnosis"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                    <div class="row g-2 clinical-three-column-row">
                    <div class="col-12 col-md-4">
                    <div id="clinical-consultation-pane-advice" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Advice</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="treatment-<?= $appointmentId ?>">Advice / treatment plan</label>
                                <textarea id="treatment-<?= $appointmentId ?>" class="form-control" name="treatment_plan"><?= esc($selectedAppointment['treatment_plan'] ?? '') ?></textarea>
                                <div class="validation-error" id="treatment_plan_<?= $appointmentId ?>Err" data-error-for="treatment_plan"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="col-12 col-md-4">
                    <div id="clinical-consultation-pane-diagnostic-tests" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Diagnostic Tests</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="diagnostic-tests-<?= $appointmentId ?>">Diagnostic tests</label>
                                <textarea id="diagnostic-tests-<?= $appointmentId ?>" class="form-control" name="diagnostic_tests"><?= esc($selectedAppointment['diagnostic_tests'] ?? '') ?></textarea>
                                <div class="validation-error" id="diagnostic_tests_<?= $appointmentId ?>Err" data-error-for="diagnostic_tests"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="col-12 col-md-4">
                    <div id="clinical-consultation-pane-diet-plan" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Diet Plan</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="diet-plan-<?= $appointmentId ?>">Diet plan</label>
                                <textarea id="diet-plan-<?= $appointmentId ?>" class="form-control" name="diet_plan"><?= esc($selectedAppointment['diet_plan'] ?? '') ?></textarea>
                                <div class="validation-error" id="diet_plan_<?= $appointmentId ?>Err" data-error-for="diet_plan"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="col-12">
                    <div id="clinical-consultation-pane-consultation-form" class="clinical-section-card">
                        <div class="clinical-section-header"><h3 class="h6 mb-0">Private Notes</h3></div>
                        <div class="form-row">
                            <div class="col-12 mb-3">
                                <label for="notes-<?= $appointmentId ?>">Consultation notes</label>
                                <textarea id="notes-<?= $appointmentId ?>" class="form-control" name="clinical_notes"><?= esc($selectedAppointment['clinical_notes'] ?? '') ?></textarea>
                                <div class="validation-error" id="clinical_notes_<?= $appointmentId ?>Err"></div>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                </div>
            <?php endif; ?>

    <div id="clinical-pane-prescription" class="clinical-workflow-pane">
        <div class="card clinical-prescription-card">
            <div class="card-header clinical-prescription-header">
                <div class="clinical-prescription-heading">
                    <h2 class="h5 mb-0">Prescription</h2>
                    <div class="clinical-prescription-tools" role="group" aria-label="Prescription tools">
                        <button id="clinical-create-medicine" class="btn btn-sm btn-light" type="button" data-toggle="modal" data-target="#clinical-medicine-modal" aria-controls="clinical-medicine-modal" aria-label="Create a medicine for the prescription" title="Create a medicine" <?= $selectedAppointment === null || ! $can('prescription.create') || ! $can('medicine.create') ? 'disabled' : '' ?>><i class="bi bi-plus-circle mr-1" aria-hidden="true"></i>+ Medicine</button>
                        <button class="btn btn-sm btn-light" type="button" data-toggle="modal" data-target="#clinical-action-modal" data-clinical-action="past-prescription" aria-controls="clinical-action-modal" <?= $selectedAppointment === null || ! $can('prescription.create') ? 'disabled' : '' ?>><i class="bi bi-clock-history mr-1" aria-hidden="true"></i>Past Prescription</button>
                    </div>
                </div>
                <div class="clinical-prescription-duration">
                    <label class="sr-only" for="clinical-medicine-duration">Medicine Duration (Days)</label>
                    <input id="clinical-medicine-duration" class="form-control" type="number" name="medicine_duration" min="1" max="3650" step="1" inputmode="numeric" placeholder="Medicine Duration (Days)" aria-label="Medicine Duration (Days)" aria-describedby="medicine_durationErr" value="<?= esc((string) ($selectedPrescriptionItems[0]['duration'] ?? '')) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?>>
                    <div class="validation-error" id="medicine_durationErr" data-error-for="medicine_duration"></div>
                </div>
            </div>
            <div class="card-body">
            <?php if ($selectedAppointment === null): ?><div class="empty-state p-4" role="status">Select an appointment before creating a prescription.</div>
            <?php elseif (! $can('prescription.create')): ?><div class="alert alert-warning" role="alert">You do not have permission to create prescriptions.</div>
            <?php else: ?>
                <?php $appointmentId = (int) $selectedAppointment['id']; ?>
                <?php $prescriptionRows = $selectedPrescriptionItems !== [] ? $selectedPrescriptionItems : [[]]; ?>
                <div class="prescription-form" data-appointment-id="<?= $appointmentId ?>">
                <div class="table-responsive clinical-prescription-table-wrap">
                        <table class="prescription-medicine-table clinical-prescription-table mb-2">
                            <thead>
                                <tr>
                                    <th scope="col">Medicine</th>
                                    <th scope="col">Duration</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Frequency</th>
                                    <th scope="col">Dose</th>
                                    <th scope="col">Intake</th>
                                    <th scope="col">Note</th>
                                </tr>
                            </thead>
                            <tbody id="clinical-prescription-items">
                                <?php foreach ($prescriptionRows as $index => $prescriptionItem): ?>
                                    <?php
                                $medicineName = (string) ($prescriptionItem['medicine_name'] ?? '');
                                    $medicineId = (int) ($prescriptionItem['medicine_id'] ?? 0);
                                    $duration = (string) ($prescriptionItem['duration'] ?? '');
                                    $durationOptionId = $optionId($prescriptionOptionSets['duration'], $duration);
                                    $frequency = (string) ($prescriptionItem['frequency'] ?? '');
                                    $frequencyOptionId = $optionId($prescriptionOptionSets['frequency'], $frequency);
                                    $dose = (string) ($prescriptionItem['dosage'] ?? '');
                                    $doseOptionId = $optionId($prescriptionOptionSets['dose'], $dose);
                                    $intake = (string) ($prescriptionItem['instructions'] ?? '');
                                    $intakeOptionId = $optionId($prescriptionOptionSets['intake'], $intake);

                                    if ($prescriptionItem === [] && $intakeOptionId === '' && $defaultIntakeOptionId !== '') {
                                        $intakeOptionId = $defaultIntakeOptionId;
                                    }
                                    $note = (string) ($prescriptionItem['notes'] ?? '');
                                    $quantity = (string) ($prescriptionItem['quantity'] ?? '');
                                    ?>
                                    <tr class="clinical-medicine-row" data-row-index="<?= (int) $index ?>">
                                        <td data-label="Medicine">
                                            <div class="clinical-medicine-field">
                                                <button class="btn btn-sm btn-outline-danger clinical-remove-medicine" type="button" aria-label="Remove medicine row" title="Remove medicine row"><i class="bi bi-trash" aria-hidden="true"></i><span class="sr-only">Remove</span></button>
                                                <div class="clinical-medicine-control">
                                                    <label class="clinical-prescription-field-label" for="clinical-medicine-<?= $appointmentId ?>-<?= (int) $index ?>">Medicine</label>
                                                    <select id="clinical-medicine-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control btSelect clinical-medicine-select" name="items[<?= (int) $index ?>][medicine_id]" data-live-search="true" data-search-url="<?= base_url('doctor/clinical/medicines/search') ?>" data-autofill-url="<?= base_url('doctor/clinical/medicines') ?>" title="Search item..." aria-describedby="items_<?= (int) $index ?>_medicine_idErr">
                                                        <option value="">Search item...</option>
                                                        <?php if ($medicineId > 0): ?><option value="<?= $medicineId ?>" selected><?= esc($medicineName) ?></option><?php elseif ($medicineName !== ''): ?><option value="" selected disabled><?= esc($medicineName) ?> (select again)</option><?php endif; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="validation-error" id="items_<?= (int) $index ?>_medicine_idErr" data-error-for="items.<?= (int) $index ?>.medicine_id"></div>
                                        </td>
                                        <td data-label="Duration">
                                            <label class="clinical-prescription-field-label" for="clinical-duration-<?= $appointmentId ?>-<?= (int) $index ?>">Duration</label>
                                            <select id="clinical-duration-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control btSelect" name="items[<?= (int) $index ?>][duration]" aria-describedby="items_<?= (int) $index ?>_durationErr" title="Select duration">
                                                <option value="">Choose...</option>
                                                <?php foreach ($prescriptionOptionSets['duration'] as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $durationOptionId === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="items[<?= (int) $index ?>][duration_unit]" value="days">
                                            <div class="validation-error" id="items_<?= (int) $index ?>_durationErr" data-error-for="items.<?= (int) $index ?>.duration"></div>
                                        </td>
                                        <td data-label="Quantity">
                                            <label class="clinical-prescription-field-label" for="clinical-quantity-<?= $appointmentId ?>-<?= (int) $index ?>">Quantity</label>
                                            <input id="clinical-quantity-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control clinical-prescription-quantity" type="number" name="items[<?= (int) $index ?>][quantity]" min="1" step="1" inputmode="numeric" value="<?= esc($quantity) ?>" aria-describedby="items_<?= (int) $index ?>_quantityErr">
                                            <div class="validation-error" id="items_<?= (int) $index ?>_quantityErr" data-error-for="items.<?= (int) $index ?>.quantity"></div>
                                        </td>
                                        <td data-label="Frequency">
                                            <label class="clinical-prescription-field-label" for="clinical-frequency-<?= $appointmentId ?>-<?= (int) $index ?>">Frequency</label>
                                            <select id="clinical-frequency-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control btSelect" name="items[<?= (int) $index ?>][frequency]" aria-describedby="items_<?= (int) $index ?>_frequencyErr">
                                                <option value="">Choose...</option>
                                                <?php foreach ($prescriptionOptionSets['frequency'] as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $frequencyOptionId === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?>
                                            </select>
                                            <div class="validation-error" id="items_<?= (int) $index ?>_frequencyErr" data-error-for="items.<?= (int) $index ?>.frequency"></div>
                                        </td>
                                        <td data-label="Dose">
                                            <label class="clinical-prescription-field-label" for="clinical-dose-<?= $appointmentId ?>-<?= (int) $index ?>">Dose</label>
                                            <select id="clinical-dose-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control btSelect" name="items[<?= (int) $index ?>][dosage]" aria-describedby="items_<?= (int) $index ?>_dosageErr">
                                                <option value="">Choose...</option>
                                                <?php foreach ($prescriptionOptionSets['dose'] as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $doseOptionId === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?>
                                            </select>
                                            <div class="validation-error" id="items_<?= (int) $index ?>_dosageErr" data-error-for="items.<?= (int) $index ?>.dosage"></div>
                                        </td>
                                        <td data-label="Intake">
                                            <label class="clinical-prescription-field-label" for="clinical-intake-<?= $appointmentId ?>-<?= (int) $index ?>">Intake</label>
                                            <select id="clinical-intake-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control btSelect" name="items[<?= (int) $index ?>][intake]" aria-describedby="items_<?= (int) $index ?>_intakeErr">
                                                <option value="">Choose...</option>
                                                <?php foreach ($prescriptionOptionSets['intake'] as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $intakeOptionId === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?>
                                            </select>
                                            <div class="validation-error" id="items_<?= (int) $index ?>_intakeErr" data-error-for="items.<?= (int) $index ?>.intake"></div>
                                        </td>
                                        <td data-label="Note">
                                            <label class="clinical-prescription-field-label" for="clinical-note-<?= $appointmentId ?>-<?= (int) $index ?>">Note</label>
                                            <textarea id="clinical-note-<?= $appointmentId ?>-<?= (int) $index ?>" class="form-control" name="items[<?= (int) $index ?>][notes]" maxlength="65535" rows="1" placeholder="Add a note" aria-describedby="items_<?= (int) $index ?>_notesErr"><?= esc($note) ?></textarea>
                                            <div class="validation-error" id="items_<?= (int) $index ?>_notesErr" data-error-for="items.<?= (int) $index ?>.notes"></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="validation-error" id="itemsErr" data-error-for="items"></div>
                    <div class="clinical-prescription-footer" role="group" aria-label="Prescription row actions">
                        <button class="btn btn-sm btn-primary clinical-add-medicine-trigger" type="button" <?= $selectedAppointment === null || ! $can('prescription.create') ? 'disabled' : '' ?>><i class="bi bi-plus-circle mr-1" aria-hidden="true"></i>Add More</button>
                        <span class="clinical-prescription-or" aria-hidden="true">OR Use</span>
                        <button class="btn btn-sm btn-outline-secondary clinical-add-medicine-trigger" type="button" aria-label="Add medicine using Alt plus N shortcut" title="Add medicine (Alt + N)" <?= $selectedAppointment === null || ! $can('prescription.create') ? 'disabled' : '' ?>>Alt + N</button>
                    </div>
                    <template id="clinical-medicine-row-template">
                        <tr class="clinical-medicine-row" data-row-index="__INDEX__">
                            <td data-label="Medicine"><div class="clinical-medicine-field"><button class="btn btn-sm btn-outline-danger clinical-remove-medicine" type="button" aria-label="Remove medicine row" title="Remove medicine row"><i class="bi bi-trash" aria-hidden="true"></i><span class="sr-only">Remove</span></button><div class="clinical-medicine-control"><label class="sr-only" for="clinical-medicine-<?= $appointmentId ?>-__INDEX__">Medicine</label><select id="clinical-medicine-<?= $appointmentId ?>-__INDEX__" class="form-control btSelect clinical-medicine-select" name="items[__INDEX__][medicine_id]" data-live-search="true" data-search-url="<?= base_url('doctor/clinical/medicines/search') ?>" data-autofill-url="<?= base_url('doctor/clinical/medicines') ?>" title="Search item..." aria-describedby="items___INDEX___medicine_idErr"><option value="">Search item...</option></select></div></div><div class="validation-error" id="items___INDEX___medicine_idErr" data-error-for="items.__INDEX__.medicine_id"></div></td>
                            <td data-label="Duration"><label class="sr-only" for="clinical-duration-<?= $appointmentId ?>-__INDEX__">Duration</label><select id="clinical-duration-<?= $appointmentId ?>-__INDEX__" class="form-control btSelect" name="items[__INDEX__][duration]" aria-describedby="items___INDEX___durationErr" title="Select duration"><option value="">Select duration</option><?php foreach ($prescriptionOptionSets['duration'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><input type="hidden" name="items[__INDEX__][duration_unit]" value="days"><div class="validation-error" id="items___INDEX___durationErr" data-error-for="items.__INDEX__.duration"></div></td>
                            <td data-label="Quantity"><label class="sr-only" for="clinical-quantity-<?= $appointmentId ?>-__INDEX__">Quantity</label><input id="clinical-quantity-<?= $appointmentId ?>-__INDEX__" class="form-control clinical-prescription-quantity" type="number" name="items[__INDEX__][quantity]" min="1" step="1" inputmode="numeric" value="" aria-describedby="items___INDEX___quantityErr"><div class="validation-error" id="items___INDEX___quantityErr" data-error-for="items.__INDEX__.quantity"></div></td>
                            <td data-label="Frequency"><label class="sr-only" for="clinical-frequency-<?= $appointmentId ?>-__INDEX__">Frequency</label><select id="clinical-frequency-<?= $appointmentId ?>-__INDEX__" class="form-control btSelect" name="items[__INDEX__][frequency]" aria-describedby="items___INDEX___frequencyErr" title="Select frequency"><option value="">Select frequency</option><?php foreach ($prescriptionOptionSets['frequency'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="items___INDEX___frequencyErr" data-error-for="items.__INDEX__.frequency"></div></td>
                            <td data-label="Dose"><label class="sr-only" for="clinical-dose-<?= $appointmentId ?>-__INDEX__">Dose</label><select id="clinical-dose-<?= $appointmentId ?>-__INDEX__" class="form-control btSelect" name="items[__INDEX__][dosage]" aria-describedby="items___INDEX___dosageErr" title="Select dose"><option value="">Select dose</option><?php foreach ($prescriptionOptionSets['dose'] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="items___INDEX___dosageErr" data-error-for="items.__INDEX__.dosage"></div></td>
                            <td data-label="Intake"><label class="sr-only" for="clinical-intake-<?= $appointmentId ?>-__INDEX__">Intake</label><select id="clinical-intake-<?= $appointmentId ?>-__INDEX__" class="form-control btSelect" name="items[__INDEX__][intake]" aria-describedby="items___INDEX___intakeErr" title="Select intake"><option value="">Select intake</option><?php foreach ($prescriptionOptionSets['intake'] as $option): ?><option value="<?= (int) $option['id'] ?>" <?= (string) $option['id'] === $defaultIntakeOptionId ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select><div class="validation-error" id="items___INDEX___intakeErr" data-error-for="items.__INDEX__.intake"></div></td>
                            <td data-label="Note"><label class="sr-only" for="clinical-note-<?= $appointmentId ?>-__INDEX__">Note</label><textarea id="clinical-note-<?= $appointmentId ?>-__INDEX__" class="form-control" name="items[__INDEX__][notes]" maxlength="65535" rows="1" placeholder="Add a note" aria-describedby="items___INDEX___notesErr"></textarea><div class="validation-error" id="items___INDEX___notesErr" data-error-for="items.__INDEX__.notes"></div></td>
                        </tr>
                    </template>
                </div>
            <?php endif; ?>
        </div></div>
    </div>

    <div id="clinical-consultation-pane-follow-up-setup" class="clinical-section-card">
        <div class="clinical-section-header"><h3 class="h6 mb-0">Follow-up Setup</h3></div>
        <div class="form-row clinical-compact-row">
            <div class="col-md-3 col-12 mb-2">
                <label for="follow-up-after-days">Next Follow-up After</label>
                <input id="follow-up-after-days" class="form-control" type="number" name="follow_up_after_days" min="0" max="65535" step="1" inputmode="numeric" placeholder="Number of days" value="<?= esc($selectedAppointment['follow_up_after_days'] ?? '') ?>" data-appointment-base-date="<?= esc((string) ($selectedAppointment['appointment_date'] ?? date('Y-m-d'))) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="follow_up_after_daysErr">
                <div class="validation-error" id="follow_up_after_daysErr" data-error-for="follow_up_after_days"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="follow-up-date">Expected Follow-up Date</label>
                <input id="follow-up-date" class="form-control datepicker" type="text" name="follow_up_date" placeholder="dd-mm-yyyy" autocomplete="off" data-submit-display-date="true" value="<?= esc($displayDate($selectedAppointment['follow_up_date'] ?? '')) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="follow_up_dateErr">
                <div class="validation-error" id="follow_up_dateErr" data-error-for="follow_up_date"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="follow-up-time">Expected Follow-up Time</label>
                <input id="follow-up-time" class="form-control" type="text" name="follow_up_time" placeholder="hh:mm AM/PM" inputmode="text" autocomplete="off" value="<?= esc($displayTime($selectedAppointment['follow_up_time'] ?? '')) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="follow_up_timeErr">
                <div class="validation-error" id="follow_up_timeErr" data-error-for="follow_up_time"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="follow-up-type">Follow-up Type</label>
                <?php $followUpTypeId = (int) ($selectedAppointment['follow_up_type_id'] ?? 0); ?>
                <select id="follow-up-type" class="form-control btSelect" name="follow_up_type_id" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="follow_up_type_idErr" title="Select follow-up type">
                    <option value="">Select type</option>
                    <?php foreach ($followUpTypes as $type): ?>
                        <option value="<?= (int) ($type['id'] ?? 0) ?>" <?= $followUpTypeId === (int) ($type['id'] ?? 0) ? 'selected' : '' ?>><?= esc($type['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="validation-error" id="follow_up_type_idErr" data-error-for="follow_up_type_id"></div>
            </div>
            <div class="col-12 mb-2">
                <label for="follow-up-remarks">Follow-up Remarks</label>
                <textarea id="follow-up-remarks" class="form-control" name="follow_up_remarks" rows="2" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="follow_up_remarksErr"><?= esc($selectedAppointment['follow_up_remarks'] ?? '') ?></textarea>
                <div class="validation-error" id="follow_up_remarksErr" data-error-for="follow_up_remarks"></div>
            </div>
        </div>
    </div>

    <div id="clinical-consultation-pane-appointment-setup" class="clinical-section-card">
        <div class="clinical-section-header"><h3 class="h6 mb-0">Appointment Setup</h3></div>
        <div class="form-row clinical-compact-row">
            <div class="col-md-3 col-12 mb-2">
                <label for="next-appointment-after-days">Next Appointment After</label>
                <input id="next-appointment-after-days" class="form-control" type="number" name="next_appointment_after_days" min="0" max="65535" step="1" inputmode="numeric" placeholder="Number of days" value="<?= esc($selectedAppointment['next_appointment_after_days'] ?? '') ?>" data-appointment-base-date="<?= esc((string) ($selectedAppointment['appointment_date'] ?? date('Y-m-d'))) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="next_appointment_after_daysErr">
                <div class="validation-error" id="next_appointment_after_daysErr" data-error-for="next_appointment_after_days"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="next-appointment-date">Next Appointment Date</label>
                <input id="next-appointment-date" class="form-control datepicker" type="text" name="next_appointment_date" placeholder="dd-mm-yyyy" autocomplete="off" data-submit-display-date="true" value="<?= esc($displayDate($selectedAppointment['next_appointment_date'] ?? '')) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="next_appointment_dateErr">
                <div class="validation-error" id="next_appointment_dateErr" data-error-for="next_appointment_date"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="next-appointment-time">Next Appointment Time</label>
                <input id="next-appointment-time" class="form-control" type="text" name="next_appointment_time" placeholder="hh:mm AM/PM" inputmode="text" autocomplete="off" value="<?= esc($displayTime($selectedAppointment['next_appointment_time'] ?? '')) ?>" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="next_appointment_timeErr">
                <div class="validation-error" id="next_appointment_timeErr" data-error-for="next_appointment_time"></div>
            </div>
            <div class="col-md-3 col-12 mb-2">
                <label for="next-appointment-type">Appointment Type</label>
                <?php $nextAppointmentType = (string) ($selectedAppointment['next_appointment_type'] ?? ''); ?>
                <select id="next-appointment-type" class="form-control" name="next_appointment_type" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-describedby="next_appointment_typeErr">
                    <option value="">Select type</option>
                    <?php foreach (['in_person' => 'In person', 'video' => 'Video'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $nextAppointmentType === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                    <?php if ($nextAppointmentType !== '' && ! in_array($nextAppointmentType, ['in_person', 'video'], true)): ?>
                        <option value="<?= esc($nextAppointmentType) ?>" selected><?= esc($nextAppointmentType) ?></option>
                    <?php endif; ?>
                </select>
                <div class="validation-error" id="next_appointment_typeErr" data-error-for="next_appointment_type"></div>
            </div>
        </div>
    </div>

    </div>
    </div>
    <div class="clinical-common-actions sticky-bottom" role="group" aria-label="Consultation actions">
        <div class="clinical-save-mode">
            <label class="sr-only" for="clinical-consultation-status">Save consultation as</label>
            <select id="clinical-consultation-status" class="form-control form-control-sm" name="consultation_status" <?= $selectedAppointment === null ? 'disabled' : '' ?> aria-label="Save consultation as">
                <option value="draft">Draft</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <button class="btn btn-light clinical-cancel" type="button" <?= $selectedAppointment === null ? 'disabled' : '' ?>>Cancel</button>
        <button id="clinical-save-workspace" class="btn btn-primary" type="submit" <?= $selectedAppointment === null ? 'disabled' : '' ?>><i class="bi bi-save mr-1" aria-hidden="true"></i>Save Consultation</button>
        <?php if (($selectedAppointment['consultation_status'] ?? '') === 'completed' && $can('consultation.edit')): ?><button id="clinical-amend-consultation" class="btn btn-warning" type="button"><i class="bi bi-pencil-square mr-1" aria-hidden="true"></i>Amend consultation</button><?php endif; ?>
    </div>
    </form>
    </div>
    <?php if (($selectedAppointment['consultation_status'] ?? '') === 'completed' && $can('consultation.edit')): ?>
    <div class="modal fade" id="clinical-amendment-modal" tabindex="-1" role="dialog" aria-labelledby="clinical-amendment-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="clinical-amendment-title">Amend completed consultation</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="small text-muted">The original consultation and prescription remain preserved. Explain why this correction is required.</p><label for="clinical-amendment-reason">Amendment reason <span class="text-danger">*</span></label><textarea id="clinical-amendment-reason" class="form-control" rows="4" maxlength="2000" required></textarea><div class="validation-error" id="clinical-amendment-reasonErr"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" id="clinical-confirm-amendment">Continue amendment</button></div></div></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    const clinicalActionModal = $('#clinical-action-modal');
    const amendmentButton = $('#clinical-amend-consultation');
    const workspaceForm = $('#clinical-workspace-form');
    if (amendmentButton.length && workspaceForm.length) {
        workspaceForm.find(':input').not('[type="hidden"]').prop('disabled', true);
        $('#clinical-save-workspace, #clinical-workspace-form .clinical-cancel').prop('disabled', true).hide();
        amendmentButton.on('click', function () { $('#clinical-amendment-modal').modal('show'); });
        $('#clinical-confirm-amendment').on('click', function () {
            const reason = String($('#clinical-amendment-reason').val() || '').trim();
            $('#clinical-amendment-reasonErr').text('');
            if (!reason) { $('#clinical-amendment-reasonErr').text('An amendment reason is required.'); return; }
            workspaceForm.find(':input').prop('disabled', false);
            workspaceForm.find('select.btSelect').selectpicker('refresh');
            workspaceForm.find('input[name="amendment_reason"]').remove();
            $('<input>', {type: 'hidden', name: 'amendment_reason', value: reason}).appendTo(workspaceForm);
            workspaceForm.attr('action', '<?= base_url('doctor/clinical/amend') ?>').attr('data-amendment-mode', '1');
            $('#clinical-amendment-modal').modal('hide');
            $('#clinical-save-workspace').show().prop('disabled', false).trigger('click');
        });
    }
    const clinicalActionBody = $('#clinical-action-modal-body');
    const clinicalActionTitle = $('#clinical-action-modal-title');
    const clinicalActionPrimary = $('#clinical-action-modal-primary');
    const clinicalActionContext = <?= json_encode($clinicalActionContext, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let clinicalActionXhr = null;
    let clinicalSelectionRequest = 0;
    let clinicalSelectionXhr = null;
    const clinicalMain = $('main[aria-labelledby="clinical-title"]');
    const clinicalSelection = {
        appointmentId: Number(clinicalMain.data('selected-appointment-id') || 0),
        patientId: Number(clinicalMain.data('selected-patient-id') || 0),
    };
    window.rootinsClinicalSelection = clinicalSelection;

    function hideClinicalPatientContent() {
        $('#clinical-patient-specific-content').attr('hidden', true);
        $('.clinical-opd-toolbar [data-clinical-action]').attr('hidden', true);
    }

    function clearClinicalSelection() {
        clinicalSelection.appointmentId = 0;
        clinicalSelection.patientId = 0;
        window.rootinsClinicalSelection = clinicalSelection;
        hideClinicalPatientContent();
        $('#clinical-feedback').text('No appointment selected.');
    }

    function selectClinicalAppointment(item) {
        const appointmentId = Number(item && item.id || 0);
        const patientId = Number(item && item.patient_id || 0);
        if (!appointmentId || !patientId) {
            return;
        }

        const requestId = ++clinicalSelectionRequest;
        if (clinicalSelectionXhr && clinicalSelectionXhr.readyState !== 4) {
            clinicalSelectionXhr.abort();
        }
        clinicalSelection.appointmentId = 0;
        clinicalSelection.patientId = 0;
        window.rootinsClinicalSelection = clinicalSelection;
        hideClinicalPatientContent();
        const button = $('.opd-select-appointment[data-id="' + appointmentId + '"]').first();
        if (button.length) {
            button.prop('disabled', true).attr('aria-busy', 'true').addClass('is-selecting').empty()
                .append($('<i>', {class: 'spinner-border spinner-border-sm mr-1', 'aria-hidden': 'true'}))
                .append(document.createTextNode('Opening Consultation…'));
        }
        $('#clinical-feedback').text('Validating the selected appointment…');
        clinicalSelectionXhr = ajaxRequest({
            url: '<?= base_url('doctor/clinical/appointments/search') ?>',
            method: 'GET',
            data: {
                appointment_id: appointmentId,
                patient_id: patientId,
                page: 1,
                page_size: 20,
                _selection_refresh: Date.now(),
            },
            success: function (response) {
                if (requestId !== clinicalSelectionRequest) {
                    return;
                }
                const items = response && response.status && response.data && Array.isArray(response.data.items)
                    ? response.data.items
                    : [];
                const verified = items.find(function (appointment) {
                    return Number(appointment.id) === appointmentId && Number(appointment.patient_id) === patientId;
                });
                if (!verified) {
                    clearClinicalSelection();
                    renderOpdStatus('The selected appointment is no longer available in your clinic scope.', false);
                    if (button.length) {
                        button.prop('disabled', false).removeAttr('aria-busy').removeClass('is-selecting').empty()
                            .append($('<i>', {class: 'bi bi-box-arrow-in-right mr-1', 'aria-hidden': 'true'}))
                            .append(document.createTextNode('Consult'));
                    }
                    return;
                }
                clinicalSelection.appointmentId = appointmentId;
                clinicalSelection.patientId = patientId;
                window.rootinsClinicalSelection = clinicalSelection;
                $('#clinical-feedback').text('Appointment selected. Opening the Clinical Workspace…');
                window.setTimeout(function () {
                    $('#clinical-opd-queue-modal').modal('hide');
                    window.location.replace('<?= base_url('doctor/clinical') ?>?appointment_id=' + encodeURIComponent(appointmentId) + '&patient_id=' + encodeURIComponent(patientId) + '&clinical_refresh=' + Date.now() + '#patient-summary');
                }, 120);
            },
            error: function (xhr, status) {
                if (requestId !== clinicalSelectionRequest || status === 'abort') {
                    return;
                }
                clearClinicalSelection();
                renderOpdStatus('The selected appointment could not be loaded. Please try again.', false);
                if (button.length) {
                    button.prop('disabled', false).removeAttr('aria-busy').removeClass('is-selecting').empty()
                        .append($('<i>', {class: 'bi bi-box-arrow-in-right mr-1', 'aria-hidden': 'true'}))
                        .append(document.createTextNode('Consult'));
                }
            },
            complete: function () {
                if (requestId === clinicalSelectionRequest) {
                    clinicalSelectionXhr = null;
                }
            },
        });
    }

    if (!clinicalSelection.appointmentId || !clinicalSelection.patientId) {
        clearClinicalSelection();
    }

    function clinicalActionValue(value, fallback) {
        const text = String(value || '').trim();

        return text !== '' ? text : (fallback || 'Not recorded');
    }

    function clinicalActionLoading() {
        clinicalActionPrimary.addClass('d-none').off('click');
        clinicalActionBody.html('<div class="clinical-action-loading text-muted" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading…</div>');
    }

    function clinicalActionEmpty(message) {
        clinicalActionPrimary.addClass('d-none').off('click');
        clinicalActionBody.html('<div class="empty-state" role="status"><i class="bi bi-inbox d-block h3 mb-2" aria-hidden="true"></i>' + $('<div>').text(message).html() + '</div>');
    }

    function clinicalActionError(message) {
        clinicalActionPrimary.addClass('d-none').off('click');
        clinicalActionBody.html('<div class="alert alert-danger" role="alert">' + $('<div>').text(message).html() + '</div>');
    }

    function appendClinicalSummary(container) {
        const context = clinicalActionContext;
        const summary = $('<div>', {class: 'clinical-action-summary card mb-3'});
        const body = $('<div>', {class: 'card-body py-3'});
        const title = $('<h3>', {class: 'h6 mb-3', text: clinicalActionValue(context.patient_name, 'No patient selected')});
        const details = $('<dl>', {class: 'row mb-0'});
        [['Patient ID', context.registration_number || (context.patient_id ? '#' + context.patient_id : '')], ['Mobile', context.mobile], ['Appointment', [context.appointment_date, context.appointment_time].filter(Boolean).join(' ')], ['Status', String(context.appointment_status || '').replace(/_/g, ' ')]].forEach(function (item) {
            $('<dt>', {class: 'col-sm-4 text-muted', text: item[0]}).appendTo(details);
            $('<dd>', {class: 'col-sm-8 mb-2', text: clinicalActionValue(item[1])}).appendTo(details);
        });
        body.append(title, details).appendTo(summary);
        summary.appendTo(container);
    }

    function appendClinicalField(container, label, value) {
        const row = $('<div>', {class: 'clinical-action-field border rounded p-3 mb-2'});
        $('<strong>', {class: 'd-block mb-1', text: label}).appendTo(row);
        $('<div>', {class: 'text-muted', text: clinicalActionValue(value)}).appendTo(row);
        row.appendTo(container);
    }

    function printClinicalAction(printArea) {
        $('.clinical-action-print-area').removeClass('is-print-target');
        printArea.addClass('is-print-target');
        document.body.classList.add('doctor-clinical-printing');

        const cleanup = function () {
            document.body.classList.remove('doctor-clinical-printing');
            printArea.removeClass('is-print-target');
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);
        window.setTimeout(function () {
            window.print();
        }, 0);
    }

    function renderClinicalLocalAction(action) {
        const context = clinicalActionContext;
        if (!context.appointment_id) {
            clinicalActionEmpty('Select an appointment from Today’s OPD before opening this action.');
            return;
        }

        clinicalActionBody.empty();
        if (action === 'consultation') {
            appendClinicalSummary(clinicalActionBody);
            clinicalActionTitle.text('Consultation Form');
            appendClinicalField(clinicalActionBody, 'Chief Complaints', context.consultation.chief_complaint);
            appendClinicalField(clinicalActionBody, 'Diagnosis', context.consultation.diagnosis);
            appendClinicalField(clinicalActionBody, 'Advice', context.consultation.treatment_plan);
            appendClinicalField(clinicalActionBody, 'Diagnostic Tests', context.consultation.diagnostic_tests);
            appendClinicalField(clinicalActionBody, 'Diet Plan', context.consultation.diet_plan);
            appendClinicalField(clinicalActionBody, 'Private Notes', context.consultation.clinical_notes);
            clinicalActionPrimary.removeClass('d-none').text('Go to consultation form').off('click').on('click', function () {
                clinicalActionModal.one('hidden.bs.modal', function () {
                    const target = document.querySelector('.clinical-workflow-content .consultation-form');
                    if (target) target.scrollIntoView({behavior: 'smooth', block: 'start'});
                }).modal('hide');
            });
            return;
        }

        const prescription = context.prescription;
        if (prescription === null) {
            clinicalActionEmpty('No prescription is available for the selected appointment.');
            return;
        }

        clinicalActionTitle.text(action === 'past-prescription' ? 'Past Prescription' : 'Print Prescription');
        const printArea = $('<div>', {class: 'clinical-action-print-area rootins-print-document'});
        const clinicHeader = $('<div>', {class: 'clinical-action-prescription-clinic'});
        if (context.clinic_logo_url) {
            $('<img>', {class: 'clinical-action-prescription-clinic-logo', src: context.clinic_logo_url, alt: clinicalActionValue(context.clinic_name, 'Clinic logo')}).appendTo(clinicHeader);
        }
        const clinicDetails = $('<div>');
        $('<div>', {class: 'clinical-action-prescription-clinic-name', text: clinicalActionValue(context.clinic_name, 'Clinic')}).appendTo(clinicDetails);
        [context.clinic_address].filter(Boolean).forEach(function (detail) {
            $('<div>', {class: 'small text-muted', text: detail}).appendTo(clinicDetails);
        });
        clinicDetails.appendTo(clinicHeader);
        clinicHeader.appendTo(printArea);
        $('<h3>', {class: 'clinical-action-prescription-document-title', text: 'Prescription'}).appendTo(printArea);
        $('<h3>', {class: 'clinical-action-prescription-title', text: 'Patient Information'}).appendTo(printArea);
        const patientCard = $('<div>', {class: 'clinical-action-prescription-patient-card'});
        const patientMeta = $('<dl>', {class: 'clinical-action-prescription-meta'});
        const patientMetaItems = [
            ['Name', context.patient_name],
            ['Patient ID', context.registration_number || ''],
            ['Contact', context.mobile],
            ['Prescription date', prescription.date],
            ['Doctor', context.doctor_name],
            ['Appointment / consultation', [context.appointment_number, context.consultation_number].filter(Boolean).join(' · ')],
        ];
        if (context.patient_age && context.patient_age !== 'Not recorded') {
            patientMetaItems.splice(1, 0, ['Age', context.patient_age]);
        }
        patientMetaItems.filter(function (item) {
            return item[0] === 'Name' || String(item[1] || '').trim() !== '';
        }).forEach(function (item) {
            const metaItem = $('<div>', {class: 'clinical-action-prescription-meta-item'});
            if (item[0] === 'Prescription date' || item[0] === 'Appointment / consultation') {
                metaItem.addClass('clinical-action-prescription-meta-item-emphasis');
            }
            $('<dt>', {text: item[0]}).appendTo(metaItem);
            $('<dd>', {text: clinicalActionValue(item[1])}).appendTo(metaItem);
            metaItem.appendTo(patientMeta);
        });
        patientMeta.appendTo(patientCard);
        patientCard.appendTo(printArea);

        const durations = (prescription.items || []).map(function (item) {
            return [item.duration, item.duration_unit].filter(Boolean).join(' ');
        }).filter(Boolean);
        $('<h3>', {class: 'clinical-action-prescription-title', text: 'Prescribed Medicines' + (durations.length ? ' (for ' + durations[0] + ')' : '')}).appendTo(printArea);
        const table = $('<table>', {class: 'table table-bordered table-sm clinical-action-prescription-table'});
        $('<caption>', {class: 'sr-only', text: 'Prescription medicine items'}).appendTo(table);
        $('<thead><tr><th scope="col">#</th><th scope="col">Medicine</th><th scope="col">Duration</th><th scope="col">Frequency</th><th scope="col">Dose</th><th scope="col">Intake</th><th scope="col">Note</th></tr></thead>').appendTo(table);
        const tableBody = $('<tbody>').appendTo(table);
        if (!prescription.items || !prescription.items.length) {
            $('<tr><td colspan="7" class="text-muted">No medicine items recorded.</td></tr>').appendTo(tableBody);
        } else {
            prescription.items.forEach(function (item, index) {
                const row = $('<tr>');
                [
                    index + 1,
                    clinicalActionValue(item.medicine_name),
                    [item.duration, item.duration_unit].filter(Boolean).join(' ') || '—',
                    clinicalActionValue(item.frequency, '—'),
                    clinicalActionValue(item.dosage, '—'),
                    clinicalActionValue(item.instructions, '—'),
                    clinicalActionValue(item.notes, '—'),
                ].forEach(function (value, columnIndex) {
                    const cell = $('<td>', {text: String(value)}).attr('data-label', ['#', 'Medicine', 'Duration', 'Frequency', 'Dose', 'Intake', 'Note'][columnIndex]);
                    cell.appendTo(row);
                });
                row.appendTo(tableBody);
            });
        }
        $('<div>', {class: 'table-responsive clinical-action-prescription-table-wrap'}).append(table).appendTo(printArea);
        if (String(context.clinic_mobile || '').trim() !== '') {
            const clinicFooter = $('<footer>', {class: 'clinical-action-prescription-footer'});
            $('<span>', {text: 'Clinic contact'}).appendTo(clinicFooter);
            $('<span>', {text: context.clinic_mobile}).appendTo(clinicFooter);
            clinicFooter.appendTo(printArea);
        }
        printArea.appendTo(clinicalActionBody.empty());
        clinicalActionPrimary.removeClass('d-none').text('Print').off('click').on('click', function () { printClinicalAction(printArea); });
    }

    function loadClinicalRemoteAction(url, selector) {
        clinicalActionLoading();
        if (clinicalActionXhr && clinicalActionXhr.readyState !== 4) {
            clinicalActionXhr.abort();
        }
        clinicalActionXhr = ajaxRequest({
            url: url,
            method: 'GET',
            dataType: 'html',
            success: function (html) {
                const parsed = $('<div>').append($.parseHTML(html, document, true));
                const content = parsed.find(selector).first();
                content.find('script').remove();
                if (!content.length) {
                    clinicalActionError('Details could not be loaded safely.');
                    return;
                }
                clinicalActionBody.empty().append(content);
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    clinicalActionError(xhr.status === 403 ? 'You do not have permission to view these details.' : 'Details could not be loaded. Please try again.');
                }
            },
        });
    }

    function clinicalPastText(value, fallback) {
        return $('<div>').text(clinicalActionValue(value, fallback)).html();
    }

    function renderClinicalPastVisits(data) {
        const pagination = data.pagination || {page: 1, pages: 1, total: 0};
        const rows = data.items || [];
        let html = '<div class="clinical-past-visits-picker mb-3"><label for="clinical-past-visit-select">Visits</label><select id="clinical-past-visit-select" class="form-control btSelect" aria-label="Select a past visit"><option value="">All Visits</option>' + rows.map(function (visit) { return '<option value="' + Number(visit.id || 0) + '">' + clinicalPastText([visit.date, visit.time, visit.appointment_type].filter(Boolean).join(' · '), 'Visit') + '</option>'; }).join('') + '</select></div>';
        html += '<div class="small text-muted mb-2" role="status" aria-live="polite">' + String(pagination.total || 0) + ' visit' + (Number(pagination.total || 0) === 1 ? '' : 's') + ' found</div>';
        if (!rows.length) {
            html += '<div class="empty-state" role="status"><i class="bi bi-journal-x d-block h3 mb-2" aria-hidden="true"></i>No past visits match the selected filters.</div>';
        } else {
            html += '<div class="clinical-past-visits-reference-list" aria-live="polite">';
            rows.forEach(function (visit) {
                const visitId = Number(visit.id || 0);
                html += '<article class="clinical-past-visit-loading-card" data-past-visit-detail-id="' + visitId + '" tabindex="-1" aria-label="Loading visit details"><div class="clinical-action-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading visit details…</div></article>';
            });
            html += '</div>';
        }
        html += '<div class="d-flex flex-wrap align-items-center justify-content-between mt-3 clinical-past-visits-pagination" role="navigation" aria-label="Past visits pagination"><span class="small text-muted">Page ' + Number(pagination.page || 1) + ' of ' + Number(pagination.pages || 1) + '</span><div><button class="btn btn-sm btn-outline-secondary mr-1" type="button" data-past-visits-page="' + Math.max(1, Number(pagination.page || 1) - 1) + '"' + (Number(pagination.page || 1) <= 1 ? ' disabled' : '') + '>Previous</button><button class="btn btn-sm btn-outline-secondary" type="button" data-past-visits-page="' + Math.min(Number(pagination.pages || 1), Number(pagination.page || 1) + 1) + '"' + (Number(pagination.page || 1) >= Number(pagination.pages || 1) ? ' disabled' : '') + '>Next</button></div></div>';
        clinicalActionBody.html(html);
        if (typeof clinicalActionModal !== 'undefined') clinicalActionModal.find('.bs-searchbox input:visible').first().trigger('focus');
        clinicalActionBody.find('[data-past-visit-detail-id]').each(function () {
            loadClinicalPastVisitDetails(Number($(this).attr('data-past-visit-detail-id')) || 0, $(this));
        });
    }

    function renderClinicalPastVisitDetails(data, target) {
        const appointment = data.appointment || {};
        const patient = data.patient || {};
        const consultation = data.consultation || {};
        const prescription = data.prescription;
        const appointmentType = appointment.appointment_type ? String(appointment.appointment_type).replace(/_/g, ' ') : '';
        const appointmentStatus = appointment.status ? String(appointment.status).replace(/_/g, ' ') : '';
        let html = '<article class="clinical-past-visit-reference-card" aria-label="Past visit details for ' + clinicalPastText(patient.name, 'selected patient') + '" tabindex="-1">';
        html += '<aside class="clinical-past-visit-reference-meta"><div class="clinical-past-visit-meta-line"><strong>Visit Date:</strong><span class="clinical-past-visit-date">' + clinicalPastText([appointment.date, appointment.time].filter(Boolean).join(' '), '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Type</strong><span>' + clinicalPastText(appointmentType, '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Remarks:</strong><span>' + clinicalPastText(appointment.remarks, '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Status</strong><span class="badge clinical-past-visit-status">' + clinicalPastText(appointmentStatus, '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Service:</strong><span>' + clinicalPastText(appointment.service_name, '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Doctor:</strong><span>' + clinicalPastText(appointment.doctor, '—') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Private Note:</strong><span>' + clinicalPastText(consultation.private_notes, 'NA') + '</span></div><div class="clinical-past-visit-meta-line"><strong>Queue:</strong><span>' + clinicalPastText(appointment.queue_number ? '#' + appointment.queue_number : '', '—') + '</span></div></aside>';
        html += '<div class="clinical-past-visit-reference-panels">';
        function panel(title, value) {
            return '<section class="clinical-past-visit-reference-panel"><h4>' + clinicalPastText(title) + '</h4><div class="clinical-past-visit-reference-value' + (value ? '' : ' is-empty') + '">' + clinicalPastText(value, 'No information recorded.') + '</div></section>';
        }
        html += panel('Advice', consultation.advice);
        html += panel('Tests', consultation.diagnostic_tests);
        html += panel('Diet', consultation.diet_plan);
        html += panel('Complaints', consultation.chief_complaint);
        html += panel('Diagnosis', consultation.diagnosis);
        if (prescription === null) {
            html += panel('Prescription', 'No prescription recorded for this visit.');
        } else {
            const prescriptionTitle = 'Prescription' + (prescription.duration ? ' (For ' + clinicalPastText(prescription.duration) + ' Days)' : '');
            let prescriptionHtml = '<section class="clinical-past-visit-reference-panel"><h4>' + prescriptionTitle + '</h4><div class="small text-muted mb-2">' + clinicalPastText([prescription.number, prescription.date, prescription.status ? String(prescription.status).replace(/_/g, ' ') : ''].filter(Boolean).join(' · '), 'Prescription details') + '</div>';
            if (Array.isArray(prescription.items) && prescription.items.length) {
                prescriptionHtml += '<div class="table-responsive clinical-past-visit-prescription-table"><table class="table table-sm mb-0"><caption class="sr-only">Prescription medicines</caption><thead><tr><th scope="col">Medicine</th><th scope="col">Duration</th><th scope="col">Frequency</th><th scope="col">Dose</th><th scope="col">Intake</th><th scope="col">Notes</th></tr></thead><tbody>' + prescription.items.map(function (item) { return '<tr><td>' + clinicalPastText(item.medicine_name, '—') + '</td><td>' + clinicalPastText(item.duration, '—') + '</td><td>' + clinicalPastText(item.frequency, '—') + '</td><td>' + clinicalPastText(item.dosage, '—') + '</td><td>' + clinicalPastText(item.instructions, '—') + '</td><td>' + clinicalPastText(item.notes, '—') + '</td></tr>'; }).join('') + '</tbody></table></div>';
            } else {
                prescriptionHtml += '<div class="clinical-past-visit-reference-value is-empty">No medicines recorded for this visit.</div>';
            }
            if (prescription.notes) prescriptionHtml += '<div class="small clinical-past-visit-prescription-notes mt-2"><strong>Notes:</strong> ' + clinicalPastText(prescription.notes) + '</div>';
            html += prescriptionHtml + '</section>';
        }
        html += panel('Follow-up Setup', [consultation.follow_up_after_days ? consultation.follow_up_after_days + ' days' : '', consultation.follow_up_date, consultation.follow_up_time, consultation.follow_up_type, consultation.follow_up_remarks].filter(Boolean).join(' · '));
        html += panel('Appointment Setup', [consultation.next_appointment_after_days ? consultation.next_appointment_after_days + ' days' : '', consultation.next_appointment_date, consultation.next_appointment_time, consultation.next_appointment_type].filter(Boolean).join(' · '));
        html += '</div></article>';
        if (target && target.length) {
            target.replaceWith(html);
            return;
        }
        clinicalActionBody.html(html);
    }

    function loadClinicalPastVisitDetails(visitId, target) {
        if (!target || !target.length) {
            clinicalActionTitle.text('Past Visit Details');
            clinicalActionLoading();
            if (clinicalActionXhr && clinicalActionXhr.readyState !== 4) clinicalActionXhr.abort();
        }
        const request = ajaxRequest({
            url: '<?= base_url('doctor/clinical/past-visits') ?>/' + encodeURIComponent(visitId),
            method: 'GET',
            data: {patient_id: clinicalActionContext.patient_id},
            success: function (response) {
                if (response && response.status && response.data) {
                    renderClinicalPastVisitDetails(response.data, target);
                    return;
                }
                if (target && target.length) target.replaceWith('<article class="clinical-past-visit-reference-card clinical-past-visit-load-error" role="alert"><i class="bi bi-exclamation-triangle mr-1" aria-hidden="true"></i>' + clinicalPastText(response && response.msg ? response.msg : 'Visit details could not be loaded safely.') + '</article>');
                else clinicalActionError(response && response.msg ? response.msg : 'Visit details could not be loaded safely.');
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                const message = xhr.status === 403 ? 'You do not have permission to view these details.' : 'Visit details could not be loaded. Please try again.';
                if (target && target.length) target.replaceWith('<article class="clinical-past-visit-reference-card clinical-past-visit-load-error" role="alert"><i class="bi bi-exclamation-triangle mr-1" aria-hidden="true"></i>' + clinicalPastText(message) + '</article>');
                else clinicalActionError(message);
            },
        });
        if (!target || !target.length) clinicalActionXhr = request;
    }

    function loadClinicalPastVisits(page, filters) {
        if (!clinicalActionContext.patient_id) {
            clinicalActionEmpty('Select an appointment from Today’s OPD before opening Past Visits.');
            return;
        }
        const currentFilters = filters || {};
        clinicalActionTitle.text('Past Visits Details');
        clinicalActionLoading();
        if (clinicalActionXhr && clinicalActionXhr.readyState !== 4) clinicalActionXhr.abort();
        clinicalActionXhr = ajaxRequest({
            url: '<?= base_url('doctor/clinical/past-visits') ?>',
            method: 'GET',
            data: $.extend({patient_id: clinicalActionContext.patient_id, page: page || 1, page_size: 10}, currentFilters),
            success: function (response) {
                if (response && response.status && response.data) {
                    renderClinicalPastVisits(response.data);
                    return;
                }
                clinicalActionError(response && response.msg ? response.msg : 'Past visits could not be loaded safely.');
            },
            error: function (xhr, status) {
                if (status !== 'abort') clinicalActionError(xhr.status === 403 ? 'You do not have permission to view past visits.' : 'Past visits could not be loaded. Please try again.');
            },
        });
    }

    $(document).off('click.clinicalPastVisits', '[data-past-visits-page]').on('click.clinicalPastVisits', '[data-past-visits-page]', function () {
        if ($(this).prop('disabled')) return;
        loadClinicalPastVisits(Number($(this).data('past-visits-page')) || 1);
    });
    $(document).off('change.clinicalPastVisits', '#clinical-past-visit-select').on('change.clinicalPastVisits', '#clinical-past-visit-select', function () {
        const visitId = Number($(this).val()) || 0;
        if (visitId <= 0) return;
        const target = clinicalActionBody.find('[data-past-visit-detail-id="' + visitId + '"]').first();
        if (!target.length) return;
        target[0].scrollIntoView({behavior: 'smooth', block: 'start'});
        target.trigger('focus');
    });

    function openClinicalAction(action) {
        const titles = {'past-visits': 'Past Visits', consultation: 'Consultation Form', 'past-prescription': 'Past Prescription', 'print-prescription': 'Print Prescription', 'new-payment': 'New Payment', 'payment-history': 'Payment History', images: 'Images'};
        clinicalActionTitle.text(titles[action] || 'Clinical action');
        clinicalActionLoading();
        if (action === 'past-visits') {
            loadClinicalPastVisits(1);
        } else if (action === 'payment-history') {
            loadClinicalRemoteAction(clinicalActionContext.payment_history_url, 'main[aria-labelledby="doctor-billing-title"]');
        } else if (action === 'consultation' || action === 'past-prescription' || action === 'print-prescription') {
            renderClinicalLocalAction(action);
        } else if (action === 'new-payment') {
            if (!clinicalActionContext.appointment_id) {
                clinicalActionEmpty('Select an appointment before reviewing payment options.');
            } else {
                appendClinicalSummary(clinicalActionBody.empty());
                $('<div>', {class: 'alert alert-warning mb-0', role: 'status', text: 'New payment entry is not available for the Doctor role. No payment data was submitted.'}).appendTo(clinicalActionBody);
            }
        } else {
            if (!clinicalActionContext.appointment_id) {
                clinicalActionEmpty('Select an appointment before opening patient images.');
            } else {
                appendClinicalSummary(clinicalActionBody.empty());
                $('<div>', {class: 'alert alert-warning mb-0', role: 'status', text: 'Patient images are not available in the Doctor panel yet. No unscoped image request was made.'}).appendTo(clinicalActionBody);
            }
        }
    }

    $(document).on('click', '[data-clinical-action]', function () {
        openClinicalAction(String($(this).data('clinical-action') || ''));
    });
    clinicalActionModal.on('hidden.bs.modal', function () {
        if (clinicalActionXhr && clinicalActionXhr.readyState !== 4) {
            clinicalActionXhr.abort();
        }
        clinicalActionBody.empty();
        clinicalActionPrimary.addClass('d-none').off('click');
    });

    $(document).on('click', '.clinical-cancel', function () { const form = $(this).closest('form'); if (form.length) { form[0].reset(); if (typeof clearFormErrors === 'function') clearFormErrors(form); } });
    const opdSearch = $('#clinical-opd-search');
    const opdResults = $('#clinical-opd-results');
    let opdSearchTimer = null;
    let opdSearchRequest = 0;
    let opdSearchXhr = null;
    let opdCurrentPage = 1;
    let opdTotalPages = 1;
    function resetOpdModalSelectionState() {
        opdResults.removeData('items').data('active-index', -1).find('.clinical-opd-result')
            .removeClass('active').attr('aria-selected', 'false');
        opdSearch.removeAttr('aria-activedescendant');
    }
    function renderOpdStatus(message, loading) { opdResults.empty().append($('<div>', {class: 'list-group-item text-muted', role: 'status', text: message})); $('#clinical-opd-pagination').prop('hidden', true); if (loading) opdResults.find('[role="status"]').prepend($('<span>', {class: 'spinner-border spinner-border-sm mr-2', 'aria-hidden': 'true'})); }
    function renderOpdAppointments(items, pagination) {
        opdResults.empty();
        if (!items || !items.length) {
            renderOpdStatus('No appointments found in your clinic scope.', false);
            return;
        }
        const table = $('<table>', {
            class: 'table table-sm mb-0 clinical-opd-table',
        }).attr('aria-label', 'Today’s OPD appointments');
        const headings = ['Patient', 'Appointment', 'Action'];
        const header = $('<tr>');
        headings.forEach(function (heading) { $('<th>', {scope: 'col', text: heading}).appendTo(header); });
        $('<thead>').append(header).appendTo(table);
        const body = $('<tbody>').appendTo(table);
        items.forEach(function (item, index) {
            const row = $('<tr>', {
                class: 'clinical-opd-result',
                role: 'option',
                id: 'clinical-opd-result-' + item.id,
                tabindex: '-1',
                'data-index': index,
                'aria-selected': 'false',
            }).appendTo(body);
            const queueLabel = Number(item.queue_number || 0) > 0 ? 'Queue #' + item.queue_number : 'Queue pending';
            const typeLabel = [item.service_name, item.appointment_type].filter(Boolean).join(' · ') || 'Not specified';
            const patientNumber = item.registration_number || ('#' + item.patient_id);
            const statusLabel = String(item.status || 'unknown').replace(/_/g, ' ');
            const patientCell = $('<td>', {class: 'clinical-opd-queue-cell', 'data-label': 'Patient'}).appendTo(row);
            $('<strong>', {class: 'd-block clinical-opd-truncate', text: item.patient_name || 'Patient', title: item.patient_name || 'Patient'}).appendTo(patientCell);
            $('<span>', {class: 'small text-muted d-block', text: 'Patient no: ' + patientNumber, title: 'Patient number ' + patientNumber}).appendTo(patientCell);
            $('<span>', {class: 'small text-muted d-block clinical-opd-truncate', text: 'Mobile: ' + (item.mobile || 'Not recorded'), title: item.mobile || 'Mobile not recorded'}).appendTo(patientCell);
            const appointmentCell = $('<td>', {class: 'clinical-opd-queue-cell', 'data-label': 'Appointment'}).appendTo(row);
            $('<strong>', {class: 'd-block clinical-opd-truncate', text: typeLabel, title: typeLabel, 'aria-label': typeLabel}).appendTo(appointmentCell);
            $('<span>', {class: 'small text-muted d-block', text: queueLabel}).appendTo(appointmentCell);
            $('<span>', {class: 'badge badge-light clinical-opd-status', text: statusLabel, title: 'Appointment status: ' + statusLabel, 'aria-label': 'Appointment status: ' + statusLabel}).appendTo(appointmentCell);
            const actionCell = $('<td>', {class: 'clinical-opd-queue-cell clinical-opd-action-cell', 'data-label': 'Action'}).append(
                $('<button>', {
                    type: 'button',
                    class: 'btn btn-sm btn-outline-primary clinical-opd-primary-action opd-select-appointment',
                    'data-id': item.id,
                    'data-patient-id': item.patient_id,
                    'aria-label': 'Consult ' + (item.patient_name || 'patient'),
                    title: 'Consult this patient',
                }).append($('<i>', {class: 'bi bi-box-arrow-in-right mr-1', 'aria-hidden': 'true'})).append(document.createTextNode('Consult'))
            ).appendTo(row);
            const appointmentStatus = String(item.status || '').toLowerCase();
            const actionMenu = $('<div>', {class: 'dropdown clinical-opd-action-menu'});
            const actionToggle = $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-outline-secondary dropdown-toggle',
                'data-toggle': 'dropdown',
                'data-boundary': 'viewport',
                'aria-haspopup': 'true',
                'aria-expanded': 'false',
                'aria-label': 'More actions for ' + (item.patient_name || 'patient'),
                title: 'More appointment actions',
            }).append($('<i>', {class: 'fa-solid fa-ellipsis-vertical ri-actions-icon mr-1', 'aria-hidden': 'true'})).append(document.createTextNode('More Actions')).appendTo(actionMenu);
            const menu = $('<div>', {class: 'dropdown-menu dropdown-menu-right', role: 'menu'}).appendTo(actionMenu);
            function appendQueueAction(icon, label, href, title) {
                $('<a>', {class: 'dropdown-item', role: 'menuitem', href: href, title: title, 'aria-label': label + ' for ' + (item.patient_name || 'patient')})
                    .append($('<i>', {class: icon + ' mr-2', 'aria-hidden': 'true'}))
                    .append(document.createTextNode(label))
                    .appendTo(menu);
            }
            if (<?= $can('patient.view') ? 'true' : 'false' ?>) {
                appendQueueAction('bi bi-person', 'View Patient', '<?= base_url('doctor/patients') ?>/' + encodeURIComponent(item.patient_id), 'View patient details');
            }
            if (<?= $can('appointment.view') ? 'true' : 'false' ?>) {
                appendQueueAction('bi bi-calendar-event', 'View Appointment', '<?= base_url('doctor/appointments') ?>/' + encodeURIComponent(item.id), 'View appointment details');
            }
            if (<?= $can('appointment.view') ? 'true' : 'false' ?> && String(item.appointment_type || '').toLowerCase() === 'video' && !['cancelled', 'no_show'].includes(appointmentStatus)) {
                appendQueueAction('bi bi-camera-video', 'Start Video Consultation', '<?= base_url('doctor/video') ?>/' + encodeURIComponent(item.id), 'Start video consultation');
            }
            if (<?= $can('prescription.view') ? 'true' : 'false' ?> && Number(item.prescription_id || 0) > 0) {
                appendQueueAction('bi bi-file-medical', 'View Prescription', '<?= base_url('doctor/prescriptions') ?>?prescription_id=' + encodeURIComponent(item.prescription_id), 'View prescription details');
            }
            if (menu.children().length) {
                actionMenu.appendTo(actionCell);
            }
        });
        $('<div>', {class: 'table-responsive clinical-opd-table-wrap'}).append(table).appendTo(opdResults);
        opdResults.data('items', items).data('active-index', -1);
        opdCurrentPage = Number((pagination && pagination.page) || 1);
        opdTotalPages = Number((pagination && pagination.pages) || 1);
        $('#clinical-opd-page-status').text('Page ' + opdCurrentPage + ' of ' + opdTotalPages);
        $('#clinical-opd-prev').prop('disabled', opdCurrentPage <= 1);
        $('#clinical-opd-next').prop('disabled', opdCurrentPage >= opdTotalPages);
        $('#clinical-opd-pagination').prop('hidden', opdTotalPages <= 1);
        opdSearch.attr('aria-expanded', 'true');
    }
    function searchOpd(query, page) { const requestId = ++opdSearchRequest; if (opdSearchXhr && opdSearchXhr.readyState !== 4) opdSearchXhr.abort(); renderOpdStatus('Loading today’s appointments…', true); opdSearchXhr = ajaxRequest({url: '<?= base_url('doctor/clinical/appointments/search') ?>', method: 'GET', data: {q: query, page: page || 1, page_size: 20}, success: function (response) { if (requestId !== opdSearchRequest) return; if (response.status) renderOpdAppointments(response.data && response.data.items ? response.data.items : [], response.data && response.data.pagination ? response.data.pagination : {}); else renderOpdStatus(response.msg || 'Appointments could not be loaded.', false); }, error: function (xhr, status) { if (requestId === opdSearchRequest && status !== 'abort') renderOpdStatus('Today’s queue is unavailable. Try again.', false); }}); }
    $('#clinical-opd-queue-modal').on('shown.bs.modal', function () {
        resetOpdModalSelectionState();
        searchOpd(String(opdSearch.val() || '').trim(), 1);
    });
    $('#clinical-opd-queue-modal').on('hidden.bs.modal', function () {
        window.clearTimeout(opdSearchTimer);
        opdSearchRequest++;
        if (opdSearchXhr && opdSearchXhr.readyState !== 4) {
            opdSearchXhr.abort();
        }
        clinicalSelectionRequest++;
        if (clinicalSelectionXhr && clinicalSelectionXhr.readyState !== 4) {
            clinicalSelectionXhr.abort();
        }
        resetOpdModalSelectionState();
    });
    opdSearch.on('input', function () { const query = String(this.value || '').trim(); window.clearTimeout(opdSearchTimer); opdSearchTimer = window.setTimeout(function () { searchOpd(query, 1); }, 280); });
    $('#clinical-opd-prev').on('click', function () { if (opdCurrentPage > 1) searchOpd(String(opdSearch.val() || '').trim(), opdCurrentPage - 1); });
    $('#clinical-opd-next').on('click', function () { if (opdCurrentPage < opdTotalPages) searchOpd(String(opdSearch.val() || '').trim(), opdCurrentPage + 1); });
    opdSearch.on('keydown', function (event) { const items = opdResults.data('items') || []; const active = Number(opdResults.data('active-index')); if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) { event.preventDefault(); const next = event.key === 'ArrowDown' ? active + 1 : (active <= 0 ? items.length - 1 : active - 1); const index = (next + items.length) % items.length; opdResults.data('active-index', index).find('.clinical-opd-result').removeClass('active').attr('aria-selected', 'false').eq(index).addClass('active').attr('aria-selected', 'true'); opdSearch.attr('aria-activedescendant', 'clinical-opd-result-' + items[index].id); } else if (event.key === 'Enter' && active >= 0 && items[active]) { event.preventDefault(); selectClinicalAppointment(items[active]); } else if (event.key === 'Escape') $('#clinical-opd-queue-modal').modal('hide'); });
    $(document).on('click', '.opd-select-appointment', function () { const id = Number($(this).data('id') || 0); const patientId = Number($(this).data('patient-id') || 0); const item = (opdResults.data('items') || []).find(function (appointment) { return Number(appointment.id) === id && Number(appointment.patient_id) === patientId; }); if (item) selectClinicalAppointment(item); });
    function setClinicalDaysError(fieldSelector, errorSelector, message) {
        const field = $(fieldSelector);
        const error = $(errorSelector);
        if (!field.length || !error.length) return;
        error.text(message || '');
        field.toggleClass('is-invalid', Boolean(message)).attr('aria-invalid', message ? 'true' : 'false');
    }
    function setClinicalCalculatedDate(selector, isoDate) {
        const field = $(selector);
        if (!field.length) return;
        field.data('setting-auto-date', true);
        if (typeof window.setSharedDateValue === 'function') {
            window.setSharedDateValue(selector, isoDate || '');
        } else {
            const parts = String(isoDate || '').split('-');
            field.val(parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : '');
        }
        if (field.data('datepicker')) {
            field.datepicker('update', field.val() || '');
        }
        field.data('setting-auto-date', false).data('auto-date', Boolean(isoDate));
    }
    function calculateClinicalRelativeDate(daysSelector, dateSelector, errorSelector, dateLabel) {
        const daysField = $(daysSelector);
        if (!daysField.length || daysField.prop('disabled')) return;
        const rawDays = String(daysField.val() || '').trim();
        setClinicalDaysError(daysSelector, errorSelector, '');
        if (rawDays === '') {
            setClinicalCalculatedDate(dateSelector, '');
            return;
        }
        const days = Number(rawDays);
        if (!/^\d+$/.test(rawDays) || !Number.isSafeInteger(days) || days > 65535) {
            setClinicalDaysError(daysSelector, errorSelector, 'Enter a whole number of days from 0 to 65535.');
            setClinicalCalculatedDate(dateSelector, '');
            return;
        }
        const baseValue = String(daysField.attr('data-appointment-base-date') || '');
        const baseParts = baseValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!baseParts) {
            setClinicalCalculatedDate(dateSelector, '');
            $(errorSelector.replace('after_daysErr', 'dateErr')).text(dateLabel + ' date could not be calculated safely.');
            return;
        }
        const baseDate = new Date(Date.UTC(Number(baseParts[1]), Number(baseParts[2]) - 1, Number(baseParts[3])));
        if (Number.isNaN(baseDate.getTime()) || baseDate.getUTCFullYear() !== Number(baseParts[1]) || baseDate.getUTCMonth() !== Number(baseParts[2]) - 1 || baseDate.getUTCDate() !== Number(baseParts[3])) {
            setClinicalCalculatedDate(dateSelector, '');
            $(errorSelector.replace('after_daysErr', 'dateErr')).text(dateLabel + ' date could not be calculated safely.');
            return;
        }
        baseDate.setUTCDate(baseDate.getUTCDate() + days);
        const year = baseDate.getUTCFullYear();
        const month = String(baseDate.getUTCMonth() + 1).padStart(2, '0');
        const day = String(baseDate.getUTCDate()).padStart(2, '0');
        setClinicalCalculatedDate(dateSelector, year + '-' + month + '-' + day);
        $(errorSelector.replace('after_daysErr', 'dateErr')).text('');
    }
    function calculateNextAppointmentDate() {
        calculateClinicalRelativeDate('#next-appointment-after-days', '#next-appointment-date', '#next_appointment_after_daysErr', 'Appointment');
    }
    function calculateFollowUpDate() {
        calculateClinicalRelativeDate('#follow-up-after-days', '#follow-up-date', '#follow_up_after_daysErr', 'Follow-up');
    }
    $(document).on('input.doctorAppointmentSetup change.doctorAppointmentSetup', '#follow-up-after-days', calculateFollowUpDate);
    $(document).on('input.doctorAppointmentSetup change.doctorAppointmentSetup', '#next-appointment-after-days', calculateNextAppointmentDate);
    $(document).on('input.doctorAppointmentSetup change.doctorAppointmentSetup', '#follow-up-date, #next-appointment-date', function () {
        if (!$(this).data('setting-auto-date')) $(this).data('auto-date', false);
    });
    function medicineSelectForSearchInput(input) {
        // `input` is a native DOM element here because this handler is
        // delegated from the document.  Native Element.closest() already
        // returns the menu element; indexing it turns it into undefined and
        // prevents remote medicine search from resolving its select.
        const menu = input.closest('.dropdown-menu');
        if (!menu) return $();
        return $('#clinical-prescription-items select.clinical-medicine-select').filter(function () {
            const picker = $(this).data('selectpicker');
            const pickerMenu = picker && picker.$menu ? picker.$menu[0] : null;
            return pickerMenu && (pickerMenu === menu || $.contains(pickerMenu, input[0]));
        }).first();
    }
    function clearClinicalMedicineRemoteOptions(select) {
        select.find('option[data-remote-medicine]').remove();
        if (!select.find('option[value=""]').length) {
            $('<option>', {value: '', text: 'Search item...'}).prependTo(select);
        }
        select.val('');
        if (typeof select.selectpicker === 'function') select.selectpicker('refresh');
    }
    function searchClinicalMedicines(input, select, query) {
        const requestId = Number(select.data('medicine-search-request') || 0) + 1;
        const previous = select.data('medicine-search-xhr');
        if (previous && previous.readyState !== 4) previous.abort();
        select.data('medicine-search-request', requestId);
        input.attr('aria-busy', 'true');
        const xhr = ajaxRequest({
            url: select.data('search-url'),
            method: 'GET',
            dataType: 'json',
            data: {q: query},
            success: function (response) {
                if (requestId !== Number(select.data('medicine-search-request'))) return;
                select.find('option[data-remote-medicine]').remove();
                const items = response && response.status && response.data && Array.isArray(response.data.items)
                    ? response.data.items
                    : [];
                if (!items.length) {
                    $('<option>', {text: 'No medicines found', disabled: true, 'data-remote-medicine': 'true'}).appendTo(select);
                } else {
                    items.forEach(function (item) {
                        const label = item.display_name || [item.medicine_name, item.strength].filter(Boolean).join(' · ');
                        $('<option>', {value: item.id, text: label, 'data-remote-medicine': 'true'})
                            .attr('data-medicine-id', item.id)
                            .appendTo(select);
                    });
                }
                select.selectpicker('refresh');
            },
            error: function (xhr, status) {
                if (status !== 'abort' && requestId === Number(select.data('medicine-search-request'))) {
                    select.find('option[data-remote-medicine]').remove();
                    $('<option>', {text: 'Medicine search unavailable', disabled: true, 'data-remote-medicine': 'true'}).appendTo(select);
                    select.selectpicker('refresh');
                }
            },
            complete: function () {
                if (requestId === Number(select.data('medicine-search-request'))) input.removeAttr('aria-busy');
            }
        });
        select.data('medicine-search-xhr', xhr);
    }
    $(document).off('input.clinicalMedicineSearch', '.clinical-prescription-dropdown-menu .bs-searchbox input')
        .on('input.clinicalMedicineSearch', '.clinical-prescription-dropdown-menu .bs-searchbox input', function () {
            const input = $(this);
            const select = medicineSelectForSearchInput(input);
            if (!select.length) return;
            const query = String(input.val() || '').trim();
            window.clearTimeout(select.data('medicine-search-timer'));
            if (query.length < 2) {
                const nextRequest = Number(select.data('medicine-search-request') || 0) + 1;
                const previous = select.data('medicine-search-xhr');
                if (previous && previous.readyState !== 4) previous.abort();
                select.data('medicine-search-request', nextRequest);
                clearClinicalMedicineRemoteOptions(select);
                input.removeAttr('aria-busy');
                return;
            }
            select.data('medicine-search-timer', window.setTimeout(function () {
                searchClinicalMedicines(input, select, query);
            }, 280));
        });
    function bindClinicalMedicineSelect(select) {
        if (!select.length || typeof select.selectpicker !== 'function') return;
        const picker = select.data('selectpicker');
        if (picker && picker.$menu) picker.$menu.addClass('clinical-prescription-dropdown-menu').addClass('clinical-medicine-dropdown-menu');
        select.off('show.bs.select.clinicalPrescriptionMenu shown.bs.select.clinicalPrescriptionMenu')
            .on('show.bs.select.clinicalPrescriptionMenu shown.bs.select.clinicalPrescriptionMenu', function () {
                const activePicker = $(this).data('selectpicker');
                if (activePicker && activePicker.$menu) activePicker.$menu.addClass('clinical-prescription-dropdown-menu').addClass('clinical-medicine-dropdown-menu');
            });
    }
    function initializeClinicalMedicineRows(root) {
        $(root).find('select.btSelect:not(.clinical-medicine-select)').each(function () {
            $(this).find('option[value=""]').text('Choose...');
            $(this).attr('title', 'Choose...');
        });
        $(root).find('select.btSelect').each(function () {
            const select = $(this);
            if (typeof select.selectpicker !== 'function') return;
            if (select.hasClass('selectpicker')) {
                select.selectpicker('destroy');
            }
            const options = {liveSearch: true, container: 'body'};
            if (select.hasClass('clinical-medicine-select')) {
                options.noneSelectedText = 'Search item...';
                options.liveSearchPlaceholder = 'Type at least 2 characters';
            }
            select.selectpicker(options);
            if (!select.hasClass('clinical-medicine-select') && select.closest('.prescription-medicine-table').length) {
                const markOptionMenu = function () {
                    const picker = select.data('selectpicker');
                    if (picker && picker.$menu) picker.$menu.addClass('clinical-prescription-options-dropdown-menu');
                };
                markOptionMenu();
                select.off('show.bs.select.clinicalPrescriptionOptionsMenu shown.bs.select.clinicalPrescriptionOptionsMenu')
                    .on('show.bs.select.clinicalPrescriptionOptionsMenu shown.bs.select.clinicalPrescriptionOptionsMenu', markOptionMenu);
            }
        });
        $(root).find('.clinical-medicine-select').each(function () {
            const select = $(this);
            bindClinicalMedicineSelect(select);
            if (select.data('selected-medicine-id') === undefined) {
                select.data('selected-medicine-id', String(select.val() || ''));
            }
        });
    }
    initializeClinicalMedicineRows(document);
    let activeClinicalMedicineSelect = $();
    $(document).on('focusin change', '#clinical-prescription-items .clinical-medicine-select', function () {
        activeClinicalMedicineSelect = $(this);
    });
    function setClinicalMedicineAutofillField(row, fieldName, value) {
        const field = row.find('[name*="[' + fieldName + ']"]').first();
        if (!field.length) return;
        const normalized = value === null || value === undefined ? '' : String(value);
        if (field.is('select')) {
            if (typeof field.selectpicker === 'function') field.selectpicker('val', normalized);
            else field.val(normalized);
            if (typeof field.selectpicker === 'function') field.selectpicker('refresh');
            return;
        }
        field.val(normalized);
    }
    function clearClinicalMedicineAutofill(row) {
        ['duration', 'frequency', 'dosage', 'intake', 'notes'].forEach(function (fieldName) {
            setClinicalMedicineAutofillField(row, fieldName, '');
        });
    }
    function loadClinicalMedicineAutofill(select, medicineId) {
        const row = select.closest('.clinical-medicine-row');
        if (!row.length) return;
        const previousMedicineId = String(select.data('selected-medicine-id') || '');
        const currentMedicineId = String(medicineId || '');
        if (previousMedicineId === currentMedicineId) return;
        select.data('selected-medicine-id', currentMedicineId);
        const requestId = Number(select.data('medicine-autofill-request') || 0) + 1;
        const previousRequest = select.data('medicine-autofill-xhr');
        if (previousRequest && previousRequest.readyState !== 4) previousRequest.abort();
        select.data('medicine-autofill-request', requestId);
        if (!currentMedicineId) {
            clearClinicalMedicineAutofill(row);
            row.removeAttr('aria-busy');
            return;
        }
        clearClinicalMedicineAutofill(row);
        row.attr('aria-busy', 'true');
        const baseUrl = String(select.data('autofill-url') || '').replace(/\/$/, '');
        const xhr = ajaxRequest({
            url: baseUrl + '/' + encodeURIComponent(currentMedicineId) + '/autofill',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (requestId !== Number(select.data('medicine-autofill-request'))) return;
                const autofill = response && response.status && response.data ? response.data : null;
                if (!autofill) {
                    return;
                }
                setClinicalMedicineAutofillField(row, 'duration', autofill.prescription_duration_id);
                setClinicalMedicineAutofillField(row, 'frequency', autofill.prescription_frequency_id);
                setClinicalMedicineAutofillField(row, 'dosage', autofill.prescription_dose_id);
                setClinicalMedicineAutofillField(row, 'intake', autofill.prescription_intake_id);
                setClinicalMedicineAutofillField(row, 'notes', autofill.prescription_note);
            },
            complete: function () {
                if (requestId === Number(select.data('medicine-autofill-request'))) row.removeAttr('aria-busy');
            }
        });
        select.data('medicine-autofill-xhr', xhr);
    }
    $(document).off('change.clinicalMedicineAutofill', '#clinical-prescription-items .clinical-medicine-select')
        .on('change.clinicalMedicineAutofill', '#clinical-prescription-items .clinical-medicine-select', function () {
            const quantity = $(this).closest('.clinical-medicine-row').find('.clinical-prescription-quantity').first();
            if (quantity.length && String(quantity.val() || '').trim() === '') quantity.val('1');
            loadClinicalMedicineAutofill($(this), $(this).val());
        });
    const clinicalMedicineModal = $('#clinical-medicine-modal');
    const clinicalMedicineForm = $('#clinical-medicine-form');
    const clinicalMedicineSave = $('#clinical-medicine-save');
    let clinicalMedicineBusy = false;
    function resetClinicalMedicineForm() {
        if (!clinicalMedicineForm.length) return;
        clinicalMedicineForm[0].reset();
        clearFormErrors(clinicalMedicineForm);
        const statusSelect = clinicalMedicineForm.find('[name="status_id"]');
        statusSelect.val(statusSelect.find('option:not(:first)').first().val() || '');
        clinicalMedicineForm.find('select.btSelect').each(function () {
            const select = $(this);
            if (typeof select.selectpicker === 'function' && !select.hasClass('selectpicker')) select.selectpicker({liveSearch: true});
            if (typeof select.selectpicker === 'function') select.selectpicker('refresh');
        });
        clinicalMedicineForm.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
        clinicalMedicineModal.find('.modal-title').text('Add Medicine');
        clinicalMedicineSave.text('Create Medicine');
    }
    function refreshClinicalMedicineOptions(medicine) {
        const medicineId = String(medicine && medicine.id || '');
        if (!medicineId) return;
        const label = [medicine.medicine_name, medicine.strength].filter(Boolean).join(' · ') || 'Medicine ' + medicineId;
        $('#clinical-prescription-items .clinical-medicine-select').each(function () {
            const select = $(this);
            let option = select.find('option').filter(function () { return String($(this).val()) === medicineId; }).first();
            if (!option.length) {
                option = $('<option>', {value: medicineId, text: label, 'data-remote-medicine': 'true'}).appendTo(select);
            }
            if (typeof select.selectpicker === 'function') select.selectpicker('refresh');
        });
        if (!activeClinicalMedicineSelect.length || !$.contains(document, activeClinicalMedicineSelect[0])) {
            activeClinicalMedicineSelect = $('#clinical-prescription-items .clinical-medicine-select').filter(function () { return !$(this).val(); }).first();
        }
        if (activeClinicalMedicineSelect.length) {
            activeClinicalMedicineSelect.val(medicineId);
            if (typeof activeClinicalMedicineSelect.selectpicker === 'function') {
                activeClinicalMedicineSelect.selectpicker('val', medicineId);
                activeClinicalMedicineSelect.selectpicker('refresh');
            } else {
                activeClinicalMedicineSelect.trigger('change');
            }
        }
    }
    $(document).on('click', '#clinical-create-medicine', function () {
        if ($(this).prop('disabled')) return;
        resetClinicalMedicineForm();
        if (!activeClinicalMedicineSelect.length) {
            activeClinicalMedicineSelect = $('#clinical-prescription-items .clinical-medicine-select').filter(function () { return !$(this).val(); }).first();
        }
    });
    clinicalMedicineModal.on('shown.bs.modal', function () {
        clinicalMedicineForm.find('[name="medicine_name"]').trigger('focus');
    });
    clinicalMedicineForm.on('submit', function (event) {
        event.preventDefault();
        if (clinicalMedicineBusy) return;
        clearFormErrors(clinicalMedicineForm);
        clinicalMedicineBusy = true;
        clinicalMedicineSave.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: clinicalMedicineForm.attr('action'),
            method: 'POST',
            data: clinicalMedicineForm.serialize(),
            success: function (response) {
                if (!response || !response.status) {
                    bindFormError(clinicalMedicineForm, response && response.errors ? response.errors : {});
                    showError(response && response.msg ? response.msg : 'Please correct the highlighted fields.');
                    return;
                }
                if (!response.data || !response.data.id) {
                    showError('Medicine was created but could not be selected safely.');
                    return;
                }
                refreshClinicalMedicineOptions(response.data);
                clinicalMedicineModal.modal('hide');
                showSuccess(response.msg || 'Medicine created successfully.');
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                if (xhr.status === 422 && response.errors) {
                    bindFormError(clinicalMedicineForm, response.errors);
                    showError(response.msg || 'Please correct the highlighted fields.');
                    return;
                }
                showError(response.msg || 'Unable to create medicine safely.');
            },
            complete: function () {
                clinicalMedicineBusy = false;
                clinicalMedicineSave.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });
    function focusClinicalMedicineSearch(select) {
        if (!select.length) return;
        if (typeof select.selectpicker !== 'function') {
            select.trigger('focus');
            return;
        }
        const focusSearch = function () {
            const picker = select.data('selectpicker');
            const menu = picker && picker.$menu
                ? picker.$menu.filter('.clinical-prescription-dropdown-menu.show').first()
                : $();
            const search = menu.find('.bs-searchbox input[type="search"]:visible').first();
            if (!menu.length || !search.length) return false;
            search[0].focus({preventScroll: true});
            search[0].select();
            return true;
        };
        const previousAutofocusHandler = select.data('clinical-medicine-autofocus-handler');
        if (previousAutofocusHandler) select.off('shown.bs.select', previousAutofocusHandler);
        select.data('clinical-medicine-autofocus-handler', focusSearch)
            .one('shown.bs.select', focusSearch);
        const toggle = select.closest('.bootstrap-select').find('.dropdown-toggle').first();
        if (toggle.length && toggle.attr('aria-expanded') !== 'true') {
            toggle.trigger('click');
        } else if (!toggle.length) {
            select.selectpicker('toggle');
        }
        const deferFocus = function () {
            window.requestAnimationFrame(function () {
                if (!focusSearch()) window.setTimeout(focusSearch, 50);
            });
        };
        if (typeof window.requestAnimationFrame === 'function') deferFocus();
        else window.setTimeout(focusSearch, 0);
    }
    $(document).on('click', '.clinical-add-medicine-trigger', function () {
        const template = $('#clinical-medicine-row-template');
        const rows = $('#clinical-prescription-items .clinical-medicine-row');
        if (!template.length || !rows.length) return;
        const nextIndex = Math.max.apply(null, rows.map(function () { return Number($(this).data('row-index')) || 0; }).get()) + 1;
        const html = template.html().replace(/__INDEX__/g, String(nextIndex));
        const row = $(html).appendTo('#clinical-prescription-items');
        initializeClinicalMedicineRows(row);
        const newMedicineSelect = row.find('.clinical-medicine-select').first();
        // bootstrap-select builds its list on a zero-delay timer during init.  Refreshing
        // synchronously here races that build and can leave its internal element cache with
        // a non-DOM value, producing "appendChild ... parameter 1 is not of type Node".
        // Run the required refresh after the plugin's own build has completed, then open and
        // focus this row's detached menu.
        window.setTimeout(function () {
            if (!newMedicineSelect.length || !document.documentElement.contains(newMedicineSelect[0])) return;
            try {
                if (typeof newMedicineSelect.selectpicker === 'function') newMedicineSelect.selectpicker('refresh');
            } catch (error) {
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('Prescription medicine dropdown could not be refreshed safely.', error);
                }
                return;
            }
            focusClinicalMedicineSearch(newMedicineSelect);
        }, 0);
    });
    $(document).on('keydown.clinicalPrescription', function (event) {
        if (event.altKey && String(event.key || '').toLowerCase() === 'n') {
            const trigger = $('.clinical-add-medicine-trigger:enabled').first();
            if (trigger.length) {
                event.preventDefault();
                trigger.trigger('click');
            }
        }
    });
    $(document).on('click', '.clinical-remove-medicine', function () {
        const rows = $('#clinical-prescription-items .clinical-medicine-row');
        if (rows.length > 1) {
            $(this).closest('.clinical-medicine-row').remove();
            return;
        }
        const row = rows.first();
        row.find('select').val('').selectpicker('refresh');
        row.find('input:not([type="hidden"]), textarea').val('');
        row.find('.validation-error').empty();
        row.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
    });
    function bindWorkspaceErrors(form, errors) {
        bindFormError(form, errors || {});
        $.each(errors || {}, function (key, message) {
            if (String(key) === 'items') {
                form.find('.validation-error[data-error-for="items"], #itemsErr').first().text(message);
                return;
            }
            const match = String(key).match(/^items\.(\d+)\.([a-z_]+)$/);
            if (!match) return;
            const field = form.find('[name="items[' + match[1] + '][' + match[2] + ']"]');
            const error = form.find('.validation-error[data-error-for="' + key + '"]');
            if (field.length && error.length) {
                error.text(message);
                field.addClass('is-invalid').attr('aria-invalid', 'true').attr('aria-describedby', error.attr('id'));
            }
        });
    }

    $('#clinical-workspace-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const button = $('#clinical-save-workspace');
        if (!button.length || button.prop('disabled')) return;
        clearFormErrors(form);
        const prescriptionDuration = String(form.find('[name="medicine_duration"]').val() || '').trim();
        const prescriptionRows = form.find('#clinical-prescription-items .clinical-medicine-row');
        const nonEmptyPrescriptionRows = prescriptionRows.filter(function () {
            return $(this).find('select[name*="[medicine_id]"], select[name*="[duration]"], select[name*="[frequency]"], select[name*="[dosage]"], input[name*="[quantity]"]:not([type="hidden"]), textarea[name*="[notes]"]').filter(function () {
                return String($(this).val() || '').trim() !== '';
            }).length > 0;
        });
        const prescriptionErrors = {};
        const validPrescriptionDuration = /^\d+$/.test(prescriptionDuration)
            && Number.isSafeInteger(Number(prescriptionDuration))
            && Number(prescriptionDuration) >= 1
            && Number(prescriptionDuration) <= 3650;
        if (prescriptionDuration !== '' && !validPrescriptionDuration) {
            prescriptionErrors.medicine_duration = 'Medicine duration must be a positive whole number.';
        }
        if (nonEmptyPrescriptionRows.length && prescriptionDuration === '') {
            prescriptionErrors.medicine_duration = 'Medicine duration is required when medicines are added.';
        }
        if (!nonEmptyPrescriptionRows.length && prescriptionDuration !== '') {
            prescriptionErrors.items = 'Add at least one medicine or clear the duration.';
        }
        prescriptionRows.each(function () {
            const row = $(this);
            const rowHasData = row.find('select[name*="[medicine_id]"], select[name*="[duration]"], select[name*="[frequency]"], select[name*="[dosage]"], input[name*="[quantity]"]:not([type="hidden"]), textarea[name*="[notes]"]').filter(function () {
                return String($(this).val() || '').trim() !== '';
            }).length > 0;
            if (!rowHasData) return;
            const medicine = row.find('[name*="[medicine_id]"]').first();
            if (!String(medicine.val() || '').trim()) {
                prescriptionErrors['items.' + row.data('row-index') + '.medicine_id'] = 'Medicine is required.';
            }
            const quantity = row.find('.clinical-prescription-quantity').first();
            const quantityValue = String(quantity.val() || '').trim();
            if (quantityValue === '' || !/^\d+$/.test(quantityValue) || Number(quantityValue) < 1) {
                prescriptionErrors['items.' + row.data('row-index') + '.quantity'] = 'Quantity is required and must be at least 1.';
            }
        });
        if (Object.keys(prescriptionErrors).length) {
            bindWorkspaceErrors(form, prescriptionErrors);
            showError('Please correct the highlighted fields.');
            return;
        }
        const consultationStatus = String($('#clinical-consultation-status').val() || 'draft');
        $('#clinical-prescription-status').val(consultationStatus === 'completed' ? 'completed' : 'draft');
        $('#clinical-prescription-present').val(nonEmptyPrescriptionRows.length ? '1' : '0');
        button.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: form.attr('action') || '<?= base_url('doctor/clinical/save') ?>',
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    $('#clinical-feedback').text(response.msg);
                    showSuccess(response.msg);
                    if (String(form.attr('action') || '').indexOf('/clinical/amend') !== -1) {
                        reload_page(500);
                    }
                    return;
                }
                bindWorkspaceErrors(form, response.errors || {});
                showError(response.msg || 'Please correct the highlighted fields.');
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                if (response.errors) bindWorkspaceErrors(form, response.errors);
                handleFormAjaxError(form, xhr, 'Consultation could not be saved safely.');
            },
            complete: function () {
                button.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
