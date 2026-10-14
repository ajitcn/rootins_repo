<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container medicine-lookups-page" aria-labelledby="medicine-lookup-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Masters', 'page' => $types[$type]['title']]) ?>
    <div class="action-btn-container d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 id="medicine-lookup-title" class="h4 mb-2 mb-md-0"><?= esc($types[$type]['title']) ?></h1>
        <button type="button" class="btn btn-sm theme-btn" id="openMedicineLookupModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add value</button>
    </div>
    <?php if (! empty($pageError)): ?><div class="alert alert-warning" role="alert"><?= esc($pageError) ?></div><?php endif; ?>
    <nav class="nav nav-pills mb-3" aria-label="Medicine lookup types">
        <?php foreach ($types as $key => $definition): ?>
            <a class="nav-link <?= $type === $key ? 'active' : '' ?>" href="<?= base_url('admin/medicine-lookups?type=' . rawurlencode($key)) ?>"><?= esc($definition['title']) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="form-inline mb-3" method="get" action="<?= current_url() ?>">
        <input type="hidden" name="type" value="<?= esc($type) ?>">
        <label class="sr-only" for="medicineLookupSearch">Search options</label>
        <input class="form-control form-control-sm mr-2" id="medicineLookupSearch" type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Search options">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light"><tr><th>Value</th><?php if ($types[$type]['code']): ?><th>Code</th><?php endif; ?><th>Scope</th><th>Status</th><th class="text-right">More actions</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?><tr><td colspan="<?= $types[$type]['code'] ? 5 : 4 ?>" class="text-center text-muted py-4">No lookup values found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= esc($row['name']) ?></strong></td>
                        <?php if ($types[$type]['code']): ?><td><?= esc($row['code'] ?? '') ?></td><?php endif; ?>
                        <td><?= ($row['clinic_id'] ?? null) === null ? 'Global' : 'Clinic ' . (int) $row['clinic_id'] ?></td>
                        <td><span class="badge <?= (int) $row['status'] === 1 ? 'badge-success' : 'badge-secondary' ?>"><?= (int) $row['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-right"><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item edit-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-lookup-type="<?= esc($type, 'attr') ?>" data-value="<?= esc($row['name'], 'attr') ?>" data-status="<?= (int) $row['status'] ?>" data-target="#medicineLookupEditModal" aria-label="Edit <?= esc($row['name'], 'attr') ?>">Edit</button><?php if ((int) $row['status'] === 1): ?><button type="button" class="dropdown-item text-danger toggle-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-status="0">Deactivate</button><?php else: ?><button type="button" class="dropdown-item text-success toggle-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-status="1">Activate</button><?php endif; ?></div></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'medicine_lookups', 'filters' => ['type' => $type, 'search' => $filters['search'] ?? '', 'page_size' => $filters['page_size'] ?? 15]]) ?>
</main>

<div class="modal fade ri-scroll-modal" id="medicineLookupEditModal" tabindex="-1" role="dialog" aria-labelledby="medicineLookupEditModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="medicineLookupEditModalTitle">New option</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <?= form_open('admin/medicine-lookups/save/' . $type, 'id="medicineLookupForm" method="post" autocomplete="off"') ?>
        <input type="hidden" name="id" id="medicineLookupId">
        <input type="hidden" name="type" value="<?= esc($type) ?>">
        <div class="modal-body">
            <div class="form-group"><label for="medicineLookupName">Name</label><input class="form-control" id="medicineLookupName" name="name" required maxlength="255" aria-describedby="medicineLookupNameErr"><div class="validation-error" id="medicineLookupNameErr"></div></div>
            <?php if ($types[$type]['code']): ?><div class="form-group"><label for="medicineLookupCode">Code (optional)</label><input class="form-control" id="medicineLookupCode" name="code" maxlength="80" aria-describedby="medicineLookupCodeErr"><div class="validation-error" id="medicineLookupCodeErr"></div></div><?php endif; ?>
            <div class="form-check"><input class="form-check-input" id="medicineLookupStatus" type="checkbox" name="status" value="1" checked><label class="form-check-label" for="medicineLookupStatus">Active</label><div class="validation-error" id="statusErr"></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save option</button></div>
        <?= form_close() ?>
    </div></div>
</div>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/medicine/lookups.js?v=20261014-edit-modal3') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
