<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-delivery-page pharmacy-delivery-list-page" aria-labelledby="pharmacy-delivery-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/delivery/_toolbar') ?>
    <section class="card pharmacy-delivery-card" aria-label="Pharmacy delivery records">
        <div class="table-responsive pharmacy-delivery-table-wrap">
            <table class="table pharmacy-delivery-table mb-0">
                <caption class="sr-only">Pharmacy delivery records</caption>
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Patient</th>
                        <th scope="col">Delivery</th>
                        <th scope="col">Order status</th>
                        <th scope="col">Payment status</th>
                        <th scope="col">Delivery status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($rows ?? []) === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="pharmacy-delivery-empty" role="status">
                                <i class="bi bi-truck d-block" aria-hidden="true"></i>
                                <strong>No delivery records found</strong>
                                <span>Adjust the search or filters.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $row): ?>
                    <?php
                    $orderStatus = (string) ($row['order_status'] ?? '');
                    $orderClass = $orderStatus === 'cancelled' ? 'danger' : ($orderStatus === 'fulfilled' ? 'success' : 'warning');
                    $paymentStatus = (string) ($row['payment_status'] ?? '');
                    $paymentClass = $paymentStatus === 'paid' ? 'success' : (in_array($paymentStatus, ['failed', 'refunded'], true) ? 'danger' : 'info');
                    $deliveryStatus = trim((string) ($row['delivery_status'] ?? ''));
                    $deliveryMissing = $deliveryStatus === '' || strtolower($deliveryStatus) === 'not recorded';
                    $deliveryClass = $deliveryMissing ? 'light' : (in_array($deliveryStatus, ['failed', 'returned'], true) ? 'danger' : ($deliveryStatus === 'delivered' ? 'success' : 'warning'));
                    ?>
                    <tr>
                        <td data-label="Order">
                            <strong class="delivery-value" title="<?= esc($row['order_number'], 'attr') ?>"><?= esc($row['order_number']) ?></strong>
                            <small class="delivery-meta d-block"><?= esc($row['created_at']) ?></small>
                            <small class="delivery-meta d-block">Prescription: <?= esc($row['prescription_number']) ?></small>
                        </td>
                        <td data-label="Patient">
                            <strong class="delivery-value d-block" title="<?= esc($row['patient_name'], 'attr') ?>"><?= esc($row['patient_name']) ?></strong>
                            <small class="delivery-meta d-block"><?= esc($row['patient_number']) ?></small>
                            <small class="delivery-meta d-block"><?= esc($row['patient_mobile']) ?></small>
                        </td>
                        <td data-label="Delivery">
                            <strong class="delivery-value d-block"><?= esc($row['provider']) ?></strong>
                            <small class="delivery-meta d-block delivery-wrap"><?= esc($row['tracking_reference'] === 'Not recorded' ? '—' : $row['tracking_reference']) ?></small>
                            <small class="delivery-meta d-block">Dispatched: <?= esc($row['dispatched_at'] ?: '—') ?></small>
                            <small class="delivery-meta d-block">Delivered: <?= esc($row['delivered_at'] ?: '—') ?></small>
                        </td>
                        <td data-label="Order status"><span class="badge delivery-status-badge badge-<?= $orderClass ?>"><?= esc(ucwords(str_replace('_', ' ', $orderStatus ?: 'Unknown'))) ?></span></td>
                        <td data-label="Payment status"><span class="badge delivery-status-badge badge-<?= $paymentClass ?>"><?= esc(ucwords(str_replace('_', ' ', $paymentStatus ?: 'Unknown'))) ?></span></td>
                        <td data-label="Delivery status"><span class="badge delivery-status-badge badge-<?= $deliveryClass ?>"><?= esc($deliveryMissing ? 'Not recorded' : ucwords(str_replace('_', ' ', $deliveryStatus))) ?></span></td>
                        <td data-label="Actions" class="delivery-actions-cell"><?= view(PHARMACY_VIEW . 'pages/delivery/_actions', ['row' => $row, 'listView' => true]) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= $this->include(PHARMACY_VIEW . 'parts/delivery_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/delivery/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/delivery/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
