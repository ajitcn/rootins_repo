<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyRouteSecurityTest extends TestCase
{
    public function testEveryAdminShopifyRouteHasAResourcePermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $adminGroup = substr($routes, 0, (int) strpos($routes, "});\n\n\$routes->post('integrations/shopify/webhooks"));

        self::assertNotFalse($adminGroup);
        self::assertSame(32, substr_count($adminGroup, "['filter' => 'permission:"));
        self::assertStringContainsString("filter' => 'auth'", $adminGroup);
        self::assertStringContainsString('permission:shopify.integration.view', $adminGroup);
        self::assertStringContainsString('permission:shopify.order.import', $adminGroup);
        self::assertStringContainsString('permission:shopify.order.reconcile', $adminGroup);
    }

    public function testShopifyReviewRouteHasDefenseInDepthAndWebhookStaysHmacBoundary(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $verifier = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookVerifier.php');

        self::assertStringContainsString("'filter' => ['auth', 'permission:pharmacy.prescription.review.view']", $routes);
        self::assertStringContainsString("integrations/shopify/webhooks/(:any)", $routes);
        self::assertStringNotContainsString('permission:', substr($routes, strpos($routes, "integrations/shopify/webhooks/(:any)"), 180));
        self::assertStringContainsString('ShopifyWebhookVerifier', $webhook);
        self::assertStringContainsString('hash_equals', $verifier);
    }
}
