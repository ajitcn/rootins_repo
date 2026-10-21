<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyCustomerIdentityService;
use App\Services\Shopify\ShopifyOrderImportService;
use App\Services\Shopify\ShopifyOrderImportFailure;
use App\Services\Shopify\ShopifyWebhookEventService;
use App\Services\Shopify\ShopifyWebhookVerifier;
use App\Services\Shopify\ShopifyAppointmentIntentService;
use CodeIgniter\HTTP\ResponseInterface;

final class Webhook extends BaseController
{
    private string $requestCorrelationId = '';

    public function receive(string $topic): ResponseInterface
    {
        $rawBody = (string) $this->request->getBody();
        $configuration = new ShopifyConfiguration();
        $signature = $this->request->getHeaderLine('X-Shopify-Hmac-Sha256');
        $headerTopic = trim($this->request->getHeaderLine('X-Shopify-Topic'));
        $topic = $headerTopic !== '' ? $headerTopic : trim($topic);

        if (! (new ShopifyWebhookVerifier($configuration))->isValid($rawBody, $signature)) {
            log_message('warning', 'Shopify webhook signature rejected.');

            return $this->error(401, 'SHOPIFY_WEBHOOK_AUTH_FAILED', 'Webhook authentication failed.');
        }

        $shopDomain = strtolower(trim($this->request->getHeaderLine('X-Shopify-Shop-Domain')));
        $configuredDomain = strtolower(trim((string) preg_replace('#^https?://#', '', $configuration->storeDomain())));
        if ($shopDomain === '' || rtrim($shopDomain, '/') !== rtrim($configuredDomain, '/')) {
            log_message('warning', 'Shopify webhook store scope rejected.');

            return $this->error(403, 'SHOPIFY_WEBHOOK_SCOPE_INVALID', 'Webhook store scope is not authorized.');
        }

        $eventId = trim($this->request->getHeaderLine('X-Shopify-Webhook-Id'));
        if ($eventId === '') {
            return $this->error(422, 'SHOPIFY_WEBHOOK_EVENT_ID_REQUIRED', 'Webhook delivery identifier is required.');
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
            return $this->error(422, 'SHOPIFY_WEBHOOK_EVENT_INVALID', 'Webhook event could not be accepted safely.');
        }

        if ($result['duplicate']) {
            if ($topic === 'orders/paid' && $result['id'] !== null) {
                $events = new ShopifyWebhookEventService();
                $event = $events->find((int) $result['id']);
                if (is_array($event) && (string) ($event['status'] ?? '') !== 'processed') {
                    try {
                        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
                        if (! is_array($payload)) throw new \RuntimeException('Stored paid order payload is invalid.');
                        (new ShopifyAppointmentIntentService())->finalizePaid($event, $payload);
                        $events->markProcessed((int) $result['id']);
                    } catch (\Throwable $exception) {
                        $events->markFailed((int) $result['id'], 'Appointment payment processing failed.');
                        log_message('error', 'Shopify paid appointment retry failed safely.');
                        return $this->error(500, 'SHOPIFY_APPOINTMENT_FINALIZATION_FAILED', 'Paid appointment could not be finalized safely.');
                    }
                }
            }
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

                return $this->error(500, 'SHOPIFY_CUSTOMER_SYNC_FAILED', 'Customer identity could not be processed safely.');
            }
        }

        if ($topic === 'orders/paid' && $result['id'] !== null) {
            try {
                $event = (new ShopifyWebhookEventService())->find((int) $result['id']);
                $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
                if (is_array($event) && is_array($payload)) {
                    (new ShopifyAppointmentIntentService())->finalizePaid($event, $payload);
                }
                (new ShopifyWebhookEventService())->markProcessed((int) $result['id']);
            } catch (\Throwable $exception) {
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], 'Appointment payment processing failed.');
                log_message('error', 'Shopify paid appointment processing failed safely.');
                return $this->error(500, 'SHOPIFY_APPOINTMENT_FINALIZATION_FAILED', 'Paid appointment could not be finalized safely.');
            }
        }

        if (in_array($topic, ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled', 'refunds/create', 'fulfillments/create', 'fulfillments/update'], true) && $result['id'] !== null) {
            try {
                (new ShopifyOrderImportService())->processEvent((int) $result['id']);
            } catch (\Throwable $exception) {
                $failure = ShopifyOrderImportFailure::fromException($exception, $topic);
                $imports = new ShopifyOrderImportService();
                $imports->markFailureForEvent((int) $result['id'], $failure->category);
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], $failure->category);
                log_message('error', 'Shopify order import failed category=' . $failure->category . ' topic=' . $topic . ' exception=' . get_class($exception) . ' file=' . basename($exception->getFile()) . ' line=' . $exception->getLine());

                return $this->error(500, 'SHOPIFY_ORDER_IMPORT_FAILED', 'Shopify order could not be imported safely.');
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

    private function error(int $status, string $code, string $message): ResponseInterface
    {
        if ($this->requestCorrelationId === '') {
            try {
                $this->requestCorrelationId = bin2hex(random_bytes(16));
            } catch (\Throwable) {
                $this->requestCorrelationId = 'unavailable';
            }
        }
        return $this->response->setStatusCode($status)->setJSON([
            'status' => false,
            'msg' => $message,
            'error' => ['code' => $code, 'message' => $message, 'correlation_id' => $this->requestCorrelationId],
        ]);
    }
}
