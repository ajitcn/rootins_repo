<?php
$batchId = (int) ($row['batch_id'] ?? 0);
ob_start();
?>
<button class="dropdown-item inventory-detail-action" role="menuitem" type="button" data-toggle="modal" data-target="#pharmacy-inventory-detail" data-medicine="<?= esc($row['medicine_name'], 'attr') ?>" data-batch="<?= esc($row['batch_number'], 'attr') ?>" data-expiry="<?= esc($row['expiry_display'], 'attr') ?>" data-quantity="<?= (int) $row['quantity_available'] ?>" data-unit="<?= esc($row['unit'], 'attr') ?>" data-status="<?= esc($row['availability_label'], 'attr') ?>" data-expiry-state="<?= esc($row['expiry_label'], 'attr') ?>">
    <i class="bi bi-eye mr-2" aria-hidden="true"></i>View batch details
</button>
<?php if (in_array('pharmacy.inventory.batch.adjust', session('permissions') ?? [], true)): ?>
    <button class="dropdown-item inventory-adjust-action" role="menuitem" type="button" data-toggle="modal" data-target="#pharmacy-inventory-adjust" data-batch-id="<?= $batchId ?>" data-batch="<?= esc($row['batch_number'], 'attr') ?>" data-medicine="<?= esc($row['medicine_name'], 'attr') ?>">
        <i class="bi bi-sliders mr-2" aria-hidden="true"></i>Adjust central stock
    </button>
<?php endif; ?>
<?php $items = ob_get_clean(); ?>
<?= view(PHARMACY_VIEW . 'parts/more_actions', [
    'actionId' => 'inventory-actions-' . $batchId,
    'ariaLabel' => 'More actions for batch ' . (string) ($row['batch_number'] ?? ''),
    'class' => 'inventory-actions',
    'items' => $items,
]) ?>
