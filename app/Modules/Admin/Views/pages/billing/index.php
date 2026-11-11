<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?>
<style>
    .admin-billing-page .billing-header-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .admin-billing-page .billing-header-actions .btn { min-height: 38px; }
    .admin-billing-page .billing-summary { margin-top: 1.25rem; }
    .admin-billing-page .billing-summary .card-body { min-height: 118px; padding: 1rem 1.15rem; }
    .admin-billing-page #billingFilters { padding: 1rem; }
    .admin-billing-page #billingFilters .card-body { padding: .25rem; }
    .admin-billing-page #billingFilters .form-group { margin-bottom: .85rem; }
    .admin-billing-page .billing-filter-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    .admin-billing-page .billing-filter-actions .btn { min-width: 92px; }
    .admin-billing-page .billing-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: .35rem; }
    .admin-billing-page .billing-actions .btn { min-width: 34px; }
    .admin-billing-page .billing-actions .billing-action-wide { min-width: 88px; }
    .admin-billing-page .crm-table td { vertical-align: middle; }
    @media (max-width: 767.98px) {
        .admin-billing-page .billing-header-actions { width: 100%; }
        .admin-billing-page .billing-header-actions .btn { flex: 1 1 auto; }
        .admin-billing-page .billing-actions { justify-content: flex-start; }
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<?php $filters = $filters ?? []; ?>
<main class="dashboard-container doctor-management-standard crm-page admin-billing-page" aria-labelledby="billing-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Billing and Invoices', 'page' => 'Billing']) ?>
    <header class="crm-header action-btn-container d-flex flex-wrap justify-content-between align-items-center">
        <div><h1 id="billing-title" class="h4 mb-1">Billing and Invoices</h1><p class="text-muted mb-0">Create, finalize, and reconcile clinic invoices.</p></div>
        <div class="billing-header-actions"><button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#billingFilters" aria-expanded="false" aria-controls="billingFilters"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><a class="btn btn-sm theme-btn" href="<?= base_url('admin/billing/new') ?>"><i class="bi bi-plus-lg mr-1" aria-hidden="true"></i>New bill</a></div>
    </header>
    <div id="billing-alert" role="status" aria-live="polite"></div>
    <div class="row mb-4 billing-summary" aria-label="Billing summary">
        <?php foreach (['count' => 'Invoices in scope', 'drafts' => 'Drafts', 'finalized' => 'Finalized'] as $key => $label): ?><div class="col-sm-6 col-xl-3 mb-3"><div class="card crm-card h-100"><div class="card-body"><div class="stat-title"><?= esc($label) ?></div><div class="stat-value"><?= (int) ($summary[$key] ?? 0) ?></div><small class="text-muted">Current permitted scope</small></div></div></div><?php endforeach; ?>
        <div class="col-sm-6 col-xl-3 mb-3"><div class="card crm-card h-100"><div class="card-body"><div class="stat-title">Invoice value</div><div class="stat-value">₹<?= esc(number_format((float) ($summary['total'] ?? 0), 2)) ?></div><small class="text-muted">Current result page</small></div></div></div>
    </div>
    <div class="card crm-card mb-4 collapse" id="billingFilters"><div class="card-body"><form method="get" action="<?= current_url() ?>"><div class="form-row">
        <div class="form-group col-md-4"><label for="billing-search">Search</label><input id="billing-search" class="form-control" name="search" type="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Invoice, patient, or clinic"></div>
        <div class="form-group col-md-2"><label for="billing-status">Invoice status</label><select id="billing-status" class="form-control btSelect" name="status" title="All statuses"><option value="">All statuses</option><?php foreach (['draft', 'finalized'] as $status): ?><option value="<?= $status ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-2"><label for="billing-payment">Payment status</label><select id="billing-payment" class="form-control btSelect" name="payment_status" title="All payment statuses"><option value="">All statuses</option><?php foreach (['pending', 'authorized', 'paid', 'failed', 'refunded'] as $status): ?><option value="<?= $status ?>" <?= ($filters['payment_status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4"><label for="billing-clinic">Clinic</label><select id="billing-clinic" class="form-control btSelect" name="clinic_id" title="All clinics"><option value="">All clinics</option><?php foreach ($clinics as $clinic): ?><option value="<?= (int) $clinic['id'] ?>" <?= (string) ($filters['clinic_id'] ?? '') === (string) $clinic['id'] ? 'selected' : '' ?>><?= esc($clinic['clinic_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-3"><label for="billing-date-from">Date from</label><input id="billing-date-from" class="form-control datepicker" name="date_from" type="text" value="<?= esc($filters['date_from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
        <div class="form-group col-md-3"><label for="billing-date-to">Date to</label><input id="billing-date-to" class="form-control datepicker" name="date_to" type="text" value="<?= esc($filters['date_to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
    </div><div class="billing-filter-actions"><a href="<?= current_url() ?>" class="btn btn-sm btn-outline-secondary">Clear</a><button class="btn btn-sm theme-btn" type="submit">Apply filters</button></div></form></div></div>
    <section class="card crm-card"><div class="table-responsive"><table class="table crm-table table-hover mb-0" id="invoice-table"><caption class="sr-only">Invoice register</caption><thead><tr><th>Invoice</th><th>Patient</th><th>Clinic</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th class="text-right">Actions</th></tr></thead><tbody>
        <?php if ($invoices === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No invoices match the selected filters.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $invoice): ?>
            <?php $invoiceStatusTone = $invoice['status'] === 'finalized' ? 'green' : 'amber'; $paymentStatus = (string) ($invoice['payment_status'] ?? 'pending'); $paymentStatusTone = match ($paymentStatus) { 'paid' => 'green', 'failed' => 'red', 'refunded' => 'purple', 'authorized' => 'blue', default => 'amber' }; ?>
            <tr>
                <td data-label="Invoice"><strong><?= esc($invoice['invoice_number']) ?></strong></td>
                <td data-label="Patient"><?= esc(trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($invoice['registration_number'] ?? '') ?></small></td>
                <td data-label="Clinic"><?= esc($invoice['clinic_name'] ?? $invoice['clinic_id']) ?></td>
                <td data-label="Total"><strong><?= esc($invoice['currency'] . ' ' . number_format((float) $invoice['total'], 2)) ?></strong></td>
                <td data-label="Status"><span class="badge ri-status ri-status--<?= esc($invoiceStatusTone) ?>"><?= esc(ucfirst($invoice['status'])) ?></span></td>
                <td data-label="Payment"><span class="badge ri-status ri-status--<?= esc($paymentStatusTone) ?>"><?= esc(ucwords(str_replace('_', ' ', $paymentStatus))) ?></span></td>
                <td data-label="Date"><?= esc($invoice['created_at']) ?></td>
                <td data-label="Actions" class="text-right">
                    <div class="dropdown billing-actions">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots-vertical mr-1" aria-hidden="true"></i>Actions</button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <button class="dropdown-item" type="button" data-toggle="tooltip" title="Created: <?= esc($invoice['created_at']) ?> | Updated: <?= esc($invoice['updated_at'] ?? '—') ?>"><i class="fa-solid fa-circle-info mr-2" aria-hidden="true"></i>Record dates</button>
                            <?php if ($invoice['status'] === 'draft'): ?>
                                <a class="dropdown-item" href="<?= base_url('admin/billing/edit/' . (int) $invoice['id']) ?>" aria-label="Edit invoice <?= esc($invoice['invoice_number']) ?>"><i class="bi bi-pencil-square mr-2" aria-hidden="true"></i>Edit invoice</a>
                                <button class="dropdown-item finalize-invoice" type="button" data-id="<?= (int) $invoice['id'] ?>"><i class="bi bi-check2-circle mr-2" aria-hidden="true"></i>Finalize invoice</button>
                            <?php elseif ($invoice['status'] === 'finalized' && $invoice['payment_status'] !== 'paid'): ?>
                                <button class="dropdown-item payment-invoice text-success" type="button" data-id="<?= (int) $invoice['id'] ?>"><i class="bi bi-cash-coin mr-2" aria-hidden="true"></i>Mark paid</button>
                            <?php else: ?>
                                <span class="dropdown-item-text text-muted">No available action</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager ?? null, 'group' => 'invoices', 'filters' => $filters]) ?>
    </section>
</main>
<?= $this->section('scripts') ?><script>
$(function(){function post(url,data){ajaxRequest({url:url,data:data,success:function(r){if(r.status){showSuccess(r.msg);reload_page(700);}else{showError(r.msg);if(r.errors)bindFormError('#invoice-form',r.errors);}}});}$('#invoice-form').on('submit',function(e){e.preventDefault();clearFormErrors();post('<?= base_url('admin/billing/create') ?>',$(this).serialize());});$('.finalize-invoice').on('click',function(){if(window.confirm('Finalize this invoice? Finalized invoices are protected from ordinary edits.'))post('<?= base_url('admin/billing/finalize') ?>/'+$(this).data('id'),{csrf_rtn:$('input[name=csrf_rtn]').val()});});$('.payment-invoice').on('click',function(){post('<?= base_url('admin/billing/payment') ?>/'+$(this).data('id'),{payment_status:'paid',csrf_rtn:$('input[name=csrf_rtn]').val()});});});
</script><?= $this->endSection() ?>
<?= $this->endSection() ?>
