<?= $this->extend(ADMIN_VIEW . 'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container" aria-labelledby="doctor-leaves-title">
	<!-- breadcrumb -->
    <?=
        view(
            'templates/_breadcrumb',
            [
                'module' => 'admin',
                'menu_title' => 'Doctor Management',
                'page' => 'Doctor Leaves',
            ],
        )
?>

 	<!-- main content -->
        <div class="page-heading mb-3"><div><h1 id="doctor-leaves-title" class="h3 mb-1">Doctor Leaves</h1><p class="text-muted mb-0">Manage doctor leave periods and approval status.</p></div><div class="d-flex flex-wrap align-items-center">
            <?= view(ADMIN_VIEW . 'parts/doctor_list_filter', ['filterId' => 'doctorLeaveFilter', 'filters' => $filters ?? []]) ?>
  			<button type="button" class="btn btn-primary ml-2 mb-2 mb-md-0 open_doctor_leave_modal"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New Leave</button>
  		</div></div>

        <?= display_session_msg()?>

          <div class="card"><div class="card-header"><h2 class="h6 mb-0">Leave records</h2></div><div class="card-body p-0"><div class="table-responsive">

            <table class="table table-bordered table-hover table-striped">

                <thead>

                    <tr>
                        <th width="50">#</th>
                        <th>Doctor</th>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Total Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($leaves)): ?>

                    <?php foreach ($leaves as $key => $row): ?>

                        <tr>

                            <td><?= $key + 1 ?></td>

                            <td>
                                <strong><?= esc($row['doctor_code']) ?></strong><br>
                                <?= esc($row['first_name'] . ' ' . $row['last_name']) ?>
                            </td>

                            <td>
                                <?= esc($row['leave_type']) ?>
                            </td>

                            <td>
                        <?= esc(_dateOnly($row['start_date'])) ?>
                                <br>
                                <small class="text-muted">
                                    to
                                    <?= esc(_dateOnly($row['end_date'])) ?>
                                </small>
                            </td>

                            <td>
                                <?php
                                $days = (strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400 + 1;
                        echo $days . ' Day' . ($days > 1 ? 's' : '');
                        ?>
                            </td>

                            <td>
                                <?= !empty($row['reason']) ? esc($row['reason']) : '-' ?>
                            </td>

                            <td>

                                <?php if ($row['status'] == 1): ?>

                                <span class="badge ri-status ri-status--green">
                                        Approved
                                    </span>
                                <?php elseif ($row['status'] == 2): ?>
                                    <span class="badge ri-status ri-status--amber">
                                        Pending
                                    </span>
                                <?php else: ?>

                                    <span class="badge ri-status ri-status--red">
                                        Cancelled
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    onclick="openLeaveStatusForm(<?= $row['id'] ?>, '<?= $row['status'] ?>')">
                                    <i class="fa fa-edit"></i>
                                </button>

                                <a href="<?= base_url('admin/doctor-leaves/delete/' . $row['id']) ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure?')">
                                    <i class="bi bi-trash"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                            No leave records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div></div></div>
 

<?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'doctor_leaves', 'filters' => $filters ?? []]) ?>
</main>


<?= view(ADMIN_VIEW . 'pages/doctor/modals/_leave')?>



	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/doctor/doctor_leave.js') ?>"></script>
	<?= $this->endSection() ?>
 <?= $this->endSection()?>
