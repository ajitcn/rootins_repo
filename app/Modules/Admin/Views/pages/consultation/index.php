<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'OPD Consultations',
    ]) ?>

    <?php if ($canCreate): ?>
        <div class="action-btn-container">
            <button type="button" class="btn btn-sm theme-btn" id="openConsultationModal">
                + New Consultation
            </button>
        </div>
    <?php endif; ?>

    <?= display_session_msg() ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable">
            <thead class="thead-light">
                <tr>
                    <th>Consultation No.</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Clinic</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th width="140">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultations as $consultation): ?>
                    <?php
                    $patientName = trim(implode(' ', array_filter([
                        $consultation['patient_first_name'] ?? '',
                        $consultation['patient_middle_name'] ?? '',
                        $consultation['patient_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $doctorName = trim(implode(' ', array_filter([
                        $consultation['doctor_first_name'] ?? '',
                        $consultation['doctor_last_name'] ?? '',
                    ], static fn ($part) => $part !== null && $part !== '')));
                    $statusClass = match ($consultation['status']) {
                        'completed' => 'badge-success',
                        'cancelled'  => 'badge-danger',
                        'in_progress'=> 'badge-warning',
                        default      => 'badge-info',
                    };
                    ?>
                    <tr>
                        <td><strong><?= esc($consultation['consultation_number']) ?></strong></td>
                        <td>
                            <strong><?= esc($patientName) ?></strong><br>
                            <small class="text-muted"><?= esc($consultation['registration_number']) ?></small>
                        </td>
                        <td><?= esc($doctorName) ?></td>
                        <td><?= esc($consultation['clinic_name']) ?></td>
                        <td><?= esc($consultation['consultation_date']) ?></td>
                        <td><?= esc(substr((string) $consultation['consultation_time'], 0, 5)) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $consultation['status']))) ?></span></td>
                        <td>
                            <a href="<?= base_url('admin/consultations/view/' . (int) $consultation['id']) ?>"
                               class="btn btn-sm btn-info" title="View Consultation">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($canEdit && $consultation['status'] !== 'completed'): ?>
                                <button type="button" class="btn btn-sm btn-primary edit-consultation"
                                        data-id="<?= (int) $consultation['id'] ?>" title="Edit Consultation">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($canDelete && $consultation['status'] !== 'completed'): ?>
                                <button type="button" class="btn btn-sm btn-danger delete-consultation"
                                        data-id="<?= (int) $consultation['id'] ?>" title="Delete Consultation">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?= form_open('', 'id="consultationCsrfForm" class="d-none"') ?>
<?= form_close() ?>

<?php if ($canCreate || $canEdit): ?>
    <div class="modal fade" id="consultationModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Consultation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?= form_open('', 'id="consultationForm" autocomplete="off"') ?>
                    <input type="hidden" name="id" id="consultation_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="consultation_patient_id">Patient <span class="text-danger">*</span></label>
                                    <select name="patient_id" id="consultation_patient_id" class="form-control btSelect" data-live-search="true" title="Select Patient">
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <?php $name = trim(implode(' ', array_filter([$patient['first_name'] ?? '', $patient['middle_name'] ?? '', $patient['last_name'] ?? '']))); ?>
                                            <option value="<?= (int) $patient['id'] ?>"><?= esc($patient['registration_number'] . ' - ' . $name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-error" id="patient_idErr"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="consultation_doctor_id">Doctor <span class="text-danger">*</span></label>
                                    <select name="doctor_id" id="consultation_doctor_id" class="form-control btSelect" data-live-search="true" title="Select Doctor">
                                        <option value="">Select Doctor</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?= (int) $doctor['id'] ?>" data-clinic-id="<?= (int) $doctor['clinic_id'] ?>">Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name']) . ' (' . $doctor['doctor_code'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-error" id="doctor_idErr"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="consultation_clinic_id">Clinic <span class="text-danger">*</span></label>
                                    <select name="clinic_id" id="consultation_clinic_id" class="form-control btSelect" data-live-search="true" title="Select Clinic">
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
                                    <label for="consultation_appointment_id">Appointment</label>
                                    <select name="appointment_id" id="consultation_appointment_id" class="form-control btSelect" data-live-search="true" title="Optional appointment">
                                        <option value="">No linked appointment</option>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <option value="<?= (int) $appointment['id'] ?>" data-patient-id="<?= (int) $appointment['patient_id'] ?>" data-doctor-id="<?= (int) $appointment['doctor_id'] ?>" data-clinic-id="<?= (int) $appointment['clinic_id'] ?>">
                                                <?= esc($appointment['appointment_number'] . ' - ' . $appointment['appointment_date'] . ' ' . substr((string) $appointment['appointment_time'], 0, 5)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-error" id="appointment_idErr"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="consultation_date">Consultation Date <span class="text-danger">*</span></label>
                                    <input type="date" name="consultation_date" id="consultation_date" class="form-control">
                                    <div class="validation-error" id="consultation_dateErr"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="consultation_time">Time</label>
                                    <input type="time" name="consultation_time" id="consultation_time" class="form-control">
                                    <div class="validation-error" id="consultation_timeErr"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="consultation_status">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="consultation_status" class="form-control btSelect" title="Select Status">
                                        <option value="draft">Draft</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    <div class="validation-error" id="statusErr"></div>
                                </div>
                            </div>
                            <?php foreach ([
                                'chief_complaint' => 'Chief Complaint',
                                'clinical_notes'  => 'Clinical Notes',
                                'diagnosis'       => 'Diagnosis',
                                'treatment_plan'  => 'Treatment Plan',
                            ] as $field => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="consultation_<?= $field ?>"><?= $label ?></label>
                                        <textarea name="<?= $field ?>" id="consultation_<?= $field ?>" class="form-control" rows="3"></textarea>
                                        <div class="validation-error" id="<?= $field ?>Err"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="follow_up_date">Follow-up Date</label>
                                    <input type="date" name="follow_up_date" id="follow_up_date" class="form-control">
                                    <div class="validation-error" id="follow_up_dateErr"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary save">Save Consultation</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/consultation/consultation.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
