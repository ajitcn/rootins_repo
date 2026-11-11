<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?><link rel="stylesheet" href="<?= base_url('assets/css/admin/payment-providers.css?v=20261012.1') ?>"><style>
    .payment-provider-page { padding-bottom: 2rem; }
    .payment-provider-page .payment-provider-header { align-items: center; margin-bottom: 1.25rem; }
    .payment-provider-page .payment-provider-eyebrow { color: #64748b; font-weight: 800; }
    .payment-provider-page .payment-provider-filters { display: none; }
    .payment-provider-page .payment-provider-filters.is-visible { display: flex; }
    .payment-provider-page .payment-provider-toolbar { display: none; }
    .payment-provider-page .payment-provider-filter-slot { margin-bottom: 1.25rem; padding: 0; }
    .payment-provider-page .payment-provider-workspace { margin-top: 1.25rem; padding: 0; overflow: visible; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
    .payment-provider-page .payment-provider-table { overflow: hidden; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; }
    .payment-provider-page .payment-provider-summary { gap: .8rem; margin-bottom: 1.25rem; }
    .payment-provider-page .payment-provider-summary > div { min-width: 0; border-left: 4px solid #242424; border-radius: .65rem; box-shadow: 0 8px 20px rgba(17,17,17,.04); }
    .payment-provider-page .payment-provider-summary > div:nth-child(2) { border-left-color: #247a45; }
    .payment-provider-page .payment-provider-summary > div:nth-child(3) { border-left-color: #a76e10; }
    .payment-provider-page .payment-provider-summary > div:nth-child(4) { border-left-color: #777; }
    @media (max-width: 767.98px) { .payment-provider-page .payment-provider-header { align-items: stretch; } }
</style><?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell payment-provider-page" aria-labelledby="payment-providers-title">
<?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Masters', 'page' => 'Payment Providers']) ?>
<header class="payment-provider-header crm-header access-hero"><div><div class="payment-provider-eyebrow">ADMIN / MASTERS</div><h1 id="payment-providers-title">Payment Providers</h1><p>Manage payment methods available to pharmacy payment workflows.</p></div><div class="d-flex flex-wrap" style="gap:.5rem"><button class="btn btn-outline-secondary" type="button" aria-expanded="false"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><button class="btn theme-btn" type="button" data-toggle="modal" data-target="#paymentProviderModal" data-payment-provider-reset="1">Add provider</button></div></header>
<section class="payment-provider-summary"><div><span>Total</span><strong><?= (int)$summary['total'] ?></strong></div><div><span>Active</span><strong><?= (int)$summary['active'] ?></strong></div><div><span>Inactive</span><strong><?= (int)$summary['inactive'] ?></strong></div><div><span>Archived</span><strong><?= (int)$summary['archived'] ?></strong></div></section>
<section class="card payment-provider-workspace"><div class="payment-provider-toolbar"><div><h2>Payment provider master</h2><p>Only active providers appear in payment updates.</p></div><form class="payment-provider-filters" method="get"><input class="form-control" name="search" value="<?= esc($search) ?>" placeholder="Search name, code, or type" aria-label="Search payment providers"><select class="form-control" name="state" aria-label="Filter by state"><option value="">All states</option><option value="active" <?= $state === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $state === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="archived" <?= $state === 'archived' ? 'selected' : '' ?>>Archived</option></select><button class="btn btn-outline-dark">Filter</button><a class="btn btn-light" href="<?= base_url('admin/payment-providers') ?>">Clear</a></form></div>
<div class="table-responsive"><table class="table payment-provider-table mb-0"><thead><tr><th>Provider</th><th>Code</th><th>Type</th><th>Currency</th><th>Sort</th><th>Status</th><th class="text-right">More actions</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center py-5">No payment providers found.</td></tr><?php else: foreach ($rows as $row): $archived = !empty($row['archived_at']); $active = (int)$row['status'] === 1 && !$archived; ?>
<tr><td data-label="Provider"><strong><?= esc($row['provider_name']) ?></strong></td><td data-label="Code"><code><?= esc($row['provider_code']) ?></code></td><td data-label="Type"><?= esc(ucfirst($row['provider_type'])) ?></td><td data-label="Currency"><?= esc($row['default_currency'] ?: '—') ?></td><td data-label="Sort"><?= (int)$row['sort_order'] ?></td><td data-label="Status"><span class="badge payment-provider-badge" data-status="<?= esc($archived ? 'Archived' : ($active ? 'Active' : 'Inactive')) ?>"><?= $archived ? 'Archived' : ($active ? 'Active' : 'Inactive') ?></span></td><td data-label="More actions" class="text-right"><div class="dropdown"><button class="btn btn-sm btn-outline-dark dropdown-toggle" data-toggle="dropdown" type="button">More Actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item js-payment-provider-edit" data-payment-provider="<?= esc(json_encode($row), 'attr') ?>">Edit</button><?php if (!$archived): ?><form class="js-payment-provider-action" method="post" action="<?= base_url('admin/payment-providers/toggle/' . (int)$row['id']) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><?= $active ? 'Deactivate' : 'Activate' ?></button></form><form class="js-payment-provider-action" method="post" action="<?= base_url('admin/payment-providers/archive/' . (int)$row['id']) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger" type="submit">Archive</button></form><?php endif; ?></div></div></td></tr>
<?php endforeach; endif; ?></tbody></table></div><div class="payment-provider-pagination"><?= $pager?->links('payment_providers') ?></div></section></main>
<?= $this->endSection() ?>
<?= $this->section('footer') ?>
<div class="modal fade payment-provider-modal" id="paymentProviderModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form id="paymentProviderForm" method="post" action="<?= base_url('admin/payment-providers/save') ?>"><?= csrf_field() ?><div class="modal-header"><div><div class="payment-provider-eyebrow">PAYMENT PROVIDER MASTER</div><h2 class="modal-title h5 mb-0" id="paymentProviderModalTitle">Add payment provider</h2></div><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><input type="hidden" name="id" id="paymentProviderId"><div class="payment-provider-form-grid"><?php foreach ([['provider_name','Provider name','text'],['provider_code','Provider code','text'],['default_currency','Default currency','text'],['sort_order','Sort order','number']] as $field): ?><div class="form-group"><label for="paymentProvider<?= ucfirst($field[0]) ?>"><?= $field[1] ?></label><input class="form-control" id="paymentProvider<?= ucfirst($field[0]) ?>" name="<?= $field[0] ?>" type="<?= $field[2] ?>" <?= $field[0] !== 'default_currency' ? 'required' : '' ?>><div class="validation-error" id="<?= $field[0] ?>Err"></div></div><?php endforeach; ?><div class="form-group"><label for="paymentProviderType">Provider type</label><select class="form-control" id="paymentProviderType" name="provider_type" required><option value="manual">Manual</option><option value="gateway">Gateway</option><option value="bank">Bank</option><option value="wallet">Wallet</option><option value="other">Other</option></select><div class="validation-error" id="provider_typeErr"></div></div><div class="form-group"><label for="paymentProviderStatus">Provider status</label><select class="form-control" id="paymentProviderStatus" name="status"><option value="1">Active</option><option value="0">Inactive</option></select><div class="validation-error" id="statusErr"></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn theme-btn">Save provider</button></div></form></div></div></div>
<script defer src="<?= base_url('assets/js/admin/payment_provider.js?v=20261012.1') ?>"></script>
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('.payment-provider-header .btn-outline-secondary');
    if (!button) return;
    var filters = document.querySelector('.payment-provider-filters');
    if (!filters) return;
    filters.classList.toggle('is-visible');
    button.setAttribute('aria-expanded', filters.classList.contains('is-visible') ? 'true' : 'false');
});
document.addEventListener('DOMContentLoaded', function () {
    var page = document.querySelector('.payment-provider-page');
    var summary = page && page.querySelector('.payment-provider-summary');
    var form = page && page.querySelector('.payment-provider-filters');
    if (!summary || !form || form.closest('.payment-provider-filter-slot')) return;
    var slot = document.createElement('div');
    slot.className = 'payment-provider-filter-slot';
    slot.appendChild(form);
    summary.parentNode.insertBefore(slot, summary);
});
</script>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><?= $this->endSection() ?>
