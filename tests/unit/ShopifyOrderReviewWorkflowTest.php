<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ShopifyOrderReviewWorkflowTest extends CIUnitTestCase
{
    public function testReviewWorkflowContractsArePresent(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderReviewService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-26-120000_CreateShopifyOrderReviewWorkflow.php');
        $outbox = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderNotificationOutboxService.php');
        foreach (['prescription_missing', 'prescription_invalid', 'out_of_stock', 'approved', 'rejected', 'substituted', 'fulfilled'] as $status) self::assertStringContainsString($status, $service);
        foreach (['shopify_order_notifications', 'safe_payload_json', 'retry_count', 'last_error'] as $field) self::assertStringContainsString($field, $migration);
        self::assertStringContainsString('NOTIFICATION_DELIVERY_FAILED', $outbox . file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportFailure.php'));
    }

    public function testReviewRoutesAndCustomerScopeAreExplicit(): void
    {
        $pharmacy = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $admin = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $customer = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/CustomerOrderStatus.php');
        self::assertStringContainsString("shopify-orders/review/line", $pharmacy);
        self::assertStringContainsString("shopify-orders/review/order", $pharmacy);
        self::assertStringContainsString("order-operations/review/line", $admin);
        self::assertStringContainsString("store_domain", $customer);
        self::assertStringContainsString("shopify_customer_id", $customer);
    }
}
