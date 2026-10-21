<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyAppointmentPaymentIntentTest extends TestCase
{
    public function testProxyBookingCreatesAnIntentAndReturnsShopifyMetadata(): void
    {
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $intent = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentIntentService.php');

        self::assertStringContainsString("new ShopifyAppointmentIntentService())->create", $proxy);
        self::assertStringContainsString("'status' => 'pending_payment'", $intent);
        self::assertStringContainsString('rootins_appointment_intent', $intent);
        self::assertStringContainsString('Idempotency-Key', file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php'));
        self::assertStringNotContainsString("CoreApiAppointmentService())->book", $proxy);
    }

    public function testPaidWebhookFinalizesOnlyFromTheServerCreatedIntent(): void
    {
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $intent = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentIntentService.php');
        $catalog = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php');
        $registration = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');

        self::assertStringContainsString("\$topic === 'orders/paid'", $webhook);
        self::assertStringContainsString('finalizePaid', $webhook);
        self::assertStringContainsString("'financial_status'", $intent);
        self::assertStringContainsString('FOR UPDATE', $intent);
        self::assertStringContainsString("'topic' => 'orders/paid'", $catalog);
        self::assertStringContainsString("'orders/paid'", $registration);
    }

    public function testExplicitPaidRouteAndIntentMigrationArePresent(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-21-120000_CreateShopifyAppointmentIntents.php');

        self::assertStringContainsString('integrations/shopify/webhooks/orders/paid', $routes);
        self::assertStringContainsString('shopify_appointment_intents', $migration);
        self::assertStringContainsString('pending_payment', $migration);
        self::assertStringContainsString('appointment_id', $migration);
    }
}
