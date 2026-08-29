<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Appointment Management',
        'page'       => 'Waiting Queue',
    ]) ?>

    <?= form_open('', 'id="appointmentQueueForm" autocomplete="off"') ?>
        <div class="row align-items-end mb-3">
            <div class="col-md-5">
                <label for="queueClinic">Clinic</label>
                <select id="queueClinic" name="clinic_id" class="form-control btSelect" title="Select Clinic">
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= (int) $clinic['id'] ?>" <?= (int) $clinic['id'] === $selectedClinic ? 'selected' : '' ?>>
                            <?= esc($clinic['clinic_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="queueDate">Date</label>
                <input type="date" id="queueDate" name="appointment_date" class="form-control" value="<?= esc($selectedDate) ?>">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn theme-btn" id="loadAppointmentQueue">Load Queue</button>
                <a href="<?= base_url('admin/appointments') ?>" class="btn btn-outline-secondary">List View</a>
            </div>
        </div>
    <?= form_close() ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable" id="appointmentQueueTable">
            <thead class="thead-light">
                <tr>
                    <th width="80">Position</th>
                    <th>Appointment No.</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Checked In</th>
                    <th>Status</th>
                    <th width="240">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</main>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/appointment/appointment_queue.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
