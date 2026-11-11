<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?><link rel="stylesheet" href="<?= base_url('assets/css/crm/masters.css?v=20261012.1') ?>"><style>
  .crm-masters-page { padding-bottom: 2rem; }
  .crm-masters-page .crm-masters-header { align-items: center; }
  .crm-masters-page .crm-masters-filter { display: none; }
  .crm-masters-page .crm-masters-filter.is-visible { display: flex; }
  .crm-masters-page .crm-masters-toolbar { display: none; }
  .crm-masters-page .crm-master-filter-slot { margin-bottom: 1.25rem; padding: 0; }
  .crm-masters-page .crm-masters-card { margin-top: 1.25rem; padding: 0; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
  .crm-masters-page .crm-masters-table { overflow: hidden; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
  @media (max-width: 767.98px) { .crm-masters-page .crm-masters-header { align-items: stretch; } }
</style><?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell crm-masters-page" aria-labelledby="crm-masters-title">
  <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'CRM Master', 'page' => $definition['label']]) ?>
  <header class="crm-masters-header crm-header access-hero"><div><p class="crm-eyebrow">CRM / MASTERS</p><h1 id="crm-masters-title"><?= esc($definition['label']) ?></h1><p class="text-muted mb-0">Manage active values used by lead and follow-up workflows.</p></div><div class="d-flex flex-wrap" style="gap:.5rem"><button type="button" class="btn btn-outline-secondary js-crm-master-filter" aria-expanded="false"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><button type="button" class="btn theme-btn" data-toggle="modal" data-target="#crmMasterModal" data-crm-master-reset="1"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>Add <?= esc($definition['singular']) ?></button></div></header>
  <section class="card crm-masters-card"><div class="crm-masters-toolbar"><div><strong><?= (int) $total ?></strong> records</div><form class="crm-masters-filter" method="get"><label class="sr-only" for="crmMasterSearch">Search</label><input class="form-control" id="crmMasterSearch" name="search" value="<?= esc($search) ?>" placeholder="Search name or code"><button class="btn btn-outline-dark" type="submit">Search</button><a class="btn btn-light" href="<?= base_url('admin/crm/masters/' . $slug) ?>">Clear</a></form></div>
    <div class="table-responsive"><table class="table crm-masters-table mb-0"><caption class="sr-only"><?= esc($definition['label']) ?></caption><thead><tr><th>Name</th><th>Code</th><th>Description</th><th>Sort order</th><th>Status</th><th class="text-right">More Actions</th></tr></thead><tbody>
    <?php if (! $rows): ?><tr><td colspan="6"><div class="crm-master-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>No records found</strong><span>Try another search or add a new master value.</span></div></td></tr><?php else: foreach ($rows as $row): $active = (int) ($row['status'] ?? 0) === 1; ?><tr><td data-label="Name"><strong><?= esc($row['name'] ?? '') ?></strong></td><td data-label="Code"><code><?= esc($row['code'] ?? '—') ?></code></td><td data-label="Description"><?= esc($row['description'] ?? '—') ?></td><td data-label="Sort order"><?= (int) ($row['sort_order'] ?? 0) ?></td><td data-label="Status"><span class="badge crm-status-badge crm-status-<?= $active ? 'active' : 'inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td><td data-label="More Actions" class="text-right"><div class="dropdown"><button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-toggle="dropdown">More Actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item js-crm-master-edit" data-crm-master='<?= esc(json_encode($row), 'attr') ?>'>Edit</button><form class="js-crm-master-action" method="post" action="<?= base_url('admin/crm/masters/' . $slug . '/toggle/' . (int) $row['id']) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><?= $active ? 'Deactivate' : 'Activate' ?></button></form><div class="dropdown-divider"></div><form class="js-crm-master-action" method="post" action="<?= base_url('admin/crm/masters/' . $slug . '/archive/' . (int) $row['id']) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger" type="submit">Archive</button></form></div></div></td></tr><?php endforeach; endif; ?></tbody></table></div><div class="crm-masters-pagination"><?= $pager?->links('crm_master_' . str_replace('-', '_', $slug)) ?></div></section>
</main>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
document.addEventListener('click', function (event) {
  var button = event.target.closest('.js-crm-master-filter');
  if (!button) return;
  var filter = document.querySelector('.crm-masters-filter');
  if (!filter) return;
  filter.classList.toggle('is-visible');
  button.setAttribute('aria-expanded', filter.classList.contains('is-visible') ? 'true' : 'false');
});
document.addEventListener('DOMContentLoaded', function () {
  var page = document.querySelector('.crm-masters-page');
  var card = page && page.querySelector('.crm-masters-card');
  var form = page && page.querySelector('.crm-masters-filter');
  if (!card || !form || form.closest('.crm-master-filter-slot')) return;
  var slot = document.createElement('div');
  slot.className = 'crm-master-filter-slot';
  slot.appendChild(form);
  card.parentNode.insertBefore(slot, card);
});
</script><?= $this->endSection() ?>
