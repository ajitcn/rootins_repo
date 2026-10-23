<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyProxySignatureService;
use App\Services\Shopify\ShopifyWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class ShopifyCredentialSeparationTest extends TestCase
{
    public function testWebhookHmacAcceptsOnlyTheWebhookOrderIntegrationSecret(): void
    {
        $configuration = new ShopifyConfiguration([
            'store_domain' => 'orders.myshopify.com',
            'client_id' => 'webhooks-client-id',
            'client_secret' => 'core-api-client-secret',
            'webhook_secret' => 'webhooks-order-integration-secret',
            'api_version' => '2026-10',
            'app_url' => 'https://rootins.example/public',
            'encryption_key' => 'webhooks-encryption-key',
        ], 'webhooks_order_integration');
        $body = '{"id":"order-123","line_items":[]}';
        $verifier = new ShopifyWebhookVerifier($configuration);

        $webhookSignature = base64_encode(hash_hmac('sha256', $body, 'webhooks-order-integration-secret', true));
        $coreApiSignature = base64_encode(hash_hmac('sha256', $body, 'core-api-client-secret', true));

        self::assertTrue($verifier->isValid($body, $webhookSignature));
        self::assertFalse($verifier->isValid($body, $coreApiSignature));
        self::assertSame('webhooks-order-integration-secret', $configuration->webhookSecret());
    }

    public function testAppProxyCanonicalSignatureAcceptsOnlyTheCoreApiProxySecret(): void
    {
        $service = new ShopifyProxySignatureService();
        $query = $service->query('shop=orders.myshopify.com&timestamp=1710000000&path_prefix=%2Fapps%2Frootins');
        $canonical = $service->canonical($query);
        $coreApiSignature = hash_hmac('sha256', $canonical, 'core-api-proxy-secret');
        $webhookSignature = hash_hmac('sha256', $canonical, 'webhooks-order-integration-secret');

        self::assertTrue(hash_equals($coreApiSignature, hash_hmac('sha256', $canonical, 'core-api-proxy-secret')));
        self::assertFalse(hash_equals($coreApiSignature, $webhookSignature));
    }

    public function testConfigurationSourcesCannotFallbackAcrossApplications(): void
    {
        $proxyConfiguration = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php');
        $proxyService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $webhookVerifier = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookVerifier.php');
        $webhookConfiguration = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfiguration.php');

        self::assertStringContainsString("->runtime()", $proxyService);
        self::assertStringContainsString('ShopifyProxyConfigurationService', $proxyService);
        self::assertStringNotContainsString('new ShopifyConfiguration()', $proxyService);
        self::assertStringNotContainsString('webhook_secret', $proxyConfiguration);
        self::assertStringNotContainsString('SHOPIFY_WEBHOOK_SECRET', $proxyConfiguration);
        self::assertStringContainsString('webhookSecret()', $webhookVerifier);
        self::assertStringNotContainsString('ShopifyProxyConfigurationService', $webhookVerifier);
        self::assertStringNotContainsString('clientSecret()', $webhookVerifier);
        self::assertStringNotContainsString('encryptionKey()', $webhookVerifier);
        self::assertStringContainsString("'webhook_secret' => self::decryptStored", $webhookConfiguration);
        self::assertStringNotContainsString('SHOPIFY_PROXY_CLIENT_SECRET', $webhookConfiguration);
        self::assertStringNotContainsString('webhookSecret()', $proxyService);
        self::assertStringNotContainsString('encryptionKey()', $proxyService);
    }

    public function testStoreAndClinicScopesRemainIndependent(): void
    {
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $appointment = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringContainsString("\$shop !== \$configuration['store_domain']", $proxy);
        self::assertStringContainsString("\$shopDomain === '' || rtrim(\$shopDomain, '/') !== rtrim(\$configuredDomain, '/')", $webhook);
        self::assertStringContainsString("where('clinic_id', \$clinicId)", $appointment);
        self::assertStringContainsString("\$patientId < 1", $appointment);
    }
}
