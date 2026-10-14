<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="doctor-patients-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Clinical relationships</div>
            <h1 id="doctor-patients-title">My Patients</h1>
            <p class="text-muted mb-0">Patients in your assigned clinic relationship.</p>
        </div>
        <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#new-patient-modal">
            <i class="bi bi-person-plus mr-1" aria-hidden="true"></i>New Patient
        </button>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <h2 class="h5 mb-2 mb-md-0">Patient register</h2>
            <div class="d-flex flex-wrap align-items-center">
                <form method="get" class="form-inline mr-2 mb-2 mb-md-0" action="<?= current_url() ?>">
                    <label class="sr-only" for="doctor-patient-search">Search patients</label>
                    <input id="doctor-patient-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control form-control-sm mr-2" type="search" placeholder="Search name or registration number">
                    <?php foreach ($filters as $key => $value): ?>
                        <?php if (! in_array($key, ['search', 'page', 'page_size'], true) && ! is_array($value)): ?>
                            <input type="hidden" name="<?= esc($key) ?>" value="<?= esc((string) $value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="page_size" value="<?= (int) ($filters['page_size'] ?? 15) ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Search</button>
                </form>
                <button class="btn btn-sm btn-outline-secondary mb-2 mb-md-0" type="button" data-toggle="modal" data-target="#doctor-patient-filter-modal" aria-controls="doctor-patient-filter-modal">
                    <i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="doctor-patients-table">
                <thead><tr><th>Patient</th><th>Registration</th><th>Source</th><th>Gender</th><th>Mobile</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($patients as $patient): ?>
                    <?php $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?>
                    <?php $patientContact = service('telephoneContact')->formatDisplay($patient['telephone_country_code'] ?? null, $patient['mobile_normalized'] ?? $patient['mobile_number'] ?? null, $patient['country_code'] ?? null); ?>
                    <tr>
                        <td><strong><?= esc($patientName) ?></strong></td>
                        <td><?= esc($patient['registration_number']) ?></td>
                        <td><span class="badge <?= esc(patient_source_badge_class($patient['source_type'] ?? null)) ?>"><?= esc(patient_source_label($patient['source_type'] ?? null)) ?></span></td>
                        <td><?= esc($patient['gender'] ?? '—') ?></td>
                        <td><?= esc($patientContact) ?></td>
                        <td><span class="badge <?= (int) $patient['status'] === 1 ? 'badge-success' : 'badge-light' ?>"><?= (int) $patient['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-right"><a class="btn btn-sm btn-outline-primary mr-1" href="<?= base_url('doctor/patients/' . (int) $patient['id']) ?>" aria-label="View patient <?= esc($patientName) ?>">View</a><a class="btn btn-sm btn-outline-secondary" href="<?= base_url('doctor/patients/' . (int) $patient['id']) ?>#edit-doctor-patient" aria-label="Edit patient <?= esc($patientName) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($patients === []): ?><div class="empty-state"><i class="bi bi-people d-block h3 mb-2" aria-hidden="true"></i><div>No patients found</div><small>Create a patient or adjust the search.</small></div><?php endif; ?>
    </div>
    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]) ?>
</main>

<div class="modal fade ri-scroll-modal" id="doctor-patient-filter-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-patient-filter-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="get" action="<?= current_url() ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="doctor-patient-filter-modal-title">Filter patients</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close filters">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (! empty($filterErrors)): ?>
                        <div class="alert alert-warning" role="alert">Please correct the filter dates before applying.</div>
                    <?php endif; ?>
                    <input type="hidden" name="search" value="<?= esc($filters['search'] ?? '') ?>">
                    <div class="row g-3">
                        <div class="form-group col-md-6 col-12">
                            <label for="doctor-filter-patient-name">Patient name</label>
                            <input id="doctor-filter-patient-name" class="form-control" type="search" name="patient_name" value="<?= esc($filters['patient_name'] ?? '') ?>" placeholder="First, middle, or last name" aria-describedby="doctor_filter_patient_nameErr">
                            <div class="validation-error" id="doctor_filter_patient_nameErr" aria-live="polite"><?= esc($filterErrors['patient_name'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-6 col-12">
                            <label for="doctor-filter-mobile">Mobile number</label>
                            <input id="doctor-filter-mobile" class="form-control" type="search" name="mobile_number" value="<?= esc($filters['mobile_number'] ?? '') ?>" placeholder="Mobile number" aria-describedby="doctor_filter_mobile_numberErr">
                            <div class="validation-error" id="doctor_filter_mobile_numberErr" aria-live="polite"><?= esc($filterErrors['mobile_number'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-source">Source type</label>
                            <select id="doctor-filter-source" class="selectpicker form-control btSelect" data-live-search="true" title="All sources" name="source_type" aria-describedby="doctor_filter_source_typeErr">
                                <option value="">All sources</option>
                                <?php foreach (['self' => 'Self', 'shopify' => 'Shopify', 'unknown' => 'Unknown'] as $sourceValue => $sourceLabel): ?>
                                    <option value="<?= esc($sourceValue) ?>" <?= ($filters['source_type'] ?? '') === $sourceValue ? 'selected' : '' ?>><?= esc($sourceLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_source_typeErr" aria-live="polite"><?= esc($filterErrors['source_type'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-status">Status</label>
                            <select id="doctor-filter-status" class="selectpicker form-control btSelect" data-live-search="true" title="All statuses" name="status" aria-describedby="doctor_filter_statusErr">
                                <option value="">All statuses</option>
                                <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <div class="validation-error" id="doctor_filter_statusErr" aria-live="polite"><?= esc($filterErrors['status'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-page-size">Rows per page</label>
                            <select id="doctor-filter-page-size" class="selectpicker form-control btSelect" data-live-search="true" name="page_size" aria-describedby="doctor_filter_page_sizeErr">
                                <?php foreach ([10, 15, 25, 50] as $size): ?>
                                    <option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_page_sizeErr" aria-live="polite"><?= esc($filterErrors['page_size'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-country">Country</label>
                            <select id="doctor-filter-country" class="selectpicker form-control btSelect" data-live-search="true" title="All countries" name="country_id" aria-describedby="doctor_filter_country_idErr">
                                <option value="">All countries</option>
                                <?php foreach (($filterCountries ?? []) as $country): ?>
                                    <option value="<?= (int) $country['id'] ?>" <?= (int) ($filters['country_id'] ?? 0) === (int) $country['id'] ? 'selected' : '' ?>><?= esc($country['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_country_idErr" aria-live="polite"><?= esc($filterErrors['country_id'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-state">State/Division</label>
                            <select id="doctor-filter-state" class="selectpicker form-control btSelect" data-live-search="true" title="All states/divisions" name="state_id" aria-describedby="doctor_filter_state_idErr">
                                <option value="">All states/divisions</option>
                                <?php foreach (($filterStates ?? []) as $state): ?>
                                    <option value="<?= (int) $state['id'] ?>" <?= (int) ($filters['state_id'] ?? 0) === (int) $state['id'] ? 'selected' : '' ?>><?= esc($state['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_state_idErr" aria-live="polite"><?= esc($filterErrors['state_id'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-city">City</label>
                            <select id="doctor-filter-city" class="selectpicker form-control btSelect" data-live-search="true" title="All cities" name="city_id" aria-describedby="doctor_filter_city_idErr">
                                <option value="">All cities</option>
                                <?php foreach (($filterCities ?? []) as $city): ?>
                                    <option value="<?= (int) $city['id'] ?>" <?= (int) ($filters['city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>><?= esc($city['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_city_idErr" aria-live="polite"><?= esc($filterErrors['city_id'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-locality">Locality</label>
                            <select id="doctor-filter-locality" class="selectpicker form-control btSelect" data-live-search="true" title="All localities" name="locality_id" aria-describedby="doctor_filter_locality_idErr">
                                <option value="">All localities</option>
                                <?php foreach (($filterLocalities ?? []) as $locality): ?>
                                    <option value="<?= (int) $locality['id'] ?>" <?= (int) ($filters['locality_id'] ?? 0) === (int) $locality['id'] ? 'selected' : '' ?>><?= esc($locality['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="doctor_filter_locality_idErr" aria-live="polite"><?= esc($filterErrors['locality_id'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-created-from">Created from</label>
                            <input id="doctor-filter-created-from" class="form-control datepicker" type="text" name="created_from" value="<?= esc($filters['created_from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off" aria-describedby="doctor_filter_created_fromErr">
                            <div class="validation-error" id="doctor_filter_created_fromErr" aria-live="polite"><?= esc($filterErrors['created_from'] ?? '') ?></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="doctor-filter-created-to">Created to</label>
                            <input id="doctor-filter-created-to" class="form-control datepicker" type="text" name="created_to" value="<?= esc($filters['created_to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off" aria-describedby="doctor_filter_created_toErr">
                            <div class="validation-error" id="doctor_filter_created_toErr" aria-live="polite"><?= esc($filterErrors['created_to'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= base_url('doctor/patients') ?>" class="btn btn-link mr-auto">Clear</a>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade ri-scroll-modal doctor-patient-form-modal" id="new-patient-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="new-patient-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form id="doctor-patient-create-form">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title h5" id="new-patient-modal-title">Create New Patient</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="row g-3 doctor-patient-form-grid">
                            <div class="form-group col-md-4 col-12">
                                <label for="new-patient-first">First name <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <input id="new-patient-first" class="form-control" name="first_name" required maxlength="100">
                                <div class="validation-error" id="first_nameErr"></div>
                            </div>
                        <div class="form-group col-md-4 col-12">
                                <label for="new-patient-last">Last name <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <input id="new-patient-last" class="form-control" name="last_name" required maxlength="100">
                                <div class="validation-error" id="last_nameErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12 contact-number-field">
                                <label for="new-patient-mobile">Mobile number</label>
                                <div class="input-group contact-number-input-group">
                                <input id="new-patient-mobile" class="form-control mobile-number-field" name="mobile_number" maxlength="30" inputmode="tel">
                            </div>
                                <div class="validation-error" id="country_codeErr"></div>
                                <div class="validation-error" id="telephone_country_code_idErr"></div>
                                <div class="validation-error" id="mobile_numberErr"></div>
                                <div class="contact-number-meta"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="new-patient-email">Email</label>
                                <input id="new-patient-email" class="form-control" type="email" name="email" maxlength="255">
                                <div class="validation-error" id="emailErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="new-patient-gender">Gender</label>
                                <select id="new-patient-gender" class="form-control btSelect" name="gender">
                                    <option value="">Not specified</option>
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                    <option value="other">Other</option>
                                </select>
                                <div class="validation-error" id="genderErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="new-patient-dob">Date of birth <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <input id="new-patient-dob" class="form-control datepicker" type="text" name="date_of_birth" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" required>
                                <div class="validation-error" id="date_of_birthErr"></div>
                            </div>
                            <div class="col-12 doctor-patient-location-slot" aria-hidden="true"></div>
                            <div class="form-group col-md-4 col-12">
                                <label for="new-patient-postal-code">Postal / PIN code</label>
                                <input id="new-patient-postal-code" class="form-control" name="postal_code" maxlength="20" inputmode="numeric">
                                <div class="validation-error" id="postal_codeErr"></div>
                            </div>
                            <div class="form-group col-12">
                                <label for="new-patient-address">Address</label>
                                <textarea id="new-patient-address" class="form-control" name="address" rows="3" maxlength="1000"></textarea>
                                <div class="validation-error" id="addressErr"></div>
                            </div>
                            <div class="col-12 border rounded p-3">
                                <div class="custom-control custom-switch mb-3">
                            <input class="custom-control-input" type="checkbox" id="create-appointment-now" name="create_appointment_now" value="1">
                            <label class="custom-control-label" for="create-appointment-now">Create the first appointment in the same transaction</label>
                            <div class="validation-error" id="create_appointment_nowErr"></div>
                                </div>
                                <div id="new-patient-appointment-fields" class="row g-3 d-none">
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-doctor">Doctor</label>
                                <select id="appointment-doctor" class="form-control btSelect" name="appointment_doctor_id" disabled>
                                    <option value="<?= (int) ($doctor['id'] ?? 0) ?>">Dr. <?= esc(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))) ?></option>
                                </select>
                                <div class="validation-error" id="appointment_doctor_idErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-service">Service <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <select id="appointment-service" class="form-control btSelect" data-live-search="true" name="service_name" disabled required>
                                    <option value="">Select service</option>
                                    <?php foreach (($services ?? []) as $service): ?>
                                        <option value="<?= esc($service['name']) ?>"><?= esc($service['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="validation-error" id="service_nameErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-date">Date <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <input id="appointment-date" class="form-control datepicker" type="text" name="appointment_date" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" disabled required>
                                <div class="validation-error" id="appointment_dateErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-time">Time <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                                <input id="appointment-time" class="form-control" type="time" name="appointment_time" disabled required>
                                <div class="validation-error" id="appointment_timeErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-status">Status</label>
                                <select id="appointment-status" class="form-control btSelect" name="appointment_status" disabled>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                                <div class="validation-error" id="appointment_statusErr"></div>
                            </div>
                            <div class="form-group col-md-4 col-12">
                                <label for="appointment-reason">Reason</label>
                                <textarea id="appointment-reason" class="form-control" name="appointment_reason" rows="2" maxlength="1000" disabled></textarea>
                                <div class="validation-error" id="appointment_reasonErr"></div>
                            </div>
                                </div>
                            </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus mr-1" aria-hidden="true"></i>Create patient</button></div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/doctor/patient_location.js') ?>"></script>
<script>
DoctorPatientLocation.init('#doctor-patient-create-form', '', <?= json_encode($countries ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, {}, <?= json_encode($telephoneCodes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
$(function () {
    $('#doctor-patient-filter-modal select.btSelect').selectpicker();
    const resetPatientFilterLocation = function (selector, label, disabled) {
        const select = $(selector);
        select.html('<option value="">All ' + label + '</option>').val('').prop('disabled', disabled).selectpicker('refresh');
    };
    const initializePatientFilterLocations = function () {
        const country = $('#doctor-filter-country').val();
        const state = $('#doctor-filter-state').val();
        const city = $('#doctor-filter-city').val();
        $('#doctor-filter-state').prop('disabled', !country).selectpicker('refresh');
        $('#doctor-filter-city').prop('disabled', !state).selectpicker('refresh');
        $('#doctor-filter-locality').prop('disabled', !city).selectpicker('refresh');
    };
    $('#doctor-filter-country').on('change', function () {
        resetPatientFilterLocation('#doctor-filter-state', 'states/divisions', !this.value);
        resetPatientFilterLocation('#doctor-filter-city', 'cities', true);
        resetPatientFilterLocation('#doctor-filter-locality', 'localities', true);
        if (this.value) {
            loadStates(this.value, '#doctor-filter-state', function () {
                $('#doctor-filter-state').prop('disabled', false).selectpicker('refresh');
            });
        }
    });
    $('#doctor-filter-state').on('change', function () {
        resetPatientFilterLocation('#doctor-filter-city', 'cities', !this.value);
        resetPatientFilterLocation('#doctor-filter-locality', 'localities', true);
        if (this.value) {
            loadCities(this.value, '#doctor-filter-city', function () {
                $('#doctor-filter-city').prop('disabled', false).selectpicker('refresh');
            });
        }
    });
    $('#doctor-filter-city').on('change', function () {
        resetPatientFilterLocation('#doctor-filter-locality', 'localities', !this.value);
        if (this.value) {
            loadLocalities(this.value, '#doctor-filter-locality', function () {
                $('#doctor-filter-locality').prop('disabled', false).selectpicker('refresh');
            });
        }
    });
    initializePatientFilterLocations();
    $('#doctor-patient-filter-modal').on('shown.bs.modal', function () {
        $(this).find('select.btSelect').selectpicker('refresh');
        if (window.initSharedDatepickers) {
            window.initSharedDatepickers(this);
        }
    });
    const setNewPatientAppointmentState = function (enabled) {
        const fields = $('#new-patient-appointment-fields');
        fields.toggleClass('d-none', !enabled).attr('aria-hidden', enabled ? 'false' : 'true');
        fields.find(':input').each(function () {
            const input = $(this);
            input.prop('disabled', !enabled);
            if (input.is('select.btSelect')) {
                if (!input.hasClass('selectpicker')) {
                    input.selectpicker({noneSelectedText: 'Select an option', liveSearchPlaceholder: 'Type to search...'});
                }
                input.selectpicker(enabled ? 'enable' : 'disable').selectpicker('refresh');
            }
        });
        if (enabled && window.initSharedDatepickers) {
            window.initSharedDatepickers(fields);
        }
    };
    $(document).off('change.doctorNewPatientAppointment', '#create-appointment-now')
        .on('change.doctorNewPatientAppointment', '#create-appointment-now', function () {
            setNewPatientAppointmentState(this.checked);
        });
    setNewPatientAppointmentState($('#create-appointment-now').is(':checked'));
    $('#new-patient-modal').on('shown.bs.modal', function () {
        setNewPatientAppointmentState($('#create-appointment-now').is(':checked'));
        if (window.initSharedDatepickers) window.initSharedDatepickers(this);
    });
    $('#doctor-patient-create-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this), submit = form.find('[type="submit"]');
        clearFormErrors('#doctor-patient-create-form'); submit.prop('disabled', true);
        ajaxRequest({
            url: '<?= base_url('doctor/patients/create') ?>',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                } else {
                    showError(response.msg);
                    if (response.errors) bindFormError('#doctor-patient-create-form', response.errors);
                }
            },
            error: function (xhr) {
                handleFormAjaxError('#doctor-patient-create-form', xhr, 'Patient could not be created safely.');
            },
            complete: function () { submit.prop('disabled', false); }
        });
    });
});
</script>
<?= $this->endSection() ?>
