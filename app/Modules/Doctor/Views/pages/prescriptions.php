<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$doctorCanViewPatients = in_array('patient.view', session('permissions') ?? [], true);
$doctorCanViewClinical = in_array('consultation.view', session('permissions') ?? [], true);
$requestedPrescriptionView = (string) ($filters['view'] ?? 'list');
$prescriptionView = in_array($requestedPrescriptionView, ['list', 'grid'], true) ? $requestedPrescriptionView : 'list';
$prescriptionContext = static function (array $item): array {
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
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
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
                                <?php if (trim((string) ($item['clinic_logo'] ?? '')) !== ''): ?>
                                    <img src="<?= esc(base_url((string) $item['clinic_logo'])) ?>" alt="<?= esc($item['clinic_name'] ?? 'Clinic') ?> logo" class="doctor-prescription-clinic-logo">
                                <?php endif; ?>
                                <div>
                                    <?php if (trim((string) ($item['clinic_name'] ?? '')) !== ''): ?><strong><?= esc($item['clinic_name']) ?></strong><?php endif; ?>
                                    <?php if (trim((string) ($item['clinic_address'] ?? '')) !== ''): ?><div><?= nl2br(esc($item['clinic_address'])) ?></div><?php endif; ?>
                                    <?php if (trim((string) ($item['clinic_mobile'] ?? '')) !== ''): ?><div><?= esc($item['clinic_mobile']) ?></div><?php endif; ?>
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

                        <h3 class="doctor-prescription-section-title">Prescribed Medicines<?= $medicineDuration !== '' ? ' (for ' . esc($medicineDuration) . ')' : '' ?></h3>
                        <div class="table-responsive doctor-prescription-table-wrap">
                            <table class="table table-bordered table-sm doctor-prescription-table mb-0">
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
                                            <td colspan="7" class="text-muted">No medicine items recorded.</td>
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

                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                    <button
                        class="btn btn-outline-primary doctor-prescription-print-trigger"
                        type="button"
                        data-print-target="<?= esc($printId) ?>"
                        aria-label="Print prescription <?= esc($item['prescription_number']) ?>"
                        title="Print prescription"
                    >
                        <i class="bi bi-printer mr-1" aria-hidden="true"></i>
                        Print
                    </button>
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

        $(document).on('click.doctorPrescriptionPrint', '.doctor-prescription-print-trigger', function () {
            const button = this;
            const modal = $(button).closest('.doctor-prescription-print-modal');
            const printId = button.getAttribute('data-print-target');
            const printArea = document.getElementById(printId);

            if (!modal.length || !printArea) {
                return;
            }

            const originalParent = printArea.parentNode;
            const originalNextSibling = printArea.nextSibling;

            if (!originalParent) {
                return;
            }

            $('.doctor-prescription-print-area').removeClass('is-print-target');
            printArea.classList.add('is-print-target');
            document.body.appendChild(printArea);
            document.body.classList.add('doctor-prescription-printing');

            let restored = false;
            const cleanup = function () {
                if (restored) {
                    return;
                }

                restored = true;
                document.body.classList.remove('doctor-prescription-printing');
                printArea.classList.remove('is-print-target');
                if (originalNextSibling && originalNextSibling.parentNode === originalParent) {
                    originalParent.insertBefore(printArea, originalNextSibling);
                } else {
                    originalParent.appendChild(printArea);
                }
                window.removeEventListener('afterprint', cleanup);
            };

            window.addEventListener('afterprint', cleanup);
            window.setTimeout(function () {
                window.print();
            }, 0);
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
