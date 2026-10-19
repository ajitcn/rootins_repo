<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyProxySetupTest extends TestCase
{
    public function testProxyRoutesAndSecurityContractsAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        self::assertStringContainsString("rootins-proxy/appointments/availability", $routes);
        self::assertStringContainsString("rootins-proxy/prescriptions/upload", $routes);
        self::assertStringNotContainsString("shopify-proxy/appointments/availability", $routes);
        self::assertStringContainsString("rootins-proxy/*", file_get_contents(ROOTPATH . 'app/Config/Filters.php'));
        self::assertStringContainsString("/public/rootins-proxy", file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php'));
        self::assertStringContainsString("proxy-setup/rotate-secret", $routes);
        self::assertStringContainsString("proxy-setup/enable", $routes);
        self::assertStringContainsString("proxy-setup/disable", $routes);
        foreach (['Idempotency-Key', 'ShopifyProxyService', 'CoreApiRateLimitService', 'ShopifyOperationIdempotencyService', 'logged_in_customer_id'] as $contract) self::assertStringContainsString($contract, $proxy . file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php'));
        self::assertStringContainsString('shopify_core_api_proxy_configurations', file_get_contents(ROOTPATH . 'app/Modules/Shopify/Models/ShopifyCoreApiProxyConfigurationModel.php'));
        self::assertStringNotContainsString('new ShopifyConfiguration()', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php'));
    }

    public function testProxySetupDoesNotRenderSecrets(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_proxy_setup.php');
        self::assertStringNotContainsString("['client_secret']", $view);
        self::assertStringNotContainsString("['client_id']", $view);
        self::assertStringContainsString('Shopify Webhooks / Order Integration', $view);
        self::assertStringContainsString('Shopify Core API App Proxy', $view);
    }
}
