<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyWebhookEventModel;

final class ShopifyWebhookCatalogService
{
    /** @return list<array<string, mixed>> */
    public function catalog(?ShopifyConfiguration $configuration = null): array
    {
        $configuration ??= new ShopifyConfiguration();
        $storeDomain = strtolower(trim($configuration->storeDomain()));
        $events = new ShopifyWebhookEventModel();
        $rows = [];

        foreach ($this->definitions() as $definition) {
            $latest = db_connect()->tableExists('shopify_webhook_events')
                ? $events
                    ->where('store_domain', $storeDomain)
                    ->where('topic', $definition['topic'])
                    ->orderBy('id', 'DESC')
                    ->first()
                : null;
            $status = $latest === null ? 'Not configured' : match ((string) ($latest['status'] ?? '')) {
                'failed' => 'Failed',
                'processed' => 'Received',
                default => 'Received',
            };

            $rows[] = array_merge($definition, [
                'callback_url' => $this->callbackUrlForTopic($configuration->appUrl(), (string) $definition['topic']),
                'expected_response' => '202 Accepted JSON; 401 invalid HMAC; 403 store-scope rejection; 422 invalid event.',
                'status' => $status,
                'last_received' => $latest['created_at'] ?? null,
                'last_processed' => $latest['processed_at'] ?? null,
                'last_result' => $latest === null ? 'No event received.' : ((string) ($latest['status'] ?? 'received')),
                'last_event_reference' => $latest === null || trim((string) ($latest['external_event_id'] ?? '')) === '' ? null : hash('sha256', (string) $latest['external_event_id']),
                'last_error_category' => $latest !== null && (string) ($latest['status'] ?? '') === 'failed' ? 'processing_failed' : null,
                'live_status' => $latest === null ? 'Not yet verified' : 'Live event received',
                'duplicate_behavior' => 'Reject duplicate X-Shopify-Webhook-Id values per store before processing.',
            ]);
        }

        return $rows;
    }

    public function publicCallbackBase(string $appUrl): string
    {
        $appUrl = rtrim(trim($appUrl), '/');
        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
        if ($appUrl === '' || $scheme !== 'https' || $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return '';
        }

        return $appUrl . '/integrations/shopify/webhooks';
    }

    public function endpointTestUrl(string $appUrl, string $topic): string
    {
        return $this->callbackUrlForTopic($appUrl, $topic);
    }

    public function callbackUrlForTopic(string $appUrl, string $topic): string
    {
        $topic = trim($topic);
        foreach ($this->definitions() as $definition) {
            if ((string) $definition['topic'] === $topic) {
                $base = $this->normalizedEndpointTestBase($appUrl);

                return $base === '' ? '' : $base . '/integrations/shopify/webhooks/' . $topic;
            }
        }

        return '';
    }

    private function normalizedEndpointTestBase(string $appUrl): string
    {
        $appUrl = trim($appUrl);
        $parts = parse_url($appUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https' || $host === '' || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
            return '';
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            return '';
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . $host . $port . '/public';
    }

    /** @return list<array{topic:string,purpose:string,method:string,required_fields:list<string>,hmac:string,scope:string,processing:string,required:bool}> */
    private function definitions(): array
    {
        $customerFields = ['Shopify customer ID', 'Email', 'Phone', 'First name', 'Last name', 'Default address summary', 'Updated timestamp'];
        $orderFields = ['Shopify order ID', 'Order number', 'Customer ID', 'Line items', 'Product/variant IDs', 'Quantities', 'Financial status', 'Fulfillment status', 'Currency and totals', 'ROOTINS prescription-link note attributes'];
        $fulfillmentFields = ['Order ID', 'Fulfillment ID', 'Status', 'Tracking company', 'Tracking number', 'Tracking URL', 'Fulfillment timestamp'];
        $productFields = ['Product ID', 'Variant ID', 'SKU', 'Title', 'Status', 'Updated timestamp'];

        return [
            ['topic' => 'customers/create', 'purpose' => 'Create or match a Shopify customer identity.', 'method' => 'POST', 'required_fields' => $customerFields, 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Minimize customer fields, match by verified email/mobile, and place unmatched or ambiguous identities in review.', 'required' => true],
            ['topic' => 'customers/update', 'purpose' => 'Refresh an existing Shopify customer identity.', 'method' => 'POST', 'required_fields' => $customerFields, 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Update the scoped identity snapshot and re-evaluate safe email/mobile matching.', 'required' => true],
            ['topic' => 'customers/delete', 'purpose' => 'Optional customer unlinking or cleanup.', 'method' => 'POST', 'required_fields' => ['Shopify customer ID', 'Deleted timestamp'], 'hmac' => 'Required', 'scope' => 'Configured store', 'processing' => 'Not currently registered for automatic unlinking; review manually before enabling.', 'required' => false],
            ['topic' => 'orders/create', 'purpose' => 'Import a new prescription-linked medicine order.', 'method' => 'POST', 'required_fields' => $orderFields, 'hmac' => 'Required', 'scope' => 'Configured store, clinic, and linked patient mapping', 'processing' => 'Idempotently store minimized order data, map medicines, and import or quarantine the order.', 'required' => true],
            ['topic' => 'orders/updated', 'purpose' => 'Reconcile order and payment changes.', 'method' => 'POST', 'required_fields' => $orderFields, 'hmac' => 'Required', 'scope' => 'Configured store, clinic, and linked patient mapping', 'processing' => 'Idempotently reconcile the existing order import without duplicating records.', 'required' => true],
            ['topic' => 'orders/cancelled', 'purpose' => 'Apply Shopify order cancellation state.', 'method' => 'POST', 'required_fields' => ['Shopify order ID', 'Order number', 'Customer ID', 'Cancelled timestamp', 'Financial status'], 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Reconcile cancellation through the existing order import workflow.', 'required' => true],
            ['topic' => 'refunds/create', 'purpose' => 'Reconcile refunds and payment state.', 'method' => 'POST', 'required_fields' => ['Order ID', 'Refund ID', 'Refund amount', 'Refund line items', 'Created timestamp'], 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Reconcile the refund against the existing imported order.', 'required' => true],
            ['topic' => 'fulfillments/create', 'purpose' => 'Capture initial fulfillment and delivery details.', 'method' => 'POST', 'required_fields' => $fulfillmentFields, 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Update delivery status and safe tracking fields through order reconciliation.', 'required' => true],
            ['topic' => 'fulfillments/update', 'purpose' => 'Capture delivery status and tracking changes.', 'method' => 'POST', 'required_fields' => $fulfillmentFields, 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Update delivery status and safe tracking fields through order reconciliation.', 'required' => true],
            ['topic' => 'orders/fulfilled', 'purpose' => 'Optional legacy fulfillment notification where supported.', 'method' => 'POST', 'required_fields' => ['Order ID', 'Fulfillment status', 'Fulfillment timestamp'], 'hmac' => 'Required', 'scope' => 'Configured store and clinic', 'processing' => 'Not required when fulfillment create/update topics are enabled; verify API-version support before registering.', 'required' => false],
            ['topic' => 'products/create', 'purpose' => 'Optional medicine mapping catalog refresh.', 'method' => 'POST', 'required_fields' => $productFields, 'hmac' => 'Required', 'scope' => 'Configured store', 'processing' => 'No automatic product mapping processor is currently registered; manage mappings in the Admin workflow.', 'required' => false],
            ['topic' => 'products/update', 'purpose' => 'Optional medicine mapping catalog refresh.', 'method' => 'POST', 'required_fields' => $productFields, 'hmac' => 'Required', 'scope' => 'Configured store', 'processing' => 'No automatic product mapping processor is currently registered; manage mappings in the Admin workflow.', 'required' => false],
            ['topic' => 'products/delete', 'purpose' => 'Optional medicine mapping cleanup.', 'method' => 'POST', 'required_fields' => ['Product ID', 'Deleted timestamp'], 'hmac' => 'Required', 'scope' => 'Configured store', 'processing' => 'Not currently required because mapping cleanup is not automatic.', 'required' => false],
            ['topic' => 'app/uninstalled', 'purpose' => 'Optional Shopify app lifecycle cleanup.', 'method' => 'POST', 'required_fields' => ['Shopify shop domain', 'Uninstalled timestamp'], 'hmac' => 'Required', 'scope' => 'Configured store', 'processing' => 'No automatic destructive cleanup is performed; investigate and disconnect configuration manually.', 'required' => false],
        ];
    }
}
