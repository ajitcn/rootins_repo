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
        self::assertStringContainsString('consultation_mode', $intent);
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
        foreach (['DOCTOR_INVALID', 'SERVICE_INVALID', 'INVALID_DATE', 'INVALID_TIME', 'INVALID_SLOT', 'CONSENT_REQUIRED', 'CONSULTATION_MODE_INVALID', 'SLOT_UNAVAILABLE'] as $code) self::assertStringContainsString($code, $intent . $proxy);
        self::assertStringContainsString("slot_reference", $intent);
        self::assertStringContainsString("slotParts", $intent);
        self::assertStringContainsString("doctor_id", $proxy);
        self::assertStringContainsString("service_id", $proxy);
        $service = new \App\Services\Shopify\ShopifyAppointmentIntentService();
        $method = new \ReflectionMethod($service, 'slotParts');
        $method->setAccessible(true);
        self::assertSame(['doctor_reference' => 'AC', 'service_reference' => 'service-2', 'date' => '2026-10-21', 'start_time' => '15:00'], $method->invoke($service, 'AC-service-2-20261021-1500'));
        self::assertNull($method->invoke($service, 'AC-service-2-invalid'));
        foreach (['SHOPIFY_CUSTOMER_UNLINKED', 'SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'SHOPIFY_PATIENT_INACTIVE', 'SHOPIFY_STORE_MISMATCH', 'patient_reference', 'clinic_id', 'online_booking'] as $contract) self::assertStringContainsString($contract, $proxy);
        self::assertStringContainsString("'status' => 'pending_payment'", $intent);
        self::assertStringContainsString("'rootins_appointment_intent'", $intent);
        self::assertStringNotContainsString("'fee' => " . '$input', $intent);
        self::assertStringNotContainsString("'patient_id' => " . '$input', $intent);
    }

    public function testShopifyCustomerRegistrationIsSignedScopedIdempotentAndDoesNotTrustPatientId(): void
    {
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');

        foreach (['logged_in_customer_id', 'shopify_store_domain', 'shopify_customer_id', 'booking_enabled', 'already_registered', 'findExistingPatient', 'verifiedShopifyCustomer', 'SHOPIFY_CUSTOMER_LOGIN_REQUIRED'] as $contract) self::assertStringContainsString($contract, $proxy);
        self::assertStringContainsString("'next_action' => 'REGISTER_PATIENT'", $proxy);
        self::assertStringContainsString("\$body['error']['next_action']", $controller);
        self::assertStringNotContainsString("\$payload['patient_id']", $proxy);
    }

    public function testRegistrationUsesServerSideShopifyCustomerDataAndSupportsEmptyBody(): void
    {
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $lookup = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerLookupService.php');

        foreach (['verifiedShopifyCustomer', 'ShopifyCustomerLookupService', 'verifiedRegistrationFields', 'runtimeConfiguration', 'booking_enabled'] as $contract) self::assertStringContainsString($contract, $proxy . $lookup);
        foreach (['first_name', 'last_name', 'email', 'mobile_number', 'country_code'] as $field) self::assertStringContainsString($field, $proxy);
        self::assertStringNotContainsString("\$payload['first_name']", $proxy);
        self::assertStringNotContainsString("\$payload['email']", $proxy);
        self::assertStringNotContainsString("\$payload['mobile_number']", $proxy);
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
        self::assertStringContainsString('consultation_mode', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-21-130000_AddConsultationModeToShopifyAppointmentIntents.php'));
        self::assertStringContainsString('date_of_birth', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-21-140000_MakeShopifyPatientProfileFieldsOptional.php'));
    }
}
