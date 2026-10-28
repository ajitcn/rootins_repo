<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$doctorCanViewPatients = in_array('patient.view', session('permissions') ?? [], true);
$doctorCanViewClinical = in_array('consultation.view', session('permissions') ?? [], true);
$formatPrescriptionDateTime = static function ($value): string {
    $value = trim((string) $value);
    $timestamp = $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? 'â€”' : date('d-m-Y h:i A', $timestamp);
};
$requestedPrescriptionView = (string) ($filters['view'] ?? 'list');
$prescriptionView = in_array($requestedPrescriptionView, ['list', 'grid'], true) ? $requestedPrescriptionView : 'list';
$cartSyncLabels = ['not_synced' => 'Not synced', 'syncing' => 'Syncing', 'synced' => 'Synced', 'already_in_cart' => 'Already in cart', 'sync_failed' => 'Sync failed', 'ordered' => 'Ordered', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];
$prescriptionContext = static function (array $item) use ($cartSyncLabels): array {
    $prescriptionId = max(0, (int) ($item['id'] ?? 0));
    $patientId = max(0, (int) ($item['patient_id'] ?? 0));
    $patientName = trim((string) ($item['patient_first_name'] ?? '') . ' ' . (string) ($item['patient_last_name'] ?? '')) ?: 'Unknown patient';
    $patientReference = trim((string) ($item['registration_number'] ?? '')) ?: ($patientId > 0 ? '#' . $patientId : '—');
    $rawDate = trim((string) ($item['prescription_date'] ?? ''));
    $date = '—';

    if ($rawDate !== '') {
        $timestamp = strtotime($rawDate);
        $date = $timestamp !== false ? date('d-m-Y', $timestamp) : '—';
    }
    $status = trim((string) ($item['status'] ?? '')) ?: 'unknown';
    $cartSyncStatus = in_array(($item['cart_sync_status'] ?? ''), ['not_synced', 'syncing', 'synced', 'already_in_cart', 'sync_failed', 'ordered', 'paid', 'expired', 'cancelled'], true) ? (string) $item['cart_sync_status'] : 'not_synced';
    $cartSyncStatusLabel = ucwords(str_replace('_', ' ', $cartSyncStatus));
    $cartSyncStatusClass = match ($cartSyncStatus) { 'syncing' => 'syncing', 'synced' => 'synced', 'already_in_cart' => 'already-in-cart', 'sync_failed' => 'sync-failed', 'ordered' => 'ordered', 'paid' => 'paid', 'expired' => 'expired', 'cancelled' => 'cancelled', default => 'not-synced' };

    return [
        'prescriptionId' => $prescriptionId,
        'patientId' => $patientId,
        'patientName' => $patientName,
        'patientReference' => $patientReference,
        'mobile' => trim((string) ($item['patient_mobile_display'] ?? '')) ?: 'Mobile not recorded',
        'initial' => strtoupper(substr($patientName, 0, 1)),
        'prescriptionNumber' => trim((string) ($item['prescription_number'] ?? '')) ?: 'Prescription',
        'date' => $date,
        'medicineCount' => max(0, (int) ($item['medicine_count'] ?? 0)),
        'statusClass' => preg_replace('/[^a-z0-9-]+/', '-', strtolower(str_replace('_', '-', $status))) ?: 'unknown',
        'statusLabel' => ucwords(str_replace('_', ' ', $status)),
        'cartSyncStatus' => $cartSyncStatus,
        'cartSyncStatusLabel' => $cartSyncLabels[$cartSyncStatus] ?? 'Not synced',
        'cartSyncStatusClass' => $cartSyncStatusClass,
        'detailId' => 'doctor-prescription-details-' . $prescriptionId,
        'createdAt' => trim((string) ($item['created_at'] ?? '')) ?: '—',
        'updatedAt' => trim((string) ($item['updated_at'] ?? '')) ?: '—',
    ];
};
?>
<main class="dashboard-container doctor-prescriptions-page" aria-labelledby="doctor-prescriptions-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Clinical records</div>
            <h1 id="doctor-prescriptions-title">Prescriptions</h1>
            <p class="text-muted mb-0">Your authored prescriptions and their review handoff status.</p>
        </div>
        <?php if ($doctorCanViewClinical): ?>
            <a class="btn btn-primary" href="<?= base_url('doctor/clinical') ?>">
                <i class="bi bi-plus-circle mr-1" aria-hidden="true"></i>Create prescription
            </a>
        <?php endif; ?>
    </div>

    <section class="doctor-prescription-toolbar-card" aria-labelledby="doctor-prescription-toolbar-title">
        <h2 class="sr-only" id="doctor-prescription-toolbar-title">Prescription search and filters</h2>
        <form method="get" action="<?= esc(current_url()) ?>" class="doctor-prescription-toolbar-form" role="search">
            <div class="doctor-prescription-search">
                <label for="doctor-prescription-search">Search prescriptions</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span></div>
                    <input type="search" id="doctor-prescription-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control" placeholder="Search patient, mobile, prescription number, or status" autocomplete="off">
                </div>
            </div>
            <div class="doctor-prescription-toolbar-actions">
                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#doctor-prescription-filter-modal" aria-controls="doctor-prescription-filter-modal">
                    <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filters
                </button>
                <a class="btn btn-light" href="<?= esc(current_url()) ?>"><i class="fa-solid fa-rotate-left mr-1" aria-hidden="true"></i>Clear</a>
                <div class="btn-group doctor-prescription-view-toggle" role="group" aria-label="Prescription view">
                    <button type="button" class="btn btn-outline-secondary <?= $prescriptionView === 'list' ? 'active' : '' ?>" data-prescription-view="list" aria-pressed="<?= $prescriptionView === 'list' ? 'true' : 'false' ?>" title="List View">
                        <i class="fa-solid fa-list" aria-hidden="true"></i><span class="d-none d-sm-inline ml-1">List</span><span class="sr-only"> List View</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary <?= $prescriptionView === 'grid' ? 'active' : '' ?>" data-prescription-view="grid" aria-pressed="<?= $prescriptionView === 'grid' ? 'true' : 'false' ?>" title="Grid View">
                        <i class="fa-solid fa-grip" aria-hidden="true"></i><span class="d-none d-sm-inline ml-1">Grid</span><span class="sr-only"> Grid View</span>
                    </button>
                </div>
            </div>
            <div class="doctor-prescription-page-size">
                <label for="doctor-prescription-page-size">Rows</label>
                <select class="custom-select custom-select-sm" id="doctor-prescription-page-size" name="page_size" onchange="this.form.submit()">
                    <?php foreach ([10, 15, 25, 50] as $size): ?>
                        <option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="status" value="<?= esc($filters['status'] ?? '') ?>">
            <input type="hidden" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>">
            <input type="hidden" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>">
            <input type="hidden" name="view" value="<?= esc($prescriptionView) ?>" data-prescription-view-input>
        </form>
    </section>

    <?php if (($filters['filter_errors'] ?? []) !== []): ?>
        <div class="alert alert-warning doctor-prescription-filter-alert" role="alert">
            <?= esc(implode(' ', $filters['filter_errors'])) ?>
        </div>
    <?php endif; ?>

    <div class="doctor-prescription-results">
    <section class="card doctor-prescription-register-card" aria-labelledby="doctor-prescription-register-title">
        <div class="card-header doctor-prescription-register-header">
            <div>
                <h2 class="h5 mb-1" id="doctor-prescription-register-title">Prescription register</h2>
                <small class="text-muted" role="status" aria-live="polite"><?= (int) ($pagination['total'] ?? count($prescriptions)) ?> prescription(s) in scope</small>
            </div>
        </div>
        <div class="doctor-prescription-records" data-prescription-view-container data-view="<?= esc($prescriptionView) ?>" role="list" aria-label="Prescriptions">
            <?php if ($prescriptions !== []): ?>
                <?= view(DOCTOR_VIEW . 'pages/prescriptions_' . $prescriptionView, ['prescriptions' => $prescriptions, 'prescriptionContext' => $prescriptionContext, 'doctorCanViewPatients' => $doctorCanViewPatients, 'doctorCanViewClinical' => $doctorCanViewClinical]) ?>
            <?php else: ?>
                <div class="empty-state doctor-prescription-empty-state" role="status" aria-live="polite">
                    <i class="bi bi-prescription2 d-block h3 mb-2" aria-hidden="true"></i>
                    <div>No prescriptions found</div>
                    <small>Completed consultations can receive a prescription from the clinical workspace.</small>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]) ?>
    </div>

    <div class="modal fade ri-scroll-modal" id="doctor-prescription-filter-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-prescription-filter-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-prescription-filter-title">Filter prescriptions</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close filters"><span aria-hidden="true">&times;</span></button>
                </div>
                <form method="get" action="<?= esc(current_url()) ?>">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="doctor-prescription-filter-status">Status</label>
                                <select class="form-control btSelect" id="doctor-prescription-filter-status" name="status" title="All statuses">
                                    <option value="">All statuses</option>
                                    <?php foreach ($statusOptions ?? [] as $option): ?>
                                        <option value="<?= esc($option['code']) ?>" <?= ($filters['status'] ?? '') === $option['code'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="doctor-prescription-filter-from">Prescription date from</label>
                                <input type="text" class="form-control datepicker" id="doctor-prescription-filter-from" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="doctor-prescription-filter-to">Prescription date to</label>
                                <input type="text" class="form-control datepicker" id="doctor-prescription-filter-to" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off">
                            </div>
                        </div>
                        <input type="hidden" name="search" value="<?= esc($filters['search'] ?? '') ?>">
                        <input type="hidden" name="page_size" value="<?= (int) ($filters['page_size'] ?? 15) ?>">
                        <input type="hidden" name="view" value="<?= esc($prescriptionView) ?>" data-prescription-view-input>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-outline-secondary mr-auto" href="<?= esc(current_url()) ?>">Clear</a>
                        <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Apply filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php foreach ($prescriptions as $item): ?>
    <?php $prescriptionId = (int) $item['id']; ?>
    <?php $detailId = 'doctor-prescription-details-' . $prescriptionId; ?>
    <?php $printId = $detailId . '-print'; ?>
    <?php $patientName = trim(($item['patient_first_name'] ?? '') . ' ' . ($item['patient_last_name'] ?? '')); ?>
    <?php $patientName = $patientName !== '' ? $patientName : '—'; ?>
    <?php $patientReference = trim((string) ($item['registration_number'] ?? '')); ?>
    <?php $patientReference = $patientReference !== '' ? $patientReference : '#' . (int) ($item['patient_id'] ?? 0); ?>
    <?php $prescriptionStatus = trim((string) ($item['status'] ?? '')) ?: 'unknown'; ?>
    <?php $prescriptionStatusLabel = ucwords(str_replace('_', ' ', $prescriptionStatus)); ?>
    <?php $prescriptionStatusClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower(str_replace('_', '-', $prescriptionStatus))) ?: 'unknown'; ?>
    <?php $cartSyncStatus = in_array(($item['cart_sync_status'] ?? ''), ['not_synced', 'syncing', 'synced', 'already_in_cart', 'sync_failed', 'ordered', 'paid', 'expired', 'cancelled'], true) ? (string) $item['cart_sync_status'] : 'not_synced'; ?>
    <?php $cartSyncStatusLabel = $cartSyncLabels[$cartSyncStatus] ?? 'Not synced'; ?>
    <?php $cartSyncStatusClass = match ($cartSyncStatus) { 'syncing' => 'syncing', 'synced' => 'synced', 'already_in_cart' => 'already-in-cart', 'sync_failed' => 'sync-failed', 'ordered' => 'ordered', 'paid' => 'paid', 'expired' => 'expired', 'cancelled' => 'cancelled', default => 'not-synced' }; ?>
    <?php $prescriptionNotes = trim((string) ($item['notes'] ?? '')); ?>
    <?php $followUpDate = trim((string) ($item['follow_up_date'] ?? '')); ?>
    <?php $followUpTime = trim((string) ($item['follow_up_time'] ?? '')); ?>
    <?php $followUpTimestamp = $followUpTime !== '' ? strtotime($followUpTime) : false; ?>
    <?php $followUpInformation = trim(implode(' · ', array_filter([
        $followUpDate !== '' ? $formatPrescriptionDateTime($followUpDate) : '',
        $followUpTimestamp !== false ? date('h:i A', $followUpTimestamp) : '',
        trim((string) ($item['follow_up_type_name'] ?? $item['follow_up_type'] ?? '')),
        trim((string) ($item['follow_up_remarks'] ?? '')),
    ]))); ?>
    <?php $prescriptionDate = trim((string) ($item['prescription_date'] ?? '')); ?>
    <?php $displayPrescriptionDate = $prescriptionDate !== '' ? date('d-m-Y', strtotime($prescriptionDate)) : '—'; ?>
    <?php $medicineDuration = ''; ?>
    <?php foreach (($items[$prescriptionId] ?? []) as $line): ?>
        <?php $lineDuration = trim((string) ($line['duration'] ?? '') . ' ' . (string) ($line['duration_unit'] ?? '')); ?>
        <?php if ($lineDuration !== ''): ?>
            <?php $medicineDuration = $lineDuration; ?>
            <?php break; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <div
        class="modal fade ri-scroll-modal doctor-prescription-print-modal doctor-prescription-detail-modal"
        id="<?= esc($detailId) ?>"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        aria-labelledby="<?= esc($detailId) ?>-title"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="doctor-prescription-modal-heading">
                        <h2 class="modal-title h5" id="<?= esc($detailId) ?>-title">
                            Prescription <?= esc($item['prescription_number']) ?>
                        </h2>
                        <div class="doctor-prescription-modal-context" aria-label="Prescription summary">
                            <span><strong>Patient:</strong> <?= esc($patientName) ?></span>
                            <span><strong>Patient number:</strong> <?= esc($patientReference) ?></span>
                            <span><strong>Date:</strong> <?= esc($displayPrescriptionDate) ?></span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close prescription details"
                        data-modal-autofocus
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" role="document">
                    <div class="doctor-prescription-print-area" id="<?= esc($printId) ?>">
                        <?php if (trim((string) ($item['clinic_name'] ?? '')) !== '' || trim((string) ($item['clinic_address'] ?? '')) !== '' || trim((string) ($item['clinic_mobile'] ?? '')) !== ''): ?>
                            <div class="doctor-prescription-clinic-header">
                                <div class="doctor-prescription-brand" aria-label="ROOTINS">ROOTINS<span>Healthcare and Pharmacy</span></div>
                                <?php if (trim((string) ($item['clinic_logo'] ?? '')) !== ''): ?>
                                    <img src="<?= esc(base_url((string) $item['clinic_logo'])) ?>" alt="<?= esc($item['clinic_name'] ?? 'Clinic') ?> logo" class="doctor-prescription-clinic-logo">
                                <?php endif; ?>
                                <div>
                                    <?php if (trim((string) ($item['clinic_name'] ?? '')) !== ''): ?><strong><?= esc($item['clinic_name']) ?></strong><?php endif; ?>
                                    <?php if (trim((string) ($item['clinic_address'] ?? '')) !== ''): ?><div><?= nl2br(esc($item['clinic_address'])) ?></div><?php endif; ?>
                                    <?php if (trim((string) ($item['clinic_mobile'] ?? '')) !== ''): ?><div><?= esc($item['clinic_mobile']) ?></div><?php endif; ?>
                                    <?php if (trim((string) ($item['clinic_email'] ?? '')) !== ''): ?><div><?= esc($item['clinic_email']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="doctor-prescription-print-heading">
                            <div>
                                <span class="doctor-prescription-print-eyebrow">Prescription</span>
                                <h3 class="h4 mb-0"><?= esc($item['prescription_number']) ?></h3>
                            </div>
                            <span class="doctor-prescription-print-date">Prescription date: <?= esc($displayPrescriptionDate) ?></span>
                        </div>

                        <h3 class="doctor-prescription-section-title">Patient Information</h3>
                        <div class="doctor-prescription-patient-card mb-3">
                            <dl class="doctor-prescription-meta mb-0">
                                <div class="doctor-prescription-meta-item">
                                    <dt>Name</dt>
                                    <dd><?= esc($patientName) ?></dd>
                                </div>
                                <div class="doctor-prescription-meta-item">
                                    <dt>Age</dt>
                                    <dd><?= esc($item['patient_age'] ?? '—') ?></dd>
                                </div>
                                <div class="doctor-prescription-meta-item">
                                    <dt>Patient number</dt>
                                    <dd><?= esc($patientReference) ?></dd>
                                </div>
                                <div class="doctor-prescription-meta-item">
                                    <dt>Mobile</dt>
                                    <dd><?= esc($item['patient_mobile_display'] ?? '—') ?></dd>
                                </div>
                            </dl>
                        </div>

                        <h3 class="doctor-prescription-section-title">Prescription Details</h3>
                        <dl class="doctor-prescription-meta doctor-prescription-detail-grid mb-3">
                            <div class="doctor-prescription-meta-item">
                                <dt>Prescription number</dt>
                                <dd><?= esc($item['prescription_number'] ?? 'â€”') ?></dd>
                            </div>
                            <div class="doctor-prescription-meta-item">
                                <dt>Status</dt>
                                <dd><span class="badge doctor-prescription-status-badge doctor-prescription-status-<?= esc($prescriptionStatusClass) ?>"><?= esc($prescriptionStatusLabel) ?></span></dd>
                            </div>
                            <div class="doctor-prescription-meta-item">
                                <dt class="doctor-prescription-screen-only">Cart Sync</dt>
                                <dd class="doctor-prescription-screen-only"><span class="badge doctor-prescription-cart-sync-badge doctor-prescription-cart-sync-<?= esc($cartSyncStatusClass) ?>"><?= esc($cartSyncStatusLabel) ?></span><?php if (!empty($item['cart_sync_failure_reason'])): ?><small class="d-block text-danger"><?= esc($item['cart_sync_failure_reason']) ?></small><?php endif; ?></dd>
                            </div>
                            <div class="doctor-prescription-meta-item">
                                <dt>Valid until</dt>
                                <dd><?= esc($formatPrescriptionDateTime($item['valid_until'] ?? '')) ?></dd>
                            </div>
                            <div class="doctor-prescription-meta-item">
                                <dt>Created</dt>
                                <dd><?= esc($formatPrescriptionDateTime($item['created_at'] ?? '')) ?></dd>
                            </div>
                            <div class="doctor-prescription-meta-item">
                                <dt>Last updated</dt>
                                <dd><?= esc($formatPrescriptionDateTime($item['updated_at'] ?? '')) ?></dd>
                            </div>
                        </dl>

                        <h3 class="doctor-prescription-section-title">Doctor Details</h3>
                        <div class="doctor-prescription-patient-card mb-3">
                            <strong>Authoring doctor</strong>
                            <div class="text-muted small mt-1"><?= esc(trim((string) ($item['doctor_name'] ?? '')) ?: 'This prescription is in your clinic and doctor scope.') ?></div>
                        </div>

                        <h3 class="doctor-prescription-section-title">Doctor Instructions</h3>
                        <div class="doctor-prescription-empty-section mb-2" role="status"><?= esc(trim((string) ($item['instructions'] ?? '')) ?: 'No doctor instructions recorded.') ?></div>

                        <h3 class="doctor-prescription-section-title">Notes</h3>
                        <div class="doctor-prescription-empty-section mb-2" role="status"><?= esc($prescriptionNotes !== '' ? $prescriptionNotes : 'No prescription notes recorded.') ?></div>

                        <h3 class="doctor-prescription-section-title">Follow-up Information</h3>
                        <div class="doctor-prescription-empty-section mb-2" role="status"><?= esc($followUpInformation !== '' ? $followUpInformation : 'No follow-up information recorded.') ?></div>

                        <h3 class="doctor-prescription-section-title">Prescribed Medicines<?= $medicineDuration !== '' ? ' (for ' . esc($medicineDuration) . ')' : '' ?></h3>
                        <div class="table-responsive doctor-prescription-table-wrap">
                            <table class="table table-bordered doctor-prescription-clinical-table mb-0">
                                <caption class="sr-only">Clinical medicine items for prescription <?= esc($item['prescription_number']) ?></caption>
                                <thead><tr><th>No.</th><th>Medicine name</th><th>Strength / form</th><th>Dose</th><th>Route</th><th>Frequency</th><th>Duration</th><th>Quantity</th><th>Instructions</th></tr></thead>
                                <tbody>
                                <?php if (($items[$prescriptionId] ?? []) === []): ?>
                                    <tr><td colspan="9" class="text-muted">No prescription medicines.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($items[$prescriptionId] as $lineNumber => $line): ?>
                                        <?php $duration = trim((string) ($line['duration'] ?? '') . ' ' . (string) ($line['duration_unit'] ?? '')); ?>
                                        <tr>
                                            <td><?= (int) $lineNumber + 1 ?></td>
                                            <td><strong><?= esc($line['medicine_catalog_name'] ?: ($line['medicine_name'] ?? '')) ?></strong></td>
                                            <td><?= esc(trim((string) ($line['strength'] ?? $line['medicine_strength'] ?? '') . ' ' . (string) ($line['dosage_form'] ?? $line['medicine_form'] ?? ($line['form'] ?? '')))) ?></td>
                                            <td><?= esc($line['dosage'] ?? '') ?></td>
                                            <td><?= esc($line['route'] ?? '') ?></td>
                                            <td><?= esc($line['frequency'] ?? '') ?></td>
                                            <td><?= esc($duration) ?></td>
                                            <td><?= esc($line['quantity'] ?? '') ?></td>
                                            <td><?= nl2br(esc($line['instructions'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive doctor-prescription-table-wrap">
                            <table class="table table-bordered table-sm doctor-prescription-table doctor-prescription-legacy-table mb-0" aria-hidden="true">
                                <caption class="sr-only">Medicine items for prescription <?= esc($item['prescription_number']) ?></caption>
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Medicine</th>
                                        <th scope="col">Duration</th>
                                        <th scope="col">Frequency</th>
                                        <th scope="col">Dose</th>
                                        <th scope="col">Intake</th>
                                        <th scope="col">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (($items[$prescriptionId] ?? []) === []): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted">No prescription medicines.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items[$prescriptionId] as $lineNumber => $line): ?>
                                            <?php $duration = trim((string) ($line['duration'] ?? '') . ' ' . (string) ($line['duration_unit'] ?? '')); ?>
                                            <tr>
                                                <td data-label="#"><?= esc((string) ($lineNumber + 1)) ?></td>
                                                <td data-label="Medicine"><?= esc($line['medicine_catalog_name'] ?: ($line['medicine_name'] ?? '—')) ?></td>
                                                <td data-label="Duration"><?= esc($duration !== '' ? $duration : '—') ?></td>
                                                <td data-label="Frequency"><?= esc($line['frequency'] ?? '—') ?></td>
                                                <td data-label="Dose"><?= esc($line['dosage'] ?? '—') ?></td>
                                                <td data-label="Intake"><?= esc($line['instructions'] ?? '—') ?></td>
                                                <td data-label="Note"><?= nl2br(esc($line['notes'] ?? '—')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="doctor-prescription-section-title">Attachments</h3>
                        <div class="doctor-prescription-empty-section mb-2" role="status">No attachments recorded for this prescription.</div>
                        <div class="doctor-prescription-print-footer">
                            <div class="doctor-prescription-signature"><span></span><strong>Doctor signature</strong><small>Digitally generated prescription</small></div>
                            <div class="doctor-prescription-confidential">Confidential medical document<br><?= esc($item['prescription_number'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                    <button
                        class="btn btn-outline-primary doctor-prescription-print-trigger"
                        type="button"
                        data-prescription-id="<?= esc($prescriptionId) ?>"
                        data-print-target="<?= esc($printId) ?>"
                        aria-label="Print prescription <?= esc($item['prescription_number']) ?>"
                        title="Print prescription"
                    >
                        <i class="bi bi-printer mr-1" aria-hidden="true"></i>
                        Print
                    </button>
                    <?php if ($prescriptionStatus === 'completed'): ?>
                        <a class="btn btn-primary doctor-prescription-pdf-download" href="<?= esc(base_url('doctor/prescriptions/pdf/' . $prescriptionId)) ?>" target="_blank" rel="noopener" aria-label="Download PDF for <?= esc($item['prescription_number']) ?>">
                            <i class="bi bi-file-earmark-pdf mr-1" aria-hidden="true"></i>Download PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();

        $('.ri-scroll-modal').on('shown.bs.modal', function () {
            if (window.RootinsModal) {
                window.RootinsModal.focus(this);
            }
        });

        $(document)
            .off('show.bs.modal.doctorPrescriptionDetails', '.doctor-prescription-detail-modal')
            .on('show.bs.modal.doctorPrescriptionDetails', '.doctor-prescription-detail-modal', function () {
                $('.modal.show').not(this).modal('hide');
                $(this).attr('aria-busy', 'false');
            })
            .off('hidden.bs.modal.doctorPrescriptionDetails', '.doctor-prescription-detail-modal')
            .on('hidden.bs.modal.doctorPrescriptionDetails', '.doctor-prescription-detail-modal', function () {
                $(this).removeAttr('aria-busy');
                if (!$('.modal.show').length) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open rootins-modal-lock').css('padding-right', '');
                }
            });

        $(document).on('click.doctorPrescriptionListPrint', '.doctor-prescription-list-print', function () {
            const target = this.getAttribute('data-print-modal');
            const modal = target ? $(target) : $();
            if (!modal.length) {
                return;
            }
            modal.one('shown.bs.modal.doctorPrescriptionListPrint', function () {
                modal.find('.doctor-prescription-print-trigger').trigger('click');
            });
            modal.modal('show');
        });

        $(document).on('click.doctorPrescriptionAction', '.doctor-prescription-action-menu .dropdown-item', function () {
            const toggle = $(this).closest('.dropdown').find('[data-toggle="dropdown"]').first();
            if (toggle.length) {
                toggle.dropdown('hide');
            }
        });

        let prescriptionPrintInProgress = false;

        const prescriptionPrintDiagnostics = function (details) {
            if (window.console && typeof window.console.info === 'function') {
                window.console.info('[prescription-print]', details);
            }
        };

        const waitForPrintDocumentAssets = function (printDocument) {
            const images = Array.from(printDocument.images || []);
            const imagePromises = images.map(function (image) {
                if (image.complete) {
                    return Promise.resolve();
                }
                return new Promise(function (resolve) {
                    image.addEventListener('load', resolve, {once: true});
                    image.addEventListener('error', resolve, {once: true});
                });
            });
            const fontsReady = printDocument.fonts && printDocument.fonts.ready
                ? printDocument.fonts.ready.catch(function () { return undefined; })
                : Promise.resolve();

            return Promise.all([Promise.all(imagePromises), fontsReady]).then(function () {
                return new Promise(function (resolve) {
                    printDocument.defaultView.requestAnimationFrame(function () {
                        printDocument.defaultView.requestAnimationFrame(resolve);
                    });
                });
            });
        };

        const buildPrescriptionPrintDocument = function (printableContent, prescriptionId) {
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || window.location.origin + '/';
            const styleUrl = new URL('assets/css/style.css?v=20261019-prescription-print1', baseUrl).href;
            const printStyleUrl = new URL('assets/css/print.css?v=20261016-rootins-print1', baseUrl).href;
            const responsiveStyleUrl = new URL('assets/css/responsive-tables.css?v=20261019-native-tables6', baseUrl).href;
            const title = 'Prescription ' + String(prescriptionId || '');

            return '<!doctype html><html lang="en"><head>'
                + '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                + '<title>' + title.replace(/[&<>"']/g, function (character) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[character]; }) + '</title>'
                + '<link rel="stylesheet" href="' + printStyleUrl + '">'
                + '<link rel="stylesheet" href="' + styleUrl + '">'
                + '<link rel="stylesheet" href="' + responsiveStyleUrl + '">'
                + '<style>html,body{margin:0;padding:0;background:#fff;color:#20242b}body{font-family:Arial,sans-serif}.doctor-prescription-print-area{display:block!important;visibility:visible!important;opacity:1!important;width:auto!important;max-width:none!important;margin:0!important;padding:0!important;background:#fff!important}.doctor-prescription-print-area .doctor-prescription-table-wrap{overflow:visible!important}.doctor-prescription-print-area .doctor-prescription-table{display:table!important;width:100%!important;border-collapse:collapse}.doctor-prescription-print-area .doctor-prescription-table thead{display:table-header-group!important}.doctor-prescription-print-area .doctor-prescription-table tbody{display:table-row-group!important}.doctor-prescription-print-area .doctor-prescription-table tr{display:table-row!important;break-inside:avoid;page-break-inside:avoid}.doctor-prescription-print-area .doctor-prescription-table th,.doctor-prescription-print-area .doctor-prescription-table td{display:table-cell!important;overflow-wrap:anywhere}.doctor-prescription-section-title,.doctor-prescription-patient-card,.doctor-prescription-print-heading,.doctor-prescription-clinic-header{break-inside:avoid;page-break-inside:avoid}@page{size:A4;margin:8mm}</style>'
                + '</head><body class="doctor-prescription-printing">'
                + '<main id="doctor-prescription-print-document" class="doctor-prescription-print-area is-print-target">'
                + printableContent.innerHTML
                + '</main></body></html>';
        };

        $(document).on('click.doctorPrescriptionPrint', '.doctor-prescription-print-trigger', function () {
            const button = this;
            const modal = $(button).closest('.doctor-prescription-print-modal');
            const printId = button.getAttribute('data-print-target');
            const prescriptionId = button.getAttribute('data-prescription-id') || '';
            const source = document.getElementById(printId);

            if (!modal.length || !source || prescriptionPrintInProgress) {
                if (!source) {
                    showError('Prescription could not be prepared for printing.');
                }
                return;
            }

            const printableContent = source.cloneNode(true);
            if (!printableContent || !printableContent.textContent.trim()) {
                showError('Prescription data is not available for printing.');
                return;
            }

            printableContent.removeAttribute('id');
            printableContent.querySelectorAll('[id]').forEach(function (element) {
                element.removeAttribute('id');
            });

            prescriptionPrintInProgress = true;
            $(button).prop('disabled', true).attr('aria-busy', 'true');
            const frame = document.createElement('iframe');
            frame.id = 'doctor-prescription-print-frame';
            frame.title = 'Prescription print preview';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.position = 'fixed';
            frame.style.width = '1px';
            frame.style.height = '1px';
            frame.style.right = '0';
            frame.style.bottom = '0';
            frame.style.border = '0';
            frame.style.opacity = '0';

            let cleaned = false;
            const cleanup = function () {
                if (cleaned) return;
                cleaned = true;
                window.clearTimeout(frame._printTimeout);
                frame.remove();
                $(button).prop('disabled', false).removeAttr('aria-busy');
                prescriptionPrintInProgress = false;
            };
            const fail = function (message) {
                cleanup();
                showError(message);
            };

            document.body.appendChild(frame);
            const printDocument = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
            prescriptionPrintDiagnostics({
                prescriptionId: prescriptionId,
                responseStatus: source ? 'loaded' : 'missing',
                renderedHtmlLength: printableContent.innerHTML.length,
                printFrameCreated: Boolean(frame.parentNode && printDocument)
            });

            if (!printDocument || !frame.contentWindow) {
                fail('The print preview could not be opened. Please allow print previews and try again.');
                return;
            }

            frame.onload = function () {
                waitForPrintDocumentAssets(printDocument).then(function () {
                    if (cleaned || !printDocument.body || !printDocument.body.textContent.trim()) {
                        fail('Prescription data could not be rendered for printing.');
                        return;
                    }
                    prescriptionPrintDiagnostics({
                        prescriptionId: prescriptionId,
                        responseStatus: 'loaded',
                        renderedHtmlLength: printDocument.body.innerHTML.length,
                        printFrameCreated: true
                    });
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                    frame._printTimeout = window.setTimeout(cleanup, 5000);
                }).catch(function () {
                    fail('Prescription could not be prepared for printing.');
                });
            };

            try {
                printDocument.open();
                printDocument.write(buildPrescriptionPrintDocument(printableContent, prescriptionId));
                printDocument.close();
            } catch (error) {
                prescriptionPrintDiagnostics({
                    prescriptionId: prescriptionId,
                    responseStatus: 'render-failed',
                    renderedHtmlLength: printableContent.innerHTML.length,
                    printFrameCreated: true
                });
                fail('The prescription print document could not be created.');
            }
        });

        $('.doctor-prescription-print-modal').on('hidden.bs.modal', function () {
            document.body.classList.remove('doctor-prescription-printing');
        });

        let prescriptionViewRequest = null;

        $(document).on('click.doctorPrescriptionView', '[data-prescription-view]', function (event) {
            event.preventDefault();
            const view = this.getAttribute('data-prescription-view');
            if (view !== 'list' && view !== 'grid') {
                return;
            }
            const form = $('.doctor-prescription-toolbar-form').first();
            const currentView = $('[data-prescription-view-container]').attr('data-view') || 'list';
            if (!form.length || (view === currentView && !prescriptionViewRequest)) return;
            if (prescriptionViewRequest && prescriptionViewRequest.readyState !== 4) prescriptionViewRequest.abort();
            const url = new URL(form.attr('action'), window.location.href);
            const params = new URLSearchParams(form.serialize());
            params.set('view', view);
            url.search = params.toString();
            $('[data-prescription-view-container]').attr('aria-busy', 'true');
            $('[data-prescription-view]').each(function () {
                const isActive = this.getAttribute('data-prescription-view') === view;
                this.classList.toggle('active', isActive);
                this.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            $('[data-prescription-view-input]').val(view);
            $('[data-prescription-view]').prop('disabled', true);
            prescriptionViewRequest = ajaxRequest({
                url: url.toString(), method: 'GET', dataType: 'html', timeout: 30000,
                success: function (html) {
                    const parsed = $('<div>').html(html);
                    const nextResults = parsed.find('.doctor-prescription-results').first();
                    const nextRecords = nextResults.find('[data-prescription-view-container]').first();
                    const nextPagination = nextResults.find('.card-footer').first();
                    const nextEmptyState = nextResults.find('.doctor-prescription-empty-state').first();
                    if (!nextResults.length || (!nextRecords.length && !nextEmptyState.length) || !nextPagination.length) {
                        showError('Prescription view could not be loaded safely.');
                        return;
                    }
                    $('.doctor-prescription-results').replaceWith(nextResults);
                    $('[data-toggle="tooltip"]').tooltip();
                    $('[data-prescription-view-input]').val(view);
                    window.history.pushState({}, '', url.toString());
                },
                error: function (xhr, status) {
                    if (status === 'abort') return;
                    showError(status === 'timeout' ? 'Prescription view loading timed out.' : 'Prescription view could not be loaded.');
                },
                complete: function () {
                    $('[data-prescription-view]').prop('disabled', false);
                    $('[data-prescription-view-container]').removeAttr('aria-busy');
                }
            });
        });

        const selectedPrescriptionId = <?= (int) ($filters['prescription_id'] ?? 0) ?>;
        if (selectedPrescriptionId > 0) {
            const selectedModal = $('#doctor-prescription-details-' + selectedPrescriptionId);
            if (selectedModal.length) {
                selectedModal.modal('show');
            }
        }
    });
</script>
<?= $this->endSection() ?>
