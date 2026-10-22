<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionOrderWorkflowTest extends TestCase
{
    private function source(string $path): string
    {
        return (string) file_get_contents(ROOTPATH . $path);
    }

    public function testProxyUploadContractIsCustomerScopedPrivateAndIdempotent(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');
        $upload = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $controller = $this->source('app/Modules/Shopify/Controllers/Proxy.php');
        self::assertStringContainsString("'logged_in_customer_id'", $proxy);
        self::assertStringContainsString('uploadFromProxy', $proxy);
        self::assertStringContainsString('finfo_open(FILEINFO_MIME_TYPE)', $upload);
        self::assertStringContainsString('MAX_BYTES = 10485760', $upload);
        self::assertStringContainsString('prescription_reference', $upload);
        self::assertStringContainsString('RXP-', $controller);
        self::assertStringNotContainsString('getTemporaryUrl', $controller);
    }

    public function testCartOrderReferenceAndWebhookQuarantineContractsAreDocumented(): void
    {
        $docs = $this->source('docs/SHOPIFY_PRESCRIPTION_WORKFLOW.md');
        $events = $this->source('app/Services/Shopify/ShopifyWebhookEventService.php');
        $imports = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');
        foreach (['rootins.prescription_reference', 'cart-to-order', 'private cart attribute', 'bundle', 'awaiting_review', 'quarantine'] as $contract) self::assertStringContainsString($contract, $docs . $imports);
        foreach (['shopifyPrescriptionReference', 'metafields', 'note_attributes', 'attributes'] as $contract) self::assertStringContainsString($contract, $events);
        self::assertStringContainsString('invalid_prescription_reference', $imports);
        self::assertStringContainsString('missing_prescription', $imports);
    }

    public function testReviewActionsRequireAuthorizationReasonsAndAudit(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        foreach (['pharmacy.prescription.review.view', 'pharmacy.prescription.review.approve', 'pharmacy.prescription.review.reject', 'pharmacy.prescription.review.clarify', 'A reason is required', 'shopify.prescription_upload_reviewed', 'temporaryUrl'] as $contract) self::assertStringContainsString($contract, $service);
    }
}
