<?= $this->extend(DOCTOR_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="doctor-followups-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Continuity of care</div>
            <h1 id="doctor-followups-title">Follow-ups</h1>
            <p class="text-muted mb-0">Upcoming follow-up dates from your clinic-scoped consultations.</p>
        </div>
        <div class="d-flex flex-wrap">
            <button class="btn btn-primary mr-2 mb-2" type="button" data-toggle="modal" data-target="#doctor-followup-create-modal">
                <i class="bi bi-calendar-plus mr-1" aria-hidden="true"></i> New Follow-up
            </button>
            <a class="btn btn-outline-secondary mb-2" href="<?= base_url('doctor/clinical') ?>">Open clinical workspace</a>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <h2 class="h5 mb-2 mb-md-0">Follow-up register</h2>
                <form class="form-inline" method="get" action="<?= current_url() ?>">
                    <label class="sr-only" for="doctor-followup-search">Search follow-ups</label>
                    <input id="doctor-followup-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control form-control-sm mr-2 mb-2 mb-md-0" type="search" placeholder="Search patient or consultation">
                    <input type="hidden" name="page_size" value="<?= esc((string) ($filters['page_size'] ?? 15)) ?>">
                    <button class="btn btn-sm btn-outline-primary mb-2 mb-md-0" type="submit">Search</button>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="doctor-followups-table">
                <caption class="sr-only">Doctor follow-up register</caption>
                <thead><tr><th>Follow-up date</th><th>Patient</th><th>Consultation</th><th>Status</th><th>Reason / notes</th></tr></thead>
                <tbody>
                <?php foreach ($followUps as $item): ?>
                    <tr>
                        <td><strong><?= esc($item['follow_up_date']) ?></strong></td>
                        <td><?= esc(trim(($item['patient_first_name'] ?? '') . ' ' . ($item['patient_last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($item['registration_number'] ?? '') ?></small></td>
                        <td><?= esc($item['consultation_number']) ?></td>
                        <td><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', $item['status']))) ?></span></td>
                        <td><?= esc($item['treatment_plan'] ?? $item['clinical_notes'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($followUps === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No follow-ups scheduled.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($followUps === []): ?><div class="empty-state"><i class="bi bi-calendar2-check d-block h3 mb-2" aria-hidden="true"></i><small>Follow-up dates recorded during consultations will appear here.</small></div><?php endif; ?>
    </div>
    <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]) ?>
</main>

<div class="modal fade doctor-followup-create-modal ri-scroll-modal" id="doctor-followup-create-modal" tabindex="-1" role="dialog" aria-labelledby="doctor-followup-create-title" aria-hidden="true" data-backdrop="true" data-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="doctor-followup-create-title">New Follow-up</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close follow-up form"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="doctor-followup-create-form" novalidate>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <div id="doctor-followup-create-status" class="alert d-none" role="status" aria-live="polite"></div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-patient">Patient <span class="text-danger">*</span></label>
                            <select id="doctor-followup-patient" name="patient_id" class="form-control btSelect" data-live-search="true" required>
                                <option value="">Select patient</option>
                                <?php foreach ($patients ?? [] as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= esc(trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?> (<?= esc($patient['registration_number'] ?? '') ?>)</option><?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="patient_idErr"></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-date">Follow-up date <span class="text-danger">*</span></label>
                            <input id="doctor-followup-date" type="text" name="follow_up_date" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off" required>
                            <div class="validation-error" id="follow_up_dateErr"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-consultation">Consultation <span class="text-danger">*</span></label>
                            <select id="doctor-followup-consultation" name="consultation_id" class="form-control btSelect" data-live-search="true" required>
                                <option value="">Select consultation</option>
                                <?php foreach ($consultations ?? [] as $item): ?><option value="<?= (int) $item['id'] ?>" data-patient-id="<?= (int) $item['patient_id'] ?>"><?= esc($item['consultation_number']) ?> · <?= esc($item['consultation_date']) ?></option><?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="consultation_idErr"></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="doctor-followup-appointment">Appointment reference</label>
                            <select id="doctor-followup-appointment" name="appointment_id" class="form-control btSelect" data-live-search="true">
                                <option value="">Use consultation appointment</option>
                                <?php foreach ($appointments ?? [] as $item): ?><option value="<?= (int) $item['id'] ?>" data-patient-id="<?= (int) $item['patient_id'] ?>"><?= esc($item['appointment_number']) ?> · <?= esc($item['appointment_date']) ?></option><?php endforeach; ?>
                            </select>
                            <div class="validation-error" id="appointment_idErr"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-followup-reason">Reason <span class="text-danger">*</span></label>
                        <textarea id="doctor-followup-reason" name="reason" class="form-control" rows="2" maxlength="1000" required></textarea>
                        <div class="validation-error" id="reasonErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-followup-notes">Notes</label>
                        <textarea id="doctor-followup-notes" name="notes" class="form-control" rows="3" maxlength="5000"></textarea>
                        <div class="validation-error" id="notesErr"></div>
                    </div>
                    <div class="form-group">
                        <label for="doctor-followup-status">Status <span class="text-danger">*</span></label>
                        <select id="doctor-followup-status" name="status" class="form-control" required><option value="draft">Draft</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
                        <div class="validation-error" id="statusErr"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" id="doctor-followup-create-submit" class="btn btn-primary"><span class="doctor-followup-submit-label">Create follow-up</span></button></div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
$(function () {
    const form = $('#doctor-followup-create-form');
    const consultation = $('#doctor-followup-consultation');
    const appointment = $('#doctor-followup-appointment');
    $('#doctor-followup-patient').on('changed.bs.select change', function () {
        const patientId = String($(this).val() || '');
        [consultation, appointment].forEach(function (select) {
            select.find('option[data-patient-id]').each(function () { $(this).toggle(String($(this).data('patient-id')) === patientId || patientId === ''); });
            select.selectpicker('refresh');
        });
        consultation.val('').selectpicker('refresh');
        appointment.val('').selectpicker('refresh');
    });
    $('#doctor-followup-create-modal').on('shown.bs.modal', function () { $('#doctor-followup-patient').selectpicker('refresh').trigger('focus'); });
    form.on('submit', function (event) {
        event.preventDefault();
        const submit = $('#doctor-followup-create-submit');
        if (submit.prop('disabled')) return;
        clearFormErrors();
        $('#doctor-followup-create-status').removeClass('d-none alert-danger alert-success').addClass('alert-info').text('Saving follow-up...');
        submit.prop('disabled', true).attr('aria-busy', 'true').find('.doctor-followup-submit-label').text('Saving...');
        ajaxRequest({
            url: '<?= base_url('doctor/follow-ups/create') ?>', method: 'POST', data: form.serialize(),
            success: function (response) {
                if (response.status) { $('#doctor-followup-create-status').removeClass('alert-info').addClass('alert-success').text(response.msg); showSuccess(response.msg); reload_page(700); return; }
                $('#doctor-followup-create-status').removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Please correct the highlighted fields.');
                showError(response.msg); if (response.errors) bindFormError('#doctor-followup-create-form', response.errors);
            },
            error: function (xhr) { const response = xhr.responseJSON || {}; $('#doctor-followup-create-status').removeClass('alert-info').addClass('alert-danger').text(response.msg || 'Follow-up could not be created safely.'); if (response.errors) bindFormError('#doctor-followup-create-form', response.errors); showError(response.msg || 'Follow-up could not be created safely.'); },
            complete: function () { submit.prop('disabled', false).removeAttr('aria-busy').find('.doctor-followup-submit-label').text('Create follow-up'); }
        });
    });
});
</script>
<?= $this->endSection() ?>
