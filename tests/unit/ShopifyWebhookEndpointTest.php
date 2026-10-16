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

        foreach (['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled', 'refunds/create', 'fulfillments/create', 'fulfillments/update'] as $topic) {
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
        self::assertStringContainsString("webhooks/preview', 'Integration::previewWebhooks'", $routes);
        self::assertStringContainsString("webhooks/apply', 'Integration::applyWebhooks'", $routes);
        self::assertStringContainsString('permission:shopify.integration.manage', $routes);
        self::assertStringContainsString("'GET', '/webhooks.json'", $service);
        self::assertStringContainsString('webhookSubscriptionCreate', $service);
        self::assertStringContainsString("'GET', '/webhooks.json'", $service);
        self::assertStringContainsString("webhooks/check-subscriptions', 'Integration::checkShopifySubscriptions'", $routes);
        self::assertStringContainsString("webhooks/verify-live-delivery', 'Integration::verifyLiveDelivery'", $routes);
        self::assertStringContainsString('last_event_reference', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php'));
        self::assertStringContainsString("'Already existing'", $service);
        self::assertStringContainsString('URL or delivery mismatch', $service);
        self::assertStringContainsString('webhookSubscriptionDelete', $service);
        self::assertStringContainsString('Review &amp; Apply Approved Topics', $view);
        self::assertStringContainsString('Subscriptions to remove', $javascript);
        self::assertStringContainsString('rootinsShopifyWebhookConfirmation', $javascript);
    }
}
