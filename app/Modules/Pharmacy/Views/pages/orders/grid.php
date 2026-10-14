<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-orders-page" aria-labelledby="pharmacy-orders-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/orders/_toolbar') ?>
    <section class="pharmacy-order-grid" aria-label="Medicine order cards">
        <?php if (($orders ?? []) === []): ?>
            <div class="card pharmacy-orders-empty" role="status"><i class="bi bi-bag-x d-block" aria-hidden="true"></i><strong>No medicine orders found</strong><span>Adjust the search or filters, or create an order from an approved prescription.</span></div>
        <?php else: foreach ($orders as $order): ?>
            <?php
            $orderStatusClass = $order['status'] === 'cancelled' ? 'danger' : ($order['status'] === 'fulfilled' ? 'success' : 'warning');
            $paymentStatusClass = $order['payment_status'] === 'paid' ? 'success' : (in_array($order['payment_status'], ['failed', 'refunded'], true) ? 'danger' : 'info');
            $deliveryStatusValue = trim((string) ($order['delivery_status'] ?? ''));
            $deliveryStatusMissing = $deliveryStatusValue === '' || strtolower($deliveryStatusValue) === 'not recorded';
            $deliveryStatusClass = $deliveryStatusMissing ? 'light' : ($deliveryStatusValue === 'delivered' ? 'success' : (in_array($deliveryStatusValue, ['failed', 'returned'], true) ? 'danger' : 'info'));
            $deliveryTracking = array_values(array_unique(array_filter([(string) ($order['delivery_provider'] ?? ''), (string) ($order['tracking_reference'] ?? '')], static fn (string $value): bool => trim($value) !== '' && strtolower(trim($value)) !== 'not recorded')));
            ?>
            <article class="card pharmacy-order-card">
                <header class="order-card-header">
                    <div class="order-card-header-content">
                        <span class="order-card-eyebrow">Medicine order</span>
                        <h2 class="h6 mb-1 order-value" title="<?= esc($order['order_number'], 'attr') ?>"><?= esc($order['order_number']) ?></h2>
                        <div class="order-card-header-meta"><span><?= esc($order['order_date']) ?></span><span>Prescription: <?= esc($order['prescription_number']) ?></span><span>Rx date: <?= esc($order['prescription_date']) ?></span></div>
                    </div>
                </header>

                <section class="order-card-section order-card-patient" aria-label="Patient">
                    <span class="order-card-label">Patient</span>
                    <strong class="order-value" title="<?= esc($order['patient_name'], 'attr') ?>"><?= esc($order['patient_name']) ?></strong>
                    <div class="order-card-meta-list"><span class="order-meta"><?= esc($order['patient_number']) ?></span><span class="order-meta"><?= esc($order['patient_mobile']) ?></span></div>
                </section>

                <section class="order-card-section order-card-summary" aria-label="Summary">
                    <div><span class="order-card-label">Medicines</span><strong><?= (int) $order['medicine_count'] ?> <?= (int) $order['medicine_count'] === 1 ? 'medicine' : 'medicines' ?></strong></div>
                    <div><span class="order-card-label">Invoice</span><span class="order-meta order-card-wrap"><?= esc($order['invoice_number']) ?></span></div>
                    <div><span class="order-card-label">Total amount</span><strong>INR <?= number_format((float) $order['total_amount'], 2) ?></strong></div>
                </section>

                <section class="order-card-section order-card-statuses" aria-label="Order, payment, and delivery status">
                    <div class="order-card-status-block"><span class="order-card-label">Order Status</span><span class="badge order-status-badge badge-<?= $orderStatusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $order['status'] ?: 'Unknown'))) ?></span></div>
                    <div class="order-card-status-block"><span class="order-card-label">Payment Status</span><span class="badge payment-status-badge badge-<?= $paymentStatusClass ?>"><?= esc(ucwords(str_replace('_', ' ', $order['payment_status'] ?: 'Unknown'))) ?><small class="d-block mt-1">Provider: <?= esc($order['payment_provider_name'] ?? $order['payment_provider']) ?></small></span></div>
                    <div class="order-card-status-block"><span class="order-card-label">Delivery Status</span><span class="badge delivery-status-badge badge-<?= $deliveryStatusClass ?>"><?= esc($deliveryStatusMissing ? 'Not recorded' : ucwords(str_replace('_', ' ', $deliveryStatusValue))) ?></span><span class="order-meta">Provider: <?= esc($order['delivery_provider']) ?></span><span class="order-meta order-tracking-summary" title="<?= esc($order['tracking_reference'], 'attr') ?>">Tracking: <?= esc($order['tracking_reference']) ?></span><span class="order-meta">Dispatched: <?= esc($order['dispatched_at'] ?: 'Not recorded') ?></span><span class="order-meta">Delivered: <?= esc($order['delivered_at'] ?: 'Not recorded') ?></span></div>
                </section>

                <footer class="order-card-footer">
                    <?= view(PHARMACY_VIEW . 'pages/orders/_actions', ['order' => $order]) ?>
                </footer>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/order_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_detail_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_create_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/orders/_labels_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->include(PHARMACY_VIEW . 'pages/delivery/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/orders/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
