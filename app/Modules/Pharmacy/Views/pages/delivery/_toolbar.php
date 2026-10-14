<?php $this->setData([
    'eyebrow' => 'Operations / logistics', 'title' => 'Delivery', 'titleId' => 'pharmacy-delivery-title',
    'description' => 'Track clinic orders through dispatch, delivery, return, and failure states.', 'count' => $pagination['total'] ?? 0,
    'countLabel' => 'delivery records matched',
    'listUrl' => base_url('pharmacy/delivery?' . http_build_query(array_merge($filters ?? [], ['view' => 'list', 'page' => 1]))),
    'gridUrl' => base_url('pharmacy/delivery?' . http_build_query(array_merge($filters ?? [], ['view' => 'grid', 'page' => 1]))),
    'view' => $view ?? 'list',
]); ?>
<?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
<form class="pharmacy-delivery-filters" method="get" action="<?= base_url('pharmacy/delivery') ?>">
    <input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>">
    <div class="form-group mb-0 flex-grow-1"><label for="deliverySearch">Search</label><input class="form-control form-control-sm" id="deliverySearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Order, patient, provider, tracking"></div>
    <div class="form-group mb-0"><label for="deliveryStatus">Delivery status</label><select class="form-control form-control-sm" id="deliveryStatus" name="delivery_status"><option value="">All</option><?php foreach (['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'] as $item): ?><option value="<?= $item ?>" <?= ($filters['delivery_status'] ?? '') === $item ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $item)) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="deliveryProvider">Provider</label><input class="form-control form-control-sm" id="deliveryProvider" name="provider" value="<?= esc($filters['provider'] ?? '') ?>" placeholder="Provider"></div>
    <div class="form-group mb-0"><label for="deliveryPageSize">Page size</label><select class="form-control form-control-sm" id="deliveryPageSize" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label for="deliveryFrom">From</label><input class="form-control form-control-sm datepicker" id="deliveryFrom" name="from" value="<?= esc($filters['from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
    <div class="form-group mb-0"><label for="deliveryTo">To</label><input class="form-control form-control-sm datepicker" id="deliveryTo" name="to" value="<?= esc($filters['to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
    <div class="pharmacy-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/delivery') ?>">Clear</a></div>
</form>
