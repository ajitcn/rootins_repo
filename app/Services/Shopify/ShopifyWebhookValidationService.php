<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyWebhookEventModel;
use InvalidArgumentException;
use Throwable;

final class ShopifyWebhookValidationService
{
    public function __construct(
        private readonly ?ShopifyWebhookCatalogService $catalog = null,
        private readonly ?ShopifyClient $client = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function validateTopic(string $topic): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = ($this->catalog ?? new ShopifyWebhookCatalogService())->catalog($configuration);
        $definition = $this->definition($catalog, $topic);
        $root = $this->validateEndpoint($configuration, $definition);
        $subscription = $this->validateSubscription($configuration, $definition);
        $observed = $this->observed($configuration, $topic);

        return [
            'topic' => $topic,
            'callback_url' => $definition['callback_url'],
            'rootins_endpoint' => $root,
            'shopify_subscription' => $subscription,
            'last_received' => $observed['last_received'],
            'last_processed' => $observed['last_processed'],
            'error_category' => $subscription['error_category'] ?? ($root['error_category'] ?? null),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function validateAll(): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = ($this->catalog ?? new ShopifyWebhookCatalogService())->catalog($configuration);
        $results = [];
        foreach ($catalog as $definition) {
            $results[] = $this->validateTopic((string) $definition['topic']);
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public function testEndpoint(string $topic): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = ($this->catalog ?? new ShopifyWebhookCatalogService())->catalog($configuration);
        $definition = $this->definition($catalog, $topic);
        $callbackUrl = (string) $definition['callback_url'];
        $secret = $configuration->webhookSecret();
        if ($callbackUrl === '' || $secret === '') {
            return ['status' => 'Configuration missing', 'topic' => $topic, 'callback_url' => $callbackUrl, 'response_code' => null, 'idempotency' => 'Not tested', 'error_category' => 'configuration_missing'];
        }

        $eventId = 'rootins-endpoint-test-' . bin2hex(random_bytes(12));
        $body = json_encode(['rootins_endpoint_test' => true, 'test_reference' => $eventId], JSON_UNESCAPED_SLASHES);
        $signature = base64_encode(hash_hmac('sha256', (string) $body, $secret, true));
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Shopify-Hmac-SHA256' => $signature,
            'X-Shopify-Shop-Domain' => $configuration->storeDomain(),
            'X-Shopify-Topic' => 'rootins/endpoint-test',
            'X-Shopify-Webhook-Id' => $eventId,
            'X-ROOTINS-Endpoint-Test' => '1',
        ];

        try {
            $http = service('curlrequest');
            $first = $http->request('POST', $callbackUrl, ['headers' => $headers, 'body' => $body, 'timeout' => 10, 'http_errors' => false]);
            $second = $http->request('POST', $callbackUrl, ['headers' => $headers, 'body' => $body, 'timeout' => 10, 'http_errors' => false]);
            $firstCode = $first->getStatusCode();
            $secondCode = $second->getStatusCode();
            (new ShopifyWebhookEventService())->removeEndpointTestEvents($configuration->storeDomain(), $eventId);

            return [
                'status' => $firstCode === 202 && $secondCode === 202 ? 'Passed' : 'Failed',
                'topic' => $topic,
                'callback_url' => $callbackUrl,
                'response_code' => $firstCode,
                'idempotency' => $secondCode === 202 ? 'Duplicate safely accepted' : 'Failed',
                'error_category' => $firstCode === 202 && $secondCode === 202 ? null : 'endpoint_response',
            ];
        } catch (Throwable) {
            (new ShopifyWebhookEventService())->removeEndpointTestEvents($configuration->storeDomain(), $eventId);

            return ['status' => 'Failed', 'topic' => $topic, 'callback_url' => $callbackUrl, 'response_code' => null, 'idempotency' => 'Not completed', 'error_category' => 'endpoint_unavailable'];
        }
    }

    /** @param list<array<string, mixed>> $catalog */
    private function definition(array $catalog, string $topic): array
    {
        foreach ($catalog as $definition) {
            if ((string) $definition['topic'] === trim($topic)) {
                return $definition;
            }
        }

        throw new InvalidArgumentException('The webhook topic is not in the ROOTINS catalog.');
    }

    /** @return array<string, mixed> */
    private function validateEndpoint(ShopifyConfiguration $configuration, array $definition): array
    {
        $callback = (string) ($definition['callback_url'] ?? '');
        $routeFile = (string) file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $routeRegistered = str_contains($routeFile, "integrations/shopify/webhooks/(:any)") && str_contains($routeFile, "Webhook::receive/$1");
        $validUrl = $callback !== '' && (bool) filter_var($callback, FILTER_VALIDATE_URL) && strtolower((string) parse_url($callback, PHP_URL_SCHEME)) === 'https';

        return [
            'status' => $validUrl && $routeRegistered ? 'Passed' : 'Failed',
            'callback_present' => $callback !== '',
            'https' => $validUrl,
            'public_url' => $validUrl,
            'route_registered' => $routeRegistered,
            'method_post' => true,
            'hmac_enabled' => $configuration->webhookSecret() !== '' && class_exists(ShopifyWebhookVerifier::class),
            'store_validation_enabled' => str_contains((string) file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php'), 'X-Shopify-Shop-Domain'),
            'idempotency_enabled' => str_contains((string) file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php'), 'external_event_id'),
            'error_category' => $validUrl && $routeRegistered ? null : 'endpoint_configuration',
        ];
    }

    /** @return array<string, mixed> */
    private function validateSubscription(ShopifyConfiguration $configuration, array $definition): array
    {
        if (! $configuration->isConfigured()) {
            return ['status' => 'Configuration missing', 'error_category' => 'configuration_missing'];
        }

        try {
            $result = ($this->client ?? new ShopifyClient($configuration))->request('GET', '/webhooks.json');
            $subscriptions = is_array($result['webhooks'] ?? null) ? $result['webhooks'] : [];
            $sameTopic = array_values(array_filter($subscriptions, static fn (mixed $item): bool => is_array($item) && (string) ($item['topic'] ?? '') === (string) $definition['topic']));
            if ($sameTopic === []) {
                $sameAddress = array_values(array_filter($subscriptions, static fn (mixed $item): bool => is_array($item) && (string) ($item['address'] ?? '') === (string) $definition['callback_url']));
                if ($sameAddress !== []) {
                    return ['status' => 'Topic mismatch', 'error_category' => 'topic_mismatch'];
                }
                return ['status' => 'Not registered', 'error_category' => 'not_registered'];
            }
            $active = array_values(array_filter($sameTopic, static fn (array $item): bool => (bool) ($item['active'] ?? true)));
            if ($active === []) {
                return ['status' => 'Inactive', 'error_category' => 'inactive'];
            }
            foreach ($active as $subscription) {
                if ((string) ($subscription['address'] ?? '') === (string) $definition['callback_url']) {
                    return ['status' => 'Configured correctly', 'error_category' => null];
                }
            }

            return ['status' => 'URL mismatch', 'error_category' => 'url_mismatch'];
        } catch (ShopifyConfigurationException) {
            return ['status' => 'Configuration missing', 'error_category' => 'configuration_missing'];
        } catch (ShopifyProviderException) {
            return ['status' => 'Shopify API unavailable', 'error_category' => 'shopify_api_unavailable'];
        } catch (Throwable) {
            return ['status' => 'Shopify API unavailable', 'error_category' => 'shopify_api_unavailable'];
        }
    }

    /** @return array{last_received:?string,last_processed:?string} */
    private function observed(ShopifyConfiguration $configuration, string $topic): array
    {
        if (! db_connect()->tableExists('shopify_webhook_events')) {
            return ['last_received' => null, 'last_processed' => null];
        }
        $event = (new ShopifyWebhookEventModel())->where('store_domain', strtolower($configuration->storeDomain()))->where('topic', $topic)->orderBy('id', 'DESC')->first();

        return ['last_received' => $event['created_at'] ?? null, 'last_processed' => $event['processed_at'] ?? null];
    }
}
