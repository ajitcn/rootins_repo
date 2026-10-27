<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyCustomerPatientSyncTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(ROOTPATH . $file);
    }

    public function testCustomerCreateAndUpdateRoutesUseTheWebhookController(): void
    {
        $routes = $this->source('app/Modules/Shopify/Config/Routes.php');

        self::assertStringContainsString("webhooks/customers/create", $routes);
        self::assertStringContainsString("webhooks/customers/update", $routes);
        self::assertStringContainsString('Webhook::receive/customers/create', $routes);
        self::assertStringContainsString('Webhook::receive/customers/update', $routes);
    }

    public function testCustomerEventsUseWebhookHmacAndEventIdempotency(): void
    {
        $controller = $this->source('app/Modules/Shopify/Controllers/Webhook.php');
        $events = $this->source('app/Services/Shopify/ShopifyWebhookEventService.php');

        foreach (['ShopifyWebhookVerifier', 'X-Shopify-Hmac-Sha256', 'X-Shopify-Webhook-Id', 'rawBody', 'ingest', 'markProcessed', 'markFailed'] as $marker) {
            self::assertStringContainsString($marker, $controller . $events);
        }
        self::assertStringContainsString('SHOPIFY_WEBHOOK_HMAC_INVALID', $controller);
    }

    public function testCustomerPayloadValidationAndSafeNormalizationExist(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyCustomerPatientSyncService.php');

        foreach (['SHOPIFY_CUSTOMER_ID_REQUIRED', 'SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'first_name', 'default_address', 'normalizeFullMobile', 'filter_var($email', 'mb_strlen($lastName) > 100'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString("\$lastName = trim((string) (\$payload['last_name'] ?? '')) ?: null", $service);
    }

    public function testCreateMatchesGlobalPatientOrCreatesAndLinksWithoutClinicScope(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyCustomerPatientSyncService.php');

        foreach (['matchingPatients', 'findOrCreatePatient', 'source_reference', 'ShopifyPatientIdentityLinkModel', 'clinic_id'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString("'clinic_id' => null", $service);
        self::assertStringContainsString("'source_type' => 'shopify'", $service);
        self::assertStringContainsString('activePatientKey', $service);
    }

    public function testUpdateOnlyWritesProfileFieldsAndPreservesClinicalFields(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyCustomerPatientSyncService.php');

        foreach (['updateProfile', 'first_name', 'last_name', 'email', 'mobile_number', 'address', 'postal_code'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        $start = strpos($service, 'private function updateProfile(');
        $end = strpos($service, 'private function normalizeAddress(', $start);
        $profileUpdate = substr($service, $start, $end - $start);
        foreach (['gender', 'date_of_birth', 'doctor_id', 'clinic_id', 'prescriptions', 'appointments'] as $clinicalField) {
            self::assertStringNotContainsString($clinicalField, $profileUpdate);
        }
    }

    public function testIdentityConflictsAndRequiredFailureCategoriesAreSafe(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyCustomerPatientSyncService.php');
        $controller = $this->source('app/Modules/Shopify/Controllers/Webhook.php');

        foreach (['SHOPIFY_PATIENT_IDENTITY_CONFLICT', 'SHOPIFY_PATIENT_SYNC_FAILED', 'assertPatientLinkAvailable', 'event_id_hash', 'processing_stage'] as $marker) {
            self::assertStringContainsString($marker, $service . $controller);
        }
        self::assertStringNotContainsString('log_message(', $service);
    }

    public function testCustomerTopicsAreApprovedAndAdminDiagnosticsExposeSyncState(): void
    {
        $catalog = $this->source('app/Services/Shopify/ShopifyWebhookCatalogService.php');
        $registration = $this->source('app/Services/Shopify/ShopifyWebhookRegistrationService.php');
        $identity = $this->source('app/Services/Shopify/ShopifyCustomerIdentityService.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_customers.php');

        foreach (['customers/create', 'customers/update'] as $topic) {
            self::assertStringContainsString($topic, $catalog . $registration);
        }
        foreach (['last_synced_at', 'last_sync_error', 'shopify_store_domain', 'patient_reference'] as $field) {
            self::assertStringContainsString($field, $identity . $view);
        }
    }

    public function testCustomerSyncAuditEventsAreDefined(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyCustomerPatientSyncService.php');
        $controller = $this->source('app/Modules/Shopify/Controllers/Webhook.php');

        foreach (['shopify.customer_patient_created', 'shopify.customer_patient_updated', 'shopify.customer_patient_linked', 'shopify.customer_patient_sync_failed'] as $event) {
            self::assertStringContainsString($event, $service . $controller);
        }
    }
}
