<?php
$permissions = session('permissions') ?? [];
$orderId = (int) ($order['id'] ?? 0);
$orderNumber = (string) ($order['order_number'] ?? 'order');
$status = (string) ($order['status'] ?? '');
$paymentStatus = (string) ($order['payment_status'] ?? 'pending');
$deliveryStatus = (string) ($order['delivery_status'] ?? 'pending');
$invoiceNumber = (string) ($order['invoice_number'] ?? '');
$patientName = (string) ($order['patient_name'] ?? '');
$patientNumber = (string) ($order['patient_number'] ?? '');
$amount = (float) ($order['total_amount'] ?? 0);
$paidAmount = in_array($paymentStatus, ['paid', 'refunded'], true) ? $amount : 0.0;
$refundableAmount = $paymentStatus === 'paid' ? $amount : 0.0;
$currency = (string) ($order['currency'] ?? 'INR');
$paymentProvider = trim((string) ($order['payment_provider'] ?? ''));
ob_start();
?>
<?php if (in_array('pharmacy.order.view', $permissions, true)): ?>
    <button class="dropdown-item js-order-detail" role="menuitem" type="button" data-order-id="<?= $orderId ?>"><i class="bi bi-eye mr-2" aria-hidden="true"></i>View Order</button>
<?php endif; ?>
<?php if ($status === 'pending' && in_array('pharmacy.order.cancel', $permissions, true)): ?>
    <button class="dropdown-item text-danger js-order-cancel" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>"><i class="bi bi-x-circle mr-2" aria-hidden="true"></i>Cancel Order</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.view', $permissions, true)): ?>
    <button class="dropdown-item js-order-related" role="menuitem" type="button" data-related-url="<?= esc(base_url('pharmacy/billing/detail/' . $orderId), 'attr') ?>" data-related-title="Invoice and payment"><i class="bi bi-receipt mr-2" aria-hidden="true"></i>View Invoice</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.payment.update', $permissions, true) && $paymentStatus !== 'refunded'): ?>
    <button class="dropdown-item js-payment-reconcile" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>" data-order-status="<?= esc($status, 'attr') ?>" data-payment-status="<?= esc($paymentStatus, 'attr') ?>" data-provider="<?= esc($paymentProvider, 'attr') ?>" data-provider-name="<?= esc((string) ($order['payment_provider_name'] ?? ''), 'attr') ?>" data-delivery-status="<?= esc($deliveryStatus, 'attr') ?>" data-invoice-number="<?= esc($invoiceNumber, 'attr') ?>" data-patient-name="<?= esc($patientName, 'attr') ?>" data-patient-number="<?= esc($patientNumber, 'attr') ?>" data-currency="<?= esc($currency, 'attr') ?>" data-amount="<?= esc(number_format($amount, 2, '.', ''), 'attr') ?>" data-paid-amount="<?= esc(number_format($paidAmount, 2, '.', ''), 'attr') ?>" data-refundable-amount="<?= esc(number_format($refundableAmount, 2, '.', ''), 'attr') ?>"><i class="bi bi-credit-card mr-2" aria-hidden="true"></i>Update Payment</button>
<?php endif; ?>
<?php if (in_array('pharmacy.billing.refund', $permissions, true) && $paymentStatus === 'paid'): ?>
    <button class="dropdown-item js-payment-refund" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>" data-order-status="<?= esc($status, 'attr') ?>" data-payment-status="<?= esc($paymentStatus, 'attr') ?>" data-delivery-status="<?= esc($deliveryStatus, 'attr') ?>" data-invoice-number="<?= esc($invoiceNumber, 'attr') ?>" data-patient-name="<?= esc($patientName, 'attr') ?>" data-patient-number="<?= esc($patientNumber, 'attr') ?>" data-currency="<?= esc($currency, 'attr') ?>" data-amount="<?= esc(number_format($amount, 2, '.', ''), 'attr') ?>" data-paid-amount="<?= esc(number_format($paidAmount, 2, '.', ''), 'attr') ?>" data-refundable-amount="<?= esc(number_format($refundableAmount, 2, '.', ''), 'attr') ?>"><i class="bi bi-arrow-counterclockwise mr-2" aria-hidden="true"></i>Refund Payment</button>
<?php endif; ?>
<?php if (in_array('pharmacy.delivery.update', $permissions, true) && $deliveryStatus !== 'returned'): ?>
    <button class="dropdown-item js-delivery-update" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>" data-current-status="<?= esc($deliveryStatus, 'attr') ?>" data-order-status="<?= esc($status, 'attr') ?>" data-payment-status="<?= esc($paymentStatus, 'attr') ?>" data-patient-name="<?= esc((string) ($order['patient_name'] ?? ''), 'attr') ?>" data-patient-number="<?= esc((string) ($order['patient_number'] ?? ''), 'attr') ?>" data-prescription-number="<?= esc((string) ($order['prescription_number'] ?? ''), 'attr') ?>" data-provider="<?= esc((string) ($order['delivery_provider'] ?? 'manual'), 'attr') ?>" data-provider-id="<?= (int) ($order['delivery_provider_id'] ?? 0) ?>" data-provider-template="<?= esc((string) ($order['tracking_url_template'] ?? ''), 'attr') ?>" data-tracking="<?= esc((string) ($order['tracking_reference'] ?? ''), 'attr') ?>" data-address="<?= esc((string) ($order['delivery_address'] ?? ''), 'attr') ?>" data-contact="<?= esc((string) ($order['delivery_contact'] ?? ''), 'attr') ?>" data-dispatched-at="<?= esc((string) ($order['dispatched_at'] ?? ''), 'attr') ?>" data-delivered-at="<?= esc((string) ($order['delivered_at'] ?? ''), 'attr') ?>"><i class="bi bi-truck mr-2" aria-hidden="true"></i>Update Delivery</button>
<?php endif; ?>
<?php if (in_array('pharmacy.prescription.review.view', $permissions, true) && !empty($order['prescription_id'])): ?>
    <a class="dropdown-item" role="menuitem" href="<?= esc(base_url('pharmacy/prescriptions?search=' . rawurlencode((string) ($order['prescription_number'] ?? $order['prescription_id'])), false), 'attr') ?>"><i class="bi bi-file-medical mr-2" aria-hidden="true"></i>View Prescription</a>
<?php endif; ?>
<?php if (in_array('pharmacy.audit.view', $permissions, true)): ?>
    <a class="dropdown-item" role="menuitem" href="<?= esc(base_url('pharmacy/audit?search=' . rawurlencode($orderNumber)), 'attr') ?>"><i class="bi bi-shield-check mr-2" aria-hidden="true"></i>View Audit History</a>
<?php endif; ?>
<?php if ($status !== 'cancelled' && in_array('pharmacy.order.label.print', $permissions, true)): ?>
    <button class="dropdown-item js-order-labels" role="menuitem" type="button" data-order-id="<?= $orderId ?>" data-order-number="<?= esc($orderNumber, 'attr') ?>"><i class="bi bi-tags mr-2" aria-hidden="true"></i>Print Labels</button>
<?php endif; ?>
<?php $items = ob_get_clean(); ?>
<?= view(PHARMACY_VIEW . 'parts/more_actions', [
    'actionId' => 'order-actions-' . $orderId,
    'ariaLabel' => 'More actions for order ' . $orderNumber,
    'class' => 'pharmacy-order-actions',
    'items' => $items,
]) ?>
