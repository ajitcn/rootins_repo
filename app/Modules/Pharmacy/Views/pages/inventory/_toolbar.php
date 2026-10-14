<?php
$current = $_GET ?? [];
$listQuery = http_build_query(array_merge($current, ['view' => 'list', 'page' => 1]));
$gridQuery = http_build_query(array_merge($current, ['view' => 'grid', 'page' => 1]));
?>
<?php $this->setData([
    'eyebrow' => 'Central inventory',
    'title' => 'Central Medicine Stock',
    'titleId' => 'pharmacy-inventory-title',
    'description' => 'Global active-medicine stock shared across clinic-owned orders. Low stock is 10 units or fewer; near expiry is within 30 days.',
    'count' => $pagination['total'] ?? 0,
    'countLabel' => 'matching batches',
    'listUrl' => base_url('pharmacy/inventory?' . $listQuery),
    'gridUrl' => base_url('pharmacy/inventory?' . $gridQuery),
    'view' => $view ?? 'list',
    'filterTarget' => '#pharmacy-inventory-filters',
    'primaryLabel' => in_array('pharmacy.inventory.batch.create', session('permissions') ?? [], true) ? 'Add Stock Batch' : null,
    'primaryUrl' => in_array('pharmacy.inventory.batch.create', session('permissions') ?? [], true) ? '#' : null,
    'primaryTarget' => in_array('pharmacy.inventory.batch.create', session('permissions') ?? [], true) ? '#pharmacy-inventory-create' : null,
]); ?>
<?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
<form class="pharmacy-filter-card" method="get" action="<?= base_url('pharmacy/inventory') ?>"><input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>"><div class="form-group mb-0 flex-grow-1"><label for="pharmacy-inventory-search-inline">Search</label><input class="form-control" id="pharmacy-inventory-search-inline" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Medicine, batch, or status"></div><div class="form-group mb-0"><label for="pharmacy-inventory-page-size-inline">Page size</label><select class="form-control" id="pharmacy-inventory-page-size-inline" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div><div class="pharmacy-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/inventory?view=' . esc($view ?? 'list')) ?>">Clear</a></div></form>
<section class="pharmacy-inventory-summary" role="status" aria-label="Central stock summary" aria-live="polite">
    <div class="pharmacy-inventory-summary-heading">
        <div>
            <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
            <span>matching central batches</span>
        </div>
        <small>Counts use the current search and filters.</small>
    </div>
    <div class="pharmacy-inventory-summary-grid">
        <?php
        $summaryCards = [
            'available' => ['Available', 'bi-check-circle', 'success', 'Ready stock'],
            'low_stock' => ['Low stock', 'bi-exclamation-triangle', 'warning', '1–10 units'],
            'near_expiry' => ['Near expiry', 'bi-hourglass-split', 'warning', 'Within 30 days'],
            'expired' => ['Expired', 'bi-calendar-x', 'danger', 'Past expiry date'],
            'out_of_stock' => ['Out of stock', 'bi-x-circle', 'danger', 'Zero quantity'],
        ];
        foreach ($summaryCards as $key => [$label, $icon, $tone, $hint]):
        ?>
            <div class="pharmacy-inventory-summary-card inventory-summary-<?= esc($tone) ?>">
                <span class="pharmacy-inventory-summary-icon" aria-hidden="true">
                    <i class="bi <?= esc($icon) ?>"></i>
                </span>
                <div class="pharmacy-inventory-summary-value">
                    <strong><?= (int) ($summary[$key] ?? 0) ?></strong>
                    <span><?= esc($label) ?></span>
                    <small><?= esc($hint) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<div class="modal fade" id="pharmacy-inventory-filters" tabindex="-1" role="dialog" aria-labelledby="pharmacy-inventory-filters-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document"><div class="modal-content">
        <form method="get" action="<?= current_url() ?>">
            <input type="hidden" name="view" value="<?= esc($view ?? 'list') ?>">
            <div class="modal-header"><h2 class="modal-title h5" id="pharmacy-inventory-filters-title">Filter medicine availability</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label for="pharmacy-inventory-search">Search</label><input class="form-control" id="pharmacy-inventory-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Medicine, batch, or status" autocomplete="off"></div>
                <div class="form-row">
                    <div class="form-group col-md-6"><label for="pharmacy-inventory-availability">Availability</label><select class="form-control" id="pharmacy-inventory-availability" name="availability"><option value="">All central stock</option><option value="available" <?= ($filters['availability'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option><option value="low" <?= ($filters['availability'] ?? '') === 'low' ? 'selected' : '' ?>>Low stock</option><option value="out_of_stock" <?= ($filters['availability'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option><option value="inactive" <?= ($filters['availability'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive batches</option></select></div>
                    <div class="form-group col-md-6"><label for="pharmacy-inventory-expiry">Expiry state</label><select class="form-control" id="pharmacy-inventory-expiry" name="expiry_state"><option value="">All expiry states</option><option value="expired" <?= ($filters['expiry_state'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option><option value="near" <?= ($filters['expiry_state'] ?? '') === 'near' ? 'selected' : '' ?>>Near expiry (30 days)</option><option value="valid" <?= ($filters['expiry_state'] ?? '') === 'valid' ? 'selected' : '' ?>>Beyond 30 days</option></select></div>
                </div>
                <div class="form-group"><label for="pharmacy-inventory-medicine">Medicine</label><select class="form-control" id="pharmacy-inventory-medicine" name="medicine_id"><option value="0">All medicines</option><?php foreach (($medicines ?? []) as $medicine): ?><option value="<?= (int) $medicine['id'] ?>" <?= (int) ($filters['medicine_id'] ?? 0) === (int) $medicine['id'] ? 'selected' : '' ?>><?= esc($medicine['medicine_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="pharmacy-inventory-page-size">Rows per page</label><select class="form-control" id="pharmacy-inventory-page-size" name="page_size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($filters['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer"><a class="btn btn-light" href="<?= base_url('pharmacy/inventory?view=' . esc($view ?? 'list')) ?>">Clear</a><button class="btn btn-primary" type="submit">Apply filters</button></div>
        </form>
    </div></div>
</div>
