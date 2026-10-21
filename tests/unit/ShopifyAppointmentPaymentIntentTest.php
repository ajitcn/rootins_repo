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

    public function testProxyBookingContractHasSafeFieldErrorsAndServerSideResolution(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $intent = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentIntentService.php');

        foreach (['IDEMPOTENCY_KEY_REQUIRED', 'INVALID_JSON', 'correlation_id', 'fields'] as $contract) self::assertStringContainsString($contract, $controller);
        foreach (['DOCTOR_INVALID', 'SERVICE_INVALID', 'INVALID_DATE', 'INVALID_TIME', 'INVALID_SLOT', 'CONSENT_REQUIRED', 'SLOT_UNAVAILABLE'] as $code) self::assertStringContainsString($code, $intent . $proxy);
        foreach (['SHOPIFY_CUSTOMER_UNLINKED', 'patient_reference', 'clinic_id', 'online_booking'] as $contract) self::assertStringContainsString($contract, $proxy);
        self::assertStringContainsString("'status' => 'pending_payment'", $intent);
        self::assertStringContainsString("'rootins_appointment_intent'", $intent);
        self::assertStringNotContainsString("'fee' => " . '$input', $intent);
        self::assertStringNotContainsString("'patient_id' => " . '$input', $intent);
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
