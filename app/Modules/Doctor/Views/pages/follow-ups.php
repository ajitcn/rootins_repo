<?= $this->extend(DOCTOR_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php
$filters = $filters ?? [];
$followUps = $followUps ?? [];
$followUpTypes = $followUpTypes ?? [];
$followUpStatuses = $followUpStatuses ?? [];
$defaultFollowUpStatusId = 0;

foreach ($followUpStatuses as $followUpStatus) {
    if ((string) ($followUpStatus['code'] ?? '') === 'draft') {
        $defaultFollowUpStatusId = (int) ($followUpStatus['id'] ?? 0);

        break;
    }
}
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'page_size' => 15, 'total' => 0];
$filterErrors = $filters['filter_errors'] ?? [];
$canEdit = in_array('consultation.edit', session('permissions') ?? [], true);
$formatDate = static function ($value): string {
    $value = trim((string) ($value ?? ''));
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false ? $date->format('d-m-Y') : $value;
};
$formatTime = static function ($value): string {
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
        return '';
    }

    foreach (['!H:i:s', '!H:i'] as $format) {
        $time = \DateTimeImmutable::createFromFormat($format, $value);

        if ($time !== false) {
            return $time->format('H:i');
        }
    }

    return $value;
};
$hasActiveFilters = trim((string) ($filters['search'] ?? '')) !== ''
    || trim((string) ($filters['status'] ?? '')) !== ''
    || (int) ($filters['follow_up_type_id'] ?? 0) > 0
    || trim((string) ($filters['date_from'] ?? '')) !== ''
    || trim((string) ($filters['date_to'] ?? '')) !== '';
?>

<main class="dashboard-container doctor-followups-page" aria-labelledby="doctor-followups-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Continuity of care</div>
            <h1 id="doctor-followups-title">Follow-ups</h1>
            <p class="text-muted mb-0">Track and update follow-up plans within your clinic scope.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            <?php if ($canEdit): ?>
                <button class="btn btn-primary mr-2 mb-2" type="button" data-toggle="modal" data-target="#doctor-followup-modal" aria-controls="doctor-followup-modal">
                    <i class="bi bi-calendar-plus mr-1" aria-hidden="true"></i> New Follow-up
                </button>
            <?php endif; ?>
            <button class="btn btn-outline-primary mr-2 mb-2" type="button" data-toggle="modal" data-target="#doctor-followup-filter-modal" aria-controls="doctor-followup-filter-modal">
                <i class="bi bi-funnel mr-1" aria-hidden="true"></i> Filters
            </button>
            <a class="btn btn-light mb-2" href="<?= base_url('doctor/clinical') ?>">
                <i class="bi bi-clipboard2-pulse mr-1" aria-hidden="true"></i> Clinical Workspace
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <h2 class="h5 mb-1">Follow-up register</h2>
                    <small class="text-muted" role="status" aria-live="polite">
                        <?= (int) ($pagination['total'] ?? count($followUps)) ?> follow-up(s) in scope
                        <span class="badge badge-light ml-1"><?= $hasActiveFilters ? 'Filters active' : 'All follow-ups' ?></span>
                    </small>
                </div>
            </div>
        </div>

        <?php if ($followUps !== []): ?>
            <div class="table-responsive doctor-followup-table-wrap">
                <table class="table mb-0" id="doctor-followups-table">
                    <caption class="sr-only">Doctor follow-up register</caption>
                    <thead>
                        <tr>
                            <th scope="col">Patient</th>
                            <th scope="col">Follow-up</th>
                            <th scope="col">Status</th>
                            <th scope="col">Remarks</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followUps as $item): ?>
                            <?php
                            $status = (string) ($item['follow_up_status_code'] ?? $item['status'] ?? '');
                            $patientName = trim(($item['patient_first_name'] ?? '') . ' ' . ($item['patient_last_name'] ?? ''));
                            $remarks = trim((string) ($item['follow_up_remarks'] ?? ''));
                            ?>
                            <tr>
                                <td data-label="Patient" class="doctor-followup-patient">
                                    <strong><?= esc($patientName !== '' ? $patientName : 'Unknown patient') ?></strong>
                                    <small class="d-block text-muted">Patient no: <?= esc($item['registration_number'] ?? '—') ?></small>
                                    <small class="d-block text-muted"><?= esc(service('telephoneContact')->formatDisplay($item['patient_telephone_country_code'] ?? null, $item['patient_mobile_normalized'] ?? $item['patient_mobile_number'] ?? null, $item['patient_legacy_country_code'] ?? null)) ?></small>
                                </td>
                                <td data-label="Follow-up" class="doctor-followup-summary">
                                    <strong><?= esc($formatDate($item['follow_up_date'] ?? '')) ?></strong>
                                    <small class="d-block text-muted">
                                        <?= esc($formatTime($item['follow_up_time'] ?? '')) ?>
                                        <?= trim((string) ($item['follow_up_type_name'] ?? $item['follow_up_type'] ?? '')) !== '' ? ' · ' . esc($item['follow_up_type_name'] ?? $item['follow_up_type']) : '' ?>
                                    </small>
                                    <small class="d-block text-muted">
                                        <?= esc($item['consultation_number'] ?? 'Consultation') ?>
                                        <?= trim((string) ($item['appointment_number'] ?? '')) !== '' ? ' · ' . esc($item['appointment_number']) : '' ?>
                                    </small>
                                </td>
                                <td data-label="Status">
                                    <span class="badge badge-light doctor-followup-status doctor-followup-status-<?= esc(str_replace('_', '-', $status)) ?>">
                                         <?= esc($item['follow_up_status_name'] ?? ucwords(str_replace('_', ' ', $status))) ?>
                                    </span>
                                </td>
                                <td data-label="Remarks" class="doctor-followup-remarks">
                                    <?= esc($remarks !== '' ? $remarks : '—') ?>
                                </td>
                                <td data-label="Actions" class="text-right doctor-followup-actions">
                                    <div class="doctor-followup-action-group">
                                        <button
                                            class="btn btn-sm btn-light doctor-followup-metadata"
                                            type="button"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title="Created: <?= esc((string) ($item['created_at'] ?? '—')) ?> | Updated: <?= esc((string) ($item['updated_at'] ?? '—')) ?>"
                                            aria-label="Created <?= esc((string) ($item['created_at'] ?? 'not available')) ?>; Updated <?= esc((string) ($item['updated_at'] ?? 'not available')) ?>"
                                        >
                                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                        </button>
                                        <?php if ($canEdit): ?>
                                            <button
                                                class="btn btn-sm btn-outline-primary js-followup-edit"
                                                type="button"
                                                data-toggle="modal"
                                                data-target="#doctor-followup-modal"
                                                data-followup-id="<?= (int) ($item['id'] ?? 0) ?>"
                                                data-patient-id="<?= (int) ($item['patient_id'] ?? 0) ?>"
                                                data-follow-up-date="<?= esc($formatDate($item['follow_up_date'] ?? ''), 'attr') ?>"
                                                data-follow-up-time="<?= esc($formatTime($item['follow_up_time'] ?? ''), 'attr') ?>"
                                                data-follow-up-type-id="<?= (int) ($item['follow_up_type_id'] ?? 0) ?>"
                                                data-status-id="<?= (int) ($item['follow_up_status_id'] ?? 0) ?>"
                                                data-patient-name="<?= esc($patientName, 'attr') ?>"
                                                data-patient-registration="<?= esc((string) ($item['registration_number'] ?? ''), 'attr') ?>"
                                                data-patient-mobile="<?= esc(service('telephoneContact')->formatDisplay($item['patient_telephone_country_code'] ?? null, $item['patient_mobile_normalized'] ?? $item['patient_mobile_number'] ?? null, $item['patient_legacy_country_code'] ?? null), 'attr') ?>"
                                                 data-remarks="<?= esc((string) ($item['follow_up_remarks'] ?? ''), 'attr') ?>"
                                                 aria-label="Edit follow-up for <?= esc($patientName !== '' ? $patientName : 'patient') ?>"
                                            >
                                                <i class="bi bi-pencil-square mr-1" aria-hidden="true"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" role="status" aria-live="polite">
                <i class="bi bi-calendar2-check d-block h3 mb-2" aria-hidden="true"></i>
                <strong>No follow-ups match the current filters.</strong>
                <small class="d-block mt-1">Record a follow-up from a consultation or adjust Filters to get started.</small>
            </div>
        <?php endif; ?>
    </div>
    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination, 'filters' => $filters]) ?>
</main>

<?php if ($canEdit): ?>
<div class="modal fade ri-scroll-modal" id="doctor-followup-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-followup-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="doctor-followup-modal-title">New Follow-up</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close follow-up form"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="doctor-followup-form" action="<?= base_url('doctor/follow-ups/create') ?>" novalidate>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <div id="doctor-followup-status-message" class="alert d-none" role="status" aria-live="polite"></div>
                    <input type="hidden" id="doctor-followup-id" value="">
                    <input type="hidden" id="doctor-followup-patient-id" name="patient_id" value="">
                    <div class="form-row">
                        <div class="form-group col-12">
                            <label for="doctor-followup-patient-search">Patient <span class="text-danger" aria-hidden="true">*</span></label>
                            <input id="doctor-followup-patient-search" type="search" class="form-control doctor-followup-patient-search" placeholder="Search patient name, number, or mobile" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="doctor-followup-patient-results" aria-describedby="patient_idErr" required>
                            <div id="doctor-followup-patient-results" class="doctor-followup-patient-results" role="listbox" aria-live="polite"></div>
                            <div class="validation-error" id="patient_idErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-followup-date">Follow-up date <span class="text-danger" aria-hidden="true">*</span></label>
                            <input id="doctor-followup-date" type="text" name="follow_up_date" class="form-control datepicker" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" required aria-describedby="follow_up_dateErr">
                            <div class="validation-error" id="follow_up_dateErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="follow_up_time">Follow-up time <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="time" name="follow_up_time" id="follow_up_time" class="form-control" step="900" placeholder="HH:mm" required aria-describedby="follow_up_timeErr">
                            <div class="validation-error" id="follow_up_timeErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-followup-type">Follow-up type <span class="text-danger" aria-hidden="true">*</span></label>
                            <select id="doctor-followup-type" name="follow_up_type_id" class="form-control btSelect" data-live-search="true" title="Select follow-up type" required aria-describedby="follow_up_type_idErr">
                                <option value="">Select follow-up type</option>
                                <?php foreach ($followUpTypes as $type): ?>
                                    <option value="<?= (int) ($type['id'] ?? 0) ?>"><?= esc($type['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="follow_up_type_idErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12 mb-0">
                            <label for="doctor-followup-status">Status <span class="text-danger" aria-hidden="true">*</span></label>
                            <select id="doctor-followup-status" name="status" class="form-control btSelect" data-live-search="true" title="Select status" required aria-describedby="statusErr">
                                <option value="">Select status</option>
                                <?php foreach ($followUpStatuses as $statusOption): ?>
                                    <option value="<?= (int) ($statusOption['id'] ?? 0) ?>" <?= (int) ($statusOption['id'] ?? 0) === $defaultFollowUpStatusId ? 'selected' : '' ?>><?= esc($statusOption['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="statusErr"></div>
                        </div>
                        <div class="form-group col-12 mb-0">
                            <label for="doctor-followup-remarks">Remarks</label>
                            <textarea id="doctor-followup-remarks" name="remarks" class="form-control" rows="2" maxlength="5000" aria-describedby="remarksErr"></textarea>
                            <div class="validation-error" id="remarksErr"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="doctor-followup-submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle mr-1" aria-hidden="true"></i><span id="doctor-followup-submit-label">Create Follow-up</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade ri-scroll-modal" id="doctor-followup-filter-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-followup-filter-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="get" action="<?= current_url() ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-followup-filter-title">Filter follow-ups</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close filters"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="doctor-followup-filter-search">Search</label>
                        <input id="doctor-followup-filter-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control" type="search" placeholder="Search patient, mobile, type, status..." autocomplete="off">
                        <div class="validation-error" id="followup_filter_searchErr"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-filter-from">Date from</label>
                            <input id="doctor-followup-filter-from" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>" class="form-control datepicker <?= isset($filterErrors['date_from']) ? 'is-invalid' : '' ?>" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" aria-describedby="followup_filter_date_fromErr" aria-invalid="<?= isset($filterErrors['date_from']) ? 'true' : 'false' ?>">
                            <div class="validation-error" id="followup_filter_date_fromErr"><?= esc($filterErrors['date_from'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-filter-to">Date to</label>
                            <input id="doctor-followup-filter-to" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>" class="form-control datepicker <?= isset($filterErrors['date_to']) ? 'is-invalid' : '' ?>" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" aria-describedby="followup_filter_date_toErr" aria-invalid="<?= isset($filterErrors['date_to']) ? 'true' : 'false' ?>">
                            <div class="validation-error" id="followup_filter_date_toErr"><?= esc($filterErrors['date_to'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-filter-type">Follow-up type</label>
                            <select id="doctor-followup-filter-type" name="follow_up_type_id" class="form-control btSelect" data-live-search="true" title="All follow-up types">
                                <option value="">All follow-up types</option>
                                <?php foreach ($followUpTypes as $type): ?>
                                    <option value="<?= (int) ($type['id'] ?? 0) ?>" <?= (int) ($filters['follow_up_type_id'] ?? 0) === (int) ($type['id'] ?? 0) ? 'selected' : '' ?>><?= esc($type['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="followup_filter_type_idErr"></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-filter-status">Status</label>
                            <select id="doctor-followup-filter-status" name="status" class="form-control btSelect" title="All statuses">
                                <option value="">All statuses</option>
                                <?php foreach ($followUpStatuses as $statusOption): ?>
                                    <option value="<?= (int) ($statusOption['id'] ?? 0) ?>" <?= (string) ($filters['status'] ?? '') === (string) ($statusOption['id'] ?? '') ? 'selected' : '' ?>><?= esc($statusOption['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="followup_filter_statusErr"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-light" href="<?= base_url('doctor/follow-ups') ?>">Reset</a>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
$(function () {
    const form = $('#doctor-followup-form');
    const modal = $('#doctor-followup-modal');
    const patientId = $('#doctor-followup-patient-id');
    const createUrl = '<?= base_url('doctor/follow-ups/create') ?>';
    const updateUrl = '<?= base_url('doctor/follow-ups/update') ?>';
    const patientSearchUrl = '<?= base_url('doctor/follow-ups/patients/search') ?>';

    function refreshSelect(select) {
        if (select.hasClass('btSelect') && typeof select.selectpicker === 'function') {
            select.selectpicker('refresh');
        }
    }

    function setFormMode(editing) {
        $('#doctor-followup-modal-title').text(editing ? 'Edit Follow-up' : 'New Follow-up');
        $('#doctor-followup-submit-label').text(editing ? 'Save Changes' : 'Create Follow-up');
        form.attr('action', editing ? updateUrl + '/' + encodeURIComponent($('#doctor-followup-id').val()) : createUrl);
    }

    function resetForm() {
        form[0].reset();
        $('#doctor-followup-id').val('');
        patientId.val('');
        $('#doctor-followup-patient-search').val('').attr('aria-expanded', 'false');
        $('#doctor-followup-patient-results').empty();
        setFormMode(false);
        $('#doctor-followup-status-message').removeClass('alert-info alert-danger alert-success').addClass('d-none').empty();
        clearFormErrors(form);
        [$('#doctor-followup-status'), $('#doctor-followup-type')].forEach(refreshSelect);
    }

    function setPatientSelection(hidden, input, results, item) {
        hidden.val(String(item.id || '')).trigger('change');
        input.val([item.name, item.registration_number, item.mobile].filter(Boolean).join(' · '));
        input.attr('aria-expanded', 'false');
        results.empty();
    }

    function initPatientAutocomplete(inputSelector, hiddenSelector, resultsSelector, allowClear) {
        const input = $(inputSelector);
        const hidden = $(hiddenSelector);
        const results = $(resultsSelector);
        let timer = null;
        let request = null;
        let sequence = 0;
        let activeIndex = -1;

        function render(items) {
            results.empty();
            activeIndex = -1;
            if (!items.length) {
                results.attr('aria-busy', 'false').html('<div class="doctor-followup-patient-state">No patients found.</div>');
                return;
            }
            items.forEach(function (item, index) {
                const option = $('<button>', {type: 'button', class: 'doctor-followup-patient-result', role: 'option', 'aria-selected': 'false', 'data-index': index});
                option.append($('<strong>').text(item.name || 'Unnamed patient'));
                option.append($('<small>').text([item.registration_number ? 'Patient no: ' + item.registration_number : '', item.mobile || ''].filter(Boolean).join(' · ')));
                option.on('click', function () {
                    setPatientSelection(hidden, input, results, item);
                });
                results.append(option);
            });
            results.attr('aria-busy', 'false');
        }

        function search() {
            const term = $.trim(input.val());
            if (term.length < 2) {
                if (request) request.abort();
                results.empty().removeAttr('aria-busy');
                input.attr('aria-expanded', 'false');
                if (allowClear && term === '') hidden.val('').trigger('change');
                return;
            }
            const requestSequence = ++sequence;
            if (request) request.abort();
            results.attr('aria-busy', 'true').html('<div class="doctor-followup-patient-state">Loading patients…</div>');
            input.attr('aria-expanded', 'true');
            request = $.ajax({url: patientSearchUrl, method: 'GET', dataType: 'json', data: {q: term, page: 1, page_size: 20, _followup_search: Date.now()}})
                .done(function (response) {
                    if (requestSequence !== sequence) return;
                    render(response && response.data && Array.isArray(response.data.items) ? response.data.items : []);
                })
                .fail(function (xhr, status) {
                    if (status === 'abort' || requestSequence !== sequence) return;
                    results.attr('aria-busy', 'false').html('<div class="doctor-followup-patient-state text-danger">Unable to load patients. Try again.</div>');
                });
        }

        $(document)
            .off('input.doctorFollowupAutocomplete', inputSelector)
            .on('input.doctorFollowupAutocomplete', inputSelector, function () {
                hidden.val('').trigger('change');
                window.clearTimeout(timer);
                timer = window.setTimeout(search, 280);
            })
            .off('keydown.doctorFollowupAutocomplete', inputSelector)
            .on('keydown.doctorFollowupAutocomplete', inputSelector, function (event) {
                const options = results.find('.doctor-followup-patient-result');
                if (!options.length) {
                    if (event.key === 'Escape') results.empty().hide();
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeIndex = event.key === 'ArrowDown' ? (activeIndex + 1) % options.length : (activeIndex - 1 + options.length) % options.length;
                    options.attr('aria-selected', 'false').eq(activeIndex).attr('aria-selected', 'true').focus();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    results.empty();
                    input.attr('aria-expanded', 'false').trigger('focus');
                } else if (event.key === 'Enter' && activeIndex >= 0) {
                    event.preventDefault();
                    options.eq(activeIndex).trigger('click');
                }
            });
        input.on('focus', function () {
            if ($.trim(input.val()).length >= 2 && results.children().length) input.attr('aria-expanded', 'true');
        });
    }

    initPatientAutocomplete('#doctor-followup-patient-search', '#doctor-followup-patient-id', '#doctor-followup-patient-results', true);

    $(document).on('click', '.js-followup-edit', function () {
        const button = $(this);
        resetForm();
        $('#doctor-followup-id').val(button.data('followup-id'));
        setFormMode(true);
        setPatientSelection(patientId, $('#doctor-followup-patient-search'), $('#doctor-followup-patient-results'), {
            id: button.data('patient-id') || '',
            name: button.data('patient-name') || '',
            registration_number: button.data('patient-registration') || '',
            mobile: button.data('patient-mobile') || ''
        });
        $('#doctor-followup-date').val(button.data('follow-up-date') || '');
        $('#follow_up_time').val(button.data('follow-up-time') || '');
        $('#doctor-followup-type').val(String(button.data('follow-up-type-id') || '')).selectpicker('refresh');
        $('#doctor-followup-status').val(String(button.data('status-id') || '')).selectpicker('refresh');
        $('#doctor-followup-remarks').val(button.data('remarks') || '');
    });

    modal.on('shown.bs.modal', function () {
        initSharedDatepickers(this);
        $('.btSelect', this).selectpicker('refresh');
        $('#doctor-followup-patient-search').trigger('focus').trigger('select');
    });

    modal.on('hidden.bs.modal', resetForm);

    form.on('submit', function (event) {
        event.preventDefault();
        const submit = $('#doctor-followup-submit');
        const status = $('#doctor-followup-status-message');
        if (submit.prop('disabled')) return;
        clearFormErrors(form);
        status.removeClass('d-none alert-danger alert-success').addClass('alert-info').text('Saving follow-up...');
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    status.removeClass('alert-info').addClass('alert-success').text(response.msg);
                    showSuccess(response.msg);
                    modal.modal('hide');
                    reload_page(700);
                    return;
                }
                if (response.errors) bindFormError(form, response.errors);
                status.removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Please correct the highlighted fields.');
                showError(response.msg || 'Please correct the highlighted fields.');
            },
            error: function (xhr) {
                handleFormAjaxError(form, xhr, 'Follow-up could not be saved safely.');
                const response = xhr.responseJSON || {};
                if (response.errors) bindFormError(form, response.errors);
                status.removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Follow-up could not be saved safely.');
            },
            complete: function () {
                submit.prop('disabled', false).removeAttr('aria-busy');
            }
        });
    });

    $('#doctor-followup-filter-modal').on('shown.bs.modal', function () {
        initSharedDatepickers(this);
        $('.btSelect', this).selectpicker('refresh');
    });
});
</script>
<?= $this->endSection() ?>
