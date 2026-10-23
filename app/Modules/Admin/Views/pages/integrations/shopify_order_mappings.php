<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php
$summary = $summary ?? ['total_products' => 0, 'mapped' => 0, 'unmapped' => 0, 'conflicts' => 0, 'inactive' => 0];
$items = $items ?? [];
$mappings = $mappings ?? [];
$medicines = $medicines ?? [];
$store = $store_domain ?? '';
$lastSync = $last_sync ?? null;
$hasCompletedSync = is_array($lastSync) && in_array(($lastSync['status'] ?? ''), ['success', 'failed'], true);
$activeMappingStatus = $active_mapping_status ?? 'needs';
$tabCounts = $tab_counts ?? ['needs' => count($items), 'mapped' => count($mappings), 'conflicts' => 0, 'inactive' => 0];
$tabUrl = static fn (string $status): string => base_url('admin/integrations/shopify/order-mappings') . '?' . http_build_query(array_merge($filters ?? [], ['mapping_status' => $status])) . '#mapping-' . $status;
$rows = [];
if ($activeMappingStatus === 'needs') {
    foreach ($items as $item) {
        $rows[] = [
            'item' => $item, 'product' => $item['title_snapshot'] ?? 'Untitled Shopify product',
            'product_id' => $item['shopify_product_id'] ?? '', 'variant' => $item['variant_title'] ?? ($item['shopify_variant_id'] ?? 'Variant unavailable'),
            'variant_id' => $item['shopify_variant_id'] ?? '', 'sku' => $item['sku'] ?? '', 'barcode' => $item['barcode'] ?? '',
            'medicine' => 'Not mapped', 'strength_unit' => '', 'prescription' => ! empty($item['prescription_required']),
            'prescription_status' => ! empty($item['prescription_required']) ? 'Required' : 'Not Required',
            'status' => 'Unmapped', 'badge' => 'warning', 'updated' => $item['earliest_order_date'] ?? '',
            'orders' => (int) ($item['pending_orders'] ?? 0), 'quarantined' => (int) ($item['quarantined_orders'] ?? 0), 'mapped' => false,
        ];
    }
} else {
    foreach ($mappings as $mapping) {
        $isInactive = ($mapping['medicine_status'] ?? $mapping['status'] ?? '') !== 'active';
        $rows[] = [
            'item' => $mapping, 'product' => $mapping['shopify_product_title'] ?? 'Shopify product',
            'product_id' => $mapping['shopify_product_id'] ?? '', 'variant' => $mapping['shopify_variant_title'] ?? ($mapping['shopify_variant_id'] ?? 'Variant unavailable'),
            'variant_id' => $mapping['shopify_variant_id'] ?? '', 'sku' => $mapping['shopify_sku'] ?? '', 'barcode' => $mapping['shopify_barcode'] ?? '',
            'medicine' => $mapping['medicine_name'] ?? 'Missing', 'strength_unit' => trim(($mapping['strength'] ?? '') . ' ' . ($mapping['unit'] ?? '')),
            'prescription' => ! empty($mapping['prescription_required']), 'prescription_status' => ! empty($mapping['prescription_required']) ? 'Required' : 'Not Required', 'status' => $activeMappingStatus === 'conflicts' ? 'Conflict' : ($isInactive ? 'Inactive Medicine' : 'Mapped'),
            'badge' => $activeMappingStatus === 'conflicts' ? 'danger' : ($isInactive ? 'secondary' : 'success'),
            'updated' => $mapping['updated_at'] ?? '', 'orders' => (int) ($mapping['affected_orders'] ?? 0), 'quarantined' => 0, 'mapped' => true,
        ];
    }
}
?>
<meta name="csrf-token-name" content="<?= esc(csrf_token(), 'attr') ?>">
<meta name="csrf-token-hash" content="<?= esc(csrf_hash(), 'attr') ?>">
<style>
    .modal-backdrop { z-index: 1040 !important; }
    .modal { z-index: 1050 !important; }
    .bootstrap-select .dropdown-menu { z-index: 1100 !important; }
    #shopifyMappingModal, #shopifyIgnoreModal, #shopifyMappingDetailsModal, #shopifySyncDetailsModal { display: none !important; position: fixed; z-index: 1050 !important; }
    #shopifyMappingModal.show { display: flex !important; }
    #shopifyIgnoreModal.show, #shopifyMappingDetailsModal.show, #shopifySyncDetailsModal.show { display: block !important; }
    body > .modal-backdrop { z-index: 1040 !important; }
</style>
<main class="container-fluid py-4 shopify-mapping-page<?= ! $hasCompletedSync ? ' mapping-no-sync' : '' ?>" id="shopify-order-mappings"
      data-sync-url="<?= base_url('admin/integrations/shopify/order-mappings/sync') ?>"
      data-recheck-url="<?= base_url('admin/integrations/shopify/order-mappings/recheck') ?>"
      data-medicines-url="<?= base_url('admin/integrations/shopify/order-mappings/medicines') ?>"
      data-store-domain="<?= esc($store, 'attr') ?>">
    <div class="mapping-page-header d-flex flex-wrap justify-content-between align-items-start mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Admin / Integrations / Shopify / Order Operations</p>
            <h1 class="h4 mb-1">Shopify Product Mapping</h1>
            <p class="text-muted mb-0">Map each Shopify product variant to the correct ROOTINS medicine before orders are imported.</p>
        </div>
        <div class="mapping-header-actions mt-3 mt-md-0">
            <button type="button" class="btn btn-primary mr-2 js-sync-products"><i class="bi bi-arrow-repeat mr-1"></i>Sync Shopify Products</button>
            <button type="button" class="btn btn-outline-secondary mr-2 js-recheck-all"><i class="bi bi-arrow-clockwise mr-1"></i>Recheck Affected Orders</button>
            <?php if ($lastSync !== null): ?><button type="button" class="btn btn-link btn-sm js-view-sync-details" data-toggle="modal" data-target="#shopifySyncDetailsModal">View sync details</button><?php endif; ?>
        </div>
    </div>
    <?php if ($lastSync !== null): ?>
        <div class="alert <?= ($lastSync['status'] ?? '') === 'success' ? 'alert-light' : 'alert-warning' ?> d-flex flex-wrap justify-content-between align-items-center mb-4" role="status">
            <span><strong>Last sync:</strong> <?= esc($lastSync['completed_at'] ?? $lastSync['started_at'] ?? 'Unknown') ?> · <?= esc(ucfirst((string) ($lastSync['status'] ?? 'unknown'))) ?> · <?= (int) ($lastSync['products_fetched'] ?? 0) ?> products / <?= (int) ($lastSync['variants_fetched'] ?? 0) ?> variants<?php if (! empty($lastSync['error_category'])): ?> · <strong><?= esc($lastSync['error_category']) ?></strong><?php endif; ?></span>
            <span class="small text-muted">Created <?= (int) ($lastSync['created_count'] ?? 0) ?> · Updated <?= (int) ($lastSync['updated_count'] ?? 0) ?> · Failed <?= (int) ($lastSync['failed_count'] ?? 0) ?></span>
        </div>
    <?php endif; ?>
    <div class="card mapping-filter-card mb-4"><div class="card-body"><form class="row align-items-end" method="get">
        <input type="hidden" name="mapping_status" value="<?= esc($activeMappingStatus, 'attr') ?>">
        <div class="col-lg-4 col-md-6 mb-2"><label for="mapping-search">Search products</label><input id="mapping-search" class="form-control" name="product" placeholder="Product, variant, SKU, or barcode" value="<?= esc($filters['product'] ?? '') ?>"></div>
        <div class="col-lg-2 col-md-3 mb-2"><label for="mapping-status">Mapping status</label><select id="mapping-status" class="form-control" name="status"><option value="">All statuses</option><option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Mapped</option><option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
        <div class="col-lg-2 col-md-3 mb-2"><label for="medicine-status">Medicine status</label><select id="medicine-status" class="form-control" name="medicine_status"><option value="">All medicines</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div class="col-lg-2 col-md-3 mb-2"><label for="prescription-required">Prescription</label><select id="prescription-required" class="form-control" name="prescription_required"><option value="">Any</option><option value="1">Required</option><option value="0">Not required</option></select></div>
        <div class="col-lg-2 col-md-3 mb-2"><label for="affected-orders">Affected orders</label><select id="affected-orders" class="form-control" name="affected_orders"><option value="">Any</option><option value="1">With affected orders</option><option value="0">None</option></select></div>
        <div class="col-12 d-flex justify-content-end mt-2"><a class="btn btn-light btn-sm mr-2" href="<?= base_url('admin/integrations/shopify/order-mappings') ?>">Clear filters</a><button class="btn btn-dark btn-sm" type="submit">Apply filters</button></div>
    </form></div></div>
    <?php /* The four supported filters are intentionally mutually exclusive: $activeMappingStatus === 'needs', $activeMappingStatus === 'mapped', $activeMappingStatus === 'conflicts', $activeMappingStatus === 'inactive'. */ ?>
    <div class="row mapping-summary mb-4"><?php foreach ([['total_products', 'Total Products', 'bi-box-seam'], ['mapped', 'Mapped', 'bi-check2-circle'], ['unmapped', 'Needs Mapping', 'bi-exclamation-circle'], ['conflicts', 'Conflicts', 'bi-shield-exclamation'], ['inactive', 'Inactive', 'bi-pause-circle']] as [$key, $label, $icon]): ?><div class="col-6 col-xl mb-3"><div class="card h-100 mapping-summary-card"><div class="card-body"><div class="text-muted small"><i class="bi <?= esc($icon) ?> mr-1" aria-hidden="true"></i><span aria-label="<?= $key === 'total_products' ? 'Total Shopify products' : esc($label) ?>"><?= esc($label) ?></span></div><div class="h3 mb-0 mt-2" data-summary="<?= esc($key) ?>"><?= (int) ($summary[$key] ?? 0) ?></div></div></div></div><?php endforeach; ?></div>
    <div class="mapping-toolbar d-flex flex-wrap align-items-center mb-3"><ul class="nav nav-pills mapping-tabs" role="tablist">
        <?php foreach (['needs' => 'Needs Mapping', 'mapped' => 'Mapped', 'conflicts' => 'Conflicts', 'inactive' => 'Inactive'] as $status => $label): ?><li class="nav-item"><a class="nav-link <?= $activeMappingStatus === $status ? 'active' : '' ?>" id="mapping-tab-<?= $status ?>" role="tab" href="<?= esc($tabUrl($status), 'attr') ?>" aria-controls="mapping-<?= $status ?>" aria-selected="<?= $activeMappingStatus === $status ? 'true' : 'false' ?>"><?= $label ?> <span class="badge badge-light ml-1"><?= (int) ($tabCounts[$status] ?? 0) ?></span></a></li><?php endforeach; ?>
    </ul></div>
    <?php if ($activeMappingStatus === 'needs' && (int) ($summary['total_products'] ?? 0) === 0): ?><div class="mapping-sync-empty card mb-4"><div class="card-body text-center py-5"><i class="bi bi-shop display-4 d-block mb-3"></i><h2 class="h5">No Shopify variants were synchronized yet.</h2><p class="text-muted mb-3">Run Shopify product sync to populate the mapping workspace.</p><button type="button" class="btn btn-primary js-sync-products"><i class="bi bi-arrow-repeat mr-1"></i>Sync Shopify Products</button></div></div><?php endif; ?>
    <?php if ($activeMappingStatus === 'needs'): ?><form id="mapping-bulk-form" class="mapping-bulk-bar d-none mb-3" data-url="<?= base_url('admin/integrations/shopify/order-mappings/bulk-save') ?>"><?= csrf_field() ?><input type="hidden" name="store_domain" value="<?= esc($store) ?>"><span><strong data-selected-count>0</strong> selected</span><select class="form-control form-control-sm ml-2" name="medicine_id"><option value="">Choose ROOTINS medicine</option><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= esc(($medicine['medicine_name'] ?? '') . ' · ' . ($medicine['strength'] ?? '') . ' ' . ($medicine['unit'] ?? '')) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary ml-2" type="submit">Apply mapping</button></form><?php endif; ?>
    <div class="card mapping-results-card" id="mappingResults"><div class="table-responsive"><table class="table mapping-results-table mb-0"><thead><tr><th>Shopify Product</th><th>ROOTINS Medicine</th><th>Prescription required</th><th>Mapping Status</th><th>Last Updated</th><th class="mapping-actions-column text-right">Actions</th></tr></thead><tbody>
        <?php if ($rows === []): ?><tr><td colspan="6" class="mapping-empty-state text-center text-muted py-5">No <?= esc($activeMappingStatus === 'needs' ? 'unmapped Shopify variants need attention.' : strtolower($activeMappingStatus) . ' mappings found.') ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $row): $payload = $row['item']; $payload['title_snapshot'] = $payload['title_snapshot'] ?? $row['product']; $payload['shopify_product_title'] = $payload['shopify_product_title'] ?? $row['product']; $payload['shopify_product_id'] = $payload['shopify_product_id'] ?? $row['product_id']; $payload['variant_title'] = $payload['variant_title'] ?? ($payload['shopify_variant_title'] ?? $row['variant']); $payload['shopify_variant_title'] = $payload['shopify_variant_title'] ?? $row['variant']; $payload['shopify_variant_id'] = $payload['shopify_variant_id'] ?? $row['variant_id']; $payload['sku'] = $payload['sku'] ?? ($payload['shopify_sku'] ?? $row['sku']); $payload['shopify_sku'] = $payload['shopify_sku'] ?? $row['sku']; $payload['barcode'] = $payload['barcode'] ?? ($payload['shopify_barcode'] ?? $row['barcode']); $payload['shopify_barcode'] = $payload['shopify_barcode'] ?? $row['barcode']; $payload['product_handle'] = $payload['product_handle'] ?? ($payload['shopify_product_handle'] ?? ''); $payload['product_type'] = $payload['product_type'] ?? ($payload['shopify_product_type'] ?? ''); $payload['vendor'] = $payload['vendor'] ?? ($payload['shopify_vendor'] ?? ''); $payload['image_url'] = $payload['image_url'] ?? ($payload['product_image_url'] ?? ''); $payload['prescription_required'] = ! empty($payload['prescription_required']); ?><tr>
            <td data-label="Shopify Product"><strong class="mapping-primary-text"><?= esc($row['product']) ?></strong><small class="mapping-secondary-text">Product ID <?= esc($row['product_id'] ?: '—') ?></small></td>
            <td data-label="ROOTINS Medicine"><strong><?= esc($row['medicine']) ?></strong><?php if ($row['strength_unit'] !== ''): ?><small class="mapping-secondary-text"><?= esc($row['strength_unit']) ?></small><?php endif; ?></td>
            <td data-label="Prescription"><span class="badge badge-light" aria-label="Prescription status"><?= esc($row['prescription_status'] ?? ($row['prescription'] ? 'Required' : 'Not Required')) ?></span></td>
            <td data-label="Mapping Status"><span class="badge badge-<?= esc($row['badge']) ?>"><?= esc($row['status']) ?></span></td>
            <td data-label="Last Updated"><span class="mapping-secondary-text"><?= esc($row['updated'] ?: '—') ?></span><?php if ($row['orders'] > 0): ?><small class="mapping-secondary-text"><?= $row['orders'] ?> affected · <?= $row['quarantined'] ?> quarantined</small><?php endif; ?></td>
            <td data-label="Actions" class="mapping-actions-cell text-right"><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More Actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item js-more-details" data-item='<?= esc(json_encode($payload), 'attr') ?>'><i class="bi bi-info-circle mr-2"></i>More Details</button><button type="button" class="dropdown-item <?= $row['mapped'] ? 'js-edit-mapping' : 'js-map-medicine' ?>" data-<?= $row['mapped'] ? 'mapping' : 'item' ?>='<?= esc(json_encode($payload), 'attr') ?>'><i class="bi bi-link-45deg mr-2"></i><?= $row['mapped'] ? 'Edit mapping' : 'Map medicine' ?></button><button type="button" class="dropdown-item js-recheck-item" data-store="<?= esc($store, 'attr') ?>" data-product="<?= esc($row['product_id'], 'attr') ?>" data-variant="<?= esc($row['variant_id'], 'attr') ?>"><i class="bi bi-arrow-clockwise mr-2"></i>Recheck orders</button><a class="dropdown-item" href="<?= base_url('admin/integrations/shopify/order-operations') ?>?search=<?= rawurlencode((string) $row['variant_id']) ?>"><i class="bi bi-box-arrow-up-right mr-2"></i>View affected orders</a><?php if (! $row['mapped']): ?><button type="button" class="dropdown-item text-warning js-ignore-mapping" data-item='<?= esc(json_encode($payload), 'attr') ?>'><i class="bi bi-eye-slash mr-2"></i>Ignore product</button><?php endif; ?></div></div></td>
        </tr><?php endforeach; ?>
    </tbody></table></div></div>
</main>
<div class="modal fade mapping-modal" id="shopifyMappingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document"><div class="modal-content"><form id="shopify-mapping-form" data-url="<?= base_url('admin/integrations/shopify/order-mappings/save') ?>">
        <?= csrf_field() ?><input type="hidden" name="store_domain" value="<?= esc($store) ?>"><input type="hidden" name="id"><input type="hidden" name="shopify_product_id"><input type="hidden" name="shopify_variant_id"><input type="hidden" name="shopify_product_title"><input type="hidden" name="shopify_variant_title"><input type="hidden" name="shopify_sku"><input type="hidden" name="status" value="active">
        <div class="modal-header"><h2 class="modal-title h5">Map Shopify variant</h2><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div class="alert alert-danger d-none" data-mapping-errors role="alert"></div><div class="row"><div class="col-md-5 mapping-preview-panel"><p class="text-uppercase text-muted small mb-2">Shopify product</p><div class="mapping-preview-image"><i class="bi bi-image"></i></div><h3 class="h5" data-preview="title">Select a product</h3><p class="mb-1" data-preview="variant"></p><p class="small text-muted mb-1" data-preview="ids"></p><p class="small text-muted" data-preview="sku"></p><div class="alert alert-light small mb-0">Product price is not used for ROOTINS medicine pricing.</div></div><div class="col-md-7"><label for="mappingMedicineSelect">ROOTINS medicine</label><select id="mappingMedicineSelect" name="medicine_id" class="form-control btSelect" data-live-search="true" data-live-search-placeholder="Search medicine..." required><option value="">Select ROOTINS medicine</option><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= esc(trim(($medicine['medicine_name'] ?? '') . ' ' . ($medicine['strength'] ?? '') . ' ' . ($medicine['unit'] ?? ''))) ?></option><?php endforeach; ?></select><div class="small text-muted py-3<?= $medicines === [] ? '' : ' d-none' ?>" data-medicine-empty>No active ROOTINS medicines are available.</div><div class="alert alert-warning d-none mt-3" data-conflict-warning></div></div></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save mapping</button></div>
    </form></div></div>
</div>
<div class="modal fade mapping-modal" id="shopifyMappingDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5">Shopify product details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
        <div class="modal-body">
            <div class="mapping-details-heading d-flex align-items-start mb-4"><div class="mapping-preview-image mr-3 mb-0" data-details="image"><i class="bi bi-image"></i></div><div><h3 class="h5 mb-1" data-details="product">Shopify product</h3><p class="small text-muted mb-0" data-details="product_id"></p></div></div>
            <div class="row mapping-details-grid">
                <div class="col-md-6"><dl class="mapping-details-list"><dt>Variant title</dt><dd data-details="variant"></dd><dt>Variant ID</dt><dd data-details="variant_id"></dd><dt>SKU</dt><dd data-details="sku"></dd><dt>Barcode</dt><dd data-details="barcode"></dd></dl></div>
                <div class="col-md-6"><dl class="mapping-details-list"><dt>Product handle</dt><dd data-details="handle"></dd><dt>Product type</dt><dd data-details="type"></dd><dt>Vendor</dt><dd data-details="vendor"></dd><dt>Prescription metadata</dt><dd data-details="prescription"></dd></dl></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div>
    </div></div>
</div>
<div class="modal fade" id="shopifyIgnoreModal" tabindex="-1" role="dialog"><div class="modal-dialog modal-dialog-centered" role="document"><form class="modal-content" id="shopify-ignore-form" data-url="<?= base_url('admin/integrations/shopify/order-mappings/ignore') ?>"><?= csrf_field() ?><input type="hidden" name="store_domain" value="<?= esc($store) ?>"><input type="hidden" name="shopify_product_id"><input type="hidden" name="shopify_variant_id"><div class="modal-header"><h2 class="modal-title h5">Ignore product mapping</h2><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><p class="small text-muted" data-ignore-title></p><label>Reason <span class="text-danger">*</span></label><textarea class="form-control" name="reason" required maxlength="255"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-outline-warning">Ignore</button></div></form></div></div></div>
<div class="modal fade" id="shopifySyncDetailsModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Shopify product sync details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-7">Status</dt><dd class="col-5"><?= esc(ucfirst((string) ($lastSync['status'] ?? 'Unknown'))) ?></dd><dt class="col-7">Products fetched</dt><dd class="col-5"><?= (int) ($lastSync['products_fetched'] ?? 0) ?></dd><dt class="col-7">Variants fetched</dt><dd class="col-5"><?= (int) ($lastSync['variants_fetched'] ?? 0) ?></dd><dt class="col-7">Created</dt><dd class="col-5"><?= (int) ($lastSync['created_count'] ?? 0) ?></dd><dt class="col-7">Updated</dt><dd class="col-5"><?= (int) ($lastSync['updated_count'] ?? 0) ?></dd><dt class="col-7">Unchanged</dt><dd class="col-5"><?= (int) ($lastSync['unchanged_count'] ?? 0) ?></dd><dt class="col-7">Deactivated</dt><dd class="col-5"><?= (int) ($lastSync['deactivated_count'] ?? 0) ?></dd><dt class="col-7">Failed</dt><dd class="col-5"><?= (int) ($lastSync['failed_count'] ?? 0) ?></dd><?php if (! empty($lastSync['error_category'])): ?><dt class="col-7">Error category</dt><dd class="col-5"><code><?= esc($lastSync['error_category']) ?></code></dd><?php endif; ?></dl></div></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/admin/shopify/order-mappings.js?v=20261023-ui20') ?>"></script>
<?= $this->endSection() ?>
