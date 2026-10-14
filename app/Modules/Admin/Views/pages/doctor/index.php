<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>

<?php
$filters = $filters ?? ['search' => '', 'status' => '', 'page_size' => 15];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'page_size' => 15, 'total' => 0];
$queryFilters = array_filter(['search' => $filters['search'], 'status' => $filters['status'], 'page_size' => $filters['page_size']], static fn ($value) => $value !== '');
$pageUrl = static fn (int $page): string => current_url() . '?' . http_build_query($queryFilters + ['page' => $page]);
$permissions = session('permissions') ?? [];
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
?>

<main class="dashboard-container" aria-labelledby="doctor-management-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Doctor Management', 'page' => 'Doctors']) ?>

    <div class="page-heading mb-3">
        <div>
            <h1 id="doctor-management-title" class="h3 mb-1">Doctor Management</h1>
            <p class="text-muted mb-0">Manage doctors, professional details, and availability settings.</p>
        </div>
        <div class="d-flex flex-wrap">
            <button type="button" class="btn btn-outline-secondary mr-2 mb-2 mb-md-0" data-toggle="modal" data-target="#doctorFilterModal" aria-controls="doctorFilterModal"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button>
            <button type="button" class="btn btn-primary mb-2 mb-md-0 open_doctor_modal" aria-controls="doctorModal"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New Doctor</button>
        </div>
    </div>

    <?= display_session_msg() ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <h2 class="h6 mb-0">Doctor directory</h2>
            <span class="small text-muted"><?= esc((string) $pagination['total']) ?> doctor<?= (int) $pagination['total'] === 1 ? '' : 's' ?> in scope</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" id="doctors-table">
                    <caption class="sr-only">Doctor records</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="doctor-col-number">#</th>
                            <th scope="col" class="doctor-col-main">Doctor</th>
                            <th scope="col" class="doctor-col-contact">Contact</th>
                            <th scope="col" class="doctor-col-professional">Professional Details</th>
                            <th scope="col" class="doctor-col-availability">Availability</th>
                            <th scope="col" class="doctor-col-status">Status</th>
                            <th scope="col" class="doctor-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($doctors !== []): ?>
                        <?php $number = (($pagination['page'] - 1) * $pagination['page_size']) + 1; ?>
                        <?php foreach ($doctors as $row): ?>
                            <?php
                            $doctorName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                            $mobile = trim(((! empty($row['telephone_country_code']) ? '+' . $row['telephone_country_code'] . ' ' : '') . ($row['mobile'] ?? '')));
                            $fee = $row['consultation_fee'] ?? null;
                            $schedule = trim((string) ($row['schedule_summary'] ?? ''));
                            $modes = trim((string) ($row['consultation_modes'] ?? ''));
                            ?>
                            <tr>
                                <td data-label="#" class="doctor-cell-number"><?= esc((string) $number++) ?></td>
                                <td data-label="Doctor" class="doctor-cell-main">
                                    <div class="ri-stack-cell">
                                        <strong class="doctor-name">Dr. <?= esc($doctorName) ?></strong>
                                        <span class="doctor-code text-muted"><?= esc($row['doctor_code'] ?? '') ?></span>
                                        <span class="doctor-meta"><?= esc($row['qualification'] ?? '') ?: 'Qualification not recorded' ?></span>
                                    </div>
                                </td>
                                <td data-label="Contact" class="doctor-cell-contact">
                                    <div class="ri-stack-cell">
                                        <span class="ri-truncate" title="<?= esc($mobile) ?>"><?= esc($mobile ?: 'Mobile not recorded') ?></span>
                                        <span class="ri-truncate text-muted" title="<?= esc($row['email'] ?? '') ?>"><?= esc($row['email'] ?? '') ?: 'Email not recorded' ?></span>
                                    </div>
                                </td>
                                <td data-label="Professional Details" class="doctor-cell-professional">
                                    <div class="ri-stack-cell">
                                        <span><strong>Specialization:</strong> <?= esc($row['specializations'] ?? 'Not recorded') ?></span>
                                        <span class="text-muted"><strong>Modes:</strong> <?= esc($modes ?: 'Not configured') ?></span>
                                        <span class="text-muted"><strong>Fee:</strong> <?= $fee !== null ? '&#8377;' . number_format((float) $fee, 2) : 'Not configured' ?></span>
                                    </div>
                                </td>
                                <td data-label="Availability" class="doctor-cell-availability">
                                    <div class="ri-stack-cell">
                                        <span class="badge ri-status ri-status--<?= ! empty($row['online_booking']) ? 'green' : 'gray' ?>"><?= ! empty($row['online_booking']) ? 'Booking enabled' : 'Booking disabled' ?></span>
                                        <span class="text-muted"><strong>Schedule:</strong> <?= esc($schedule ?: 'Not configured') ?></span>
                                        <span class="badge ri-status ri-status--<?= ! empty($row['has_upcoming_leave']) ? 'orange' : 'green' ?>"><?= ! empty($row['has_upcoming_leave']) ? 'Leave / availability review' : 'Available' ?></span>
                                    </div>
                                </td>
                                <td data-label="Status" class="doctor-cell-status">
                                    <span class="badge ri-status ri-status--<?= (int) $row['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $row['status'] === 1 ? 'Active' : 'Inactive' ?></span>
                                </td>
                                <td data-label="Actions" class="doctor-cell-actions">
                                    <div class="doctor-actions d-flex align-items-center justify-content-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More Actions</button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <?php if ($can('doctor.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctors/view/' . (int) $row['id']) ?>"><i class="bi bi-eye mr-2" aria-hidden="true"></i>View doctor</a><?php endif; ?>
                                                <button type="button" class="dropdown-item" data-toggle="tooltip" title="Created: <?= esc($row['created_at'] ?? 'Unavailable') ?> | Updated: <?= esc($row['updated_at'] ?? 'Unavailable') ?>"><i class="fa-solid fa-circle-info mr-2" aria-hidden="true"></i>Record dates</button>
                                                <?php if ($can('doctor.edit')): ?><button type="button" class="dropdown-item" onclick="editDoctorForm(<?= (int) $row['id'] ?>)"><i class="fa fa-edit mr-2" aria-hidden="true"></i>Edit doctor</button><?php endif; ?>
                                                <?php if ($can('doctor_fee.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctor-fees') ?>"><i class="fa-solid fa-money-bill-wave mr-2" aria-hidden="true"></i>Consultation Fee</a><?php endif; ?>
                                                <?php if ($can('doctor_commission.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctor-commissions') ?>"><i class="fa-solid fa-percent mr-2" aria-hidden="true"></i>Commission</a><?php endif; ?>
                                                <?php if ($can('doctor_document.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctor-documents') ?>"><i class="fa-solid fa-file-medical mr-2" aria-hidden="true"></i>Documents</a><?php endif; ?>
                                                <?php if ($can('doctor_schedule.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctor-schedules') ?>"><i class="fa-solid fa-calendar-days mr-2" aria-hidden="true"></i>Schedule / Availability</a><?php endif; ?>
                                                <?php if ($can('doctor_leave.view')): ?><a class="dropdown-item" href="<?= base_url('admin/doctor-leaves') ?>"><i class="fa-solid fa-person-walking-arrow-right mr-2" aria-hidden="true"></i>Leave</a><?php endif; ?>
                                                <?php if ($can('doctor.delete') && (int) $row['status'] === 1): ?><form method="post" action="<?= base_url('admin/doctors/delete/' . (int) $row['id']) ?>" onsubmit="return confirm('Deactivate this doctor?')">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-toggle-off mr-2" aria-hidden="true"></i>Deactivate</button>
                                                </form><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" aria-hidden="true"></i>No doctors match the current filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
            <form method="get" action="<?= current_url() ?>" class="form-inline mb-2 mb-md-0" aria-label="Doctor page size">
                <input type="hidden" name="search" value="<?= esc($filters['search']) ?>"><input type="hidden" name="status" value="<?= esc($filters['status']) ?>">
                <label class="small mr-2" for="doctor-page-size">Rows per page</label>
                <select class="custom-select custom-select-sm" id="doctor-page-size" name="page_size" onchange="this.form.submit()"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) $filters['page_size'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select>
            </form>
            <nav aria-label="Doctor pagination"><span class="small text-muted mr-3">Page <?= esc((string) $pagination['page']) ?> of <?= esc((string) $pagination['pages']) ?> (<?= esc((string) $pagination['total']) ?> total)</span><a class="btn btn-sm btn-outline-secondary <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= $pagination['page'] > 1 ? esc($pageUrl($pagination['page'] - 1)) : '#' ?>" aria-label="Previous page">Previous</a> <a class="btn btn-sm btn-outline-secondary <?= $pagination['page'] >= $pagination['pages'] ? 'disabled' : '' ?>" href="<?= $pagination['page'] < $pagination['pages'] ? esc($pageUrl($pagination['page'] + 1)) : '#' ?>" aria-label="Next page">Next</a></nav>
        </div>
    </div>
</main>

<div class="modal fade" id="doctorFilterModal" tabindex="-1" role="dialog" aria-labelledby="doctorFilterModalTitle" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form method="get" action="<?= current_url() ?>"><div class="modal-header"><h2 class="modal-title h5" id="doctorFilterModalTitle">Filter doctors</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close filters"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="form-group"><label for="doctor-filter-search">Search</label><input class="form-control" id="doctor-filter-search" name="search" type="search" value="<?= esc($filters['search']) ?>" placeholder="Name, code, clinic, or email"></div><div class="form-group"><label for="doctor-filter-status">Status</label><select class="custom-select" id="doctor-filter-status" name="status"><option value="">All statuses</option><option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inactive</option></select></div></div><div class="modal-footer"><a class="btn btn-link mr-auto" href="<?= current_url() ?>">Clear</a><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Apply filters</button></div></form></div></div></div>
<?= view(ADMIN_VIEW . 'pages/doctor/modals/_doctor', ['telephoneCodes' => $telephoneCodes ?? []]) ?>
<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/admin/doctor/doctor.js') ?>"></script><?= $this->endSection() ?>
<?= $this->endSection() ?>
