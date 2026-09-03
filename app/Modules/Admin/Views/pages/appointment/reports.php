<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Appointment Management',
        'page'       => 'Appointment Reports',
    ]) ?>

    <?= form_open('', 'id="appointmentReportForm" autocomplete="off"') ?>
        <div class="row align-items-end mb-3">
            <div class="col-md-3">
                <label for="reportDateFrom">Date From</label>
                <input type="text" name="date_from" id="reportDateFrom" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label for="reportDateTo">Date To</label>
                <input type="text" name="date_to" id="reportDateTo" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label for="reportClinic">Clinic</label>
                <select name="clinic_id" id="reportClinic" class="form-control btSelect" title="All Clinics">
                    <option value="">All Clinics</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int) $clinic['id'] ?>"><?= esc($clinic['clinic_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="reportDoctor">Doctor</label>
                <select name="doctor_id" id="reportDoctor" class="form-control btSelect" title="All Doctors">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?= (int) $doctor['id'] ?>">
                            Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name']) . ' (' . $doctor['doctor_code'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mt-3">
                <label for="reportType">Appointment Type</label>
                <select name="appointment_type" id="reportType" class="form-control btSelect" title="All Types">
                    <option value="">All Types</option>
                    <option value="in_person">In Person</option>
                    <option value="video">Video</option>
                    <option value="phone">Phone</option>
                </select>
            </div>
            <div class="col-md-3 mt-3">
                <label for="reportStatus">Status</label>
                <select name="status" id="reportStatus" class="form-control btSelect" title="All Statuses">
                    <option value="">All Statuses</option>
                    <?php foreach (['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'] as $status): ?>
                        <option value="<?= esc($status) ?>"><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mt-3">
                <label for="reportSource">Booking Source</label>
                <select name="booking_source" id="reportSource" class="form-control btSelect" title="All Sources">
                    <option value="">All Sources</option>
                    <?php foreach ($bookingSources as $source): ?>
                        <option value="<?= esc($source) ?>"><?= esc(ucwords(str_replace('_', ' ', $source))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mt-3">
                <label for="reportCreatorType">Created By Type</label>
                <select name="created_by_type" id="reportCreatorType" class="form-control btSelect" title="All Creator Types">
                    <option value="">All Creator Types</option>
                    <?php foreach ($creatorTypes as $type): ?>
                        <option value="<?= esc($type) ?>"><?= esc(ucwords(str_replace('_', ' ', $type))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn theme-btn">Run Report</button>
                <button type="button" class="btn btn-outline-secondary" id="resetAppointmentReport">Reset</button>
            </div>
        </div>
    <?= form_close() ?>

    <div class="row mb-3" id="appointmentReportSummary">
        <?php foreach ([
            'total' => 'Total Appointments', 'scheduled' => 'Scheduled', 'confirmed' => 'Confirmed',
            'checked_in' => 'Checked In', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show',
        ] as $key => $title): ?>
            <div class="col-md-3 col-lg mb-2">
                <div class="card h-100"><div class="card-body py-3">
                    <small class="text-muted d-block"><?= esc($title) ?></small>
                    <strong class="h4 mb-0" data-summary="<?= esc($key) ?>">0</strong>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="appointmentReportTable">
            <thead class="thead-light"><tr>
                <th>Appointment No.</th><th>Date</th><th>Time</th><th>Patient Reg. No.</th>
                <th>Patient</th><th>Doctor</th><th>Clinic</th><th>Type</th><th>Status</th>
                <th>Booking Source</th><th>Created By</th><th>Created At</th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</main>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment_reports.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
