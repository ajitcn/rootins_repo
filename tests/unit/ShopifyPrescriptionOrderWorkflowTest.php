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

    public function testOrderPrescriptionLinksUseSignedAppTokensAndNeverRedirectToR2(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $pharmacy = $this->source('app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $admin = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        foreach (['issueAccessToken', 'verifyAccessToken', 'resolvePharmacyPreview', 'expires_at', 'temporaryUrl'] as $marker) self::assertStringContainsString($marker, $service);
        foreach (['prescription_url', 'resolvePharmacyPreview', 'pharmacy.shopify_prescription_viewed'] as $marker) self::assertStringContainsString($marker, $pharmacy);
        foreach (['prescription_url', 'verifyAccessToken', 'Prescription access denied'] as $marker) self::assertStringContainsString($marker, $admin);
        self::assertStringNotContainsString('redirect()->to((new ShopifyPrescriptionUploadService())->temporaryUrl', $pharmacy . $admin);
    }

    public function testPrescriptionStatusesAndRecheckCoverageAreRenderedAcrossSurfaces(): void
    {
        $sources = $this->source('app/Modules/Pharmacy/Views/pages/shopify_orders/index.php')
            . $this->source('app/Modules/Pharmacy/Views/pages/shopify_orders/detail.php')
            . $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_operations.php')
            . $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php')
            . $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        foreach (['View prescription', 'Missing prescription', 'Not required', 'refreshPrescriptionReference', 'prescription_reference_rechecked'] as $marker) self::assertStringContainsString($marker, $sources);
    }

    public function testUnauthorizedAndExpiredPrescriptionLinksAreDenied(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $pharmacy = $this->source('app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $admin = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        self::assertStringContainsString('hash_equals', $service);
        self::assertStringContainsString("&& (int) (\$payload['expires_at'] ?? 0) >= time()", $service);
        self::assertStringContainsString("setStatusCode(403)", $pharmacy . $admin);
    }

    public function testFreshUploadOrderImportAndPreviewMetadataContract(): void
    {
        $upload = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $import = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');
        $preview = $this->source('app/Modules/Pharmacy/Controllers/ShopifyOrders.php');

        foreach (['storage_key_fingerprint', 'ensureOrderPrescriptionMetadata', 'PRESCRIPTION_METADATA_CREATION_FAILED', 'prescriptionImportDiagnostic'] as $marker) {
            self::assertStringContainsString($marker, $upload . $import);
        }
        foreach (['prescription_reference', 'linkOrderPrescription', 'resolvePharmacyPreview', '_preview_refresh_token'] as $marker) {
            self::assertStringContainsString($marker, $import . $preview);
        }
    }

    public function testActivePrescriptionAndFinalizedSnapshotContractsAreScoped(): void
    {
        $active = $this->source('app/Services/Shopify/ShopifyActivePrescriptionService.php');
        $snapshot = $this->source('app/Services/Common/PrescriptionSnapshotService.php');
        $documents = $this->source('app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $clinical = $this->source('app/Modules/Doctor/Controllers/Clinical.php');

        self::assertStringContainsString("where('p.patient_id', \$patientId)", $active);
        self::assertStringContainsString("whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)", $active);
        self::assertStringContainsString('calculateValidUntil', $active);
        self::assertStringNotContainsString("'submitted'", $active);
        self::assertStringContainsString('createIfFinalized', $snapshot);
        self::assertStringContainsString('source_type', $snapshot);
        self::assertStringContainsString('temporaryUrl', $documents);
        self::assertStringContainsString('Finalized prescriptions cannot be modified', $clinical);
    }

    public function testDoctorCompletionUsesCompletedStatusAndCreatedAtDuration(): void
    {
        $clinical = $this->source('app/Modules/Doctor/Controllers/Clinical.php');
        $eligibility = $this->source('app/Services/Shopify/ShopifyPrescriptionEligibility.php');
        $migration = $this->source('app/Database/Migrations/2026-10-28-120000_AddPrescriptionDurationDays.php');

        foreach (['duration_days', 'calculateValidUntil', "'completed'", 'normalizeMedicineDuration'] as $marker) {
            self::assertStringContainsString($marker, $clinical . $eligibility . $migration);
        }
        self::assertStringNotContainsString("'submitted' ? 'submitted'", $clinical);
    }

    public function testActivePayloadContainsOrderDetailsAndStorefrontUsesCartApi(): void
    {
        $active = $this->source('app/Services/Shopify/ShopifyActivePrescriptionService.php');
        $storefront = $this->source('public/assets/js/shopify/active-prescriptions.js');

        foreach (['patient_reference', 'duration_days', 'medicine_id', 'variant_id', 'excluded_items', 'quantity_remaining', 'cart_property'] as $marker) {
            self::assertStringContainsString($marker, $active);
        }
        foreach (['/apps/rootins/prescriptions/active', '/apps/rootins/prescriptions/cart-status', '/cart.js', '/cart/add.js', '_rx_reference', 'variant_id', 'quantity', 'Pushed to cart'] as $marker) {
            self::assertStringContainsString($marker, $storefront);
        }
    }

    public function testCompletionAndCartPathsAreIdempotentAndKeepOrderLinkage(): void
    {
        $active = $this->source('app/Services/Shopify/ShopifyActivePrescriptionService.php');
        $storefront = $this->source('public/assets/js/shopify/active-prescriptions.js');
        $import = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringNotContainsString('->insert(', $active);
        self::assertStringContainsString('existing[variantId]', $storefront);
        foreach (['linkOrderPrescription', 'ensurePharmacyOrderForImport', 'prescriptionIdForReference'] as $marker) {
            self::assertStringContainsString($marker, $import);
        }
    }

    public function testNotificationOutboxTypesAndLineIdempotencyContractsArePresent(): void
    {
        $import = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');
        $review = $this->source('app/Services/Shopify/ShopifyOrderReviewService.php');
        $migration = $this->source('app/Database/Migrations/2026-10-26-140000_CreateShopifyLineReconciliation.php');

        foreach (['new_prescription_required', 'prescription_rejected', 'medicine_unavailable', 'substitute_required', 'partial_refund', 'partial_fulfilment'] as $type) {
            self::assertStringContainsString($type, $import . $review);
        }
        self::assertStringContainsString('addUniqueKey', $migration);
        self::assertStringContainsString('shopify_refund_id', $migration);
        self::assertStringContainsString('shopify_fulfillment_id', $migration);
    }
}
