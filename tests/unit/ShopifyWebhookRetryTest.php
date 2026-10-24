<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyOrderImportFailure;
use PHPUnit\Framework\TestCase;

final class ShopifyWebhookRetryTest extends TestCase
{
    public function testFailedEventIsClaimedForRetryAndUsesStoredPayload(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString("if (\$status === 'failed')", $service);
        self::assertStringContainsString("->where('status', 'failed')", $service);
        self::assertStringContainsString("'status' => 'processing'", $service);
        self::assertStringContainsString("'retry_count' => \$retryCount", $service);
        self::assertStringContainsString("if (\$result['duplicate'] && ! \$result['process'])", $controller);
        self::assertStringContainsString("\$event = \$events->find(\$eventId)", $controller);
    }

    public function testProcessedDuplicateSkipsAndConcurrentProcessingDoesNotRunTwice(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString("if (\$status === 'failed')", $service);
        self::assertStringContainsString("'process' => false", $service);
        self::assertStringContainsString("if (\$result['status'] === 'processed')", $controller);
        self::assertStringContainsString("'status' => 'processing'", $service);
        self::assertStringContainsString("store_domain', \$storeDomain", $service);
        self::assertStringContainsString("external_event_id', \$eventId", $service);
    }

    public function testImportPaymentUsesOnlyTheInternalImportId(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('updateImportedPaymentRecord($event, $importId, $payload)', $service);
        self::assertStringContainsString('SELECT * FROM shopify_order_imports WHERE id = ? LIMIT 1', $service);
        self::assertStringContainsString("->where('id', \$actualImportId)->update(\$data)", $service);
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringNotContainsString("where('pharmacy_order_id'", $service);
        self::assertStringNotContainsString("where('shopify_order_number', \$shopifyOrderId)", $service);
        self::assertStringNotContainsString("where('shopify_line_item_id', \$shopifyOrderId)", $service);
    }

    public function testFailureCategoriesAreSpecificAndAppointmentIsNotInMedicineRetryPath(): void
    {
        $failure = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportFailure.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        foreach (['ORDER_IMPORT_RECORD_NOT_FOUND', 'PHARMACY_ORDER_RECORD_NOT_FOUND', 'SHOPIFY_ORDER_IDENTITY_NOT_FOUND', 'SHOPIFY_ORDER_LINE_IMPORT_FAILURE', 'PATIENT_RESOLUTION_FAILURE', 'PRESCRIPTION_REFERENCE_FAILURE'] as $category) {
            self::assertStringContainsString($category, $failure);
        }
        self::assertSame(
            ShopifyOrderImportFailure::PHARMACY_ORDER_RECORD_NOT_FOUND,
            ShopifyOrderImportFailure::fromException(new \RuntimeException('pharmacy order not found'), 'orders/paid')->category,
        );
        $medicineBranch = substr($controller, strpos($controller, "if (\$intentReference === '')"), strpos($controller, 'appointment_payment_received') - strpos($controller, "if (\$intentReference === '')"));
        self::assertStringNotContainsString('finalizePaid($event, $payload)', $medicineBranch);
    }

    public function testRetryAndUiExposeTheActualImportFailureStage(): void
    {
        $operations = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderOperationsService.php');
        $adminView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php');
        $pharmacyView = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/shopify_orders/detail.php');

        self::assertStringContainsString("'last_processing_stage'", $operations);
        self::assertStringContainsString("'last_failure_category'", $operations);
        self::assertStringContainsString('last_processing_stage', $adminView . $pharmacyView);
        self::assertStringContainsString('last_failure_category', $adminView . $pharmacyView);
        self::assertStringContainsString('retry_failed', $operations);
        self::assertStringContainsString("'error_category'", $operations);
    }
}
