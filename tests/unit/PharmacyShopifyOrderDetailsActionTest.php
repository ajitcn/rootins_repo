<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PharmacyShopifyOrderDetailsActionTest extends TestCase
{
    public function testDetailsActionsUseOneDelegatedAjaxHelperWithGuaranteedCleanup(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/shopify_orders.js');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/shopify_orders/detail.php');

        self::assertStringContainsString("off('submit.shopifyOrders'", $script);
        self::assertStringContainsString('event.preventDefault()', $script);
        self::assertStringContainsString('finally {', $script);
        self::assertStringContainsString('clearStaleLoadingState()', $script);
        self::assertStringContainsString('fetchWithTimeout(actionUrl', $script);
        self::assertStringContainsString('AbortController', $script);
        self::assertStringContainsString('The server returned an unexpected response.', $script);
        self::assertStringContainsString('error.code', $script);
        self::assertStringContainsString('.shopify-order-operation-action-form', $script);
        self::assertStringContainsString('event.stopImmediatePropagation()', $script);
        self::assertStringContainsString('Invalid Shopify order action URL.', $script);
        self::assertStringContainsString("actionField.value = 'retry_import'", $script);
        self::assertStringContainsString("$('body').removeClass('loading preloader-active rootins-loading')", $script);
        self::assertStringContainsString('credentials: \'same-origin\'', $script);
        self::assertStringContainsString('refreshCsrf(payload)', $script);
        self::assertStringContainsString("$('.modal-backdrop').remove()", $script);
        self::assertStringContainsString('id="shopifyProcessOrderModal"', $view);
        self::assertSame(4, substr_count($view, 'shopify-order-action-form'));
    }

    public function testActionServiceVerifiesPersistedStateBeforeReportingSuccess(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderOperationsService.php');

        self::assertStringContainsString('$updated = $model->update($id', $service);
        self::assertStringContainsString('$afterRow = $model->find($id);', $service);
        self::assertStringContainsString('The Shopify order state could not be saved.', $service);
        self::assertStringContainsString('The Shopify order state could not be verified after saving.', $service);
        self::assertStringContainsString("'affected_rows' => \$updated ? 1 : 0", $service);
    }

    public function testActionResponsesCoverStructuredFailuresAndRefreshCsrf(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $csrf = file_get_contents(ROOTPATH . 'app/Filters/ShopifyMappingCsrf.php');
        $permission = file_get_contents(ROOTPATH . 'app/Filters/Permission.php');

        foreach (['message', 'data', 'csrf', 'error', 'correlation_id', 'SHOPIFY_ORDER_ACTION_DATABASE_FAILURE'] as $marker) {
            self::assertStringContainsString($marker, $controller);
        }
        self::assertStringContainsString('SHOPIFY_MAPPING_CSRF_INVALID', $csrf);
        self::assertStringContainsString('SHOPIFY_MAPPING_PERMISSION_DENIED', $permission);
    }

    public function testShopifyOrderListUsesSafePrescriptionAndDeliveryDisplayRules(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/shopify_orders/index.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderOperationsService.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/shopify_orders.js');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');

        foreach (['Payment / Import Status', 'Not required', 'View prescription', 'Missing prescription', 'Not fulfilled'] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
        foreach (['prescription_document_id', 'prescription_required', 'Not fulfilled'] as $marker) {
            self::assertStringContainsString($marker, $service . $script);
        }
        foreach (['resolvePharmacyPreview', 'pharmacy.shopify_prescription_viewed', 'temporaryUrl'] as $marker) {
            self::assertStringContainsString($marker, $controller);
        }
        self::assertStringContainsString('shopify-orders/(:num)/prescription', $routes);
        self::assertStringNotContainsString('temporaryUrl($document)', $view . $script);
    }

    public function testProcessModalUsesExplicitActionContractAndScrollableBootstrapLayout(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/shopify_orders/detail.php');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/shopify-orders.css');

        foreach (['retry_validation', 'mark_ready', 'quarantine', 'release_quarantine', 'retry_payment'] as $action) {
            self::assertStringContainsString('value="' . $action . '"', $view);
        }
        self::assertStringContainsString('modal-dialog modal-lg modal-dialog-scrollable', $view);
        self::assertStringContainsString('.shopify-process-modal .modal-body { overflow-y: auto;', $css);
        self::assertStringContainsString('.shopify-process-modal { z-index: 1070;', $css);
    }

    public function testPrescriptionPreviewResolvesScopedShopifyLinkageAndUsesTemporaryPrivateStorage(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/ShopifyOrders.php');

        foreach (['resolvePharmacyPreview', 'shopify_order_id', 'external_upload_reference', 'PRESCRIPTION_LINKAGE_NOT_FOUND', 'PRESCRIPTION_DOCUMENT_NOT_FOUND', 'PRESCRIPTION_ACCESS_DENIED', 'ORDER_NOT_FOUND', 'DOCUMENT_STORAGE_NOT_FOUND', 'previewDiagnostic', 'temporaryUrl'] as $marker) {
            self::assertStringContainsString($marker, $service . $controller);
        }
        foreach (['image/jpeg', 'image/png', 'image/webp', 'application/pdf'] as $mime) self::assertStringContainsString($mime, $service);
        self::assertStringContainsString('decodeVerifiedAccessToken', $service);
        self::assertStringContainsString("setHeader('Location', \$temporaryUrl)", $controller);
        self::assertStringNotContainsString('readAuthorizedDocument($document)', $controller);
    }

    public function testPrescriptionOrderLinkageIsIdempotentAndRecreatedOnRetry(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        $import = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-26-150000_CreateShopifyPrescriptionOrderLinks.php');

        foreach (['linkOrderPrescription', 'shopify_prescription_order_links', 'shopify_order_id', 'import_id', 'document_id', 'prescription_reference', 'prescription_bundle_reference'] as $marker) {
            self::assertStringContainsString($marker, $service . $import . $migration);
        }
        self::assertStringContainsString("where('store_domain', \$data['store_domain'])", $service);
        self::assertStringContainsString('addUniqueKey', $migration);
    }

    public function testPrescriptionPreviewAuthorizationCoversPharmacistAdminAndScopeFailures(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        foreach (['Pharmacy Assistant', 'Admin', 'Super Admin', 'pharmacy.shopify_orders.view', 'prescription.view', "where('status', 1)", 'previewAuthorizationDiagnostic'] as $marker) {
            self::assertStringContainsString($marker, $controller . $service);
        }
        foreach (['USER_INACTIVE', 'PRESCRIPTION_VIEW_PERMISSION_REQUIRED', 'token_user_id', 'token_clinic_id', 'order_clinic_id', 'document_owner_patient_id', 'PRESCRIPTION_ACCESS_DENIED'] as $marker) {
            self::assertStringContainsString($marker, $service . $controller);
        }
        self::assertStringContainsString("(int) (\$import['clinic_id'] ?? 0) !== \$clinicId", $service);
        self::assertStringContainsString("(int) (\$payload['user_id'] ?? 0) !== \$userId", $service);
    }

    public function testPreviewSupportsShopifyUploadAndDoctorSnapshotTablesWithSpecificMissingDocumentError(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');

        foreach (['shopify_prescription_uploads', 'prescription_documents', 'patient_documents', 'storage_key', 'object_key', 'upload_reference', 'PRESCRIPTION_DOCUMENT_NOT_FOUND', 'storage_key_present', 'objectExists', 'normalizeKey'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('source_type', $service);
        self::assertStringContainsString("'rootins'", $service);
        self::assertStringContainsString('linkOrderPrescription', $service);
        self::assertStringContainsString('tableExists', $service);
    }
}
