<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyWebhookRegistrationTest extends TestCase
{
    public function testRegistrationUsesGraphqlMutationAndReturnsSafePerTopicFields(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');

        foreach (['webhookSubscriptionCreate', 'webhookSubscriptionDelete', 'topic', 'format', 'http_status', 'error_category', 'error_code', 'error_message'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        self::assertStringContainsString('WebhookSubscriptionInput!', $service);
        self::assertStringContainsString("'format' => 'JSON'", $service);
        self::assertStringContainsString('CONFIRMATION', $service);
        self::assertStringContainsString('ownership_verified', $service);
        self::assertStringNotContainsString('X-Shopify-Access-Token', $service);
        self::assertStringNotContainsString('webhook_secret', $service);
        self::assertStringContainsString('SHOPIFY_WEBHOOK_DELETE_FAILED', $service);
    }

    public function testFailureCategoriesAndStatusesAreExplicit(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookRegistrationService.php');

        foreach (['Invalid Shopify credentials', 'Store ownership unverified', 'Shopify API unavailable', 'Rate limited', 'Permission denied', 'Already existing', 'URL or delivery mismatch', 'To create', 'To remove', 'Skipped', 'Failed'] as $value) {
            self::assertStringContainsString($value, $service);
        }
        self::assertStringContainsString('Store ownership unverified', $service);
    }

    public function testFulfillmentEventTopicIsNotAddedWithoutProcessingSupport(): void
    {
        $catalog = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringNotContainsString("'topic' => 'fulfillment_events/create'", $catalog);
        self::assertStringNotContainsString("'fulfillment_events/create'", $webhook);
    }
}
