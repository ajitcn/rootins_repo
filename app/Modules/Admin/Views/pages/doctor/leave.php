<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container">
	<!-- breadcrumb -->
 	<?= 
 		view('templates/_breadcrumb', 

 			[
				'module' 		=> 'admin',
				'menu_title'	=> 'Doctor Management',
				'page'			=> 'Doctor Leaves' 
 			]
 		)
 	?>

 	<!-- main content -->
  		<div class="action-btn-container">
  			<button class="btn btn-sm theme-btn open_doctor_leave_modal">+ New Leave</button>
  		</div>

        <?= display_session_msg()?>

          <div class="table-responsive">

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
                                <?= date('d M Y', strtotime($row['start_date'])) ?>
                                <br>
                                <small class="text-muted">
                                    to
                                    <?= date('d M Y', strtotime($row['end_date'])) ?>
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

                                <?php if ($row['status']==1): ?>

                                    <span class="badge badge-success">
                                        Approved
                                    </span>
                                <?php elseif ($row['status']==2): ?>
                                    <span class="badge badge-warning">
                                        Pending
                                    </span>
                                <?php else: ?>

                                    <span class="badge badge-danger">
                                        Cancelled
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <button
                                    class="btn btn-sm btn-primary"
                                    onclick="openLeaveStatusForm(<?= $row['id'] ?>, '<?= $row['status'] ?>')">
                                    <i class="fa fa-edit"></i>
                                </button>

                                <a href="<?= base_url('admin/doctor-leaves/delete/' . $row['id']) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure?')">
                                    <i class="bi bi-trash"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center">
                            No leave records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
 

</main>



<?= view(ADMIN_VIEW.'pages/doctor/modals/_leave')?>


	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/doctor/doctor_leave.js') ?>"></script>
	<?= $this->endSection() ?>

 <?= $this->endSection()?>