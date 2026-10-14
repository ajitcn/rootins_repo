<?php
$permissions = session('permissions') ?? [];
$orderId = (int) ($row['id'] ?? 0);
$orderNumber = (string) ($row['order_number'] ?? 'order');
$status = (string) ($row['delivery_status'] ?? 'pending');
$canUpdate = !empty($row['can_update']) && in_array('pharmacy.delivery.update', $permissions, true);
$isListView = !empty($listView);
ob_start();
?>
<?php if (in_array('pharmacy.delivery.view', $permissions, true)): ?>
    <button class="dropdown-item js-delivery-detail" role="menuitem" type="button" data-order-id="<?= $orderId ?>"><i class="bi bi-truck mr-2" aria-hidden="true"></i>View delivery</button>
<?php endif; ?>
<?php if ($canUpdate): ?>
    <button class="dropdown-item js-delivery-update" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>" data-current-status="<?= esc($status, 'attr') ?>" data-provider="<?= esc((string) ($row['provider'] ?? 'manual'), 'attr') ?>" data-provider-id="<?= (int) ($row['provider_id'] ?? 0) ?>" data-provider-template="<?= esc((string) ($row['tracking_url_template'] ?? ''), 'attr') ?>" data-tracking="<?= esc((string) ($row['tracking_reference'] ?? ''), 'attr') ?>"><i class="bi bi-pencil-square mr-2" aria-hidden="true"></i>Update delivery status</button>
<?php endif; ?>
<?php if (in_array('pharmacy.order.view', $permissions, true)): ?>
    <button class="dropdown-item js-delivery-related" role="menuitem" type="button" data-related-url="<?= esc(base_url('pharmacy/orders/detail/' . $orderId), 'attr') ?>" data-related-title="Order details"><i class="bi bi-bag mr-2" aria-hidden="true"></i>View order</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.view', $permissions, true)): ?>
    <button class="dropdown-item js-delivery-related" role="menuitem" type="button" data-related-url="<?= esc(base_url('pharmacy/billing/detail/' . $orderId), 'attr') ?>" data-related-title="Invoice details"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>View invoice</button>
    <button class="dropdown-item js-delivery-related" role="menuitem" type="button" data-related-url="<?= esc(base_url('pharmacy/billing/detail/' . $orderId), 'attr') ?>" data-related-title="Payment details"><i class="bi bi-credit-card mr-2" aria-hidden="true"></i>View payment</button>
<?php endif; ?>
<?php if (in_array('pharmacy.prescription.review.view', $permissions, true) && !empty($row['prescription_id'])): ?>
    <a class="dropdown-item" role="menuitem" href="<?= esc(base_url('pharmacy/prescriptions?search=' . rawurlencode((string) ($row['prescription_number'] ?? $row['prescription_id'])), false), 'attr') ?>"><i class="bi bi-file-medical mr-2" aria-hidden="true"></i>View prescription</a>
<?php endif; ?>
<?php if (in_array('pharmacy.audit.view', $permissions, true)): ?>
    <a class="dropdown-item" role="menuitem" href="<?= esc(base_url('pharmacy/audit?search=' . rawurlencode($orderNumber)), 'attr') ?>"><i class="bi bi-shield-check mr-2" aria-hidden="true"></i>View audit history</a>
<?php endif; ?>
<?php $items = ob_get_clean(); ?>
<?= view(PHARMACY_VIEW . 'parts/more_actions', [
    'actionId' => 'delivery-actions-' . $orderId,
    'ariaLabel' => 'More actions for delivery ' . $orderNumber,
    'class' => 'pharmacy-delivery-actions' . ($isListView ? ' pharmacy-delivery-list-actions' : ''),
    'menuClass' => $isListView ? 'pharmacy-delivery-list-actions-menu' : '',
    'items' => $items,
]) ?>
