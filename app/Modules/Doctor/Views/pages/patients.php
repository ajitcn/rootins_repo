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
            <form method="get" class="form-inline" action="<?= current_url() ?>">
                <label class="sr-only" for="doctor-patient-search">Search patients</label>
                <input id="doctor-patient-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control form-control-sm mr-2" type="search" placeholder="Search name or registration number">
                <input type="hidden" name="page_size" value="<?= (int) ($filters['page_size'] ?? 15) ?>">
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Search</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="doctor-patients-table">
                <thead><tr><th>Patient</th><th>Registration</th><th>Gender</th><th>Mobile</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($patients as $patient): ?>
                    <?php $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?>
                    <tr>
                        <td><strong><?= esc($patientName) ?></strong></td>
                        <td><?= esc($patient['registration_number']) ?></td>
                        <td><?= esc($patient['gender'] ?? '—') ?></td>
                        <td><?= esc($patient['mobile_number'] ?? '—') ?></td>
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

<div class="modal fade ri-scroll-modal" id="new-patient-modal" tabindex="-1" role="dialog" aria-labelledby="new-patient-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="doctor-patient-create-form">
                <?= csrf_field() ?>
                <input type="hidden" name="source_type" value="doctor">
                <div class="modal-header"><h2 class="modal-title h5" id="new-patient-modal-title">Create New Patient</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-4"><label for="new-patient-first">First name <span class="text-danger">*</span></label><input id="new-patient-first" class="form-control" name="first_name" required maxlength="100"><div class="validation-error" id="first_nameErr"></div></div>
                        <div class="form-group col-md-4"><label for="new-patient-middle">Middle name</label><input id="new-patient-middle" class="form-control" name="middle_name" maxlength="100"></div>
                        <div class="form-group col-md-4"><label for="new-patient-last">Last name <span class="text-danger">*</span></label><input id="new-patient-last" class="form-control" name="last_name" required maxlength="100"><div class="validation-error" id="last_nameErr"></div></div>
                        <div class="form-group col-md-4"><label for="new-patient-gender">Gender</label><select id="new-patient-gender" class="form-control btSelect" name="gender"><option value="">Not specified</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
                        <div class="form-group col-md-4"><label for="new-patient-dob">Date of birth</label><input id="new-patient-dob" class="form-control datepicker" type="text" name="date_of_birth" placeholder="dd-mm-yyyy" autocomplete="off"></div>
                        <div class="form-group col-md-4"><label for="new-patient-mobile">Mobile number</label><input id="new-patient-mobile" class="form-control" name="mobile_number" maxlength="30" inputmode="tel"></div>
                        <div class="form-group col-md-6"><label for="new-patient-email">Email</label><input id="new-patient-email" class="form-control" type="email" name="email" maxlength="255"></div>
                        <div class="form-group col-md-6"><label for="new-patient-address">Address</label><input id="new-patient-address" class="form-control" name="address" maxlength="1000"></div>
                    </div>
                    <div class="border rounded p-3 mb-3"><h3 class="h6">Create appointment now</h3><div class="custom-control custom-switch mb-3"><input class="custom-control-input" type="checkbox" id="create-appointment-now" name="create_appointment_now" value="1"><label class="custom-control-label" for="create-appointment-now">Create the first appointment in the same transaction</label></div><div id="new-patient-appointment-fields" class="form-row d-none"><div class="form-group col-md-6"><label for="appointment-doctor">Doctor</label><select id="appointment-doctor" class="form-control btSelect" name="appointment_doctor_id" disabled><option value="<?= (int) ($doctor['id'] ?? 0) ?>">Dr. <?= esc(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))) ?></option></select></div><div class="form-group col-md-6"><label for="appointment-service">Service <span class="text-danger">*</span></label><select id="appointment-service" class="form-control btSelect" name="service_name" disabled required><option value="">Select service</option><?php foreach (($services ?? []) as $service): ?><option value="<?= esc($service['name']) ?>"><?= esc($service['name']) ?></option><?php endforeach; ?></select></div><div class="form-group col-md-4"><label for="appointment-date">Date <span class="text-danger">*</span></label><input id="appointment-date" class="form-control datepicker" type="text" name="appointment_date" placeholder="dd-mm-yyyy" autocomplete="off" disabled required></div><div class="form-group col-md-4"><label for="appointment-time">Time <span class="text-danger">*</span></label><input id="appointment-time" class="form-control" type="time" name="appointment_time" disabled required></div><div class="form-group col-md-4"><label for="appointment-status">Status</label><select id="appointment-status" class="form-control btSelect" name="appointment_status" disabled><option value="scheduled">Scheduled</option><option value="confirmed">Confirmed</option></select></div><div class="form-group col-12"><label for="appointment-reason">Reason</label><textarea id="appointment-reason" class="form-control" name="appointment_reason" rows="2" maxlength="1000" disabled></textarea></div></div></div>
                    <div class="form-text text-muted">Country, state/division, city, locality, and country-code fields are added with the shared searchable location controls.</div>
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
DoctorPatientLocation.init('#doctor-patient-create-form', '', <?= json_encode($countries ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, {});
$(function () {
    $('#create-appointment-now').on('change', function () {
        $('#new-patient-appointment-fields').toggleClass('d-none', !this.checked).find(':input').prop('disabled', !this.checked);
    });
    $('#new-patient-modal').on('shown.bs.modal', function () { if (window.initSharedDatepickers) window.initSharedDatepickers(this); });
    $('#doctor-patient-create-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this), submit = form.find('[type="submit"]');
        clearFormErrors(); submit.prop('disabled', true);
        ajaxRequest({url: '<?= base_url('doctor/patients/create') ?>', data: form.serialize(), success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(700); } else { showError(response.msg); if (response.errors) bindFormError('#doctor-patient-create-form', response.errors); } }, complete: function () { submit.prop('disabled', false); }});
    });
});
</script>
<?= $this->endSection() ?>
