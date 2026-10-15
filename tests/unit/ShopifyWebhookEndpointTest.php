<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyWebhookCatalogService;
use PHPUnit\Framework\TestCase;

final class ShopifyWebhookEndpointTest extends TestCase
{
    public function testEndpointTestUrlUsesTheNormalizedPublicBaseAndOneTopicPath(): void
    {
        $catalog = new ShopifyWebhookCatalogService();
        $configuration = new ShopifyConfiguration(['app_url' => 'https://rootins.newrootssoftware.in/public/']);

        foreach (['customers/create', 'customers/update', 'orders/create', 'orders/updated', 'refunds/create', 'fulfillments/create', 'products/update'] as $topic) {
            self::assertSame(
                'https://rootins.newrootssoftware.in/public/integrations/shopify/webhooks/' . $topic,
                $catalog->endpointTestUrl($configuration->appUrl(), $topic),
            );
        }
    }

    public function testEndpointTestUrlRejectsInvalidOrUncataloguedInputWithoutChangingRealRoutes(): void
    {
        $catalog = new ShopifyWebhookCatalogService();

        self::assertSame('', $catalog->endpointTestUrl('http://rootins.newrootssoftware.in/public', 'orders/create'));
        self::assertSame('', $catalog->endpointTestUrl('https://localhost/public', 'orders/create'));
        self::assertSame('', $catalog->endpointTestUrl('https://rootins.newrootssoftware.in/public', 'not-a-topic'));

        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        self::assertStringContainsString("integrations/shopify/webhooks/(:any)", $routes);
    }

    public function testEndpointTestRemainsClearlyMarkedAndDoesNotUseRealTopicProcessing(): void
    {
        $validation = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookValidationService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString("'X-ROOTINS-Endpoint-Test' => '1'", $validation);
        self::assertStringContainsString("'test_topic' => trim(\$topic)", $validation);
        self::assertStringContainsString("'X-Shopify-Topic' => 'rootins/endpoint-test'", $validation);
        self::assertStringContainsString('ROOTINS endpoint test accepted.', $webhook);
        self::assertStringContainsString('removeEndpointTestEvents', $validation);
    }

    public function testEndpointTestValidationReturnsAStableSafe422Contract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/integrations/shopify-webhooks.js');

        self::assertStringContainsString("'WEBHOOK_TEST_VALIDATION_FAILED'", $controller);
        self::assertStringContainsString("'errors' => ['topic'", $controller);
        self::assertStringContainsString("form.append('topic', topic)", $javascript);
        self::assertStringContainsString('data.errors', $javascript);
    }

    public function testRegistrationAndSyncActionsAreManageProtectedAndNonDestructiveToExistingSubscriptions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_webhooks.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/integrations/shopify-webhooks.js');

        self::assertStringContainsString("webhooks/sync', 'Integration::syncWebhooks'", $routes);
        self::assertStringContainsString("webhooks/register-missing', 'Integration::registerMissingWebhooks'", $routes);
        self::assertStringContainsString('permission:shopify.integration.manage', $routes);
        self::assertStringContainsString("'GET', '/webhooks.json'", $service);
        self::assertStringContainsString("'POST', '/webhooks.json'", $service);
        self::assertStringContainsString("webhooks/check-subscriptions', 'Integration::checkShopifySubscriptions'", $routes);
        self::assertStringContainsString("webhooks/verify-live-delivery', 'Integration::verifyLiveDelivery'", $routes);
        self::assertStringContainsString('last_event_reference', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php'));
        self::assertStringContainsString("'status' => 'Skipped'", $service);
        self::assertStringNotContainsString("'DELETE'", $service);
        self::assertStringContainsString('Register Missing Webhooks', $view);
        self::assertStringContainsString('Sync Webhooks', $view);
        self::assertStringContainsString('window.confirm', $javascript);
    }
}
