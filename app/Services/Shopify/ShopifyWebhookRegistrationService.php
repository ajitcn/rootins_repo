<?php

namespace App\Services\Shopify;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ShopifyWebhookRegistrationService
{
    public const CONFIRMATION = 'I confirm the approved Shopify webhook changes';

    /** @var list<string> */
    private const APPROVED_TOPICS = ['customers/create', 'customers/update', 'orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled', 'orders/paid', 'fulfillments/create', 'fulfillments/update', 'refunds/create'];

    public function __construct(
        private readonly ?ShopifyClient $client = null,
        private readonly ?ShopifyWebhookCatalogService $catalog = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function sync(): array
    {
        return $this->preview();
    }

    /** @return array<string, mixed> */
    public function registerMissing(): array
    {
        return $this->preview();
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = $this->catalog ?? new ShopifyWebhookCatalogService();
        $definitions = $catalog->catalog($configuration);

        try {
            $subscriptions = $this->subscriptions($configuration);
        } catch (Throwable $exception) {
            return $this->failedPlan($definitions, $catalog, $configuration, $exception);
        }

        $ownershipVerified = true;
        $ownershipError = null;
        try {
            $this->assertStoreOwnership($configuration);
        } catch (Throwable $exception) {
            $ownershipVerified = false;
            $ownershipError = $this->failureDetails($exception);
        }

        return $this->buildPlan($definitions, $subscriptions, $ownershipVerified, $ownershipError);
    }

    /** @return array<string, mixed> */
    public function apply(string $confirmation, string $planHash, string $mode = 'all'): array
    {
        if (trim($confirmation) !== self::CONFIRMATION) {
            throw new InvalidArgumentException('Explicit confirmation is required before Shopify webhook changes can be applied.');
        }
        if (! in_array($mode, ['all', 'register-missing', 'remove-unwanted'], true)) {
            throw new InvalidArgumentException('A valid Shopify webhook operation is required.');
        }

        $plan = $this->preview();
        if (! hash_equals((string) ($plan['plan_hash'] ?? ''), trim($planHash))) {
            throw new ShopifyProviderException('The Shopify webhook preview is stale. Review the current subscriptions again.', 409, 'SHOPIFY_WEBHOOK_PLAN_STALE', 'provider_http');
        }
        if (! ($plan['ownership_verified'] ?? false)) return $plan;

        $configuration = new ShopifyConfiguration();
        $created = [];
        $removed = [];
        $skipped = $plan['skipped'] ?? [];
        if ($mode === 'register-missing') {
            foreach ($plan['to_remove'] ?? [] as $item) $skipped[] = $this->resultRow($item, 'Skipped', null, (string) ($item['topic'] ?? ''), (string) ($item['callback_url'] ?? ''), 'Removal was not selected.');
        }
        if ($mode === 'remove-unwanted') {
            foreach ($plan['to_create'] ?? [] as $item) $skipped[] = $this->resultRow($item, 'Skipped', null, (string) ($item['topic'] ?? ''), (string) ($item['callback_url'] ?? ''), 'Registration was not selected.');
        }
        $failed = [];

        $toCreate = $mode === 'remove-unwanted' ? [] : ($plan['to_create'] ?? []);
        $toRemove = $mode === 'register-missing' ? [] : ($plan['to_remove'] ?? []);
        foreach ($toCreate as $item) {
            try {
                $subscription = $this->createSubscription($configuration, (string) $item['topic'], (string) $item['callback_url']);
                $created[] = ['topic' => $item['topic'], 'callback_url' => $item['callback_url'], 'subscription_id' => $subscription['id'] ?? null, 'status' => 'Created'];
            } catch (Throwable $exception) {
                $failed[] = $this->resultRow($item, 'Failed', $this->failureDetails($exception));
            }
        }

        foreach ($toRemove as $item) {
            try {
                $this->deleteSubscription($configuration, $item);
                $removed[] = $this->resultRow($item, 'Removed');
            } catch (Throwable $exception) {
                $failed[] = $this->resultRow($item, 'Failed', $this->failureDetails($exception));
            }
        }

        return [
            'status' => $failed === [] ? 'Completed' : 'Completed with failures',
            'plan_hash' => $plan['plan_hash'],
            'ownership_verified' => true,
            'created' => count($created),
            'already_existing' => count($plan['already_existing'] ?? []),
            'removed' => count($removed),
            'deleted' => count($removed),
            'skipped' => count($skipped),
            'failed' => count($failed),
            'registered_correctly' => count($plan['already_existing'] ?? []),
            'missing' => count($toCreate),
            'url_mismatch' => count($plan['url_mismatches'] ?? []),
            'inactive' => 0,
            'to_create' => $toCreate,
            'already_existing_rows' => $plan['already_existing'] ?? [],
            'to_remove' => $toRemove,
            'url_mismatches' => $plan['url_mismatches'] ?? [],
            'created_rows' => $created,
            'removed_rows' => $removed,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'webhooks' => array_merge($created, $removed, $skipped, $failed),
        ];
    }

    /** @param list<array<string, mixed>> $definitions @param list<array<string, mixed>> $subscriptions @return array<string, mixed> */
    private function buildPlan(array $definitions, array $subscriptions, bool $ownershipVerified, ?array $ownershipError): array
    {
        $approved = array_fill_keys(self::APPROVED_TOPICS, true);
        $toCreate = [];
        $alreadyExisting = [];
        $toRemove = [];
        $urlMismatches = [];
        $skipped = [];
        $seenRemovalIds = [];

        foreach ($definitions as $definition) {
            $topic = (string) ($definition['topic'] ?? '');
            if (! isset($approved[$topic])) continue;
            $callbackUrl = (string) ($definition['callback_url'] ?? '');
            $sameTopic = array_values(array_filter($subscriptions, static fn (array $item): bool => (string) ($item['topic'] ?? '') === $topic));
            $correct = array_values(array_filter($sameTopic, fn (array $item): bool => $this->isCorrectSubscription($item, $topic, $callbackUrl)));

            if ($correct !== []) {
                $alreadyExisting[] = $this->resultRow($correct[0], 'Already existing', null, $topic, $callbackUrl);
                foreach (array_slice($correct, 1) as $duplicate) $this->queueRemoval($duplicate, 'Duplicate approved subscription', $toRemove, $seenRemovalIds);
            } else {
                $toCreate[] = ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'To create'];
            }

            foreach ($sameTopic as $item) {
                if ($this->isCorrectSubscription($item, $topic, $callbackUrl)) continue;
                $mismatch = $this->resultRow($item, 'URL or delivery mismatch', null, $topic, $callbackUrl);
                $urlMismatches[] = $mismatch;
                $this->queueRemoval($item, 'URL, delivery method, format, or inactive mismatch', $toRemove, $seenRemovalIds);
            }
        }

        foreach ($subscriptions as $item) {
            if (! isset($approved[(string) ($item['topic'] ?? '')])) $this->queueRemoval($item, 'Topic is not approved', $toRemove, $seenRemovalIds);
        }

        if (! $ownershipVerified) {
            foreach (array_merge($toCreate, $toRemove) as $item) $skipped[] = $this->resultRow($item, 'Skipped', $ownershipError, (string) ($item['topic'] ?? ''), (string) ($item['callback_url'] ?? ''));
            $toCreate = [];
            $toRemove = [];
        }

        $plan = [
            'status' => 'Preview',
            'ownership_verified' => $ownershipVerified,
            'ownership_error' => $ownershipError,
            'callback_base' => 'https://rootins.newrootssoftware.in/public',
            'to_create' => $toCreate,
            'already_existing' => $alreadyExisting,
            'to_remove' => $toRemove,
            'url_mismatches' => $urlMismatches,
            'skipped' => $skipped,
            'created' => 0,
            'removed' => 0,
            'failed' => 0,
            'registered_correctly' => count($alreadyExisting),
            'missing' => count($toCreate),
            'url_mismatch' => count($urlMismatches),
            'inactive' => count($urlMismatches),
            'webhooks' => array_merge($toCreate, $alreadyExisting, $toRemove, $skipped),
        ];
        $plan['plan_hash'] = hash('sha256', json_encode(['to_create' => $toCreate, 'already_existing' => $alreadyExisting, 'to_remove' => $toRemove, 'skipped' => $skipped, 'ownership_verified' => $ownershipVerified], JSON_UNESCAPED_SLASHES) ?: '');

        return $plan;
    }

    private function assertStoreOwnership(ShopifyConfiguration $configuration): void
    {
        $result = ($this->client ?? new ShopifyClient($configuration))->request('GET', '/shop.json');
        $remote = $this->normalizeDomain((string) ($result['shop']['myshopify_domain'] ?? $result['shop']['domain'] ?? ''));
        $configured = $this->normalizeDomain($configuration->storeDomain());
        if ($remote === '' || $configured === '' || $remote !== $configured) throw new ShopifyProviderException('Shopify store ownership could not be verified.', 403, 'SHOPIFY_STORE_MISMATCH', 'organization_mismatch');
    }

    private function normalizeDomain(string $domain): string
    {
        return strtolower(rtrim((string) preg_replace('#^https?://#', '', trim($domain)), '/'));
    }

    /** @param array<string, mixed> $item @param list<array<string, mixed>> $toRemove @param array<string, bool> $seen */
    private function queueRemoval(array $item, string $reason, array &$toRemove, array &$seen): void
    {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '' || isset($seen[$id])) return;
        $seen[$id] = true;
        $toRemove[] = $this->resultRow($item, 'To remove', null, (string) ($item['topic'] ?? ''), (string) ($item['address'] ?? $item['uri'] ?? ''), $reason);
    }

    /** @param array<string, mixed> $item */
    private function isCorrectSubscription(array $item, string $topic, string $callbackUrl): bool
    {
        $method = strtoupper(trim((string) ($item['delivery_method'] ?? $item['method'] ?? 'HTTPS')));
        $format = strtoupper(trim((string) ($item['format'] ?? 'JSON')));
        return (string) ($item['topic'] ?? '') === $topic
            && rtrim((string) ($item['address'] ?? $item['uri'] ?? ''), '/') === rtrim($callbackUrl, '/')
            && in_array($method, ['HTTPS', 'POST'], true)
            && $format === 'JSON'
            && (bool) ($item['active'] ?? true);
    }

    /** @param array<string, mixed> $item @param array<string, mixed>|null $details */
    private function resultRow(array $item, string $status, ?array $details = null, ?string $topic = null, ?string $callbackUrl = null, ?string $reason = null): array
    {
        return ['topic' => $topic ?? (string) ($item['topic'] ?? ''), 'callback_url' => $callbackUrl ?? (string) ($item['address'] ?? $item['uri'] ?? ''), 'subscription_id' => $item['id'] ?? null, 'status' => $status, 'reason' => $reason, 'error_category' => $details['error_category'] ?? null, 'error_code' => $details['error_code'] ?? null, 'error_message' => $details['error_message'] ?? null];
    }

    /** @param array<string, mixed> $item */
    private function deleteSubscription(ShopifyConfiguration $configuration, array $item): void
    {
        $id = trim((string) ($item['subscription_id'] ?? $item['id'] ?? ''));
        if ($id === '') throw new ShopifyProviderException('Shopify subscription identifier is unavailable.', 422, 'SHOPIFY_SUBSCRIPTION_ID_MISSING', 'provider_http');
        $gid = str_starts_with($id, 'gid://') ? $id : 'gid://shopify/WebhookSubscription/' . $id;
        $result = ($this->client ?? new ShopifyClient($configuration))->graphql('mutation DeleteWebhook($id: ID!) { webhookSubscriptionDelete(id: $id) { deletedWebhookSubscriptionId userErrors { field message } } }', ['id' => $gid]);
        $payload = is_array($result['webhookSubscriptionDelete'] ?? null) ? $result['webhookSubscriptionDelete'] : [];
        $errors = is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [];
        if ($errors !== [] || empty($payload['deletedWebhookSubscriptionId'])) throw new ShopifyProviderException('Shopify rejected the webhook deletion.', 422, 'SHOPIFY_WEBHOOK_DELETE_FAILED', 'provider_error');
    }

    /** @return array<string, mixed> */
    private function createSubscription(ShopifyConfiguration $configuration, string $topic, string $callbackUrl): array
    {
        $topicEnum = strtoupper(str_replace('/', '_', $topic));
        $result = ($this->client ?? new ShopifyClient($configuration))->graphql('mutation CreateWebhook($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) { webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) { userErrors { field message } webhookSubscription { id topic uri format } } }', ['topic' => $topicEnum, 'webhookSubscription' => ['uri' => $callbackUrl, 'format' => 'JSON']]);
        $payload = is_array($result['webhookSubscriptionCreate'] ?? null) ? $result['webhookSubscriptionCreate'] : [];
        $errors = is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [];
        if ($errors !== []) throw new ShopifyProviderException('Shopify rejected the webhook subscription.', 422, 'SHOPIFY_WEBHOOK_MUTATION_FAILED', 'provider_error');
        if (! is_array($payload['webhookSubscription'] ?? null)) throw new ShopifyProviderException('Shopify returned no webhook subscription.', 502, 'SHOPIFY_EMPTY_MUTATION_RESULT', 'provider_error');
        return array_merge($payload['webhookSubscription'], ['http_status' => 201]);
    }

    /** @param list<array<string, mixed>> $definitions @return array<string, mixed> */
    private function failedPlan(array $definitions, ShopifyWebhookCatalogService $catalog, ShopifyConfiguration $configuration, Throwable $exception): array
    {
        $details = $this->failureDetails($exception);
        $skipped = [];
        foreach ($definitions as $definition) $skipped[] = ['topic' => $definition['topic'], 'callback_url' => $definition['callback_url'] ?? $catalog->callbackUrlForTopic($configuration->appUrl(), (string) $definition['topic']), 'status' => 'Skipped', 'reason' => 'Subscriptions could not be queried safely.', 'error_category' => $details['error_category'], 'error_code' => $details['error_code'], 'error_message' => $details['error_message']];
        return ['status' => 'Failed', 'ownership_verified' => false, 'plan_hash' => '', 'to_create' => [], 'already_existing' => [], 'to_remove' => [], 'url_mismatches' => [], 'skipped' => $skipped, 'created' => 0, 'removed' => 0, 'already_existing_count' => 0, 'failed' => count($skipped), 'registered_correctly' => 0, 'missing' => 0, 'url_mismatch' => 0, 'inactive' => 0, 'webhooks' => $skipped];
    }

    /** @return list<array<string, mixed>> */
    private function subscriptions(ShopifyConfiguration $configuration): array
    {
        $result = ($this->client ?? new ShopifyClient($configuration))->request('GET', '/webhooks.json');
        if (! is_array($result['webhooks'] ?? null)) throw new RuntimeException('Shopify webhook subscriptions were unavailable.');
        return array_values(array_filter($result['webhooks'], static fn (mixed $item): bool => is_array($item)));
    }

    /** @return array{http_status:?int,error_category:string,error_code:string,error_message:string} */
    private function failureDetails(Throwable $exception): array
    {
        $status = $exception instanceof ShopifyProviderException ? $exception->httpStatus : null;
        $code = $exception instanceof ShopifyProviderException ? $exception->errorCode : 'SHOPIFY_PROVIDER_ERROR';
        $category = $exception instanceof ShopifyProviderException ? $exception->category : '';
        if ($category === 'organization_mismatch' || $code === 'SHOPIFY_STORE_MISMATCH') return ['http_status' => $status, 'error_category' => 'Store ownership unverified', 'error_code' => 'SHOPIFY_STORE_MISMATCH', 'error_message' => 'The configured Shopify store could not be verified safely.'];
        if ($category === 'invalid_credentials' || $status === 401) return ['http_status' => $status, 'error_category' => 'Invalid Shopify credentials', 'error_code' => 'SHOPIFY_AUTHENTICATION_FAILED', 'error_message' => 'Shopify credentials were rejected.'];
        if ($status === 403) return ['http_status' => $status, 'error_category' => 'Permission denied', 'error_code' => 'SHOPIFY_PERMISSION_DENIED', 'error_message' => 'Shopify denied the webhook operation.'];
        if ($status === 429) return ['http_status' => $status, 'error_category' => 'Rate limited', 'error_code' => 'SHOPIFY_RATE_LIMITED', 'error_message' => 'Shopify rate limited the request.'];
        if ($category === 'network_error' || $category === 'network_timeout' || $status === null) return ['http_status' => $status, 'error_category' => 'Shopify API unavailable', 'error_code' => 'SHOPIFY_API_UNAVAILABLE', 'error_message' => 'Shopify could not be reached safely.'];
        return ['http_status' => $status, 'error_category' => 'Provider error', 'error_code' => $code, 'error_message' => 'Shopify rejected the webhook operation safely.'];
    }
}
