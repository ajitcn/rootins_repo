<?php

use CodeIgniter\Test\CIUnitTestCase;

final class PharmacyOrdersWorkspaceTest extends CIUnitTestCase
{
    public function testOrdersWorkspaceOwnsDeliveryPresentationAndActions(): void
    {
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/grid.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_actions.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/orders.js');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $deliveryModal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_modal.php');
        $deliveryScript = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/delivery.js');

        foreach (['delivery_provider', 'tracking_reference', 'dispatched_at', 'delivered_at', 'failure_reason', 'return_reason'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        foreach (['Update Payment', 'Update Delivery', 'View Invoice', 'Print Labels', 'View Prescription', 'View Audit History'] as $action) {
            self::assertStringContainsString($action, $actions);
        }
        foreach (['Order Information', 'Patient and Prescription', 'Medicines and Stock', 'Payment and Invoice', 'Delivery', 'Audit History'] as $section) {
            self::assertStringContainsString($section, $script);
        }
        self::assertStringContainsString("'delivery/update', 'Delivery::update'", $routes);
        self::assertStringContainsString('pages/billing/_modal', $list . $grid);
        self::assertStringContainsString('pages/delivery/_modal', $list . $grid);
        foreach (['Order Summary', 'Delivery Information', 'Status Update', 'deliverySummaryOrderNumber', 'deliveryAddress', 'deliveryContact', 'deliveryDispatchedAt', 'deliveryDeliveredAt', 'deliveryCurrentStatus', 'deliveryReason', 'deliveryConfirmation'] as $field) {
            self::assertStringContainsString($field, $deliveryModal);
        }
        foreach (['deliverySummaryOrderStatus', 'deliverySummaryPaymentStatus', 'deliverySummaryDeliveryStatus', 'deliveryDispatchedAt', 'deliveryDeliveredAt', 'fillTargets(currentStatus)', 'valueOrNotRecorded'] as $field) {
            self::assertStringContainsString($field, $deliveryScript);
        }
    }

    public function testOrdersAndDeliveryRoutesRemainExplicitAndScoped(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        self::assertStringContainsString("'orders', 'Orders::index'", $routes);
        self::assertStringContainsString("'orders/delivery', 'Delivery::update'", $routes);
        self::assertStringContainsString("'delivery', 'Delivery::index'", $routes);
        self::assertStringContainsString('permission:pharmacy.order.view', $routes);
        self::assertStringContainsString('permission:pharmacy.delivery.view', $routes);
    }
}
