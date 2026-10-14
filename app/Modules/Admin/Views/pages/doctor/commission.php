<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>

<?php
$activeCount = 0;
$commissionCount = count($commissions ?? []);
foreach ($commissions ?? [] as $commissionSummary) {
    $activeCount += (int) ($commissionSummary['status'] ?? 0) === 1 ? 1 : 0;
}
?>

<main class="dashboard-container" aria-labelledby="doctor-commission-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Doctor Management', 'page' => 'Doctor Commission']) ?>

    <div class="page-heading mb-3">
        <div>
            <h1 id="doctor-commission-title" class="h3 mb-1">Doctor Commission</h1>
            <p class="text-muted mb-0">Manage scoped commission plans for consultations and services.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            <div class="mr-2 mb-2 mb-md-0">
                <?= view(ADMIN_VIEW . 'parts/doctor_list_filter', ['filterId' => 'doctorCommissionFilter', 'filters' => $filters ?? []]) ?>
            </div>
            <button class="btn btn-primary mb-2 mb-md-0 open_doctor_commission_modal" type="button">
                <i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New Doctor Commission
            </button>
        </div>
    </div>

    <?= display_session_msg() ?>

    <section class="row mb-3" aria-label="Commission summary">
        <div class="col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card h-100"><div class="card-body py-3"><div class="small text-muted">Plans on this page</div><div class="h4 mb-0"><?= $commissionCount ?></div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-2 mb-lg-0">
            <div class="card h-100"><div class="card-body py-3"><div class="small text-muted">Active on this page</div><div class="h4 mb-0"><span class="badge ri-status ri-status--green"><?= $activeCount ?> Active</span></div></div></div>
        </div>
    </section>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <h2 class="h6 mb-0">Commission plan list</h2>
            <span class="small text-muted">Search and status filters apply to the permitted scope.</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="doctorCommissionTable">
                    <thead>
                        <tr>
                            <th scope="col" width="50">#</th>
                            <th scope="col">Doctor</th>
                            <th scope="col">Commission For</th>
                            <th scope="col">Reference</th>
                            <th scope="col">Type</th>
                            <th scope="col">Value</th>
                            <th scope="col">Effective From</th>
                            <th scope="col">Effective To</th>
                            <th scope="col">Status</th>
                            <th scope="col" width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($commissions)): ?>
                        <?php foreach ($commissions as $key => $row): ?>
                            <?php
                            $for = match ($row['commission_for']) {
                                'SERVICE' => ['Service', 'purple'],
                                'PROCEDURE' => ['Procedure', 'orange'],
                                default => ['Consultation', 'blue'],
                            };
                            ?>
                            <tr>
                                <td data-label="#"><?= (int) $key + 1 ?></td>
                                <td data-label="Doctor"><strong>Dr. <?= esc(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong><br><small class="text-muted"><?= esc($row['doctor_code']) ?></small></td>
                                <td data-label="Commission For"><span class="badge ri-status ri-status--<?= esc($for[1]) ?>"><?= esc($for[0]) ?></span></td>
                                <td data-label="Reference">
                                    <?= $row['commission_for'] === 'CONSULTATION' ? '<span class="text-muted">All Consultations</span>' : esc($row['reference_name'] ?? 'N/A') ?>
                                </td>
                                <td data-label="Type"><span class="badge ri-status ri-status--<?= $row['commission_type'] === 'PERCENTAGE' ? 'blue' : 'gray' ?>"><?= $row['commission_type'] === 'PERCENTAGE' ? 'Percentage' : 'Fixed' ?></span></td>
                                <td data-label="Value"><strong><?= $row['commission_type'] === 'PERCENTAGE' ? number_format((float) $row['commission_value'], 2) . '%' : '&#8377;' . number_format((float) $row['commission_value'], 2) ?></strong></td>
                                <td data-label="Effective From"><?= esc(_dateOnly($row['effective_from'])) ?></td>
                                <td data-label="Effective To"><?= !empty($row['effective_to']) ? esc(_dateOnly($row['effective_to'])) : '<span class="text-muted">No Expiry</span>' ?></td>
                                <td data-label="Status">
                                    <?php if ((int) $row['status'] === 1): ?>
                                        <span class="badge ri-status ri-status--green">Active</span>
                                    <?php else: ?>
                                        <span class="badge ri-status ri-status--gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="row-actions d-flex align-items-center justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editDoctorCommissionForm(<?= (int) $row['id'] ?>)" aria-label="Edit doctor commission"><i class="fa fa-edit" aria-hidden="true"></i></button>
                                        <form method="post" action="<?= base_url('admin/doctor-commissions/delete/' . (int) $row['id']) ?>" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete commission"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" aria-hidden="true"></i>No doctor commissions found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'doctor_commissions', 'filters' => $filters ?? []]) ?>
</main>

<?= view(ADMIN_VIEW . 'pages/doctor/modals/_commission') ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/doctor/doctor_commission.js') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
