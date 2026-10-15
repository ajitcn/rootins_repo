<?php

namespace App\Services\Shopify;

use RuntimeException;

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
        $subscriptions = $this->subscriptions($configuration);
        $rows = [];

        foreach ($catalog->catalog($configuration) as $definition) {
            $expectedUrl = $catalog->callbackUrlForTopic($configuration->appUrl(), (string) $definition['topic']);
            $matches = $this->matchingSubscriptions($subscriptions, (string) $definition['topic'], $expectedUrl);
            $sameTopic = array_values(array_filter($subscriptions, static fn (array $item): bool => (string) ($item['topic'] ?? '') === (string) $definition['topic']));
            $activeExact = array_values(array_filter($matches, static fn (array $item): bool => (bool) ($item['active'] ?? true)));
            $inactiveTopic = array_values(array_filter($sameTopic, static fn (array $item): bool => isset($item['active']) && ! (bool) $item['active']));
            $status = $activeExact !== [] ? 'Registered correctly' : ($inactiveTopic !== [] ? 'Inactive' : ($sameTopic !== [] ? 'URL mismatch' : 'Missing'));
            $rows[] = [
                'topic' => $definition['topic'],
                'callback_url' => $expectedUrl,
                'delivery_method' => 'POST',
                'status' => $status,
                'subscription_id' => $matches[0]['id'] ?? ($sameTopic[0]['id'] ?? null),
            ];
        }

        return [
            'status' => 'Synced',
            'created' => 0,
            'registered_correctly' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Registered correctly')),
            'missing' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Missing')),
            'url_mismatch' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'URL mismatch')),
            'inactive' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Inactive')),
            'failed' => 0,
            'webhooks' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    public function registerMissing(): array
    {
        $configuration = new ShopifyConfiguration();
        $catalog = $this->catalog ?? new ShopifyWebhookCatalogService();
        $subscriptions = $this->subscriptions($configuration);
        $created = 0;
        $existing = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;
        $rows = [];

        foreach ($catalog->catalog($configuration) as $definition) {
            $topic = (string) $definition['topic'];
            $callbackUrl = $catalog->callbackUrlForTopic($configuration->appUrl(), $topic);
            $matches = $this->matchingSubscriptions($subscriptions, $topic, $callbackUrl);
            $sameTopic = array_values(array_filter($subscriptions, static fn (array $item): bool => (string) ($item['topic'] ?? '') === $topic));

            if ($matches !== []) {
                $existing++;
                $rows[] = ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'Existing', 'subscription_id' => $matches[0]['id'] ?? null];
                continue;
            }
            if ($sameTopic !== []) {
                $skipped++;
                $rows[] = ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'Skipped', 'subscription_id' => $sameTopic[0]['id'] ?? null];
                continue;
            }
            if ($callbackUrl === '') {
                $missing++;
                $failed++;
                $rows[] = ['topic' => $topic, 'callback_url' => '', 'status' => 'Failed', 'error' => 'Public callback URL is not configured.'];
                continue;
            }

            try {
                $createdResponse = ($this->client ?? new ShopifyClient($configuration))->request('POST', '/webhooks.json', [
                    'webhook' => ['topic' => $topic, 'address' => $callbackUrl, 'format' => 'json'],
                ], 'shopify-webhook-register-' . hash('sha256', strtolower($configuration->storeDomain()) . '|' . $topic . '|' . $callbackUrl));
                $createdSubscription = is_array($createdResponse['webhook'] ?? null) ? $createdResponse['webhook'] : [];
                $subscriptions[] = $createdSubscription;
                $created++;
                $rows[] = ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'Created', 'subscription_id' => $createdSubscription['id'] ?? null];
            } catch (\Throwable) {
                $failed++;
                $rows[] = ['topic' => $topic, 'callback_url' => $callbackUrl, 'status' => 'Failed', 'error' => 'Shopify did not accept the subscription.'];
            }
        }

        return ['status' => $failed === 0 ? 'Completed' : 'Completed with failures', 'existing' => $existing, 'created' => $created, 'skipped' => $skipped, 'missing' => $missing, 'failed' => $failed, 'webhooks' => $rows];
    }

    /** @return list<array<string, mixed>> */
    private function subscriptions(ShopifyConfiguration $configuration): array
    {
        $result = ($this->client ?? new ShopifyClient($configuration))->request('GET', '/webhooks.json');
        if (! is_array($result['webhooks'] ?? null)) {
            throw new RuntimeException('Shopify webhook subscriptions were unavailable.');
        }

        return array_values(array_filter($result['webhooks'], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param list<array<string, mixed>> $subscriptions @return list<array<string, mixed>> */
    private function matchingSubscriptions(array $subscriptions, string $topic, string $callbackUrl): array
    {
        return array_values(array_filter($subscriptions, static function (array $item) use ($topic, $callbackUrl): bool {
            $method = strtoupper((string) ($item['delivery_method'] ?? $item['method'] ?? 'POST'));
            $format = strtolower((string) ($item['format'] ?? 'json'));

            return (string) ($item['topic'] ?? '') === $topic
                && rtrim((string) ($item['address'] ?? ''), '/') === rtrim($callbackUrl, '/')
                && $method === 'POST'
                && $format === 'json';
        }));
    }
}
