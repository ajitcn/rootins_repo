<?php

/** @var array<int, array<string, mixed>> $imports */
?>
<main class="container-fluid py-4" id="shopify-order-imports">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Shopify order reconciliation</h1>
            <p class="text-muted mb-0">Only verified, mapped orders with an approved ROOTINS prescription can enter fulfilment.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify integration</a>
    </div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <caption class="sr-only">Shopify order import queue</caption>
                <thead>
                    <tr>
                        <th>Shopify order</th>
                        <th>Status</th>
                        <th>Patient/clinic</th>
                        <th>Failure category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($imports === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No Shopify orders have been received.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($imports as $import): ?>
                        <?php $failureReason = trim((string) ($import['failure_reason'] ?? '')); $failureCategory = $failureReason === '' ? '' : \App\Services\Shopify\ShopifyOrderImportFailure::normalize($failureReason); ?>
                        <tr>
                            <td><?= esc($import['shopify_order_number'] ?: $import['shopify_order_id']) ?></td>
                            <td><span class="badge badge-light"><?= esc($import['status']) ?></span></td>
                            <td><?= (int) ($import['patient_id'] ?? 0) > 0 ? 'Linked patient' : 'Pending link' ?></td>
                            <td><?= $failureCategory !== '' ? '<span class="badge badge-light">' . esc($failureCategory) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if (in_array($import['status'], ['pending_mapping', 'quarantined', 'failed'], true)): ?>
                                    <form method="post" action="<?= base_url('admin/integrations/shopify/orders/import') ?>" class="d-flex align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="import_id" value="<?= (int) $import['id'] ?>">
                                        <input type="number" name="prescription_id" class="form-control form-control-sm mr-2" placeholder="Approved prescription ID" aria-label="Approved prescription ID">
                                        <button class="btn btn-sm btn-primary" type="submit">Import</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
