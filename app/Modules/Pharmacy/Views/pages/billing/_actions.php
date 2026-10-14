<?php
$permissions = session('permissions') ?? [];
$rowId = (int) ($row['id'] ?? 0);
$paymentStatus = (string) ($row['payment_status'] ?? 'unavailable');
ob_start();
?>
<?php if (in_array('pharmacy.billing.view', $permissions, true)): ?>
    <button class="dropdown-item js-billing-detail" role="menuitem" type="button" data-order-id="<?= $rowId ?>"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>View Invoice</button>
    <button class="dropdown-item js-billing-detail" role="menuitem" type="button" data-order-id="<?= $rowId ?>"><i class="bi bi-credit-card mr-2" aria-hidden="true"></i>View Payment</button>
<?php endif; ?>
<?php if (in_array('pharmacy.order.view', $permissions, true)): ?>
    <button class="dropdown-item js-billing-related" role="menuitem" type="button" data-related-url="<?= esc(base_url('pharmacy/orders/detail/' . $rowId), 'attr') ?>" data-related-title="Order details"><i class="bi bi-bag-check mr-2" aria-hidden="true"></i>View Order</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.payment.update', $permissions, true) && in_array($paymentStatus, ['pending', 'authorized', 'failed'], true)): ?>
    <button class="dropdown-item js-payment-reconcile" role="menuitem" type="button" data-order-id="<?= $rowId ?>" data-order-number="<?= esc($row['order_number'], 'attr') ?>" data-payment-status="<?= esc($paymentStatus, 'attr') ?>" data-provider="<?= esc((string) ($row['provider'] ?? ''), 'attr') ?>" data-provider-name="<?= esc((string) ($row['provider_name'] ?? ''), 'attr') ?>" data-order-status="<?= esc((string) ($row['order_status'] ?? ''), 'attr') ?>" data-delivery-status="<?= esc((string) ($row['delivery_status'] ?? ''), 'attr') ?>" data-invoice-number="<?= esc((string) ($row['invoice_number'] ?? ''), 'attr') ?>" data-patient-name="<?= esc((string) ($row['patient_name'] ?? ''), 'attr') ?>" data-patient-number="<?= esc((string) ($row['patient_number'] ?? ''), 'attr') ?>" data-currency="<?= esc((string) ($row['currency'] ?? 'INR'), 'attr') ?>" data-amount="<?= esc((string) ($row['amount'] ?? 0), 'attr') ?>" data-paid-amount="<?= esc((string) ($row['paid_amount'] ?? 0), 'attr') ?>" data-refundable-amount="<?= esc((string) ($row['refundable_amount'] ?? 0), 'attr') ?>"><i class="bi bi-<?= $paymentStatus === 'failed' ? 'arrow-repeat' : 'check2-circle' ?> mr-2" aria-hidden="true"></i>Change payment status</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.refund', $permissions, true) && $paymentStatus === 'paid'): ?>
    <button class="dropdown-item text-danger js-payment-refund" role="menuitem" type="button" data-order-id="<?= $rowId ?>" data-order-number="<?= esc((string) ($row['order_number'] ?? ''), 'attr') ?>" data-payment-status="<?= esc($paymentStatus, 'attr') ?>" data-order-status="<?= esc((string) ($row['order_status'] ?? ''), 'attr') ?>" data-delivery-status="<?= esc((string) ($row['delivery_status'] ?? ''), 'attr') ?>" data-invoice-number="<?= esc((string) ($row['invoice_number'] ?? ''), 'attr') ?>" data-patient-name="<?= esc((string) ($row['patient_name'] ?? ''), 'attr') ?>" data-patient-number="<?= esc((string) ($row['patient_number'] ?? ''), 'attr') ?>" data-currency="<?= esc((string) ($row['currency'] ?? 'INR'), 'attr') ?>" data-amount="<?= esc((string) ($row['amount'] ?? 0), 'attr') ?>" data-paid-amount="<?= esc((string) ($row['paid_amount'] ?? 0), 'attr') ?>" data-refundable-amount="<?= esc((string) ($row['refundable_amount'] ?? 0), 'attr') ?>"><i class="bi bi-arrow-counterclockwise mr-2" aria-hidden="true"></i>Refund</button>
<?php endif; ?>
<?php if (in_array('pharmacy.audit.view', $permissions, true)): ?>
    <a class="dropdown-item" role="menuitem" href="<?= esc(base_url('pharmacy/audit?search=' . rawurlencode((string) ($row['order_number'] ?? ''))), 'attr') ?>"><i class="bi bi-shield-check mr-2" aria-hidden="true"></i>View Audit History</a>
<?php endif; ?>
<?php $items = ob_get_clean(); ?>
<?= view(PHARMACY_VIEW . 'parts/more_actions', [
    'actionId' => 'billing-actions-' . $rowId,
    'ariaLabel' => 'More actions for billing order ' . (string) ($row['order_number'] ?? 'order'),
    'class' => 'pharmacy-billing-actions',
    'items' => $items,
]) ?>
