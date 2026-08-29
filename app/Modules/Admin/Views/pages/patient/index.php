<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<main class="dashboard-container">
    <?= view('templates/_breadcrumb', [
        'module'     => 'admin',
        'menu_title' => 'Patient Management',
        'page'       => 'Patients',
    ]) ?>

    <div class="action-btn-container">
        <button class="btn btn-sm theme-btn open_patient_modal">+ New Patient</button>
    </div>

    <?= display_session_msg() ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover datatable">
            <thead class="thead-light">
                <tr>
                    <th width="60">#</th>
                    <th>Registration Number</th>
                    <th>Patient Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Gender</th>
                    <th>Date of Birth</th>
                    <th>Clinic</th>
                    <th>City</th>
                    <th>Status</th>
                    <th width="90">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($patients)): ?>
                    <?php foreach ($patients as $key => $row): ?>
                        <?php
                        $patientName = trim(implode(' ', array_filter([
                            $row['first_name'] ?? '',
                            $row['middle_name'] ?? '',
                            $row['last_name'] ?? '',
                        ], static fn ($part) => $part !== null && $part !== '')));
                        ?>
                        <tr>
                            <td><?= $key + 1 ?></td>
                            <td><?= esc($row['registration_number'] ?? '') ?></td>
                            <td><strong><?= esc($patientName) ?></strong></td>
                            <td><?= esc($row['mobile_number'] ?? '') ?></td>
                            <td><?= esc($row['email'] ?? '') ?></td>
                            <td><?= esc($row['gender'] ?? '') ?></td>
                            <td><?= esc($row['date_of_birth'] ?? '') ?></td>
                            <td><?= esc($row['clinic_name'] ?? '') ?></td>
                            <td><?= esc($row['city_name'] ?? '') ?></td>
                            <td>
                                <?php if ((int) ($row['status'] ?? 0) === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= base_url('admin/patients/view/' . (int) $row['id']) ?>"
                                   class="btn btn-sm btn-info"
                                   title="View Patient">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-primary"
                                        onclick="editPatientForm(<?= (int) $row['id'] ?>)">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?= view(ADMIN_VIEW . 'pages/patient/modals/form', [
    'clinics'  => $clinics,
    'countries' => $countries,
]) ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/patient/patient.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
