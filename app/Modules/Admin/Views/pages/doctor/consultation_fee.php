<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<main class="dashboard-container" aria-labelledby="consultation-fee-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Doctor Management', 'page' => 'Consultation Fee']) ?>

    <div class="page-heading mb-3">
        <div>
            <h1 id="consultation-fee-title" class="h3 mb-1">Consultation Fees</h1>
            <p class="text-muted mb-0">Manage doctor consultation fees by type, mode, and effective date.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            <div class="mr-2 mb-2 mb-md-0">
                <?= view(ADMIN_VIEW . 'parts/doctor_list_filter', ['filterId' => 'doctorFeeFilter', 'filters' => $filters ?? []]) ?>
            </div>
            <button class="btn btn-primary mb-2 mb-md-0 open_doctor_fee_modal" type="button">
                <i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New Consultation Fee
            </button>
        </div>
    </div>

    <?= display_session_msg() ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <h2 class="h6 mb-0">Doctor consultation fee list</h2>
            <span class="small text-muted">Use filters to narrow the permitted scope.</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="consultationFeeTable">
                    <thead>
                        <tr>
                            <th scope="col" width="50">#</th>
                            <th scope="col">Doctor</th>
                            <th scope="col">Type</th>
                            <th scope="col">Mode</th>
                            <th scope="col">Fee</th>
                            <th scope="col">Effective From</th>
                            <th scope="col">Effective To</th>
                            <th scope="col">Status</th>
                            <th scope="col" width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($fees)): ?>
                        <?php foreach ($fees as $key => $row): ?>
                            <?php
                            $type = match ($row['consultation_type']) {
                                'NEW' => ['New', 'blue'],
                                'ALL' => ['All', 'gray'],
                                default => ['Follow-up', 'purple'],
                            };
                            ?>
                            <tr>
                                <td data-label="#"><?= (int) $key + 1 ?></td>
                                <td data-label="Doctor">
                                    <strong>Dr. <?= esc(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong><br>
                                    <small class="text-muted"><?= esc($row['doctor_code']) ?></small>
                                </td>
                                <td data-label="Type"><span class="badge ri-status ri-status--<?= esc($type[1]) ?>"><?= esc($type[0]) ?></span></td>
                                <td data-label="Mode">
                                    <?php
                                    $modeLabels = ['OFFLINE' => 'Offline', 'ONLINE' => 'Online', 'HOME_VISIT' => 'Home Visit'];
                                    echo esc($modeLabels[$row['consultation_mode']] ?? $row['consultation_mode']);
                                    ?>
                                </td>
                                <td data-label="Fee"><strong>&#8377;<?= number_format((float) $row['fee'], 2) ?></strong></td>
                                <td data-label="Effective From"><?= esc(_dateOnly($row['effective_from'])) ?></td>
                                <td data-label="Effective To">
                                    <?= !empty($row['effective_to']) ? esc(_dateOnly($row['effective_to'])) : '<span class="text-muted">No Expiry</span>' ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ((int) $row['status'] === 1): ?>
                                        <span class="badge ri-status ri-status--green">Active</span>
                                    <?php else: ?>
                                        <span class="badge ri-status ri-status--gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="row-actions d-flex align-items-center justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editDoctorFeeForm(<?= (int) $row['id'] ?>)" aria-label="Edit consultation fee">
                                            <i class="fa fa-edit" aria-hidden="true"></i>
                                        </button>
                                        <form method="post" action="<?= base_url('admin/doctor-fees/delete/' . (int) $row['id']) ?>" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete consultation fee">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No consultation fees found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'doctor_fees', 'filters' => $filters ?? []]) ?>
</main>

<?= view(ADMIN_VIEW . 'pages/doctor/modals/_fee') ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/doctor/doctor_fee.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
