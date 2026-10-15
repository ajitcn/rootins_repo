<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyWebhookRegistrationTest extends TestCase
{
    public function testRegistrationUsesGraphqlMutationAndReturnsSafePerTopicFields(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');

        foreach (['webhookSubscriptionCreate', 'topic', 'callbackUrl', 'http_status', 'error_category', 'error_code', 'error_message'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        self::assertStringContainsString('WebhookSubscriptionInput!', $service);
        self::assertStringContainsString("'webhookSubscription' => ['uri' => \$callbackUrl]", $service);
        self::assertStringContainsString('read_fulfillments', $service);
        self::assertStringContainsString('read_marketplace_orders', $service);
        self::assertStringNotContainsString('X-Shopify-Access-Token', $service);
        self::assertStringNotContainsString('webhook_secret', $service);
        self::assertStringNotContainsString("'DELETE'", $service);
    }

    public function testFailureCategoriesAndStatusesAreExplicit(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');

        foreach (['Invalid Shopify credentials', 'Missing API scope', 'Invalid topic', 'Invalid callback URL', 'Shopify API unavailable', 'Rate limited', 'Store mismatch', 'Permission denied', 'Unknown provider error', 'Already existing', 'Registered correctly', 'URL mismatch', 'Inactive', 'Missing', 'Failed'] as $value) {
            self::assertStringContainsString($value, $service);
        }
        self::assertStringContainsString('Fulfillment webhooks require the Shopify fulfillment access scope.', $service);
    }

    public function testFulfillmentEventTopicIsNotAddedWithoutProcessingSupport(): void
    {
        $catalog = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringNotContainsString("'topic' => 'fulfillment_events/create'", $catalog);
        self::assertStringNotContainsString("'fulfillment_events/create'", $webhook);
    }
}
