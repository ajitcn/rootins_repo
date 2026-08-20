<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container">
	<!-- breadcrumb -->
 	<?= 
 		view('templates/_breadcrumb', 

 			[
				'module' 		=> 'admin',
				'menu_title'	=> 'Doctor Management',
				'page'			=> 'Doctor Schedules' 
 			]
 		)
 	?>

 	<!-- main content -->
  		<div class="action-btn-container">
  			<button class="btn btn-sm theme-btn open_doctor_schedule_modal">+ New Schedule</button>
  		</div>

        <?= display_session_msg()?>

         <div class="table-responsive">

            <table class="table table-bordered table-hover table-striped">

                <thead>

                    <tr>
                        <th width="50">#</th>
                        <th>Doctor</th>
                        <th>Day</th>
                        <th>Shift</th>
                        <th>Timing</th>
                        <th>Slot</th>
                        <th>Max Patients</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if(!empty($schedules)): ?>

                    <?php foreach($schedules as $key => $row): ?>

                        <tr>

                            <td><?= $key + 1 ?></td>

                            <td>
                                <strong><?= esc($row['doctor_code']) ?></strong><br>
                                <?= esc($row['first_name'].' '.$row['last_name']) ?>
                            </td>

                            <td>
                                <?= getDayName($row['day_of_week']) ?>
                            </td>

                            <td>
                                <?= esc($row['shift_name']) ?>
                            </td>

                            <td>
                                <?= date('h:i A', strtotime($row['start_time'])) ?>
                                -
                                <?= date('h:i A', strtotime($row['end_time'])) ?>
                            </td>

                            <td>
                                <?= $row['slot_duration'] ?> Min
                            </td>

                            <td>
                                <?= $row['max_patients']==0 ? 'Unlimited' : $row['max_patients'] ?>
                            </td>

                            <td>

                                <?php if($row['status']): ?>

                                    <span class="badge badge-success">Active</span>

                                <?php else: ?>

                                    <span class="badge badge-danger">Inactive</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <button
                                    class="btn btn-sm btn-primary" 
                                    onclick="editDoctorScheduleForm(<?= $row['id'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>

                                <a href="<?= base_url('admin/doctor-schedules/delete/'.$row['id']) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure?')">
                                    <i class="bi bi-trash"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
 

</main>



<?= view(ADMIN_VIEW.'pages/doctor/modals/_schedule')?>


	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/doctor/doctor_schedule.js') ?>"></script>
	<?= $this->endSection() ?>

 <?= $this->endSection()?>