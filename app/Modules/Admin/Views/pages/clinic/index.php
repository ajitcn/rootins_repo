<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container">
	<!-- breadcrumb -->
 	<?= 
 		view('templates/_breadcrumb', 

 			[
				'module' 		=> 'admin',
				'menu_title'	=> 'Clinic Management',
				'page'			=> 'Clinics' 
 			]
 		)
 	?>

 	<!-- main content -->
  		<div class="action-btn-container">
  			<button class="btn btn-sm theme-btn open_clinic_modal">+ New Clinic</button>
  		</div>

        <?= display_session_msg()?>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="thead-light">
                    <tr>
                        <th width="50">#</th>
                        <th>Clinic Name</th>
                        <th>Contact Person</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Country</th>
                        <th>State</th>
                        <th>City</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if(!empty($records)): ?>

                        <?php foreach($records as $key => $row): ?>

                            <tr>

                                <td><?= $key + 1 ?></td>

                                <td>
                                    <strong>
                                        <?= esc($row['clinic_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= esc($row['contact_person']) ?>
                                </td>

                                <td>
                                    <?= esc($row['mobile']) ?>
                                </td>

                                <td>
                                    <?= esc($row['email']) ?>
                                </td>

                                <td>
                                    <?= esc($row['country_name']) ?>
                                </td>

                                <td>
                                    <?= esc($row['state_name']) ?>
                                </td>

                                <td>
                                    <?= esc($row['city_name']) ?>
                                </td>

                                <td>
                                    <?php if($row['status'] == 1): ?>
                                        <span class="badge badge-success">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>

                                    <a href="javascript:void(0)"
                                        onclick="editClinicForm(<?=$row['id']?>)" 
                                        class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <a href="<?= base_url('admin/clinics/delete/'.$row['id']) ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure?')">
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>
 

</main>



<?= view(ADMIN_VIEW.'pages/clinic/modals/_clinic')?>


	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/clinic/clinic.js') ?>"></script>
	<?= $this->endSection() ?>

 <?= $this->endSection()?>