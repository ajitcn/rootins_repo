<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('styles') ?><style>
    .medicine-lookups-page { padding-bottom: 2rem; }
    .medicine-lookups-page .medicine-lookups-header { align-items: center; margin-bottom: 1.25rem; }
    .medicine-lookups-page .medicine-lookups-header h1 { margin: .15rem 0 .35rem; }
    .medicine-lookups-page .medicine-lookups-header p { margin: 0; color: #64748b; }
    .medicine-lookups-page .medicine-lookup-filter { display: none; margin-bottom: 1.25rem; }
    .medicine-lookups-page .medicine-lookup-filter.is-visible { display: flex; }
    .medicine-lookups-page .medicine-lookup-tabs { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: 1.25rem; }
    .medicine-lookups-page .medicine-lookup-tabs .nav-link { border: 1px solid #e5e7eb; border-radius: .45rem; color: #475569; background: #fff; }
    .medicine-lookups-page .medicine-lookup-tabs .nav-link.active { border-color: #242424; color: #fff; background: #242424; }
    .medicine-lookups-page .medicine-lookups-table-card { overflow: hidden; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
    .medicine-lookups-page .medicine-lookups-table th { padding: .85rem 1rem; border-top: 0; color: #64748b; background: #f8fafc; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
    .medicine-lookups-page .medicine-lookups-table td { padding: 1rem; border-color: #edf1f5; vertical-align: middle; }
    .medicine-lookups-page .medicine-lookups-table tbody tr:hover { background: #fbfdff; }
    .medicine-lookups-page .medicine-lookup-status { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .6rem; border-radius: 999px; font-weight: 700; white-space: nowrap; }
    .medicine-lookups-page .medicine-lookup-status.active { color: #176b35; background: #e2f3e7; }
    .medicine-lookups-page .medicine-lookup-status.inactive { color: #64748b; background: #e2e8f0; }
    @media (max-width: 767.98px) { .medicine-lookups-page .medicine-lookups-header { align-items: stretch; } .medicine-lookups-page .medicine-lookup-filter { flex-wrap: wrap; gap: .5rem; } .medicine-lookups-page .medicine-lookup-filter .form-control { flex: 1 1 100%; margin-right: 0 !important; } .medicine-lookups-page .medicine-lookups-table thead { display: none; } .medicine-lookups-page .medicine-lookups-table tr, .medicine-lookups-page .medicine-lookups-table td { display: block; width: 100%; } .medicine-lookups-page .medicine-lookups-table tr { padding: .75rem; border-bottom: 1px solid #edf1f5; } .medicine-lookups-page .medicine-lookups-table td { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border: 0; text-align: right; } .medicine-lookups-page .medicine-lookups-table td::before { flex: 0 0 38%; color: #64748b; content: attr(data-label); font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-align: left; text-transform: uppercase; } .medicine-lookups-page .medicine-lookups-table td:last-child { display: block; text-align: left; } .medicine-lookups-page .medicine-lookups-table td:last-child::before { display: block; margin-bottom: .4rem; } }
</style><?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell medicine-lookups-page" aria-labelledby="medicine-lookup-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Masters', 'page' => $types[$type]['title']]) ?>
    <header class="action-btn-container crm-header access-hero d-flex flex-wrap justify-content-between medicine-lookups-header">
        <div><span class="crm-eyebrow">ADMIN / MEDICINE MASTERS</span><h1 id="medicine-lookup-title" class="h4"><?= esc($types[$type]['title']) ?></h1><p>Manage reusable values used across medicine and prescription workflows.</p></div>
        <div class="d-flex flex-wrap" style="gap:.5rem"><button type="button" class="btn btn-outline-secondary" id="medicineLookupFilterToggle" aria-expanded="false"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><button type="button" class="btn theme-btn" id="openMedicineLookupModal"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i> Add value</button></div>
    </header>
    <?php if (! empty($pageError)): ?><div class="alert alert-warning" role="alert"><?= esc($pageError) ?></div><?php endif; ?>
    <nav class="nav medicine-lookup-tabs" aria-label="Medicine lookup types">
        <?php foreach ($types as $key => $definition): ?>
            <a class="nav-link <?= $type === $key ? 'active' : '' ?>" href="<?= base_url('admin/medicine-lookups?type=' . rawurlencode($key)) ?>"><?= esc($definition['title']) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="form-inline medicine-lookup-filter" id="medicineLookupFilter" method="get" action="<?= current_url() ?>">
        <input type="hidden" name="type" value="<?= esc($type) ?>">
        <label class="sr-only" for="medicineLookupSearch">Search options</label>
        <input class="form-control form-control-sm mr-2" id="medicineLookupSearch" type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Search options">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
    </form>
    <section class="medicine-lookups-table-card table-responsive">
        <table class="table medicine-lookups-table mb-0">
            <thead class="thead-light"><tr><th>Value</th><?php if ($types[$type]['code']): ?><th>Code</th><?php endif; ?><th>Scope</th><th>Status</th><th class="text-right">More actions</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?><tr><td colspan="<?= $types[$type]['code'] ? 5 : 4 ?>" class="text-center text-muted py-4">No lookup values found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Value"><strong><?= esc($row['name']) ?></strong></td>
                        <?php if ($types[$type]['code']): ?><td data-label="Code"><?= esc($row['code'] ?? '') ?></td><?php endif; ?>
                        <td data-label="Scope"><?= ($row['clinic_id'] ?? null) === null ? 'Global' : 'Clinic ' . (int) $row['clinic_id'] ?></td>
                        <td data-label="Status"><span class="medicine-lookup-status <?= (int) $row['status'] === 1 ? 'active' : 'inactive' ?>"><span aria-hidden="true">●</span><?= (int) $row['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-right"><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item edit-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-lookup-type="<?= esc($type, 'attr') ?>" data-value="<?= esc($row['name'], 'attr') ?>" data-status="<?= (int) $row['status'] ?>" data-target="#medicineLookupEditModal" aria-label="Edit <?= esc($row['name'], 'attr') ?>">Edit</button><?php if ((int) $row['status'] === 1): ?><button type="button" class="dropdown-item text-danger toggle-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-status="0">Deactivate</button><?php else: ?><button type="button" class="dropdown-item text-success toggle-medicine-lookup" data-id="<?= (int) $row['id'] ?>" data-status="1">Activate</button><?php endif; ?></div></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
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
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('#medicineLookupFilterToggle');
    if (!button) return;
    var filter = document.getElementById('medicineLookupFilter');
    if (!filter) return;
    filter.classList.toggle('is-visible');
    button.setAttribute('aria-expanded', filter.classList.contains('is-visible') ? 'true' : 'false');
});
</script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
