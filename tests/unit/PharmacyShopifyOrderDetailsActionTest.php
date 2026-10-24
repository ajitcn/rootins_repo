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
        self::assertStringContainsString('fetchWithTimeout(form.action', $script);
        self::assertStringContainsString('AbortController', $script);
        self::assertStringContainsString('The server returned an unexpected response.', $script);
        self::assertStringContainsString('error.code', $script);
        self::assertStringContainsString('refreshDetailStatus()', $script);
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

    public function testProcessModalUsesExplicitActionContractAndScrollableBootstrapLayout(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/shopify_orders/detail.php');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/shopify-orders.css');

        foreach (['retry_validation', 'mark_ready', 'quarantine', 'release_quarantine'] as $action) {
            self::assertStringContainsString('value="' . $action . '"', $view);
        }
        self::assertStringContainsString('modal-dialog modal-lg modal-dialog-scrollable', $view);
        self::assertStringContainsString('.shopify-process-modal .modal-body { overflow-y: auto;', $css);
        self::assertStringContainsString('.shopify-process-modal { z-index: 1070;', $css);
    }
}
