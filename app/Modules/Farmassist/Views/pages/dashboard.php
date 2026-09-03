<?= $this->extend(FARMASSIST_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="farmassist-dashboard-title">
    <div class="page-heading"><div><div class="page-eyebrow">Operations / fulfilment</div><h1 id="farmassist-dashboard-title" class="h3 mb-1">Farmassist dashboard</h1><div class="text-muted">Clinic scope: <?= (int) $clinicId ?></div></div><a class="btn btn-primary" href="<?= base_url('farmassist/prescriptions') ?>"><i class="bi bi-prescription2 mr-1" aria-hidden="true"></i>Review prescriptions</a></div>

    <div class="row mb-4">
        <?php foreach ([
            'Review queue' => $reviewCount,
            'Medicine orders' => $orderCount,
            'Pending delivery' => $pendingDelivery,
            'Audit alerts today' => $auditAlerts ?? 0,
        ] as $label => $value): ?>
            <div class="col-md-4 mb-3"><div class="card ri-stat-card h-100"><div class="card-body"><div class="text-muted small"><?= esc($label) ?></div><div class="ri-stat-value mt-2"><?= (int) $value ?></div><span class="ri-stat-icon" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></span></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Approved prescriptions awaiting order</h5></div><div class="card-body">
        <?php if ($approvedPrescriptions === []): ?><p class="text-muted mb-0">No approved prescriptions are awaiting an order.</p><?php else: ?>
            <?php foreach ($approvedPrescriptions as $prescription): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span><?= esc($prescription['prescription_number']) ?> · <?= esc(trim(($prescription['first_name'] ?? '') . ' ' . ($prescription['last_name'] ?? ''))) ?></span>
                    <button type="button" class="btn btn-sm btn-primary create-order" data-prescription-id="<?= (int) $prescription['id'] ?>">Create order</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header d-flex flex-wrap align-items-center justify-content-between"><h2 class="h5 mb-2 mb-md-0">Recent orders</h2><button class="btn btn-sm btn-light" type="button" data-toggle="modal" data-target="#farmassist-order-filters"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button></div><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Order</th><th>Patient</th><th>Status</th><th>Payment</th><th>Delivery</th><th>Actions</th></tr></thead><tbody>
        <?php if ($orders === []): ?><tr><td colspan="6" class="text-center text-muted">No medicine orders found.</td></tr><?php else: ?>
            <?php foreach ($orders as $order): ?><tr data-order-id="<?= (int) $order['id'] ?>">
                <td><?= esc($order['order_number']) ?></td>
                <td><?= esc(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $order['status']))) ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $order['payment_status']))) ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $order['delivery_status']))) ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary payment-action" type="button" data-status="paid">Record payment</button>
                    <button class="btn btn-sm btn-outline-secondary delivery-action" data-status="packed">Pack</button>
                    <button class="btn btn-sm btn-outline-secondary delivery-action" data-status="dispatched">Dispatch</button>
                    <button class="btn btn-sm btn-outline-secondary delivery-action" data-status="out_for_delivery">Out for delivery</button>
                    <button class="btn btn-sm btn-outline-success delivery-action" data-status="delivered">Delivered</button>
                    <button class="btn btn-sm btn-outline-danger delivery-action" data-status="failed">Failed</button>
                    <button class="btn btn-sm btn-outline-danger delivery-action" data-status="returned">Returned</button>
                </td>
            </tr><?php endforeach; ?>
        <?php endif; ?></tbody>
    </table></div><?= view(FARMASSIST_VIEW . 'parts/order_pagination', ['pagination' => $orderPagination ?? [], 'filters' => $orderFilters ?? []]) ?></div>
</main>
<div class="modal fade" id="farmassist-order-filters" tabindex="-1" role="dialog" aria-labelledby="farmassist-order-filters-title" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form method="get" action="<?= current_url() ?>"><div class="modal-header"><h2 class="modal-title h5" id="farmassist-order-filters-title">Filter medicine orders</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="form-group"><label for="farmassist-order-search">Search orders</label><input class="form-control" id="farmassist-order-search" name="order_search" value="<?= esc($orderFilters['search'] ?? '') ?>" placeholder="Order, registration, or patient"></div><div class="form-group"><label for="farmassist-delivery-status">Delivery status</label><select class="form-control btSelect" id="farmassist-delivery-status" name="delivery_status"><option value="">All delivery statuses</option><?php foreach (['pending','packed','dispatched','out_for_delivery','delivered','failed','returned'] as $deliveryStatus): ?><option value="<?= esc($deliveryStatus) ?>" <?= ($orderFilters['delivery_status'] ?? '') === $deliveryStatus ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $deliveryStatus))) ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><a class="btn btn-light" href="<?= current_url() ?>">Clear</a><button class="btn btn-primary" type="submit">Apply filters</button></div></form></div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    $('.create-order').on('click', function () {
        const button = $(this);
        ajaxRequest({url: '<?= base_url('farmassist/orders/create') ?>', data: {prescription_id: button.data('prescription-id')}, success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(700); } else { showError(response.msg); } }});
    });
    $('.delivery-action').on('click', function () {
        const button = $(this), row = button.closest('tr');
        ajaxRequest({url: '<?= base_url('farmassist/orders/delivery') ?>', data: {order_id: row.data('order-id'), delivery_status: button.data('status')}, success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(700); } else { showError(response.msg); } }});
    });
    $('.payment-action').on('click', function () {
        const row = $(this).closest('tr');
        const reference = window.prompt('Payment reference (no secrets):');
        if (!reference) return;
        ajaxRequest({url: '<?= base_url('farmassist/orders/payment') ?>', data: {order_id: row.data('order-id'), payment_status: 'paid', provider: 'cashfree', reference: reference}, success: function (response) { if (response.status) { showSuccess(response.msg); reload_page(700); } else { showError(response.msg); } }});
    });
});
</script>
<?= $this->endSection() ?>
