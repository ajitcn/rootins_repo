<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Appointment Management',
        'page'       => 'Appointments',
    ]) ?>

    <div class="action-btn-container">
        <button type="button" class="btn btn-sm theme-btn" id="openAppointmentModal">
            + New Appointment
        </button>
    </div>

    <?= display_session_msg() ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable">
            <thead class="thead-light">
                <tr>
                    <th width="130">Appointment No.</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Clinic</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
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
                    ?>
                    <tr>
                        <td><strong><?= esc($appointment['appointment_number']) ?></strong></td>
                        <td>
                            <strong><?= esc($patientName) ?></strong><br>
                            <small class="text-muted"><?= esc($appointment['registration_number']) ?></small>
                        </td>
                        <td><?= esc($doctorName) ?></td>
                        <td><?= esc($appointment['clinic_name']) ?></td>
                        <td><?= esc($appointment['appointment_date']) ?></td>
                        <td><?= esc(substr((string) $appointment['appointment_time'], 0, 5)) ?></td>
                        <td><?= esc(ucwords(str_replace('_', ' ', $appointment['appointment_type']))) ?></td>
                        <td>
                            <span class="badge <?= $appointment['status'] === 'cancelled' ? 'badge-danger' : 'badge-info' ?>">
                                <?= esc(ucwords(str_replace('_', ' ', $appointment['status']))) ?>
                            </span>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary edit-appointment"
                                    data-id="<?= (int) $appointment['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-danger delete-appointment"
                                    data-id="<?= (int) $appointment['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
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
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="appointment_date">Date <span class="text-danger">*</span></label>
                                <input type="date" name="appointment_date" id="appointment_date" class="form-control">
                                <div class="validation-error" id="appointment_dateErr"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="appointment_time">Time <span class="text-danger">*</span></label>
                                <input type="time" name="appointment_time" id="appointment_time" class="form-control">
                                <div class="validation-error" id="appointment_timeErr"></div>
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

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
