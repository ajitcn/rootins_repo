<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyCustomerIdentityService;
use App\Services\Shopify\ShopifyOrderImportService;
use App\Services\Shopify\ShopifyWebhookEventService;
use App\Services\Shopify\ShopifyWebhookVerifier;
use CodeIgniter\HTTP\ResponseInterface;

final class Webhook extends BaseController
{
    public function receive(string $topic): ResponseInterface
    {
        $rawBody = (string) $this->request->getBody();
        $configuration = new ShopifyConfiguration();
        $signature = $this->request->getHeaderLine('X-Shopify-Hmac-Sha256');
        $headerTopic = trim($this->request->getHeaderLine('X-Shopify-Topic'));
        $topic = $headerTopic !== '' ? $headerTopic : trim($topic);

        if (! (new ShopifyWebhookVerifier($configuration))->isValid($rawBody, $signature)) {
            log_message('warning', 'Shopify webhook signature rejected.');

            return $this->response->setStatusCode(401)->setJSON([
                'status' => false,
                'msg' => 'Webhook authentication failed.',
            ]);
        }

        $shopDomain = strtolower(trim($this->request->getHeaderLine('X-Shopify-Shop-Domain')));
        $configuredDomain = strtolower(trim((string) preg_replace('#^https?://#', '', $configuration->storeDomain())));
        if ($shopDomain === '' || rtrim($shopDomain, '/') !== rtrim($configuredDomain, '/')) {
            log_message('warning', 'Shopify webhook store scope rejected.');

            return $this->response->setStatusCode(403)->setJSON([
                'status' => false,
                'msg' => 'Webhook store scope is not authorized.',
            ]);
        }

        $eventId = trim($this->request->getHeaderLine('X-Shopify-Webhook-Id'));
        if ($eventId === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'msg' => 'Webhook delivery identifier is required.',
            ]);
        }

        $testPayload = json_decode($rawBody, true);
        $endpointTest = $this->request->getHeaderLine('X-ROOTINS-Endpoint-Test') === '1'
            && $topic === 'rootins/endpoint-test'
            && is_array($testPayload)
            && ($testPayload['rootins_endpoint_test'] ?? false) === true;

        if ($endpointTest) {
            $events = new ShopifyWebhookEventService();
            $result = $events->ingest($configuration->storeDomain(), $eventId, $topic, $rawBody);
            if ($result['id'] !== null && ! $result['duplicate']) {
                $events->markProcessed((int) $result['id']);
            }
            service('audit')->record('shopify.endpoint_test_received', [
                'store_domain' => $configuration->storeDomain(),
                'event_id_hash' => hash('sha256', $eventId),
                'duplicate' => $result['duplicate'],
            ]);

            return $this->response->setStatusCode(202)->setJSON(['status' => true, 'msg' => 'ROOTINS endpoint test accepted.']);
        }

        if (in_array($topic, ['customers/create', 'customers/update'], true)) {
            service('audit')->record('shopify.customer_received', [
                'topic' => $topic,
                'event_id_hash' => hash('sha256', $eventId),
                'store_domain' => $configuration->storeDomain(),
            ]);
        }

        $result = (new ShopifyWebhookEventService())->ingest(
            $configuration->storeDomain(),
            $eventId,
            $topic,
            $rawBody,
        );

        if (! $result['accepted']) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'msg' => 'Webhook event could not be accepted safely.',
            ]);
        }

        if ($result['duplicate']) {
            service('audit')->record('shopify.webhook_duplicate', [
                'topic' => $topic,
                'event_id_hash' => hash('sha256', $eventId),
                'store_domain' => $configuration->storeDomain(),
            ]);

            return $this->response->setStatusCode(202)->setJSON([
                'status' => true,
                'msg' => 'Webhook event was already accepted.',
            ]);
        }

        if (in_array($topic, ['customers/create', 'customers/update'], true) && $result['id'] !== null) {
            try {
                (new ShopifyCustomerIdentityService())->processCustomerEvent((int) $result['id']);
            } catch (\Throwable $exception) {
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], 'Customer identity processing failed.');
                log_message('error', 'Shopify customer identity processing failed safely.');

                return $this->response->setStatusCode(500)->setJSON([
                    'status' => false,
                    'msg' => 'Customer identity could not be processed safely.',
                ]);
            }
        }

        if (in_array($topic, ['orders/create', 'orders/updated', 'orders/cancelled', 'refunds/create', 'fulfillments/create', 'fulfillments/update'], true) && $result['id'] !== null) {
            try {
                (new ShopifyOrderImportService())->processEvent((int) $result['id']);
            } catch (\Throwable $exception) {
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], 'Shopify order import failed.');
                log_message('error', 'Shopify order import failed event_id=' . (int) $result['id'] . ' file=' . $exception->getFile() . ' line=' . $exception->getLine());

                return $this->response->setStatusCode(500)->setJSON([
                    'status' => false,
                    'msg' => 'Shopify order could not be imported safely.',
                ]);
            }
        }

        service('audit')->record(
            $result['duplicate'] ? 'shopify.webhook_duplicate' : 'shopify.webhook_received',
            [
                'topic' => $topic,
                'event_id_hash' => hash('sha256', $eventId),
                'store_domain' => $configuration->storeDomain(),
            ],
        );

        return $this->response->setStatusCode(202)->setJSON([
            'status' => true,
            'msg' => $result['duplicate'] ? 'Webhook event was already accepted.' : 'Webhook event accepted.',
        ]);
    }
}
