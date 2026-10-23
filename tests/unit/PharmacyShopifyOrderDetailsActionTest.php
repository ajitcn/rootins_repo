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
        self::assertStringContainsString("fetch(form.action, { method: 'POST'", $script);
        self::assertStringContainsString('refreshCsrf(payload)', $script);
        self::assertStringContainsString("$('.modal-backdrop').remove()", $script);
        self::assertStringContainsString('id="shopifyProcessOrderModal"', $view);
        self::assertSame(4, substr_count($view, 'shopify-order-action-form'));
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
