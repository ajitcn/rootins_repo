<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container">
	<!-- breadcrumb -->
 	<?= 
 		view('templates/_breadcrumb', 

 			[
				'module' 		=> 'admin',
				'menu_title'	=> 'Doctor Management',
				'page'			=> 'Doctors' 
 			]
 		)
 	?>

 	<!-- main content -->
  		<div class="action-btn-container">
  			<button class="btn btn-sm theme-btn open_doctor_modal">+ New Doctor</button>
  		</div>

        <?= display_session_msg()?>

         <div class="table-responsive">

            <table class="table table-bordered table-hover datatable">

                <thead class="thead-light">

                    <tr>

                        <th width="60">#</th>
                        <th>Doctor</th>
                        <th>Code</th>
                        <th>Clinic</th>
                        <th>Specialization</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>Experience</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th width="120">Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($doctors as $key => $row): ?>

                        <tr>

                            <td><?= $key + 1 ?></td>

                            <td>
                                <strong>
                                    Dr.
                                    <?= esc($row['first_name']) ?>
                                    <?= esc($row['last_name']) ?>
                                </strong>
                            </td>

                            <td><?= esc($row['doctor_code']) ?></td>

                            <td><?= esc($row['clinic_name']) ?></td>

                            <td><?= esc($row['specializations']) ?></td>

                            <td><?= esc($row['mobile']) ?></td>

                            <td><?= esc($row['email']) ?></td>
                            <td><?= esc($row['city_name']) ?></td>

                            <td><?= $row['experience'] ?> Years</td>

                            <td><?= $row['consultation_duration'] ?> Min</td>

                            <td>

                                <?php if ($row['status']) : ?>

                                    <span class="badge badge-success">
                                        Active
                                    </span>

                                <?php else : ?>

                                    <span class="badge badge-danger">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <button class="btn btn-sm btn-primary"
                                        onclick="editDoctorForm(<?= $row['id'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>

                                <a href="<?= base_url('admin/doctors/delete/'.$row['id']) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure?')">
                                    <i class="bi bi-trash"></i>
                                </a>


                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>
 

</main>



<?= view(ADMIN_VIEW.'pages/doctor/modals/_doctor')?>


	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/doctor/doctor.js') ?>"></script>
	<?= $this->endSection() ?>

 <?= $this->endSection()?>