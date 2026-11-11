<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?><style>
    #shopify-order-operations { padding-bottom: 2rem; }
    #shopify-order-operations > .shopify-operations-breadcrumb { margin-bottom: .25rem; }
    #shopify-order-operations > .d-flex:first-of-type { align-items: center !important; padding: 1.25rem 1.4rem; margin-bottom: 1.25rem !important; border-radius: .75rem; background: #fff; box-shadow: 0 8px 22px rgba(31,41,55,.05); }
    #shopify-order-operations > .d-flex:first-of-type h1 { margin: .15rem 0 .35rem; font-weight: 800; }
    #shopify-order-operations > .d-flex:first-of-type p:first-child { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .12em; }
    #shopify-order-operations .shopify-filter-card { display: none; margin-bottom: 1.25rem !important; border: 0; border-radius: 0; background: transparent; box-shadow: none !important; }
    #shopify-order-operations .shopify-filter-card.is-visible { display: block; }
    #shopify-order-operations .shopify-filter-card .card-body { padding: 0; }
    #shopify-order-operations .shopify-filter-card form { padding: 1rem 1.1rem; border: 1px solid #e5e7eb; border-radius: .65rem; background: #fff; }
    #shopify-order-operations .shopify-operations-table-card { overflow: hidden; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; box-shadow: none !important; }
    #shopify-order-operations .shopify-operations-table { min-width: 1260px; }
    #shopify-order-operations .shopify-operations-table th { padding: .85rem .8rem; border-top: 0; color: #64748b; background: #f8fafc; font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
    #shopify-order-operations .shopify-operations-table td { padding: .9rem .8rem; border-color: #edf1f5; vertical-align: middle; }
    #shopify-order-operations .shopify-operations-table tbody tr:hover { background: #fbfdff; }
    @media (max-width: 767.98px) { #shopify-order-operations > .d-flex:first-of-type { align-items: stretch !important; } }
</style><?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<div class="shopify-operations-breadcrumb"><?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Shopify', 'page' => 'Order Operations']) ?></div>
<main class="container-fluid py-4" id="shopify-order-operations"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-uppercase text-muted small mb-1">Shopify · Order Operations</p><h1 class="h4 mb-1">Order processing queue</h1><p class="text-muted mb-0">Every received order remains visible until safely imported, cancelled, or reconciled.</p></div><div><a class="btn btn-outline-secondary mr-2" href="<?= base_url('admin/integrations/shopify/order-mappings') ?>">Mappings</a><a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify/patient-readiness') ?>">Patient readiness</a></div></div><div class="row mb-4"><?php foreach ($counters as $key => $value): ?><div class="col-md-3 col-lg-2 mb-2"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="small text-muted"><?= esc(ucwords(str_replace('_', ' ', $key))) ?></div><div class="h4 mb-0"><?= (int) $value ?></div></div></div></div><?php endforeach; ?></div><div class="card shadow-sm mb-4"><div class="card-body"><form class="form-row"><div class="col-md-3"><input class="form-control" name="search" placeholder="Order/customer/patient/SKU" value="<?= esc($filters['search'] ?? '') ?>"></div><?php foreach (['store_domain' => 'Store', 'status' => 'Processing status', 'prescription_status' => 'Prescription status', 'mapping_status' => 'Mapping status', 'financial_status' => 'Payment status', 'fulfillment_status' => 'Fulfilment status', 'patient_status' => 'Patient status'] as $field => $label): ?><div class="col-md-2 mb-2"><input class="form-control" name="<?= esc($field) ?>" placeholder="<?= esc($label) ?>" value="<?= esc($filters[$field] ?? '') ?>"></div><?php endforeach; ?><div class="col-md-2"><button class="btn btn-primary mr-2" type="submit">Filter</button><a class="btn btn-light" href="<?= base_url('admin/integrations/shopify/order-operations') ?>">Reset</a></div></form></div></div><div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Shopify order</th><th>Store</th><th>Customer</th><th>ROOTINS patient</th><th>Order date</th><th>Prescription</th><th>Mapping</th><th>Import</th><th>Payment</th><th>Fulfilment</th><th>Quarantine/error</th><th>Last attempted</th><th>Retry</th><th></th></tr></thead><tbody><?php if ($imports === []): ?><tr><td colspan="14" class="text-center text-muted py-4">No Shopify orders have been received.</td></tr><?php endif; ?><?php foreach ($imports as $import): ?><tr><td><a href="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id']) ?>"><?= esc($import['shopify_order_number'] ?: $import['shopify_order_id']) ?></a><br><small class="text-muted"><?= esc($import['shopify_order_id']) ?></small></td><td><?= esc($import['store_domain']) ?></td><td><?= esc($import['customer_name_snapshot'] ?: $import['customer_email_snapshot'] ?: $import['shopify_customer_id'] ?: 'Unknown') ?></td><td><?= esc($import['registration_number'] ?: 'Missing') ?></td><td><?= esc($import['created_at'] ?? '') ?></td><td><?php if (($import['prescription_status'] ?? '') === 'not_required'): ?>Not required<?php elseif (!empty($import['prescription_document_id']) && !empty($import['prescription_url'])): ?><a target="_blank" rel="noopener noreferrer" href="<?= esc($import['prescription_url'], 'attr') ?>">View prescription</a><?php else: ?>Missing prescription<?php endif; ?></td><td><?= esc($import['mapping_status'] ?: '—') ?></td><td><span class="badge badge-light"><?= esc($import['status']) ?></span></td><td><?= esc($import['financial_status'] ?: '—') ?></td><td><?= esc($import['fulfillment_status'] ?: '—') ?></td><td><?= esc($import['quarantine_reason'] ?: $import['failure_reason'] ?: $import['last_error'] ?: '—') ?></td><td><?= esc($import['last_attempted_at'] ?: $import['updated_at'] ?: '—') ?></td><td><?= (int) ($import['retry_count'] ?? 0) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id']) ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div></div></main>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
document.addEventListener('DOMContentLoaded', function () {
    var main = document.getElementById('shopify-order-operations');
    if (!main) return;
    main.classList.add('dashboard-container', 'doctor-management-standard', 'crm-page', 'access-shell');
    var header = main.firstElementChild;
    if (header && header.classList.contains('d-flex')) {
        header.classList.add('crm-header', 'access-hero', 'shopify-operations-header');
        var actions = header.lastElementChild;
        if (actions && !document.getElementById('shopifyOperationsFilterToggle')) {
            var button = document.createElement('button');
            button.type = 'button';
            button.id = 'shopifyOperationsFilterToggle';
            button.className = 'btn btn-outline-secondary mr-2';
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = '<i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters';
            actions.insertBefore(button, actions.firstChild);
        }
    }
    var filterForm = main.querySelector('form.form-row');
    var filterCard = filterForm && filterForm.closest('.card');
    var counters = main.querySelector('.row.mb-4');
    if (filterCard) {
        filterCard.classList.add('shopify-filter-card');
        if (counters && filterCard.parentNode === main) main.insertBefore(filterCard, counters);
    }
    var tableCard = Array.prototype.slice.call(main.querySelectorAll('.card.shadow-sm')).find(function (card) { return !!card.querySelector('table'); });
    if (tableCard) {
        tableCard.classList.add('shopify-operations-table-card');
        var table = tableCard.querySelector('table');
        if (table) table.classList.add('shopify-operations-table');
    }
});
document.addEventListener('click', function (event) {
    var button = event.target.closest('#shopifyOperationsFilterToggle');
    if (!button) return;
    var filter = document.querySelector('#shopify-order-operations .shopify-filter-card');
    if (!filter) return;
    filter.classList.toggle('is-visible');
    button.setAttribute('aria-expanded', filter.classList.contains('is-visible') ? 'true' : 'false');
});
</script><?= $this->endSection() ?>
