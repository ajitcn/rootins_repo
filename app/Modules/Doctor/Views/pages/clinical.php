<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$clinicalPatients = [];
$permissions = session('permissions') ?? [];
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
foreach ($appointments as $appointment) {
    $patientId = (int) ($appointment['patient_id'] ?? 0);
    if ($patientId > 0) {
        $clinicalPatients[$patientId] = [
            'name' => trim(implode(' ', array_filter([
                $appointment['patient_first_name'] ?? '',
                $appointment['patient_middle_name'] ?? '',
                $appointment['patient_last_name'] ?? '',
            ], static fn ($part) => $part !== null && $part !== ''))),
            'registration_number' => $appointment['registration_number'] ?? '',
        ];
    }
}
?>
<main class="dashboard-container" aria-labelledby="clinical-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Appointments / clinical care</div>
            <h1 id="clinical-title" class="h3 mb-1">Clinical workspace</h1>
            <div class="text-muted">
                Today’s queue, consultation notes, prescriptions, availability, and history
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-end">
            <?php if ($clinicalPatients !== []): ?>
                <div class="form-group mb-2 mb-md-0 mr-md-2">
                    <label class="sr-only" for="clinical-patient-context">Search today’s patients</label>
                    <select
                        id="clinical-patient-context"
                        class="form-control btSelect"
                        data-live-search="true"
                        title="Search today’s patients"
                    >
                        <option value="">Search today’s patients</option>
                        <?php foreach ($clinicalPatients as $patientId => $patient): ?>
                            <option value="<?= $patientId ?>">
                                <?= esc($patient['name'] . ' · ' . $patient['registration_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <a
                class="btn btn-sm btn-light mr-2 mb-2 mb-md-0"
                href="<?= base_url('doctor/follow-ups') ?>"
            >
                <i class="bi bi-calendar2-check mr-1" aria-hidden="true"></i>
                Follow-ups
            </a>
            <span class="badge badge-light mb-2 mb-md-0">
                <i class="bi bi-shield-check mr-1" aria-hidden="true"></i>
                Clinic scoped
            </span>
        </div>
    </div>

    <div id="clinical-feedback" class="sr-only" role="status" aria-live="polite"></div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Today’s appointments and queue</h2>
        </div>
        <div class="card-body">
            <?php if ($appointments === []): ?>
                <div class="empty-state" role="status">
                    <i class="bi bi-calendar-x d-block h3 mb-2" aria-hidden="true"></i>
                    <div>No appointments found for today.</div>
                    <small>Assigned appointments will appear here when scheduled.</small>
                </div>
            <?php endif; ?>

            <?php foreach ($appointments as $appointment): ?>
                <?php
                $appointmentId = (int) $appointment['id'];
                $patientName = trim(implode(' ', array_filter([
                    $appointment['patient_first_name'] ?? '',
                    $appointment['patient_middle_name'] ?? '',
                    $appointment['patient_last_name'] ?? '',
                ], static fn ($part) => $part !== null && $part !== '')));
                ?>
                <article class="border-bottom pb-3 mb-3" data-appointment="<?= $appointmentId ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <strong>
                            <?= esc($appointment['appointment_number']) ?> · <?= esc($patientName) ?>
                        </strong>
                        <span class="badge badge-light">
                            <?= esc(ucwords(str_replace('_', ' ', $appointment['status']))) ?>
                        </span>
                    </div>
                    <div class="text-muted small mb-2">
                        <?= esc($appointment['appointment_date']) ?>
                        <?= esc(substr((string) $appointment['appointment_time'], 0, 5)) ?>
                        · <?= esc($appointment['registration_number']) ?>
                    </div>
                    <div class="mb-2">
                        <a
                            class="btn btn-sm btn-outline-primary mr-1"
                            href="<?= base_url('doctor/patients/' . (int) $appointment['patient_id']) ?>"
                        >
                            Open patient
                        </a>
                        <?php if ($can('appointment.edit')): ?>
                            <?php foreach (['checked_in' => 'Check in', 'in_queue' => 'Queue', 'completed' => 'Complete', 'no_show' => 'No show'] as $status => $label): ?>
                                <button
                                    class="btn btn-sm btn-outline-secondary queue-action mr-1"
                                    type="button"
                                    data-id="<?= $appointmentId ?>"
                                    data-status="<?= esc($status) ?>"
                                >
                                    <?= esc($label) ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($can('consultation.edit')): ?>
                        <form class="consultation-form mb-3" data-appointment-id="<?= $appointmentId ?>">
                            <?= csrf_field() ?>
                        <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                        <input type="hidden" name="status" value="completed">
                        <div class="form-row">
                            <div class="col-md-4">
                                <label for="complaint-<?= $appointmentId ?>">Chief complaint</label>
                                <input
                                    id="complaint-<?= $appointmentId ?>"
                                    class="form-control form-control-sm mb-2"
                                    name="chief_complaint"
                                >
                                <div class="validation-error" id="chief_complaint_<?= $appointmentId ?>Err"></div>
                            </div>
                            <div class="col-md-8">
                                <label for="notes-<?= $appointmentId ?>">Consultation notes</label>
                                <textarea
                                    id="notes-<?= $appointmentId ?>"
                                    class="form-control form-control-sm mb-2"
                                    name="clinical_notes"
                                ></textarea>
                                <div class="validation-error" id="clinical_notes_<?= $appointmentId ?>Err"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-5">
                                <label for="diagnosis-<?= $appointmentId ?>">Diagnosis</label>
                                <textarea
                                    id="diagnosis-<?= $appointmentId ?>"
                                    class="form-control form-control-sm mb-2"
                                    name="diagnosis"
                                ></textarea>
                            </div>
                            <div class="col-md-5">
                                <label for="treatment-<?= $appointmentId ?>">Treatment plan</label>
                                <textarea
                                    id="treatment-<?= $appointmentId ?>"
                                    class="form-control form-control-sm mb-2"
                                    name="treatment_plan"
                                ></textarea>
                            </div>
                            <div class="col-md-2">
                                <label for="follow-up-<?= $appointmentId ?>">Follow-up</label>
                                <input
                                    id="follow-up-<?= $appointmentId ?>"
                                    class="form-control form-control-sm mb-2 datepicker"
                                    type="text"
                                    placeholder="dd-mm-yyyy"
                                    autocomplete="off"
                                    name="follow_up_date"
                                >
                            </div>
                        </div>
                            <button class="btn btn-sm btn-primary save-consultation" type="button">
                                Save completed consultation
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($can('prescription.create')): ?>
                        <form class="prescription-form" data-appointment-id="<?= $appointmentId ?>">
                            <?= csrf_field() ?>
                        <input
                            type="hidden"
                            name="consultation_id"
                            value="<?= (int) ($appointment['consultation_id'] ?? 0) ?>"
                        >
                        <input type="hidden" name="status" value="submitted">
                        <div class="form-row">
                            <div class="col-md-5">
                                <label for="medicine-<?= $appointmentId ?>">Medicine</label>
                                <input
                                    id="medicine-<?= $appointmentId ?>"
                                    class="form-control form-control-sm"
                                    name="items[0][medicine_name]"
                                >
                            </div>
                            <div class="col-md-2">
                                <label for="quantity-<?= $appointmentId ?>">Quantity</label>
                                <input
                                    id="quantity-<?= $appointmentId ?>"
                                    class="form-control form-control-sm"
                                    name="items[0][quantity]"
                                >
                            </div>
                            <div class="col-md-5">
                                <label for="instructions-<?= $appointmentId ?>">Instructions</label>
                                <input
                                    id="instructions-<?= $appointmentId ?>"
                                    class="form-control form-control-sm"
                                    name="items[0][instructions]"
                                >
                            </div>
                        </div>
                            <button class="btn btn-sm btn-outline-primary mt-2 save-prescription" type="button">
                                Submit prescription
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]) ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Availability</h2>
                </div>
                <div class="card-body">
                    <?php if ($schedules === []): ?>
                        <p class="text-muted mb-0">No active schedule.</p>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($schedules as $schedule): ?>
                                <li>
                                    <?= esc($schedule['day_of_week']) ?>:
                                    <?= esc($schedule['start_time']) ?>–<?= esc($schedule['end_time']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Upcoming leave</h2>
                </div>
                <div class="card-body">
                    <?php if ($leaves === []): ?>
                        <p class="text-muted mb-0">No approved leave.</p>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($leaves as $leave): ?>
                                <li>
                                    <?= esc($leave['start_date']) ?>–<?= esc($leave['end_date']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Consultation history</h2>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Consultation</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td><?= esc($item['consultation_number']) ?></td>
                            <td>
                                <?= esc(trim(($item['patient_first_name'] ?? '') . ' ' . ($item['patient_last_name'] ?? ''))) ?>
                            </td>
                            <td><?= esc($item['consultation_date']) ?></td>
                            <td><?= esc($item['status']) ?></td>
                            <td><?= esc($item['follow_up_date'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($history === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No consultation history yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $historyPagination ?? [], 'filters' => $filters ?? []]) ?>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    $('#clinical-patient-context').on('change', function () {
        const patientId = $(this).val();
        if (patientId) {
            window.location.href = '<?= base_url('doctor/patients') ?>/' + patientId;
        }
    });

    $('.queue-action').on('click', function () {
        const button = $(this);
        const appointment = button.closest('[data-appointment]');
        button.prop('disabled', true);
        ajaxRequest({
            url: '<?= base_url('doctor/appointments/queue') ?>/' + button.data('id'),
            data: {
                status: button.data('status'),
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
            },
            success: function (response) {
                if (response.status) {
                    $('#clinical-feedback').text(response.msg);
                    showSuccess(response.msg);
                    reload_page(500);
                } else {
                    showError(response.msg);
                    button.prop('disabled', false);
                }
            },
            error: function () {
                button.prop('disabled', false);
            }
        });
    });

    $('.save-consultation').on('click', function () {
        const button = $(this);
        const form = button.closest('form');
        button.prop('disabled', true);
        ajaxRequest({
            url: '<?= base_url('doctor/consultations/save') ?>',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    form.find('input[name="status"]').val('completed');
                    form.closest('[data-appointment]')
                        .find('.prescription-form input[name="consultation_id"]')
                        .val(response.data.consultation_id);
                    $('#clinical-feedback').text(response.msg);
                    showSuccess(response.msg);
                } else {
                    showError(response.msg);
                }
                button.prop('disabled', false);
            },
            error: function () {
                button.prop('disabled', false);
            }
        });
    });

    $('.save-prescription').on('click', function () {
        const button = $(this);
        const form = button.closest('form');
        button.prop('disabled', true);
        ajaxRequest({
            url: '<?= base_url('doctor/prescriptions/save') ?>',
            data: form.serialize(),
            success: function (response) {
                if (response.status) {
                    $('#clinical-feedback').text(response.msg);
                    showSuccess(response.msg);
                    reload_page(700);
                } else {
                    showError(response.msg);
                }
                button.prop('disabled', false);
            },
            error: function () {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
