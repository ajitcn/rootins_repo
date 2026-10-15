<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyConfigurationException;
use App\Services\Shopify\ShopifyClient;
use App\Services\Shopify\ShopifyWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class ShopifyIntegrationFoundationTest extends TestCase
{
    public function testWebhookVerifierAcceptsValidSignatureAndRejectsInvalidSignature(): void
    {
        $configuration = new ShopifyConfiguration([
            'webhook_secret' => 'test-webhook-secret',
        ]);
        $body = '{"id":123}';
        $signature = base64_encode(hash_hmac('sha256', $body, 'test-webhook-secret', true));
        $verifier = new ShopifyWebhookVerifier($configuration);

        self::assertTrue($verifier->isValid($body, $signature));
        self::assertFalse($verifier->isValid($body, 'invalid-signature'));
    }

    public function testConfigurationSummaryDoesNotContainSecretValues(): void
    {
        $configuration = new ShopifyConfiguration([
            'store_domain' => 'example.myshopify.com',
            'api_version' => '2025-01',
            'app_url' => 'https://example.test',
            'client_id' => 'secret-client-id',
            'client_secret' => 'secret-client-secret',
            'webhook_secret' => 'secret-webhook',
            'encryption_key' => 'secret-encryption-key',
        ]);
        $summary = $configuration->safeSummary();

        self::assertArrayNotHasKey('webhook_secret', $summary);
        self::assertArrayNotHasKey('client_secret', $summary);
        self::assertArrayNotHasKey('encryption_key', $summary);
        self::assertFalse(in_array('secret-client-secret', $summary, true));
        self::assertFalse(in_array('secret-webhook', $summary, true));
        self::assertFalse(in_array('secret-encryption-key', $summary, true));
    }

    public function testFoundationRoutesAndPermissionsAreDedicated(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-120000_AddShopifyIntegrationPermissions.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString('shopify.integration.view', $routes);
        self::assertStringContainsString('shopify.integration.manage', $routes);
        self::assertStringContainsString('shopify.integration.view', $migration);
        self::assertStringContainsString('shopify.integration.manage', $migration);
        self::assertStringContainsString('X-Shopify-Hmac-Sha256', $webhook);
        self::assertStringContainsString('X-Shopify-Webhook-Id', $webhook);
        self::assertStringNotContainsString('X-Shopify-Event-Id', $webhook);
    }

    public function testMissingConfigurationFailsBeforeAnyProviderRequest(): void
    {
        $this->expectException(ShopifyConfigurationException::class);

        (new ShopifyClient(new ShopifyConfiguration()))->testConnection();
    }

    public function testWebhookAndApiIdempotencyAreDatabaseBackedAndUnique(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-110000_CreateShopifyIntegrationFoundation.php');
        $eventService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
        $operationService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOperationIdempotencyService.php');

        self::assertStringContainsString("addUniqueKey(['store_domain', 'external_event_id'])", $migration);
        self::assertStringContainsString("addUniqueKey('operation_key')", $migration);
        self::assertStringContainsString('where(\'external_event_id\', $eventId)', $eventService);
        self::assertStringContainsString('where(\'operation_key\', $key)', $operationService);
    }

    public function testProviderRequestsUseTimeoutAndDoNotLogSecrets(): void
    {
        $client = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyClient.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        self::assertStringContainsString("'timeout' => 10", $client);
        self::assertStringNotContainsString('log_message(\'error\', $this->configuration->adminApiAccessToken()', $client);
        self::assertStringNotContainsString('admin_api_access_token', $controller);
        self::assertStringNotContainsString('webhook_secret', $controller);
    }

    public function testCustomerDetailsActionUsesVerifiedMinimizedCustomerData(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $events = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');
        $identity = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerIdentityService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        self::assertStringContainsString('customers/test-details', $routes);
        self::assertStringContainsString('X-Shopify-Shop-Domain', $webhook);
        self::assertStringContainsString('findLatestCustomerEvent', $events);
        self::assertStringContainsString('customerDetails', $identity);
        self::assertStringContainsString('registration_number', $identity);
        self::assertStringContainsString('testCustomerDetails', $controller);
        self::assertStringNotContainsString('date_of_birth', $events);
        self::assertStringNotContainsString('currentStaffMember', $identity . $controller);
    }

    public function testWebhookCatalogDocumentsTopicsAndPublicCallbackBoundary(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $catalog = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookCatalogService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_webhooks.php');

        self::assertStringContainsString("'webhooks', 'Integration::webhooks'", $routes);
        foreach (['customers/create', 'customers/update', 'orders/create', 'orders/updated', 'orders/cancelled', 'refunds/create', 'fulfillments/create', 'fulfillments/update', 'products/create', 'products/update', 'app/uninstalled'] as $topic) {
            self::assertStringContainsString("'topic' => '{$topic}'", $catalog);
        }
        self::assertStringContainsString("\$scheme !== 'https'", $catalog);
        self::assertStringContainsString('X-Shopify-Hmac-SHA256', $view);
        self::assertStringContainsString('Copy URL', $view);
    }
}
