<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $patient['first_name'] ?? '',
    $patient['middle_name'] ?? '',
    $patient['last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
?>
<?php $patientContact = service('telephoneContact')->formatDisplay($patient['telephone_country_code'] ?? null, $patient['mobile_normalized'] ?? $patient['mobile_number'] ?? null, $patient['country_code'] ?? null); ?>
<main class="dashboard-container" aria-labelledby="doctor-patient-title">
    <div class="page-heading">
        <div>
            <a href="<?= base_url('doctor/patients') ?>" class="btn btn-sm btn-light mb-3">
                <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
                Back to patients
            </a>
            <div class="page-eyebrow">Scoped patient profile</div>
            <h1 id="doctor-patient-title"><?= esc($patientName) ?></h1>
            <p class="text-muted mb-0">
                <?= esc($patient['registration_number'] ?? '') ?>
                · <?= esc($patientContact) ?>
            </p>
        </div>
        <button
            class="btn btn-outline-primary"
            type="button"
            data-toggle="modal"
            data-target="#edit-doctor-patient"
        >
            <i class="bi bi-pencil mr-1" aria-hidden="true"></i>
            Edit patient
        </button>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Patient details</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Gender</dt>
                        <dd><?= esc($patient['gender'] ?? 'Not recorded') ?></dd>
                        <dt>Source</dt>
                        <dd><span class="badge <?= esc(patient_source_badge_class($patient['source_type'] ?? null)) ?>"><?= esc(patient_source_label($patient['source_type'] ?? null)) ?></span></dd>
                        <dt>Status</dt>
                        <dd><?= (int) $patient['status'] === 1 ? 'Active' : 'Inactive' ?></dd>
                        <dt>Date of birth</dt>
                        <dd><?= esc(rootins_format_date($patient['date_of_birth'] ?? '') ?: 'Not recorded') ?></dd>
                        <dt>Location</dt>
                        <dd>
                            <?= esc($patient['locality_name'] ?? $patient['city_name'] ?? $patient['state_name'] ?? $patient['country_name'] ?? 'Not recorded') ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Appointments</h2>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $item): ?>
                                <tr>
                                    <td><?= esc($item['appointment_number']) ?></td>
                                    <td>
                                        <?= esc(rootins_format_date($item['appointment_date'] ?? '')) ?>
                                        <?= esc(rootins_format_time($item['appointment_time'] ?? '')) ?>
                                    </td>
                                    <td><?= esc(ucwords(str_replace('_', ' ', $item['appointment_type']))) ?></td>
                                    <td><?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($appointments === []): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Consultation history</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($consultations as $item): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <strong><?= esc($item['consultation_number']) ?></strong>
                            <div class="small text-muted">
                                <?= esc(rootins_format_date($item['consultation_date'] ?? '')) ?> · <?= esc($item['status']) ?>
                            </div>
                            <div><?= nl2br(esc($item['diagnosis'] ?? $item['clinical_notes'] ?? 'No notes')) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($consultations === []): ?>
                        <p class="text-muted mb-0">No consultations found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Prescription history</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($prescriptions as $item): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <strong><?= esc($item['prescription_number']) ?></strong>
                            <div class="small text-muted">
                                <?= esc(rootins_format_date($item['prescription_date'] ?? '')) ?> · <?= esc($item['status']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($prescriptions === []): ?>
                        <p class="text-muted mb-0">No prescriptions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<div
    class="modal fade doctor-patient-edit-modal doctor-patient-form-modal ri-scroll-modal"
    id="edit-doctor-patient"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="edit-doctor-patient-title"
    aria-describedby="doctor-patient-edit-status"
    data-backdrop="true"
    data-keyboard="true"
    aria-hidden="true"
    data-focus="true"
>
    <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="edit-doctor-patient-title">Edit patient record</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="doctor-patient-edit-form" autocomplete="off" novalidate>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <div id="doctor-patient-edit-status" class="alert d-none" role="status" aria-live="polite"></div>
                    <div class="row g-3">
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-first">First name <span class="text-danger">*</span></label>
                            <input id="edit-patient-first" class="form-control" name="first_name" value="<?= esc($patient['first_name'] ?? '') ?>" required>
                            <div class="validation-error" id="first_nameErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-last">Last name <span class="text-danger">*</span></label>
                            <input id="edit-patient-last" class="form-control" name="last_name" value="<?= esc($patient['last_name'] ?? '') ?>" required>
                            <div class="validation-error" id="last_nameErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12 contact-number-field">
                            <label for="edit-patient-mobile">Mobile number</label>
                            <div class="input-group contact-number-input-group">
                                <input id="edit-patient-mobile" class="form-control mobile-number-field" name="mobile_number" value="<?= esc($patient['mobile_number'] ?? '') ?>">
                            </div>
                            <div class="validation-error" id="country_codeErr"></div>
                            <div class="validation-error" id="telephone_country_code_idErr"></div>
                            <div class="validation-error" id="mobile_numberErr"></div>
                            <div class="contact-number-meta"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-email">Email</label>
                            <input id="edit-patient-email" class="form-control" type="email" name="email" value="<?= esc($patient['email'] ?? '') ?>">
                            <div class="validation-error" id="emailErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-gender">Gender</label>
                            <select id="edit-patient-gender" class="form-control" name="gender">
                                <option value="">Not specified</option>
                                <?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= ($patient['gender'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="genderErr"></div>
                        </div>
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-dob">Date of birth <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">required</span></label>
                            <input id="edit-patient-dob" class="form-control datepicker" type="text" name="date_of_birth" data-submit-display-date="true" placeholder="dd-mm-yyyy" autocomplete="off" value="<?= esc(! empty($patient['date_of_birth']) ? date('d-m-Y', strtotime((string) $patient['date_of_birth'])) : '') ?>" required>
                            <div class="validation-error" id="date_of_birthErr"></div>
                        </div>
                        <div class="col-12 doctor-patient-location-slot" aria-hidden="true"></div>
                        <div class="form-group col-md-4 col-12">
                            <label for="edit-patient-postal-code">Postal / PIN code</label>
                            <input id="edit-patient-postal-code" class="form-control" name="postal_code" value="<?= esc($patient['postal_code'] ?? '') ?>">
                            <div class="validation-error" id="postal_codeErr"></div>
                        </div>
                        <div class="form-group col-12">
                            <label for="edit-patient-address">Address</label>
                            <textarea id="edit-patient-address" class="form-control" name="address"><?= esc($patient['address'] ?? '') ?></textarea>
                            <div class="validation-error" id="addressErr"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" id="doctor-patient-edit-submit" type="submit">
                        <span class="doctor-patient-edit-submit-label">Save changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/doctor/patient_location.js') ?>"></script>
<script>
DoctorPatientLocation.init(
    '',
    '#doctor-patient-edit-form',
    <?= json_encode($countries ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    <?= json_encode($patient ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    , <?= json_encode($telephoneCodes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
);

$(function () {
    if (window.location.hash === '#edit-doctor-patient') {
        $('#edit-doctor-patient').modal('show');
    }

    $('#edit-doctor-patient').on('shown.bs.modal', function () {
        $('#edit-patient-first').trigger('focus');
    }).on('hidden.bs.modal', function () {
        if (window.location.hash === '#edit-doctor-patient') {
            history.replaceState(null, document.title, window.location.pathname + window.location.search);
        }
    });

    $('#doctor-patient-edit-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const submit = $('#doctor-patient-edit-submit');
        if (submit.prop('disabled')) return;
        clearFormErrors('#doctor-patient-edit-form');
        $('#doctor-patient-edit-status').removeClass('d-none alert-danger alert-success').addClass('alert-info').text('Saving patient details...');
        submit.prop('disabled', true).attr('aria-busy', 'true').find('.doctor-patient-edit-submit-label').text('Saving...');
        ajaxRequest({
            url: '<?= base_url('doctor/patients/update/' . (int) $patient['id']) ?>',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    $('#doctor-patient-edit-status').removeClass('alert-info').addClass('alert-success').text(response.msg);
                    showSuccess(response.msg);
                    reload_page(700);
                } else {
                    $('#doctor-patient-edit-status').removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Please correct the highlighted fields.');
                    showError(response.msg);
                    if (response.errors) {
                        bindFormError('#doctor-patient-edit-form', response.errors);
                    }
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                $('#doctor-patient-edit-status').removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Patient could not be updated safely.');
                handleFormAjaxError('#doctor-patient-edit-form', xhr, 'Patient could not be updated safely.');
            },
            complete: function () {
                submit.prop('disabled', false).removeAttr('aria-busy').find('.doctor-patient-edit-submit-label').text('Save changes');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
