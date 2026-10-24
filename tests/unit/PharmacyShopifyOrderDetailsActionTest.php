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
        foreach (['patient_id', 'shopify_store_domain', 'shopify_customer_id', 'pharmacy.shopify_prescription_viewed', 'temporaryUrl'] as $marker) {
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
}
