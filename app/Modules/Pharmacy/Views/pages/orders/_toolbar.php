<?php $this->setData([
    'eyebrow' => 'Operations / fulfilment',
    'title' => 'Medicine Orders',
    'titleId' => 'pharmacy-orders-title',
    'description' => 'Review prescription fulfilment, stock allocation, payment, and delivery status.',
    'count' => $pagination['total'] ?? 0,
    'countLabel' => 'orders matched',
    'listUrl' => base_url('pharmacy/orders?' . http_build_query(array_merge($filters ?? [], ['view' => 'list', 'page' => 1]))),
    'gridUrl' => base_url('pharmacy/orders?' . http_build_query(array_merge($filters ?? [], ['view' => 'grid', 'page' => 1]))),
    'view' => $view ?? 'list',
    'primaryLabel' => in_array('pharmacy.order.create', session('permissions') ?? [], true) ? 'Create order' : '',
    'primaryUrl' => '#',
    'primaryTarget' => '#pharmacyOrderCreateModal',
]); ?>
<?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
<form class="pharmacy-orders-filters" method="get" action="<?= base_url('pharmacy/orders') ?>">
    <input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>">
    <div class="form-group mb-0 flex-grow-1"><label for="orderSearch">Search</label><input class="form-control form-control-sm" id="orderSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Order, patient, prescription, status"></div>
    <div class="form-group mb-0"><label for="orderStatus">Order status</label><select class="form-control form-control-sm" id="orderStatus" name="status"><option value="">All</option><?php foreach (['pending', 'fulfilled', 'cancelled'] as $option): ?><option value="<?= $option ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="paymentStatus">Payment</label><select class="form-control form-control-sm" id="paymentStatus" name="payment_status"><option value="">All</option><?php foreach (['pending', 'authorized', 'paid', 'failed', 'refunded'] as $option): ?><option value="<?= $option ?>" <?= ($filters['payment_status'] ?? '') === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="deliveryStatus">Delivery</label><select class="form-control form-control-sm" id="deliveryStatus" name="delivery_status"><option value="">All</option><?php foreach (['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'] as $option): ?><option value="<?= $option ?>" <?= ($filters['delivery_status'] ?? '') === $option ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $option)) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="orderPageSize">Page size</label><select class="form-control form-control-sm" id="orderPageSize" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="orderFrom">From</label><input class="form-control form-control-sm datepicker" id="orderFrom" name="from" placeholder="dd-mm-yyyy" value="<?= esc($filters['from'] ?? '') ?>" autocomplete="off"></div>
    <div class="form-group mb-0"><label for="orderTo">To</label><input class="form-control form-control-sm datepicker" id="orderTo" name="to" placeholder="dd-mm-yyyy" value="<?= esc($filters['to'] ?? '') ?>" autocomplete="off"></div>
    <div class="pharmacy-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/orders') ?>">Clear</a></div>
</form>
