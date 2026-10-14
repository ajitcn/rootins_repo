<?php $this->setData([
    'eyebrow' => 'Operations / finance', 'title' => 'Billing & payments', 'titleId' => 'pharmacy-billing-title',
    'description' => 'Review invoice linkage and payment readiness for clinic orders.', 'count' => $pagination['total'] ?? 0,
    'countLabel' => 'billing records matched',
    'listUrl' => base_url('pharmacy/billing?' . http_build_query(array_merge($filters ?? [], ['view' => 'list', 'page' => 1]))),
    'gridUrl' => base_url('pharmacy/billing?' . http_build_query(array_merge($filters ?? [], ['view' => 'grid', 'page' => 1]))),
    'view' => $view ?? 'list',
]); ?>
<?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
<form class="pharmacy-billing-filters" method="get" action="<?= base_url('pharmacy/billing') ?>">
    <input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>">
    <div class="form-group mb-0 flex-grow-1"><label for="billingSearch">Search</label><input class="form-control form-control-sm" id="billingSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Order, invoice, patient, reference, provider"></div>
    <div class="form-group mb-0"><label for="billingPaymentStatus">Payment status</label><select class="form-control form-control-sm" id="billingPaymentStatus" name="payment_status"><option value="">All</option><?php foreach (['pending', 'authorized', 'paid', 'failed', 'refunded'] as $item): ?><option value="<?= $item ?>" <?= ($filters['payment_status'] ?? '') === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="billingOrderStatus">Order status</label><select class="form-control form-control-sm" id="billingOrderStatus" name="order_status"><option value="">All</option><?php foreach (['pending', 'fulfilled', 'cancelled'] as $item): ?><option value="<?= $item ?>" <?= ($filters['order_status'] ?? '') === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="billingProvider">Provider</label><select class="form-control form-control-sm" id="billingProvider" name="provider"><option value="">All</option><?php foreach (['manual', 'cashfree', 'razorpay'] as $item): ?><option value="<?= $item ?>" <?= ($filters['provider'] ?? '') === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="billingPageSize">Page size</label><select class="form-control form-control-sm" id="billingPageSize" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="billingFrom">From</label><input class="form-control form-control-sm datepicker" id="billingFrom" name="from" value="<?= esc($filters['from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
    <div class="form-group mb-0"><label for="billingTo">To</label><input class="form-control form-control-sm datepicker" id="billingTo" name="to" value="<?= esc($filters['to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
    <div class="pharmacy-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/billing') ?>">Clear</a></div>
</form>
<?php if (!empty($filters['date_error'])): ?><div class="alert alert-warning py-2 px-3 small mb-3" role="alert"><i class="bi bi-exclamation-triangle mr-1" aria-hidden="true"></i>To date cannot be earlier than From date. Update the date range and apply again.</div><?php endif; ?>
<?php if (!empty($readiness) && (!empty($readiness['cashfree']) && empty($readiness['cashfree']['configured']) || !empty($readiness['razorpay']) && empty($readiness['razorpay']['configured']))): ?><div class="alert alert-info py-2 px-3 small mb-3" role="status"><i class="bi bi-info-circle mr-1" aria-hidden="true"></i>External payment providers are not configured. Manual reconciliation remains available; no provider success is assumed.</div><?php endif; ?>
