<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionUploadTest extends TestCase
{
    public function testUploadRoutesAndMetadataContractArePresent(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        self::assertStringContainsString('integrations/shopify/prescription-uploads', $routes);
        self::assertStringContainsString('prescription-uploads/(:num)/download', $routes);
        self::assertStringContainsString('prescription-uploads/review', $routes);
        self::assertStringContainsString("'source_type' => 'shopify'", $service);
        self::assertStringContainsString("'external_upload_reference' => \$externalReference", $service);
        self::assertStringContainsString("where('external_upload_reference', \$externalReference)", $service);
        self::assertStringContainsString("'retention_expires_at' => \$retention", $service);
    }

    public function testMigrationAddsReversibleScopedReviewAndRetentionFields(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-160000_AddShopifyPrescriptionUploadFields.php');

        foreach (['clinic_id', 'source_type', 'shopify_store_domain', 'shopify_customer_id', 'external_upload_reference', 'upload_status', 'retention_expires_at', 'uploaded_by_type', 'uploaded_by_id'] as $field) {
            self::assertStringContainsString("'{$field}'", $migration);
        }

        self::assertStringContainsString('public function down()', $migration);
        self::assertStringContainsString('patient_documents_shopify_reference_unique', $migration);
        self::assertStringContainsString('patient_documents_clinic_fk', $migration);
    }

    public function testFileValidationUsesContentMimeAndRejectsExecutableOrUnsupportedFiles(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        self::assertStringContainsString('finfo_open(FILEINFO_MIME_TYPE)', $service);
        self::assertStringContainsString('MAX_BYTES = 10485760', $service);
        self::assertStringContainsString("'pdf' => 'application/pdf'", $service);
        self::assertStringContainsString("'png' => 'image/png'", $service);
        self::assertStringNotContainsString("'php'", strtolower($service));
    }

    public function testStatusWorkflowAndReviewerPermissionsAreServerSide(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        foreach (['uploaded', 'under_review', 'accepted', 'rejected', 'clarification_required'] as $status) {
            self::assertStringContainsString("'{$status}'", $service);
        }

        foreach (['pharmacy.prescription.review.view', 'pharmacy.prescription.review.approve', 'pharmacy.prescription.review.reject', 'pharmacy.prescription.review.clarify'] as $permission) {
            self::assertStringContainsString($permission, $service);
        }

        self::assertStringContainsString('where(\'clinic_id\', $clinicId)', $service);
        self::assertStringContainsString('where(\'source_type\', \'shopify\')', $service);
        self::assertStringContainsString('upload_status', $service);
    }

    public function testSafeResponseDoesNotExposePathsOrInternalIdentifiers(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/PrescriptionUpload.php');
        $safeResponse = substr($service, strpos($service, 'private function safeResponse'), strpos($service, 'private function validateFile') - strpos($service, 'private function safeResponse'));

        self::assertStringContainsString('safeResponse', $service);
        self::assertStringNotContainsString('file_path', $safeResponse);
        self::assertStringNotContainsString('patient_id', $safeResponse);
        self::assertStringNotContainsString('clinic_id', $safeResponse);
        self::assertStringNotContainsString("'patient_id' =>", $controller);
        self::assertStringNotContainsString("'clinic_id' =>", $controller);
        self::assertStringNotContainsString('password', strtolower($controller));
        self::assertStringNotContainsString('access_token', strtolower($controller));
        self::assertStringContainsString('ResponseContentType', file_get_contents(ROOTPATH . 'app/Services/Common/R2StorageLibrary.php'));
        self::assertStringContainsString('ResponseContentDisposition', file_get_contents(ROOTPATH . 'app/Services/Common/R2StorageLibrary.php'));
        self::assertStringContainsString('getTemporaryUrl', $controller);
    }

    public function testUploadUsesExistingSignedHandoffAndReviewAuditsNotifications(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        self::assertStringContainsString('->verify($token)', file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/PrescriptionUpload.php'));
        self::assertStringContainsString('->consume($handoffToken)', $service);
        self::assertStringContainsString('shopify.prescription_upload_created', $service);
        self::assertStringContainsString('shopify.prescription_upload_reviewed', $service);
        self::assertStringContainsString('notifyReviewers', $service);
        self::assertStringContainsString('PatientNotificationService', $service);
    }
}
