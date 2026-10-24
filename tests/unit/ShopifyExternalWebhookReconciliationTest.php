<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ShopifyExternalWebhookReconciliationTest extends CIUnitTestCase
{
    public function testOnlyExternalTopicsUseDedicatedReconciliationPath(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString("processExternalEvent((int) \$result['id'], \$topic", $controller);
        self::assertStringContainsString("['refunds/create', 'fulfillments/create', 'fulfillments/update']", $controller);
        self::assertStringContainsString('public function processExternalEvent', $service);
        self::assertStringContainsString("WHERE store_domain = ? AND shopify_order_id = ?", $service);
        self::assertStringContainsString('shopify_order_import_refunds', $service);
        self::assertStringContainsString('shopify_order_import_fulfillments', $service);
        self::assertStringContainsString('internal_import_id_refund_id', $service);
        self::assertStringContainsString('internal_import_id_fulfillment_id', $service);
    }

    public function testExternalReconciliationIsDuplicateSafeAndDoesNotRequirePharmacyOrder(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-24-130000_CreateShopifyExternalReconciliation.php');

        self::assertStringContainsString('$existing !== null', $service);
        self::assertStringContainsString("'duplicate' => \$existing !== null", $service);
        self::assertStringContainsString("if ((int) (\$import['rootins_order_id'] ?? 0) > 0)", $service);
        self::assertStringContainsString('shopify_refund_identity', $migration);
        self::assertStringContainsString('shopify_fulfillment_identity', $migration);
    }

    public function testExternalRetryFailuresRemainCategorizedAndRetryable(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');

        self::assertStringContainsString("markFailed((int) \$result['id'], \$failure->category)", $controller);
        self::assertStringContainsString("if (\$status === 'failed')", $service);
        self::assertStringContainsString("'status' => 'processing'", $service);
    }
}
