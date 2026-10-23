<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php
$this->setData(['eyebrow' => 'Medicine Orders / Shopify', 'title' => 'Shopify Orders', 'titleId' => 'pharmacy-shopify-orders-title', 'description' => 'Review imported Shopify orders, prescriptions, mappings, payment, delivery, and processing history.', 'count' => count($imports ?? []), 'countLabel' => 'orders in scope']);
$filters = $filters ?? [];
$statusOptions = ['received' => 'Received', 'pending_mapping' => 'Pending mapping', 'ready_to_import' => 'Ready to process', 'quarantined' => 'Quarantined', 'imported' => 'Imported', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];
$paymentOptions = ['paid' => 'Paid', 'authorized' => 'Authorized', 'partially_paid' => 'Partially paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'voided' => 'Voided'];
$prescriptionOptions = ['awaiting_review' => 'Awaiting review', 'accepted' => 'Accepted', 'invalid' => 'Invalid'];
$mappingOptions = ['mapped' => 'Mapped', 'unmapped' => 'Unmapped', 'conflict' => 'Mapping conflict', 'inactive_medicine' => 'Inactive medicine'];
$deliveryOptions = ['pending' => 'Pending', 'packed' => 'Packed', 'fulfilled' => 'Fulfilled', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered', 'failed' => 'Failed'];
$badge = static fn (string $value): string => 'badge badge-status badge-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($value ?: 'unknown'));
?>
<main class="dashboard-container pharmacy-shopify-orders-page" aria-labelledby="pharmacy-shopify-orders-title">
    <?= $this->include(PHARMACY_VIEW . 'parts/inner_page_header') ?>
    <section class="pharmacy-shopify-summary" aria-label="Shopify order summary">
        <?php foreach ([['total_shopify_orders', 'Total Shopify Orders', 'bi-bag-check'], ['awaiting_review', 'Awaiting Review', 'bi-hourglass-split'], ['quarantined', 'Quarantined', 'bi-shield-exclamation'], ['unmapped', 'Unmapped', 'bi-link-45deg'], ['prescription_review', 'Prescription Review', 'bi-prescription2'], ['ready_to_process', 'Ready to Process', 'bi-check2-circle']] as [$key, $label, $icon]): ?>
            <div class="pharmacy-shopify-summary-card"><span class="summary-icon"><i class="bi <?= esc($icon) ?>" aria-hidden="true"></i></span><span class="summary-copy"><span><?= esc($label) ?></span><strong><?= (int) ($counters[$key] ?? 0) ?></strong></span></div>
        <?php endforeach; ?>
    </section>
    <form class="pharmacy-shopify-filters" method="get" action="<?= base_url('pharmacy/shopify-orders') ?>">
        <div class="filter-field filter-search"><label for="shopifyOrderSearch">Search</label><input class="form-control form-control-sm" id="shopifyOrderSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Order number, customer, patient, or Shopify ID"></div>
        <?php foreach ([['status', 'Order status', $statusOptions], ['financial_status', 'Payment status', $paymentOptions], ['prescription_status', 'Prescription status', $prescriptionOptions], ['mapping_status', 'Mapping status', $mappingOptions], ['fulfillment_status', 'Delivery status', $deliveryOptions]] as [$field, $label, $options]): ?>
            <div class="filter-field"><label for="shopify-<?= esc($field) ?>"><?= esc($label) ?></label><select class="form-control form-control-sm" id="shopify-<?= esc($field) ?>" name="<?= esc($field) ?>"><option value="">All</option><?php foreach ($options as $value => $optionLabel): ?><option value="<?= esc($value) ?>" <?= ($filters[$field] ?? '') === $value ? 'selected' : '' ?>><?= esc($optionLabel) ?></option><?php endforeach; ?></select></div>
        <?php endforeach; ?>
        <div class="filter-field"><label for="shopify-date-from">From</label><input class="form-control form-control-sm" type="date" id="shopify-date-from" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>"></div>
        <div class="filter-field"><label for="shopify-date-to">To</label><input class="form-control form-control-sm" type="date" id="shopify-date-to" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>"></div>
        <div class="pharmacy-shopify-filter-actions"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/shopify-orders') ?>">Clear</a></div>
    </form>
    <section class="card pharmacy-shopify-table-card" aria-label="Imported Shopify orders">
        <div class="card-header d-flex justify-content-between align-items-center"><div><h2 class="h6 mb-1">Order queue</h2><p class="small text-muted mb-0">Use More Actions to inspect a full order or recheck its medicine mapping.</p></div><span class="small text-muted"><?= count($imports ?? []) ?> shown</span></div>
        <div class="table-responsive pharmacy-shopify-table-wrap"><table class="table pharmacy-shopify-table mb-0"><caption class="sr-only">Imported Shopify orders</caption><thead><tr><th>Shopify Order</th><th>Customer / Patient</th><th>Medicines</th><th>Payment</th><th>Prescription</th><th>Mapping</th><th>Order Status</th><th>Delivery</th><th>Last Updated</th><th>Actions</th></tr></thead><tbody>
        <?php if (($imports ?? []) === []): ?><tr><td colspan="10"><div class="pharmacy-orders-empty" role="status"><i class="bi bi-shop d-block" aria-hidden="true"></i><strong>No Shopify orders found</strong><span>Imported orders for this pharmacy clinic will appear here.</span></div></td></tr><?php else: foreach ($imports as $import): ?>
            <?php $orderId = (int) $import['id']; $customer = $import['customer_name_snapshot'] ?: $import['customer_email_snapshot'] ?: $import['shopify_customer_id'] ?: 'Unknown'; $medicineSummary = $import['medicine_summary'] ?: 'No line details'; ?>
            <tr>
                <td data-label="Shopify Order"><div class="shopify-order-primary"><a href="<?= base_url('pharmacy/shopify-orders/' . $orderId) ?>"><?= esc($import['shopify_order_number'] ?: $import['shopify_order_id']) ?></a><small><?= esc($import['shopify_order_id']) ?></small></div></td>
                <td data-label="Customer / Patient"><div class="shopify-order-primary"><strong><?= esc($customer) ?></strong><small><?= esc($import['registration_number'] ? 'Patient ' . $import['registration_number'] : 'Patient not linked') ?></small></div></td>
                <td data-label="Medicines"><span class="medicine-summary"><strong><?= (int) ($import['medicine_count'] ?? 0) ?></strong> <?= esc($medicineSummary) ?></span></td>
                <td data-label="Payment"><span class="<?= $badge((string) ($import['financial_status'] ?? '')) ?>"><?= esc(ucwords(str_replace('_', ' ', $import['financial_status'] ?: 'Unknown'))) ?></span></td>
                <td data-label="Prescription"><span class="<?= $badge((string) ($import['prescription_status'] ?? '')) ?>"><?= esc(ucwords(str_replace('_', ' ', $import['prescription_status'] ?: 'Not supplied'))) ?></span></td>
                <td data-label="Mapping"><span class="<?= $badge((string) ($import['mapping_status'] ?? '')) ?>"><?= esc(ucwords(str_replace('_', ' ', $import['mapping_status'] ?: 'Unknown'))) ?></span></td>
                <td data-label="Order Status"><span class="<?= $badge((string) ($import['status'] ?? '')) ?>"><?= esc(ucwords(str_replace('_', ' ', $import['status'] ?: 'Unknown'))) ?></span></td>
                <td data-label="Delivery"><span class="<?= $badge((string) ($import['fulfillment_status'] ?? '')) ?>"><?= esc(ucwords(str_replace('_', ' ', $import['fulfillment_status'] ?: 'Unknown'))) ?></span></td>
                <td data-label="Last Updated"><time datetime="<?= esc((string) ($import['updated_at'] ?? ''), 'attr') ?>"><?= esc($import['updated_at'] ?: '—') ?></time></td>
                <td data-label="Actions"><div class="dropdown"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More Actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item js-shopify-order-details" data-detail-url="<?= base_url('pharmacy/shopify-orders/' . $orderId . '/json') ?>">View details</button><a class="dropdown-item" href="<?= base_url('pharmacy/shopify-orders/' . $orderId) ?>">Open review</a><?php if (!empty($canManage)): ?><button type="button" class="dropdown-item js-shopify-recheck" data-action-url="<?= base_url('pharmacy/shopify-orders/action/' . $orderId) ?>">Recheck mapping</button><?php endif; ?></div></div></td>
            </tr>
        <?php endforeach; endif; ?></tbody></table></div>
    </section>
</main>
<div class="modal fade pharmacy-shopify-details-modal" id="shopifyOrderDetailsModal" tabindex="-1" role="dialog" aria-labelledby="shopifyOrderDetailsTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="shopifyOrderDetailsTitle">Shopify order details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="shopifyOrderDetailsBody"><div class="text-center text-muted py-5">Select an order to load its details.</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div></div></div></div>
<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/pharmacy/shopify_orders.js?v=20261023-shopify2') ?>"></script><?= $this->endSection() ?>
<?= $this->endSection() ?>
