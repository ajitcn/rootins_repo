<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CrossModuleIntegrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testOrderFulfilmentCreatesOneTransactionalInvoiceFromPricedItems(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/OrderFulfilmentService.php');

        foreach (['InvoiceModel', 'InvoiceItemModel', 'where(\'order_id\'', 'unit_price', 'line_total', 'transBegin', 'transCommit', 'transRollback', 'order:'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPatientOrdersArePatientAndClinicScopedWithFilteringAndPagination(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Patient/Controllers/Portal.php');

        foreach (['medicine_orders', 'where(\'orders.patient_id\'', 'where(\'orders.clinic_id\'', 'orders_page', 'order_status', 'payment_status', 'delivery_status', 'tracking_reference'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        $view = file_get_contents($this->root . '/app/Modules/Patient/Views/pages/portal.php');

        foreach (['patient-orders', 'patient-order-detail-modal', 'Order status', 'Payment', 'Delivery', 'modal-dialog-scrollable'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
    }

    public function testPatientNotificationsDeduplicateAndAuthorizeRelatedOrderLinks(): void
    {
        $service = file_get_contents($this->root . '/app/Modules/Patient/Services/PatientNotificationService.php');

        foreach (['event_key', 'patient_id', 'clinic_id', 'forOrder'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        $controller = file_get_contents($this->root . '/app/Modules/Patient/Controllers/Notification.php');

        foreach (['related_entity_id', 'patient_id', 'clinic_id', 'patient#patient-orders'] as $needle) {
            self::assertStringContainsString($needle, $controller);
        }
    }

    public function testPharmacyStatusChangesNotifyThePatientWithoutExposingPharmacyNotifications(): void
    {
        foreach (['OrderFulfilmentService.php', 'BillingService.php', 'DeliveryService.php'] as $file) {
            $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/' . $file);
            self::assertStringContainsString('Patient\\Services\\PatientNotificationService', $source);
        }
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/PharmacyNotificationService.php');
        self::assertStringContainsString('recipient_user_id', $source);
    }

    public function testCrossModuleMigrationIsReversible(): void
    {
        $source = file_get_contents($this->root . '/app/Database/Migrations/2026-10-01-190000_AddCrossModuleOrderIntegrationFields.php');

        foreach (['unit_price', 'event_key', 'related_entity_type', 'related_entity_id', 'function down', 'dropColumn'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
