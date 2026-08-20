<?= $this->extend(ADMIN_VIEW.'layout') ?>


<?= $this->section('main-content') ?>

<main class="dashboard-container">

    <!-- breadcrumb -->
    <?= view(
        'templates/_breadcrumb',
        [
            'module'      => 'admin',
            'menu_title'  => 'Doctor Management',
            'page'        => 'Doctor Commission'
        ]
    ) ?>


    <!-- main content -->
    <div class="action-btn-container">

        <button class="btn btn-sm theme-btn open_doctor_commission_modal">
            + New Doctor Commission
        </button>

    </div>


    <?= display_session_msg() ?>


    <div class="table-responsive">

        <table
            class="table table-bordered table-hover align-middle"
            id="doctorCommissionTable">

            <thead>

                <tr>

                    <th width="50">#</th>

                    <th>Doctor</th>

                    <th>Commission For</th>

                    <th>Reference</th>

                    <th>Type</th>

                    <th>Value</th>

                    <th>Effective From</th>

                    <th>Effective To</th>

                    <th>Status</th>

                    <th width="120">Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($commissions)): ?>

                <?php foreach ($commissions as $key => $row): ?>

                    <tr>

                        <!-- # -->
                        <td>
                            <?= $key + 1 ?>
                        </td>


                        <!-- Doctor -->
                        <td>

                            <strong>
                                Dr.
                                <?= esc(
                                    trim(
                                        $row['first_name'] . ' ' .
                                        $row['last_name']
                                    )
                                ) ?>
                            </strong>

                            <br>

                            <small class="text-muted">
                                <?= esc($row['doctor_code']) ?>
                            </small>

                        </td>


                        <!-- Commission For -->
                        <td>

                            <?php if ($row['commission_for'] === 'CONSULTATION'): ?>

                                <span class="badge bg-primary">
                                    Consultation
                                </span>

                            <?php elseif ($row['commission_for'] === 'SERVICE'): ?>

                                <span class="badge bg-info">
                                    Service
                                </span>

                            <?php else: ?>

                                <span class="badge bg-warning">
                                    Procedure
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Reference -->
                        <td>

                            <?php if ($row['commission_for'] === 'CONSULTATION'): ?>

                                <span class="text-muted">
                                    All Consultations
                                </span>

                            <?php else: ?>

                                <?= esc(
                                    $row['reference_name']
                                    ?? 'N/A'
                                ) ?>

                            <?php endif; ?>

                        </td>


                        <!-- Commission Type -->
                        <td>

                            <?php if ($row['commission_type'] === 'PERCENTAGE'): ?>

                                <span class="badge bg-primary">
                                    Percentage
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Fixed
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Commission Value -->
                        <td>

                            <strong>

                                <?php if ($row['commission_type'] === 'PERCENTAGE'): ?>

                                    <?= number_format(
                                        (float) $row['commission_value'],
                                        2
                                    ) ?>%

                                <?php else: ?>

                                    ₹<?= number_format(
                                        (float) $row['commission_value'],
                                        2
                                    ) ?>

                                <?php endif; ?>

                            </strong>

                        </td>


                        <!-- Effective From -->
                        <td>

                            <?= date(
                                'd-m-Y',
                                strtotime($row['effective_from'])
                            ) ?>

                        </td>


                        <!-- Effective To -->
                        <td>

                            <?php if (!empty($row['effective_to'])): ?>

                                <?= date(
                                    'd-m-Y',
                                    strtotime($row['effective_to'])
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    No Expiry
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Status -->
                        <td>

                            <?php if ((int) $row['status'] === 1): ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Actions -->
                        <td>

                            <div class="d-flex gap-1">

                                <button
                                    class="btn btn-sm btn-primary"
                                    onclick="editDoctorCommissionForm(<?= $row['id'] ?>)">

                                    <i class="fa fa-edit"></i>

                                </button>


                                <a
                                    href="<?= base_url('admin/doctor-commissions/delete/' . $row['id']) ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Are you sure?')">

                                    <i class="bi bi-trash"></i>

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="10"
                        class="text-center text-muted py-4">

                        No doctor commissions found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


</main>


<?= view(ADMIN_VIEW.'pages/doctor/modals/_commission') ?>


<?= $this->section('scripts') ?>

<script src="<?= base_url('assets/js/admin/doctor/doctor_commission.js') ?>"></script>

<?= $this->endSection() ?>


<?= $this->endSection() ?>