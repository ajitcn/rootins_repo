<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$formatDate = static function (?string $value): string {
    if (! $value) {
        return '—';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date ? $date->format('d-m-Y') : '—';
};
$label = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$today = (new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
?>
<main class="patient-page patient-appointments-page" aria-labelledby="patient-appointments-title">
    <header class="patient-page-heading">
        <div>
            <span class="patient-eyebrow">Patient portal / schedule</span>
            <h1 id="patient-appointments-title">My appointments</h1>
            <p class="text-muted mb-0">Review your appointments and manage eligible bookings.</p>
            <span class="small text-muted">Patient: <?= esc(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?> · <?= esc($patient['registration_number'] ?? '—') ?> · <?= esc($patient['mobile_number'] ?? '—') ?></span>
        </div>
        <div class="patient-page-heading-actions">
            <span class="badge badge-light"><i class="bi bi-calendar-check mr-1" aria-hidden="true"></i><?= (int) ($pagination['total'] ?? 0) ?> total</span>
            <a class="btn btn-primary btn-sm" href="<?= base_url('patient/portal') ?>#booking-form"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>Book appointment</a>
        </div>
    </header>

    <section class="card patient-list-toolbar" aria-label="Appointment filters">
        <form method="get" action="<?= current_url() ?>" class="patient-filter-form">
            <input type="hidden" name="view" value="<?= esc($filters['view']) ?>">
            <div class="patient-filter-search">
                <label class="sr-only" for="patient-appointment-search">Search appointments</label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="patient-appointment-search" class="form-control" type="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Doctor, service, appointment, queue or status">
            </div>
            <select class="form-control form-control-sm" name="status" aria-label="Appointment status">
                <option value="">All statuses</option>
                <?php foreach (['scheduled', 'confirmed', 'checked_in', 'in_queue', 'in_consultation', 'completed', 'cancelled', 'no_show'] as $option): ?>
                    <option value="<?= esc($option) ?>" <?= $filters['status'] === $option ? 'selected' : '' ?>><?= esc($label($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control form-control-sm" name="doctor_id" aria-label="Doctor">
                <option value="">All doctors</option>
                <?php foreach ($doctors as $doctor): ?>
                    <option value="<?= (int) $doctor['id'] ?>" <?= (string) $filters['doctor_id'] === (string) $doctor['id'] ? 'selected' : '' ?>>Dr. <?= esc(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control form-control-sm" name="service" aria-label="Service">
                <option value="">All services</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= esc($service['service_name']) ?>" <?= $filters['service'] === $service['service_name'] ? 'selected' : '' ?>><?= esc($service['service_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_from" value="<?= esc($filters['date_from']) ?>" placeholder="From dd-mm-yyyy" aria-label="Appointment date from">
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_to" value="<?= esc($filters['date_to']) ?>" placeholder="To dd-mm-yyyy" aria-label="Appointment date to">
            <select class="form-control form-control-sm" name="page_size" aria-label="Page size">
                <?php foreach ([10, 15, 25, 50] as $size): ?>
                    <option value="<?= $size ?>" <?= (int) $filters['page_size'] === $size ? 'selected' : '' ?>><?= $size ?> / page</option>
                <?php endforeach; ?>
            </select>
            <div class="patient-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Apply</button>
                <a class="btn btn-light btn-sm" href="<?= current_url() ?>">Clear</a>
            </div>
            <div class="patient-view-toggle" role="group" aria-label="Appointment view">
                <a class="btn btn-sm <?= $filters['view'] === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'list', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'list' ? 'true' : 'false' ?>"><i class="bi bi-list-ul mr-1" aria-hidden="true"></i>List</a>
                <a class="btn btn-sm <?= $filters['view'] === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'grid', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'grid' ? 'true' : 'false' ?>"><i class="bi bi-grid mr-1" aria-hidden="true"></i>Grid</a>
            </div>
        </form>
    </section>

    <section class="card patient-appointments-content">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h2 class="h5 mb-1">Appointment history</h2>
                <span class="small text-muted">Patient number: <?= esc($patient['registration_number'] ?? '—') ?></span>
            </div>
            <span class="small text-muted"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created and updated times are shown in your clinic timezone.</span>
        </div>
        <?php if ($filters['view'] === 'grid'): ?>
            <?= view(PATIENT_VIEW . 'pages/appointments_grid', compact('appointments', 'formatDate', 'label', 'today')) ?>
        <?php else: ?>
            <?= view(PATIENT_VIEW . 'pages/appointments_list', compact('appointments', 'formatDate', 'label', 'today')) ?>
        <?php endif; ?>
    </section>

    <?= view(PATIENT_VIEW . 'parts/pagination', ['pagination' => $pagination, 'filters' => $filters, 'pageSize' => $filters['page_size']]) ?>

    <div class="modal fade patient-appointment-modal" id="patient-appointment-detail-modal" tabindex="-1" role="dialog" aria-labelledby="patient-appointment-detail-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header patient-appointment-detail-header">
                    <div>
                        <span class="patient-eyebrow">Patient appointment</span>
                        <h2 class="modal-title h5" id="patient-appointment-detail-title">Appointment Details</h2>
                    </div>
                    <div class="patient-appointment-detail-status" id="patient-appointment-detail-status" aria-live="polite"></div>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="patient-appointment-detail-body" aria-live="polite"><div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading appointment details…</div></div>
                <div class="modal-footer patient-appointment-detail-footer">
                    <div class="patient-appointment-detail-actions" id="patient-appointment-detail-actions"></div>
                    <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade patient-appointment-modal" id="patient-appointment-edit-modal" tabindex="-1" role="dialog" aria-labelledby="patient-appointment-edit-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <form class="modal-content" id="patient-appointment-edit-form">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title h5" id="patient-appointment-edit-title">Reschedule appointment</h2><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="patient-edit-appointment-id">
                    <div class="form-group"><label for="patient-edit-date">Appointment date</label><input class="form-control datepicker" id="patient-edit-date" name="appointment_date" placeholder="dd-mm-yyyy" autocomplete="off"><div class="validation-error" id="appointment_dateErr"></div></div>
                    <div class="form-group"><label for="patient-edit-time">Appointment time</label><input class="form-control" id="patient-edit-time" name="appointment_time" type="time"><div class="validation-error" id="appointment_timeErr"></div></div>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button><button class="btn btn-primary" type="submit" id="patient-reschedule-submit">Save changes</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade patient-appointment-modal" id="patient-appointment-cancel-modal" tabindex="-1" role="dialog" aria-labelledby="patient-appointment-cancel-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <form class="modal-content" id="patient-appointment-cancel-form">
                <?= csrf_field() ?>
                <div class="modal-header"><h2 class="modal-title h5" id="patient-appointment-cancel-title">Cancel appointment</h2><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body"><input type="hidden" name="appointment_id" id="patient-cancel-appointment-id"><div class="form-group"><label for="patient-cancel-reason">Reason</label><textarea class="form-control" id="patient-cancel-reason" name="cancellation_reason" rows="3" required></textarea><div class="validation-error" id="cancellation_reasonErr"></div></div><p class="small text-muted mb-0">This action cannot be undone.</p></div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Keep appointment</button><button class="btn btn-danger" type="submit" id="patient-cancel-submit">Cancel appointment</button></div>
            </form>
        </div>
    </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        var records = <?= json_encode($appointments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>; window.patientPdfRecords = records;
        var saving = false;
        var escapeHtml = function (value) { return $('<div>').text(value == null ? '' : value).html(); };

        $('.patient-date-filter, #patient-edit-date').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true,
            orientation: 'bottom auto',
            container: '.patient-appointments-page',
        });

        $(document).on('show.bs.dropdown.patientAppointments', '.patient-appointments-page .dropdown', function () {
            var dropdown = $(this);
            var menu = dropdown.children('.dropdown-menu');

            if (! menu.length) return;

            menu.data('patient-dropdown-parent', dropdown);
            menu.appendTo('body');
        });

        $(document).on('hidden.bs.dropdown.patientAppointments', '.patient-appointments-page .dropdown', function () {
            var dropdown = $(this);
            var menu = $('body > .dropdown-menu').filter(function () {
                var parent = $(this).data('patient-dropdown-parent');

                return parent && parent.is(dropdown);
            }).first();

            if (menu.length) {
                menu.appendTo(dropdown);
                menu.removeData('patient-dropdown-parent');
            }
        });

        $(document).on('click.patientAppointmentDetails', '.patient-appointment-view', function () {
            var appointment = records.find(function (item) { return Number(item.id) === Number($(this).data('id')); }.bind(this));

            $('#patient-appointment-detail-body').html('<div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading appointment details…</div>');
            $('#patient-appointment-detail-actions').empty();
            $('#patient-appointment-detail-status').empty();

            if (! appointment) {
                $('#patient-appointment-detail-body').html('<div class="patient-modal-state patient-modal-state-error" role="alert"><i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>Appointment details could not be found.</div>');
                return;
            }

            var statusClass = ['confirmed', 'checked_in', 'in_queue', 'in_consultation'].indexOf(appointment.status) !== -1
                ? 'badge-success'
                : (['cancelled', 'no_show'].indexOf(appointment.status) !== -1 ? 'badge-secondary' : 'badge-light');
            var actionMarkup = '';

            if (appointment.patient_can_edit) {
                actionMarkup += '<button class="btn btn-sm btn-outline-primary patient-appointment-reschedule mr-2" type="button" data-id="' + Number(appointment.id) + '" data-date-display="' + escapeHtml(appointment.appointment_date_display) + '" data-time="' + escapeHtml(appointment.appointment_time_display) + '"><i class="bi bi-calendar2-event mr-1" aria-hidden="true"></i>Reschedule</button>';
                actionMarkup += '<button class="btn btn-sm btn-outline-danger patient-appointment-cancel" type="button" data-id="' + Number(appointment.id) + '"><i class="bi bi-x-circle mr-1" aria-hidden="true"></i>Cancel</button>';
            }

            if (Number(appointment.prescription_id || 0) > 0) {
                actionMarkup += '<a class="btn btn-sm btn-outline-secondary mr-2" href="<?= base_url('patient/prescriptions') ?>?prescription_id=' + Number(appointment.prescription_id) + '"><i class="bi bi-file-medical mr-1" aria-hidden="true"></i>Prescription</a>';
            }

            if (Number(appointment.invoice_id || 0) > 0) {
                actionMarkup += '<a class="btn btn-sm btn-outline-secondary mr-2" href="<?= base_url('patient/invoices') ?>?invoice_id=' + Number(appointment.invoice_id) + '"><i class="bi bi-receipt mr-1" aria-hidden="true"></i>Invoice</a>';
            }

            if (Number(appointment.order_id || 0) > 0) {
                actionMarkup += '<a class="btn btn-sm btn-outline-secondary" href="<?= base_url('patient/orders') ?>?order_id=' + Number(appointment.order_id) + '"><i class="bi bi-box-seam mr-1" aria-hidden="true"></i>Order / delivery</a>';
            }

            $('#patient-appointment-detail-title').text('Appointment Details · ' + (appointment.appointment_number || '—'));
            $('#patient-appointment-detail-status').html('<span class="badge ' + statusClass + '">' + escapeHtml(appointment.status_label || 'Status unavailable') + '</span>');
            $('#patient-appointment-detail-body').html(
                '<section class="patient-modal-section"><h3><i class="bi bi-calendar2-check mr-2" aria-hidden="true"></i>Appointment information</h3><div class="patient-detail-grid">'
                + '<div><span>Appointment number</span><strong>' + escapeHtml(appointment.appointment_number || '—') + '</strong></div>'
                + '<div><span>Status</span><strong>' + escapeHtml(appointment.status_label || '—') + '</strong></div>'
                + '<div><span>Date / time</span><strong>' + escapeHtml(appointment.appointment_date_display || '—') + ' · ' + escapeHtml(appointment.appointment_time_display || '—') + '</strong></div>'
                + '<div><span>Queue number</span><strong>' + escapeHtml(appointment.queue_position || '—') + '</strong></div>'
                + '</div></section>'
                + '<section class="patient-modal-section"><h3><i class="bi bi-person-badge mr-2" aria-hidden="true"></i>Doctor information</h3><div class="patient-detail-grid">'
                + '<div><span>Doctor</span><strong>Dr. ' + escapeHtml((appointment.doctor_first_name || '') + ' ' + (appointment.doctor_last_name || '')) + '</strong></div>'
                + '<div><span>Specialization</span><strong>' + escapeHtml(appointment.specialization || '—') + '</strong></div>'
                + '</div></section>'
                + '<section class="patient-modal-section"><h3><i class="bi bi-clipboard2-pulse mr-2" aria-hidden="true"></i>Service and visit</h3><div class="patient-detail-grid">'
                + '<div><span>Service</span><strong>' + escapeHtml(appointment.service_name || '—') + '</strong></div>'
                + '<div><span>Appointment type</span><strong>' + escapeHtml(appointment.appointment_type_label || '—') + '</strong></div>'
                + '</div>'
                + (appointment.reason_for_visit ? '<div class="patient-safe-remarks"><span>Remarks</span><p>' + escapeHtml(appointment.reason_for_visit) + '</p></div>' : '')
                + '</section>'
                + '<p class="patient-modal-metadata"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created: ' + escapeHtml(appointment.created_at_display || '—') + '<br>Updated: ' + escapeHtml(appointment.updated_at_display || '—') + '</p>'
            );
            $('#patient-appointment-detail-actions').html(actionMarkup);
        });

        $(document).on('click.patientAppointmentEdit', '.patient-appointment-reschedule', function () {
            var button = $(this);
            $('#patient-edit-appointment-id').val(button.data('id'));
            $('#patient-edit-date').val(button.data('date-display'));
            $('#patient-edit-time').val(button.data('time'));
            clearFormErrors('#patient-appointment-edit-form');
            $('#patient-appointment-edit-modal').modal('show');
        });

        $(document).on('click.patientAppointmentCancel', '.patient-appointment-cancel', function () {
            $('#patient-cancel-appointment-id').val($(this).data('id'));
            $('#patient-cancel-reason').val('');
            clearFormErrors('#patient-appointment-cancel-form');
            $('#patient-appointment-cancel-modal').modal('show');
        });

        $('#patient-appointment-edit-form, #patient-appointment-cancel-form').on('submit', function (event) {
            event.preventDefault();

            if (saving) return;

            var form = $(this);
            var appointmentId = form.find('input[name="appointment_id"]').val();
            var isCancel = form.attr('id') === 'patient-appointment-cancel-form';

            if (isCancel && ! window.confirm('Cancel this appointment?')) return;

            saving = true;
            clearFormErrors(form);
            form.find('button[type="submit"]').prop('disabled', true).attr('aria-busy', 'true');
            ajaxRequest({
                url: '<?= base_url('patient/appointments') ?>/' + (isCancel ? 'cancel/' : 'reschedule/') + encodeURIComponent(appointmentId),
                method: 'POST',
                data: form.serialize(),
                success: function (response) {
                    if (response.status) {
                        showSuccess(response.msg);
                        window.setTimeout(function () { window.location.reload(); }, 700);
                    } else {
                        bindFormError(form, response.errors || {});
                        showError(response.msg);
                    }
                },
                error: function (xhr) {
                    handleFormAjaxError(form, xhr, 'Appointment could not be updated safely.');
                },
                complete: function () {
                    saving = false;
                    form.find('button[type="submit"]').prop('disabled', false).removeAttr('aria-busy');
                },
            });
        });
    });
</script>
<?= $this->endSection() ?>
