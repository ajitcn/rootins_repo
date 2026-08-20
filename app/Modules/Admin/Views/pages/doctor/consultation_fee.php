<?= $this->extend(ADMIN_VIEW.'layout')?>


 <?= $this->section('main-content')?>

<main class="dashboard-container">
	<!-- breadcrumb -->
 	<?= 
 		view('templates/_breadcrumb', 

 			[
				'module' 		=> 'admin',
				'menu_title'	=> 'Doctor Management',
				'page'			=> 'Consultation Fee' 
 			]
 		)
 	?>

 	<!-- main content -->
  		<div class="action-btn-container">
  			<button class="btn btn-sm theme-btn open_doctor_fee_modal">+ New Consultation Fee</button>
  		</div>

        <?= display_session_msg()?>
        <div class="table-responsive">

            <table
                class="table table-bordered table-hover align-middle"
                id="consultationFeeTable">

                <thead>

                    <tr>

                        <th width="50">#</th>

                        <th>Doctor</th>

                        <th>Type</th>

                        <th>Mode</th>

                        <th>Fee</th>

                        <th>Effective From</th>

                        <th>Effective To</th>

                        <th>Status</th>

                        <th width="120">Action</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($fees)): ?>

                    <?php foreach ($fees as $key => $row): ?>

                        <tr>

                            <!-- # -->
                            <td>
                                <?= $key + 1 ?>
                            </td>


                            <!-- Doctor -->
                            <td>

                                <strong>
                                    Dr. <?= esc(
                                        trim(
                                            $row['first_name'] . ' ' . $row['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <br>

                                <small class="text-muted">
                                    <?= esc($row['doctor_code']) ?>
                                </small>

                            </td>


                            <!-- Consultation Type -->
                            <td>

                                <?php if ($row['consultation_type'] === 'NEW'): ?>

                                    <span class="badge bg-primary">
                                        New
                                    </span>

                                <?php elseif($row['consultation_type'] === 'ALL'): ?>
                                    <span class="badge bg-secondary">
                                        All
                                    </span>
                                <?php else: ?>

                                    <span class="badge bg-info">
                                        Follow-up
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Consultation Mode -->
                            <td>

                                <?php
                                $modeLabels = [
                                    'OFFLINE'   => 'Offline',
                                    'ONLINE'    => 'Online',
                                    'HOME_VISIT'=> 'Home Visit',
                                ];

                                echo esc(
                                    $modeLabels[$row['consultation_mode']]
                                    ?? $row['consultation_mode']
                                );
                                ?>

                            </td>


                            <!-- Fee -->
                            <td>

                                <strong>
                                    ₹<?= number_format(
                                        (float) $row['fee'],
                                        2
                                    ) ?>
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
                                        onclick="editDoctorFeeForm(<?= $row['id'] ?>)">
                                        <i class="fa fa-edit"></i>
                                    </button>


                                    <a href="<?= base_url('admin/doctor-fees/delete/' . $row['id']) ?>"
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
                            colspan="9"
                            class="text-center text-muted py-4">

                            No consultation fees found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
          
 

</main>



<?= view(ADMIN_VIEW.'pages/doctor/modals/_fee')?>


	<?= $this->section('scripts') ?>
	<script src="<?= base_url('assets/js/admin/doctor/doctor_fee.js') ?>"></script>
	<?= $this->endSection() ?>

 <?= $this->endSection()?>