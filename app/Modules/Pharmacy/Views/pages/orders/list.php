<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-orders-page" aria-labelledby="pharmacy-orders-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/orders/_toolbar') ?>
    <section class="card pharmacy-orders-card" aria-label="Medicine orders">
        <div class="table-responsive pharmacy-orders-table-wrap">
            <table class="table pharmacy-orders-table mb-0">
                <caption class="sr-only">Medicine orders</caption>
                <thead><tr><th scope="col">Order</th><th scope="col">Patient</th><th scope="col">Medicines</th><th scope="col">Payment</th><th scope="col">Order status</th><th scope="col">Delivery status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php if (($orders ?? []) === []): ?>
                    <tr><td colspan="7"><div class="pharmacy-orders-empty" role="status"><i class="bi bi-bag-x d-block" aria-hidden="true"></i><strong>No medicine orders found</strong><span>Adjust the search or filters, or create an order from an approved prescription.</span></div></td></tr>
                <?php else: foreach ($orders as $order): ?>
                    <?php $orderStatusClass = $order['status'] === 'cancelled' ? 'danger' : ($order['status'] === 'fulfilled' ? 'success' : 'warning'); $paymentStatusClass = $order['payment_status'] === 'paid' ? 'success' : (in_array($order['payment_status'], ['failed', 'refunded'], true) ? 'danger' : 'info'); $deliveryStatusValue = trim((string) ($order['delivery_status'] ?? '')); $deliveryStatusMissing = $deliveryStatusValue === '' || strtolower($deliveryStatusValue) === 'not recorded'; $deliveryStatusClass = $deliveryStatusMissing ? 'light' : ($deliveryStatusValue === 'delivered' ? 'success' : (in_array($deliveryStatusValue, ['failed', 'returned'], true) ? 'danger' : 'info')); $deliveryTracking = array_values(array_unique(array_filter([(string) ($order['delivery_provider'] ?? ''), (string) ($order['tracking_reference'] ?? '')], static fn (string $value): bool => trim($value) !== '' && strtolower(trim($value)) !== 'not recorded'))); ?>
                    <tr>
                        <td data-label="Order"><div class="pharmacy-order-stack"><strong class="order-value" title="<?= esc($order['order_number'], 'attr') ?>"><?= esc($order['order_number']) ?></strong><span class="order-meta"><?= esc($order['order_date']) ?></span><span class="order-meta" title="<?= esc($order['prescription_number'], 'attr') ?>">Prescription: <?= esc($order['prescription_number']) ?></span><span class="order-meta">Rx date: <?= esc($order['prescription_date']) ?></span></div></td>
                        <td data-label="Patient"><div class="pharmacy-order-stack"><strong class="order-value" title="<?= esc($order['patient_name'], 'attr') ?>"><?= esc($order['patient_name']) ?></strong><span class="order-meta"><?= esc($order['patient_number']) ?></span><span class="order-meta" title="<?= esc($order['patient_mobile'], 'attr') ?>"><?= esc($order['patient_mobile']) ?></span></div></td>
                        <td data-label="Medicines"><div class="pharmacy-order-stack"><span class="order-count-badge"><?= (int) $order['medicine_count'] ?> <?= (int) $order['medicine_count'] === 1 ? 'medicine' : 'medicines' ?></span></div></td>
                        <td data-label="Payment"><div class="pharmacy-order-stack"><span class="badge payment-status-badge badge-<?= $paymentStatusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $order['payment_status'] ?: 'Unknown'))) ?></span><span class="order-meta">Provider: <?= esc($order['payment_provider_name'] ?? $order['payment_provider']) ?></span><span class="order-meta">Invoice: <?= esc($order['invoice_number']) ?></span><strong class="order-amount">INR <?= number_format((float) $order['total_amount'], 2) ?></strong></div></td>
                        <td data-label="Order status"><div class="pharmacy-order-stack"><span class="badge order-status-badge badge-<?= $orderStatusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $order['status'] ?: 'Unknown'))) ?></span></div></td>
                        <td data-label="Delivery status"><div class="pharmacy-order-stack"><span class="badge delivery-status-badge badge-<?= $deliveryStatusClass ?>"><?= esc($deliveryStatusMissing ? 'Not recorded' : ucwords(str_replace('_', ' ', $deliveryStatusValue))) ?></span><span class="order-meta">Provider: <?= esc($order['delivery_provider']) ?></span><span class="order-meta order-tracking-summary" title="<?= esc($order['tracking_reference'], 'attr') ?>">Tracking: <?= esc($order['tracking_reference']) ?></span><span class="order-meta">Dispatched: <?= esc($order['dispatched_at'] ?: 'Not recorded') ?></span><span class="order-meta">Delivered: <?= esc($order['delivered_at'] ?: 'Not recorded') ?></span></div></td>
                        <td data-label="Actions" class="order-actions-cell"><?= view(PHARMACY_VIEW . 'pages/orders/_actions', ['order' => $order]) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= $this->include(PHARMACY_VIEW . 'parts/order_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_detail_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_create_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_labels_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/delivery/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/orders/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
