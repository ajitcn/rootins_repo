<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-billing-page" aria-labelledby="pharmacy-billing-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/billing/_toolbar') ?>
    <section class="pharmacy-billing-grid" aria-label="Billing and payment cards">
        <?php if (($rows ?? []) === []): ?>
            <div class="card pharmacy-billing-empty" role="status"><i class="bi bi-receipt d-block" aria-hidden="true"></i><strong>No billing records found</strong><span>Adjust the search or filters.</span></div>
        <?php else: foreach ($rows as $row): ?>
            <?php $paymentStatus = (string) ($row['payment_status'] ?? ''); $paymentClass = $paymentStatus === 'paid' ? 'paid' : (in_array($paymentStatus, ['failed'], true) ? 'failed' : ($paymentStatus === 'refunded' ? 'refunded' : 'pending')); $delivery = trim((string) ($row['delivery_status'] ?? '')); $deliveryMissing = $delivery === '' || strtolower($delivery) === 'not recorded'; $deliveryClass = $deliveryMissing ? 'unavailable' : (in_array($delivery, ['failed', 'returned'], true) ? 'failed' : ($delivery === 'delivered' ? 'paid' : 'pending')); ?>
            <article class="card pharmacy-billing-item">
                <header class="billing-card-header"><div class="min-width-0"><span class="billing-card-eyebrow">Invoice</span><h2 class="h6 mb-1 billing-value" title="<?= esc($row['invoice_number'], 'attr') ?>"><?= esc($row['invoice_number']) ?></h2><div class="billing-card-meta"><span><?= esc($row['created_at']) ?></span><span>Order: <?= esc($row['order_number']) ?></span></div></div></header>
                <section class="billing-card-section billing-card-patient" aria-label="Patient"><span class="billing-card-label">Patient</span><strong class="billing-value" title="<?= esc($row['patient_name'], 'attr') ?>"><?= esc($row['patient_name']) ?></strong><div class="billing-card-meta-list"><span class="billing-meta"><?= esc($row['patient_number']) ?></span><span class="billing-meta"><?= esc($row['patient_mobile']) ?></span></div></section>
                <section class="billing-card-section billing-card-amount" aria-label="Amount"><div><span class="billing-card-label">Total amount</span><strong class="billing-amount"><?= esc($row['currency']) ?> <?= number_format((float) $row['amount'], 2) ?></strong></div><div><span class="billing-card-label">Subtotal</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) $row['subtotal'], 2) ?></span></div><?php if ((float) $row['tax'] !== 0.0): ?><div><span class="billing-card-label">Tax</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) $row['tax'], 2) ?></span></div><?php endif; ?><?php if ((float) $row['discount'] !== 0.0): ?><div><span class="billing-card-label">Discount</span><span class="billing-meta"><?= esc($row['currency']) ?> <?= number_format((float) $row['discount'], 2) ?></span></div><?php endif; ?></section>
                <section class="billing-card-section billing-card-statuses" aria-label="Payment and delivery status"><div><span class="billing-card-label">Payment Status</span><span class="badge billing-payment-badge billing-payment-<?= esc($paymentClass, 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $paymentStatus ?: 'Unknown'))) ?></span><small class="d-block mt-1">Provider: <?= esc($row['provider_name'] ?? $row['provider'] ?? 'Not recorded') ?></small></div><div><span class="billing-card-label">Delivery Status</span><span class="badge billing-delivery-badge billing-payment-<?= esc($deliveryClass, 'attr') ?>"><?= esc($deliveryMissing ? 'Not recorded' : ucwords(str_replace('_', ' ', $delivery))) ?></span></div></section>
                <footer class="billing-card-footer"><?= view(PHARMACY_VIEW . 'pages/billing/_actions', ['row' => $row]) ?></footer>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/billing_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/billing/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
