<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionCartStatusOperationTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(ROOTPATH . $path);
        self::assertIsString($source);

        return $source;
    }

    public function testCartStatusIsAnIndependentlyConfigurableCoreApiOperation(): void
    {
        $catalog = $this->source('app/Services/Shopify/ShopifyProxyConfigurationService.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_proxy_setup.php');
        self::assertStringContainsString("'prescriptions.cart_status'", $catalog);
        self::assertStringContainsString("'prescriptions.cart_status' => 'Record prescription cart status'", $view);
        self::assertStringContainsString('allowed_core_api_operations[]', $view);
    }

    public function testSignedRoutesAndControllerUseCoreApiCustomerContext(): void
    {
        $routes = $this->source('app/Modules/Shopify/Config/Routes.php');
        $controller = $this->source('app/Modules/Shopify/Controllers/ActivePrescriptions.php');
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');
        foreach (['apps/rootins/prescriptions/cart-status', 'rootins-proxy/prescriptions/cart-status', 'public/rootins-proxy/prescriptions/cart-status'] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        foreach (['prescriptions.cart_status', 'logged_in_customer_id', 'Idempotency-Key', 'Content-Type', 'getJSON(true)'] as $marker) {
            self::assertStringContainsString($marker, $controller . $proxy);
        }
    }

    public function testSuccessfulCartStatusStoresTheRequiredIdempotentResult(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyPrescriptionCartPushService.php');
        foreach (['prescription_reference', 'shopify_customer_id', 'cart_pushed_at', 'idempotency_key', 'items_json', 'request_hash', "'cart_status' => 'cart_pushed'"] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('existingByKey', $service);
        self::assertStringContainsString('SHOPIFY_IDEMPOTENCY_KEY_REUSED', $service);
    }

    public function testCartStatusRejectsInvalidCustomerAndPrescriptionStates(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');
        $service = $this->source('app/Services/Shopify/ShopifyPrescriptionCartPushService.php');
        self::assertStringContainsString('SHOPIFY_CUSTOMER_UNLINKED', $proxy);
        foreach (['PRESCRIPTION_NOT_FOUND', 'PRESCRIPTION_NOT_AVAILABLE', 'PRESCRIPTION_EXPIRED', 'PRESCRIPTION_VARIANT_INVALID', 'PRESCRIPTION_QUANTITY_INVALID', 'IDEMPOTENCY_KEY_REQUIRED'] as $code) {
            self::assertStringContainsString($code, $service . $this->source('app/Modules/Shopify/Controllers/ActivePrescriptions.php'));
        }
        self::assertStringContainsString("strtolower(trim((string) (\$prescription['status'] ?? ''))) !== 'completed'", $service);
        self::assertStringContainsString('validUntil < ShopifyPrescriptionEligibility::today()', $service);
        self::assertStringContainsString('quantity_remaining', $service);
    }
}
