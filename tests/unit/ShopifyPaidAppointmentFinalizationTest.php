<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPaidAppointmentFinalizationTest extends TestCase
{
    private string $intent;
    private string $webhook;
    private string $events;

    protected function setUp(): void
    {
        $this->intent = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentIntentService.php');
        $this->webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $this->events = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
    }

    public function testPaidRouteAndWebhookVerificationAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        self::assertStringContainsString("integrations/shopify/webhooks/orders/paid", $routes);
        foreach (['X-Shopify-Hmac-Sha256', 'ShopifyWebhookVerifier', 'X-Shopify-Shop-Domain', 'X-Shopify-Webhook-Id'] as $contract) self::assertStringContainsString($contract, $this->webhook);
    }

    public function testIntentReferenceSurvivesOrderPayloadMinimization(): void
    {
        foreach (['note_attributes', 'attributes', 'custom_attributes', 'line_items', 'rootins_appointment_intent', 'RINT-'] as $contract) self::assertStringContainsString($contract, $this->events . $this->intent);
        self::assertStringContainsString('safeAppointmentProperties', $this->events);
    }

    public function testPaidFinalizationUsesExactStoreCustomerReferenceAndOrderScope(): void
    {
        foreach (['shopify_store_domain = ?', 'shopify_customer_id = ?', 'public_reference = ?', 'shopify_order_id IS NULL OR shopify_order_id = ?', 'FOR UPDATE', 'SHOPIFY_PAYMENT_MISMATCH', 'SHOPIFY_CUSTOMER_MISMATCH'] as $contract) self::assertStringContainsString($contract, $this->intent);
    }

    public function testFinalizationIsTransactionalAndCreatesAllLinkedRecords(): void
    {
        $billing = file_get_contents(ROOTPATH . 'app/Services/AppointmentBillingService.php');
        foreach (['transBegin', 'transCommit', 'transRollback', "'booking_source' => 'shopify'", 'appointment_services', 'AppointmentBillingService', "'status' => 'booked'", 'shopify.appointment_finalized_paid'] as $contract) self::assertStringContainsString($contract, $this->intent);
        self::assertStringContainsString("'payment_status' => \$paymentStatus", $billing);
        self::assertStringContainsString('whereIn(\'status\', self::ACTIVE_INTENT_STATUSES)', $this->intent);
    }

    public function testFailuresRemainRetryableAndCompletedIntentsAreIdempotent(): void
    {
        foreach (['markRetryableFailure', "'status' => 'paid'", 'failure_reason', "['status'] === 'booked'", 'finalization_duplicate_completed', 'reprocessPaidOrder', 'cancelled_at'] as $contract) self::assertStringContainsString($contract, $this->intent);
    }

    public function testPaidWebhookSeparatesMedicineOrdersFromAppointmentFinalization(): void
    {
        foreach (['appointmentIntentReferenceForOrder', 'appointment_finalization_skipped', 'NO_APPOINTMENT_INTENT', 'medicine_order_payment_received', 'appointment_payment_received', 'intent_present'] as $contract) {
            self::assertStringContainsString($contract, $this->webhook . $this->intent);
        }
        self::assertStringContainsString('new ShopifyOrderImportService())->processEvent', $this->webhook);
        self::assertStringNotContainsString("'intent_public_reference' =>", $this->webhook . $this->intent);
    }

    public function testAdminCanReprocessPaidAppointment(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderOperationsService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php');
        self::assertStringContainsString('reprocess_paid_appointment', $service . $view);
        self::assertStringContainsString('Reprocess paid appointment', $view);
    }

    public function testDiagnosticsAreSafeAndDoNotLogPayloadOrSecrets(): void
    {
        foreach (['correlation_id', 'shopify_order_id', 'intent_present', 'processing_stage', 'failure_category'] as $field) self::assertStringContainsString($field, $this->webhook . $this->intent);
        foreach (['rawBody', 'webhook_secret', 'X-Shopify-Hmac-Sha256'] as $secret) self::assertStringNotContainsString("json_encode([$secret", $this->intent);
    }
}
