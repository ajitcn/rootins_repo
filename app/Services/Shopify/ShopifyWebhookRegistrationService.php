<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyWebhookRegistrationService
{
    public function __construct(
        private readonly ?ShopifyClient $client = null,
        private readonly ?ShopifyWebhookCatalogService $catalog = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function sync(): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = $this->catalog ?? new ShopifyWebhookCatalogService();
        $definitions = $catalog->catalog($configuration);
        try {
            $subscriptions = $this->subscriptions($configuration);
        } catch (ShopifyProviderException $exception) {
            return $this->failedResult($definitions, $catalog, $configuration, $exception);
        }
        $rows = [];
        $accessScopes = null;
        foreach ($definitions as $definition) {
            $rows[] = $this->compareTopic($subscriptions, $catalog, $configuration, $definition);
        }
        return $this->summary('Synced', $rows);
    }

    /** @return array<string, mixed> */
    public function registerMissing(): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = $this->catalog ?? new ShopifyWebhookCatalogService();
        $definitions = $catalog->catalog($configuration);
        try {
            $subscriptions = $this->subscriptions($configuration);
        } catch (ShopifyProviderException $exception) {
            return $this->failedResult($definitions, $catalog, $configuration, $exception);
        }
        $rows = [];
        foreach ($definitions as $definition) {
            $topic = (string) $definition['topic'];
            $callbackUrl = $catalog->callbackUrlForTopic($configuration->appUrl(), $topic);
            if ($callbackUrl === '') {
                $rows[] = $this->failureRow($topic, '', 'Invalid callback URL', 'SHOPIFY_CALLBACK_URL_INVALID', 'The public callback URL is invalid or unavailable.');
                continue;
            }
            $matching = $this->matchingSubscriptions($subscriptions, $topic, $callbackUrl);
            $sameTopic = $this->sameTopic($subscriptions, $topic);
            $activeMatching = array_values(array_filter($matching, static fn (array $item): bool => (bool) ($item['active'] ?? true)));
            if ($activeMatching !== []) {
                $rows[] = $this->row($topic, $callbackUrl, 'Already existing', $activeMatching[0]['id'] ?? null, 200);
                continue;
            }
            if ($matching !== [] || $sameTopic !== []) {
                $rows[] = $this->row($topic, $callbackUrl, $matching !== [] ? 'Inactive' : 'URL mismatch', ($matching[0]['id'] ?? $sameTopic[0]['id'] ?? null), 200);
                continue;
            }
            try {
                if (in_array($topic, ['fulfillments/create', 'fulfillments/update'], true)) {
                    $accessScopes ??= ($this->client ?? new ShopifyClient($configuration))->accessScopes();
                    if (! in_array('read_fulfillments', $accessScopes, true) && ! in_array('read_marketplace_orders', $accessScopes, true)) {
                        $rows[] = $this->failureRow($topic, $callbackUrl, 'Missing API scope', 'SHOPIFY_SCOPE_MISSING', 'Fulfillment webhooks require the Shopify fulfillment access scope. Update the app scopes and reconnect/re-authorize the app.', 403);
                        continue;
                    }
                }
                $created = $this->createSubscription($configuration, $topic, $callbackUrl);
                $subscriptions[] = $created;
                $rows[] = $this->row($topic, $callbackUrl, 'Created', $created['id'] ?? null, 201);
            } catch (Throwable $exception) {
                $details = $this->failureDetails($exception);
                $rows[] = $this->failureRow($topic, $callbackUrl, $details['error_category'], $details['error_code'], $details['error_message'], $details['http_status']);
            }
        }
        return $this->summary('Completed', $rows);
    }

    /** @return array<string, mixed> */
    private function createSubscription(ShopifyConfiguration $configuration, string $topic, string $callbackUrl): array
    {
        $topicEnum = strtoupper(str_replace('/', '_', $topic));
        $result = ($this->client ?? new ShopifyClient($configuration))->graphql(
            'mutation CreateWebhook($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) { webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) { userErrors { field message } webhookSubscription { id topic uri } } }',
            ['topic' => $topicEnum, 'webhookSubscription' => ['uri' => $callbackUrl]],
        );
        $payload = is_array($result['webhookSubscriptionCreate'] ?? null) ? $result['webhookSubscriptionCreate'] : [];
        $errors = is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [];
        if ($errors !== []) {
            $message = strtolower((string) ($errors[0]['message'] ?? ''));
            $missingScope = str_contains($message, 'scope') || str_contains($message, 'permission') || str_contains($message, 'fulfillment access');
            throw new ShopifyProviderException('Shopify rejected the webhook subscription.', $missingScope ? 403 : 422, $missingScope ? 'SHOPIFY_SCOPE_MISSING' : (str_contains($message, 'topic') ? 'SHOPIFY_INVALID_TOPIC' : 'SHOPIFY_WEBHOOK_MUTATION_FAILED'), $missingScope ? 'missing_scope' : (str_contains($message, 'topic') ? 'invalid_topic' : 'provider_error'));
        }
        if (! is_array($payload['webhookSubscription'] ?? null)) throw new ShopifyProviderException('Shopify returned no webhook subscription.', 502, 'SHOPIFY_EMPTY_MUTATION_RESULT', 'provider_error');
        return array_merge($payload['webhookSubscription'], ['http_status' => 201]);
    }

    /** @param list<array<string, mixed>> $definitions @return array<string, mixed> */
    private function failedResult(array $definitions, ShopifyWebhookCatalogService $catalog, ShopifyConfiguration $configuration, ShopifyProviderException $exception): array
    {
        $details = $this->failureDetails($exception);
        $rows = [];
        foreach ($definitions as $definition) {
            $topic = (string) $definition['topic'];
            $rows[] = $this->failureRow($topic, $catalog->callbackUrlForTopic($configuration->appUrl(), $topic), $details['error_category'], $details['error_code'], $details['error_message'], $details['http_status']);
        }
        return $this->summary('Failed', $rows);
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function compareTopic(array $subscriptions, ShopifyWebhookCatalogService $catalog, ShopifyConfiguration $configuration, array $definition): array
    {
        $topic = (string) $definition['topic'];
        $callbackUrl = $catalog->callbackUrlForTopic($configuration->appUrl(), $topic);
        if ($callbackUrl === '') return $this->failureRow($topic, '', 'Invalid callback URL', 'SHOPIFY_CALLBACK_URL_INVALID', 'The public callback URL is invalid or unavailable.');
        $matching = $this->matchingSubscriptions($subscriptions, $topic, $callbackUrl);
        $sameTopic = $this->sameTopic($subscriptions, $topic);
        $activeMatching = array_values(array_filter($matching, static fn (array $item): bool => (bool) ($item['active'] ?? true)));
        if ($activeMatching !== []) return $this->row($topic, $callbackUrl, 'Registered correctly', $activeMatching[0]['id'] ?? null, 200);
        if ($matching !== []) return $this->row($topic, $callbackUrl, 'Inactive', $matching[0]['id'] ?? null, 200);
        if ($sameTopic !== []) return $this->row($topic, $callbackUrl, 'URL mismatch', $sameTopic[0]['id'] ?? null, 200);
        return $this->row($topic, $callbackUrl, 'Missing', null, 200);
    }

    /** @return array<string, mixed> */
    private function summary(string $status, array $rows): array
    {
        return [
            'status' => $status,
            'registered_correctly' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Registered correctly')),
            'created' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Created')),
            'already_existing' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Already existing')),
            'missing' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Missing')),
            'url_mismatch' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'URL mismatch')),
            'inactive' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Inactive')),
            'failed' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Failed')),
            'webhooks' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function row(string $topic, string $callbackUrl, string $status, mixed $id = null, ?int $httpStatus = null): array
    {
        return ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => $status, 'http_status' => $httpStatus, 'error_category' => null, 'error_code' => null, 'error_message' => null, 'subscription_id' => $id];
    }

    /** @return array<string, mixed> */
    private function failureRow(string $topic, string $callbackUrl, string $category, string $code, string $message, ?int $httpStatus = null): array
    {
        return ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'Failed', 'http_status' => $httpStatus, 'error_category' => $category, 'error_code' => $code, 'error_message' => $message, 'subscription_id' => null];
    }

    /** @return array{http_status:?int,error_category:string,error_code:string,error_message:string} */
    private function failureDetails(Throwable $exception): array
    {
        $status = $exception instanceof ShopifyProviderException ? $exception->httpStatus : null;
        $code = $exception instanceof ShopifyProviderException ? $exception->errorCode : 'SHOPIFY_PROVIDER_ERROR';
        $category = $exception instanceof ShopifyProviderException ? $exception->category : '';
        if ($category === 'invalid_credentials' || $status === 401) return ['http_status' => $status, 'error_category' => 'Invalid Shopify credentials', 'error_code' => 'SHOPIFY_AUTHENTICATION_FAILED', 'error_message' => 'Shopify credentials were rejected.'];
        if ($category === 'missing_scope' || $code === 'missing_scope' || $code === 'SHOPIFY_SCOPE_MISSING') return ['http_status' => $status, 'error_category' => 'Missing API scope', 'error_code' => 'SHOPIFY_SCOPE_MISSING', 'error_message' => 'Fulfillment webhooks require the Shopify fulfillment access scope. Update the app scopes and reconnect/re-authorize the app.'];
        if ($category === 'organization_mismatch') return ['http_status' => $status, 'error_category' => 'Store mismatch', 'error_code' => 'SHOPIFY_STORE_MISMATCH', 'error_message' => 'The Shopify connection does not match the configured store.'];
        if ($status === 403) return ['http_status' => $status, 'error_category' => 'Permission denied', 'error_code' => 'SHOPIFY_PERMISSION_DENIED', 'error_message' => 'Shopify denied webhook subscription access.'];
        if ($status === 429) return ['http_status' => $status, 'error_category' => 'Rate limited', 'error_code' => 'SHOPIFY_RATE_LIMITED', 'error_message' => 'Shopify rate limited the request.'];
        if ($category === 'network_error' || $category === 'network_timeout' || $status === null) return ['http_status' => $status, 'error_category' => 'Shopify API unavailable', 'error_code' => 'SHOPIFY_API_UNAVAILABLE', 'error_message' => 'Shopify could not be reached safely.'];
        if ($category === 'invalid_topic') return ['http_status' => $status, 'error_category' => 'Invalid topic', 'error_code' => 'SHOPIFY_INVALID_TOPIC', 'error_message' => 'Shopify rejected the webhook topic.'];
        if ($status === 422) return ['http_status' => $status, 'error_category' => 'Invalid callback URL', 'error_code' => $code, 'error_message' => 'Shopify rejected the callback URL or subscription definition.'];
        return ['http_status' => $status, 'error_category' => 'Unknown provider error', 'error_code' => $code, 'error_message' => 'Shopify returned an unclassified provider error.'];
    }

    /** @return list<array<string, mixed>> */
    private function subscriptions(ShopifyConfiguration $configuration): array
    {
        $result = ($this->client ?? new ShopifyClient($configuration))->request('GET', '/webhooks.json');
        if (! is_array($result['webhooks'] ?? null)) throw new RuntimeException('Shopify webhook subscriptions were unavailable.');
        return array_values(array_filter($result['webhooks'], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param list<array<string, mixed>> $subscriptions @return list<array<string, mixed>> */
    private function sameTopic(array $subscriptions, string $topic): array
    {
        return array_values(array_filter($subscriptions, static fn (array $item): bool => (string) ($item['topic'] ?? '') === $topic));
    }

    /** @param list<array<string, mixed>> $subscriptions @return list<array<string, mixed>> */
    private function matchingSubscriptions(array $subscriptions, string $topic, string $callbackUrl): array
    {
        return array_values(array_filter($subscriptions, static function (array $item) use ($topic, $callbackUrl): bool {
            $method = strtoupper((string) ($item['delivery_method'] ?? $item['method'] ?? 'POST'));
            $format = strtolower((string) ($item['format'] ?? 'json'));
            return (string) ($item['topic'] ?? '') === $topic && rtrim((string) ($item['address'] ?? ''), '/') === rtrim($callbackUrl, '/') && in_array($method, ['POST', 'HTTPS'], true) && $format === 'json';
        }));
    }
}
