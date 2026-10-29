<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-billing-page" aria-labelledby="pharmacy-billing-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/billing/_toolbar') ?>
    <section class="card pharmacy-billing-card" aria-label="Billing and payment records">
        <div class="table-responsive pharmacy-billing-table-wrap">
            <table class="table pharmacy-billing-table mb-0">
                <caption class="sr-only">Pharmacy billing and payment records</caption>
                <thead><tr><th scope="col">Invoice</th><th scope="col">Patient</th><th scope="col">Amount</th><th scope="col">Payment status</th><th scope="col">Delivery status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr><td colspan="6"><div class="pharmacy-billing-empty" role="status"><i class="bi bi-receipt d-block" aria-hidden="true"></i><strong>No billing records found</strong><span>Payable orders without invoices are shown as unavailable.</span></div></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <?php $paymentStatus = (string) ($row['payment_status'] ?? ''); $paymentClass = $paymentStatus === 'paid' ? 'paid' : (in_array($paymentStatus, ['failed'], true) ? 'failed' : ($paymentStatus === 'refunded' ? 'refunded' : 'pending')); $delivery = trim((string) ($row['delivery_status'] ?? '')); $deliveryMissing = $delivery === '' || strtolower($delivery) === 'not recorded'; $deliveryClass = $deliveryMissing ? 'unavailable' : (in_array($delivery, ['failed', 'returned'], true) ? 'failed' : ($delivery === 'delivered' ? 'paid' : 'pending')); ?>
                    <tr>
                        <td data-label="Invoice"><strong class="billing-value" title="<?= esc($row['invoice_number'], 'attr') ?>"><?= esc($row['invoice_number']) ?></strong><small class="billing-meta d-block">Invoice date: <?= esc($row['created_at']) ?></small><small class="billing-meta d-block">Order: <?= esc($row['order_number']) ?></small></td>
                        <td data-label="Patient"><strong class="billing-value d-block" title="<?= esc($row['patient_name'], 'attr') ?>"><?= esc($row['patient_name']) ?></strong><small class="billing-meta d-block"><?= esc($row['patient_number']) ?></small><small class="billing-meta d-block"><?= esc($row['patient_mobile']) ?></small></td>
                        <td data-label="Amount"><strong class="billing-amount" data-amount="<?= esc((string) ($row['amount'] ?? '')) ?>" data-amount-source="<?= esc($row['amount_source_type'] ?? '') ?>"><?= esc($row['amount_display'] ?? 'Not available') ?></strong><?php if ($row['subtotal'] !== null): ?><small class="billing-meta d-block">Subtotal: <?= esc($row['currency']) ?> <?= number_format((float) $row['subtotal'], 2) ?></small><?php endif; ?><?php if ((float) $row['tax'] !== 0.0): ?><small class="billing-meta d-block">Tax: <?= esc($row['currency']) ?> <?= number_format((float) $row['tax'], 2) ?></small><?php endif; ?><?php if ((float) $row['discount'] !== 0.0): ?><small class="billing-meta d-block">Discount: <?= esc($row['currency']) ?> <?= number_format((float) $row['discount'], 2) ?></small><?php endif; ?></td>
                        <td data-label="Payment status"><span class="badge billing-payment-badge billing-payment-<?= esc($paymentClass, 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $paymentStatus ?: 'Unknown'))) ?></span><span class="order-meta d-block mt-1">Provider: <?= esc($row['provider_name'] ?? $row['provider'] ?? 'Not recorded') ?></span></td>
                        <td data-label="Delivery status"><span class="badge billing-delivery-badge billing-payment-<?= esc($deliveryClass, 'attr') ?>"><?= esc($deliveryMissing ? 'Not recorded' : ucwords(str_replace('_', ' ', $delivery))) ?></span></td>
                        <td data-label="Actions" class="billing-actions-cell"><?= view(PHARMACY_VIEW . 'pages/billing/_actions', ['row' => $row]) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= $this->include(PHARMACY_VIEW . 'parts/billing_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/billing/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/billing/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
