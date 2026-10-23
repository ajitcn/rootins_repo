<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyCustomerIdentityTest extends TestCase
{
    public function testIdentityMigrationContainsRequiredFieldsAndConstraints(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-140000_CreateShopifyPatientIdentityLinks.php');

        foreach (['shopify_store_domain', 'shopify_customer_id', 'patient_id', 'email_snapshot', 'mobile_snapshot', 'link_status', 'matched_by', 'linked_at'] as $field) {
            self::assertStringContainsString("'{$field}'", $migration);
        }

        self::assertStringContainsString("addUniqueKey(['shopify_store_domain', 'shopify_customer_id'])", $migration);
        self::assertStringContainsString("addUniqueKey('active_patient_key')", $migration);
    }

    public function testCustomerMatchingIsExactScopedAndUsesShopifySourceMetadata(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerIdentityService.php');
        $registration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString('where(\'patients.email\', $email)', $service);
        self::assertStringContainsString('where(\'patients.mobile_normalized\', $mobile[\'mobile\'])', $service);
        self::assertStringContainsString("where('patients.clinic_id', \$clinicId)", $service);
        self::assertStringContainsString("'source_type' => 'shopify'", $service);
        self::assertStringContainsString("'shopify'", $registration);
        self::assertStringContainsString('$status = \'ambiguous\'', $service);
    }

    public function testMedicineOrderCustomerResolutionUsesWebhookConfigurationAndKeepsUnlinkedCustomersRetryable(): void
    {
        $lookup = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerLookupService.php');
        $import = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $configuration = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfiguration.php');

        self::assertStringContainsString('new ShopifyClient($configuration)', $lookup);
        self::assertStringContainsString('read_customers', $lookup);
        self::assertStringContainsString('hydrateOrderCustomer', $import);
        self::assertStringContainsString('resolveOrderCustomer', $import . file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerIdentityService.php'));
        self::assertStringContainsString("'link_pending'", $import);
        self::assertStringContainsString("'guest'", $import);
        self::assertStringContainsString('Customer data unavailable', $import);
        self::assertStringContainsString('webhook_secret', $configuration);
        self::assertStringNotContainsString('ShopifyProxyConfigurationService', $lookup);
    }

    public function testWebhookProcessingUsesEventIdempotencyAndDoesNotStoreFullPayload(): void
    {
        $eventService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString('where(\'external_event_id\', $eventId)', $eventService);
        self::assertStringContainsString("foreach (['id', 'email', 'phone', 'first_name', 'last_name']", $eventService);
        self::assertStringContainsString('processCustomerEvent', $webhook);
        self::assertStringNotContainsString("'payload' =>", $eventService);
    }

    public function testHandoffIsSignedScopedExpiringAndSingleUse(): void
    {
        $handoff = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyHandoffService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Handoff.php');

        self::assertStringContainsString("'aud' => 'rootins-patient-session'", $handoff);
        self::assertStringContainsString("'exp' =>", $handoff);
        self::assertStringContainsString("hash_equals", $handoff);
        self::assertStringContainsString("where('used_at IS NULL', null, false)", $handoff);
        self::assertStringContainsString('session()->regenerate(true)', $controller);
        self::assertStringNotContainsString('password', $handoff);
        self::assertStringNotContainsString('access_token', $handoff);
    }

    public function testAdminActionsUseDedicatedPermissionsAndConfirmation(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        foreach (['customers/link', 'customers/create', 'customers/reject', 'customers/unlink'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        self::assertStringContainsString('permission:shopify.integration.manage', $routes);
        self::assertStringContainsString('I confirm this identity action', $controller);
    }

    public function testAdminDiagnosticUsesStoreCustomerPatientReferenceAndStatusWithoutInternalPatientId(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_customers.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerIdentityService.php');

        foreach (['shopify_store_domain', 'shopify_customer_id', 'patient_reference', 'link_status'] as $field) self::assertStringContainsString($field, $view);
        self::assertStringContainsString("unset(\$row['patient_id'])", $service);
    }
}
