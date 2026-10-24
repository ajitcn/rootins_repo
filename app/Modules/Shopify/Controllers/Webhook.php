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
        $this->requestCorrelationId = $this->newCorrelationId();
        $rawBody = (string) $this->request->getBody();
        $configuration = new ShopifyConfiguration();
        $signature = $this->request->getHeaderLine('X-Shopify-Hmac-Sha256');
        $headerTopic = trim($this->request->getHeaderLine('X-Shopify-Topic'));
        $topic = $headerTopic !== '' ? $headerTopic : trim($topic);
        $medicineOrderTopic = $this->isMedicineOrderTopic($topic);
        $this->diagnostic($topic, '', '', 'request_received', 'none');
        if ($medicineOrderTopic) $this->medicineOrderDiagnostic($topic, '', '', '', 'request_received', 'none');

        if (! (new ShopifyWebhookVerifier($configuration))->isValid($rawBody, $signature)) {
            if ($medicineOrderTopic) {
                $invalidEventId = trim($this->request->getHeaderLine('X-Shopify-Webhook-Id'));
                $invalidShopDomain = strtolower(trim($this->request->getHeaderLine('X-Shopify-Shop-Domain')));
                $this->medicineOrderDiagnostic($topic, '', $invalidEventId, $invalidShopDomain, 'verification_failed', 'SHOPIFY_WEBHOOK_HMAC_INVALID');
            }
            $this->diagnostic($topic, '', '', 'verification_failed', 'SHOPIFY_WEBHOOK_HMAC_INVALID');

            return $this->error(401, 'SHOPIFY_WEBHOOK_AUTH_FAILED', 'Webhook authentication failed.');
        }

        $shopDomain = strtolower(trim($this->request->getHeaderLine('X-Shopify-Shop-Domain')));
        $configuredDomain = strtolower(trim((string) preg_replace('#^https?://#', '', $configuration->storeDomain())));
        if ($shopDomain === '' || rtrim($shopDomain, '/') !== rtrim($configuredDomain, '/')) {
            $this->diagnostic($topic, '', '', 'verification_failed', 'SHOPIFY_WEBHOOK_SCOPE_INVALID');

            return $this->error(403, 'SHOPIFY_WEBHOOK_SCOPE_INVALID', 'Webhook store scope is not authorized.');
        }

        $eventId = trim($this->request->getHeaderLine('X-Shopify-Webhook-Id'));
        if ($eventId === '') {
            $this->diagnostic($topic, '', '', 'verification_failed', 'SHOPIFY_WEBHOOK_EVENT_ID_REQUIRED');
            return $this->error(422, 'SHOPIFY_WEBHOOK_EVENT_ID_REQUIRED', 'Webhook delivery identifier is required.');
        }

        if ($medicineOrderTopic) {
            $verifiedPayload = json_decode($rawBody, true);
            $verifiedOrderId = is_array($verifiedPayload)
                ? (string) ($verifiedPayload['id'] ?? $verifiedPayload['order_id'] ?? '')
                : '';
            $this->medicineOrderDiagnostic($topic, $verifiedOrderId, $eventId, $shopDomain, 'hmac_verified', 'none');
        }

        $testPayload = json_decode($rawBody, true);
        if ($medicineOrderTopic) {
            $payloadOrderId = is_array($testPayload) ? (string) ($testPayload['id'] ?? $testPayload['order_id'] ?? '') : '';
            $this->medicineOrderDiagnostic($topic, $payloadOrderId, $eventId, $shopDomain, 'payload_parsed', is_array($testPayload) ? 'none' : ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID);
        }
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

        if ($medicineOrderTopic && $result['id'] !== null) {
            $payloadForDiagnostic = json_decode($rawBody, true);
            $orderIdForDiagnostic = is_array($payloadForDiagnostic)
                ? (string) ($payloadForDiagnostic['id'] ?? $payloadForDiagnostic['order_id'] ?? '')
                : '';
            $this->medicineOrderDiagnostic($topic, $orderIdForDiagnostic, $eventId, $shopDomain, 'event_stored', 'none');
        }

        if ($result['duplicate'] && ! $result['process']) {
            if ($result['status'] === 'processed') {
                service('audit')->record('shopify.webhook_duplicate', [
                    'topic' => $topic,
                    'event_id_hash' => hash('sha256', $eventId),
                    'store_domain' => $configuration->storeDomain(),
                    'status' => 'processed',
                ]);
                return $this->response->setStatusCode(202)->setJSON(['status' => true, 'msg' => 'Webhook event was already processed.']);
            }
            service('audit')->record('shopify.webhook_duplicate', [
                'topic' => $topic,
                'event_id_hash' => hash('sha256', $eventId),
                'store_domain' => $configuration->storeDomain(),
                'status' => $result['status'],
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
            $failureResponse = $this->processPaidEvent((int) $result['id'], $eventId, $shopDomain);
            if ($failureResponse !== null) return $failureResponse;
        }

        if (in_array($topic, ['refunds/create', 'fulfillments/create', 'fulfillments/update'], true) && $result['id'] !== null) {
            try {
                (new ShopifyOrderImportService())->processExternalEvent((int) $result['id'], $topic, $this->requestCorrelationId);
            } catch (\Throwable $exception) {
                $failure = ShopifyOrderImportFailure::fromException($exception, $topic);
                (new ShopifyOrderImportService())->markFailureForEvent((int) $result['id'], $failure->category);
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], $failure->category);
                log_message('error', 'Shopify external reconciliation failed safely ' . json_encode([
                    'correlation_id' => $this->requestCorrelationId,
                    'topic' => $topic,
                    'failure_category' => $failure->category,
                    'exception_class' => get_class($exception),
                ], JSON_UNESCAPED_SLASHES));
                return $this->error(500, $failure->category, 'Shopify external order update could not be processed safely.');
            }
        } elseif (in_array($topic, ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled'], true) && $result['id'] !== null) {
            try {
                (new ShopifyOrderImportService())->processEvent((int) $result['id'], false, $this->requestCorrelationId);
                $payloadForDiagnostic = json_decode($rawBody, true);
                $orderIdForDiagnostic = is_array($payloadForDiagnostic)
                    ? (string) ($payloadForDiagnostic['id'] ?? $payloadForDiagnostic['order_id'] ?? '')
                    : '';
                $this->medicineOrderDiagnostic($topic, $orderIdForDiagnostic, $eventId, $shopDomain, 'order_import_processed', 'none');
            } catch (\Throwable $exception) {
                $failure = ShopifyOrderImportFailure::fromException($exception, $topic);
                $imports = new ShopifyOrderImportService();
                $imports->markFailureForEvent((int) $result['id'], $failure->category);
                (new ShopifyWebhookEventService())->markFailed((int) $result['id'], $failure->category);
                $payloadForDiagnostic = json_decode($rawBody, true);
                $orderIdForDiagnostic = is_array($payloadForDiagnostic)
                    ? (string) ($payloadForDiagnostic['id'] ?? $payloadForDiagnostic['order_id'] ?? '')
                    : '';
                $this->medicineOrderDiagnostic($topic, $orderIdForDiagnostic, $eventId, $shopDomain, 'order_import_failed', $failure->category);
                log_message('error', 'Shopify order import failed category=' . $failure->category . ' topic=' . $topic . ' exception=' . get_class($exception) . ' correlation_id=' . $this->requestCorrelationId);

                return $this->error(500, $failure->category, 'Shopify order could not be imported safely.');
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

    private function newCorrelationId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    private function diagnostic(string $topic, string $orderId, string $reference, string $stage, string $category): void
    {
        log_message('info', 'Shopify webhook diagnostic ' . json_encode([
            'correlation_id' => $this->requestCorrelationId,
            'shopify_order_id' => $orderId,
            'topic' => $topic,
            'intent_present' => $reference !== '',
            'processing_stage' => $stage,
            'failure_category' => $category,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @return list<string> */
    private function medicineOrderTopics(): array
    {
        return ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled', 'orders/paid', 'refunds/create', 'fulfillments/create', 'fulfillments/update'];
    }

    private function isMedicineOrderTopic(string $topic): bool
    {
        return in_array($topic, $this->medicineOrderTopics(), true);
    }

    private function medicineOrderDiagnostic(string $topic, string $orderId, string $eventId, string $storeDomain, string $stage, string $category, bool $intentPresent = false): void
    {
        log_message('info', 'Shopify medicine-order webhook diagnostic ' . json_encode([
            'correlation_id' => $this->requestCorrelationId,
            'topic' => $topic,
            'shopify_order_id' => $orderId,
            'webhook_id_hash' => $eventId !== '' ? hash('sha256', $eventId) : '',
            'store_domain' => strtolower(trim($storeDomain)),
            'internal_import_id' => null,
            'intent_present' => $intentPresent,
            'processing_stage' => $stage,
            'current_import_status' => null,
            'payment_status' => null,
            'target_table' => 'shopify_order_imports',
            'lookup_type' => 'none',
            'row_found' => false,
            'affected_row_count' => 0,
            'failure_category' => $category,
            'exception_class' => null,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function processPaidEvent(int $eventId, string $webhookId, string $shopDomain): ?ResponseInterface
    {
        $events = new ShopifyWebhookEventService();
        $event = $events->find($eventId);
        $payload = is_array($event) ? json_decode((string) ($event['payload_json'] ?? ''), true) : null;
        $orderId = is_array($payload) ? (string) ($payload['id'] ?? $payload['order_id'] ?? '') : '';

        if (! is_array($event) || ! is_array($payload) || trim($orderId) === '') {
            $events->markFailed($eventId, ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID);
            $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'payload_parsing', ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID);
            return $this->error(500, 'SHOPIFY_ORDER_PAYLOAD_INVALID', 'Shopify order payload could not be processed safely.');
        }

        $event['correlation_id'] = $this->requestCorrelationId;
        $intentReference = (new ShopifyAppointmentIntentService())->appointmentIntentReferenceForOrder($payload);
        if ($intentReference === '') {
            $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'appointment_finalization_skipped', 'NO_APPOINTMENT_INTENT');
            try {
                (new ShopifyOrderImportService())->processEvent($eventId, false, $this->requestCorrelationId);
                $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'medicine_order_payment_received', 'none');
                return null;
            } catch (\Throwable $exception) {
                $failure = ShopifyOrderImportFailure::fromException($exception, 'orders/paid');
                (new ShopifyOrderImportService())->markFailureForEvent($eventId, $failure->category);
                $events->markFailed($eventId, $failure->category);
                $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'medicine_order_payment_failed', $failure->category);
                log_message('error', 'Shopify medicine payment import failed category=' . $failure->category . ' correlation_id=' . $this->requestCorrelationId);
                return $this->error(500, $failure->category, 'Shopify medicine order could not be processed safely.');
            }
        }

        $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'appointment_payment_received', 'none', true);
        if ((string) ($event['status'] ?? '') === 'processed') return null;
        try {
            (new ShopifyAppointmentIntentService())->finalizePaid($event, $payload);
            $events->markProcessed($eventId);
            return null;
        } catch (\Throwable $exception) {
            $events->markFailed($eventId, 'APPOINTMENT_FINALIZATION_FAILED');
            $this->medicineOrderDiagnostic('orders/paid', $orderId, $webhookId, $shopDomain, 'appointment_finalization_failed', 'APPOINTMENT_FINALIZATION_FAILED', true);
            return $this->error(500, 'SHOPIFY_APPOINTMENT_FINALIZATION_FAILED', 'Paid appointment could not be finalized safely.');
        }
    }
}
