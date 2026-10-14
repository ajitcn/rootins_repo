<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyOrderImportTest extends TestCase
{
    public function testPhaseFiveRoutesPermissionsAndTablesAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-170000_CreateShopifyOrderImport.php');
        $permissions = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-171000_AddShopifyOrderImportPermissions.php');

        self::assertStringContainsString("orders/create", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php'));
        self::assertStringContainsString("orders/import", $routes);
        self::assertStringContainsString("orders/reconcile", $routes);
        self::assertStringContainsString('shopify.order.import', $permissions);
        self::assertStringContainsString('shopify.order.reconcile', $permissions);

        foreach (['shopify_order_imports', 'shopify_order_import_lines', 'shopify_medicine_mappings', 'shopify_order_id', 'shopify_line_item_id'] as $field) {
            self::assertStringContainsString($field, $migration);
        }
    }

    public function testImportUsesIdentityAndControlledMappingBeforeFulfilment(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('linkedCustomer', $service);
        self::assertStringContainsString('storeLinesAndResolveMappings', $service);
        self::assertStringContainsString('approvedPrescriptionMatches', $service);
        self::assertStringContainsString('new OrderFulfilmentService', $service);
        self::assertStringNotContainsString("where('medicine_name'", $service);
        self::assertStringNotContainsString('unit_price', $service);
    }

    public function testWebhookOrderTopicsUseExistingHmacAndEventIdempotencyPipeline(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $eventService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');

        foreach (['orders/create', 'orders/updated', 'orders/cancelled', 'refunds/create', 'fulfillments/create', 'fulfillments/update'] as $topic) {
            self::assertStringContainsString($topic, $controller);
        }
        self::assertStringContainsString('isValid($rawBody, $signature)', $controller);
        self::assertStringContainsString('external_event_id', $eventService);
        self::assertStringContainsString('replacePayload', $eventService);
    }

    public function testSensitiveShopifyFieldsAreNotStoredByTheOrderPipeline(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        foreach (['access_token', 'password', 'otp', 'webhook_secret'] as $secret) {
            self::assertStringNotContainsString($secret, strtolower($service));
        }
    }
}
