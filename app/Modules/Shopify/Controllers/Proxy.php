<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Services\Shopify\ShopifyOperationIdempotencyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class Proxy extends BaseController
{
    private string $requestCorrelationId = '';
    private string $requestOperation = '';

    public function health(): ResponseInterface
    {
        $this->begin('health');
        $body = ['status' => true, 'message' => 'ROOTINS Shopify Core API Proxy is reachable.'];
        return $this->response($body, 200);
    }

    public function availability(): ResponseInterface { return $this->handle('appointments.availability', false); }
    public function book(): ResponseInterface { return $this->handle('appointments.book', true); }
    public function register(): ResponseInterface { return $this->handle('patients.register', true); }
    public function upload(): ResponseInterface { return $this->handle('prescriptions.upload', true); }

    private function handle(string $operation, bool $write): ResponseInterface
    {
        $this->begin($operation);
        $correlationId = $this->requestCorrelationId;
        $key = trim($this->request->getHeaderLine('Idempotency-Key'));
        if ($write && ($key === '' || strlen($key) > 191)) {
            return $this->json(422, false, 'A valid Idempotency-Key header is required.', [], 'IDEMPOTENCY_KEY_REQUIRED', ['Idempotency-Key' => 'A valid Idempotency-Key header is required.']);
        }
        $claimed = false;
        try {
            $queryString = (string) $this->request->getUri()->getQuery();
            $query = (new \App\Services\Shopify\ShopifyProxySignatureService())->query($queryString);
            $context = (new ShopifyProxyService())->verify($operation, $query, $queryString);
            $payload = strtoupper($this->request->getMethod()) === 'GET' ? $this->request->getGet() : $this->requestPayload();
            $idempotencyKey = $operation === 'appointments.book'
                ? hash('sha256', $operation . '|' . strtolower((string) ($context['store_domain'] ?? '')) . '|' . $key)
                : $key;
            $idempotency = new ShopifyOperationIdempotencyService();
            if ($write) {
                if (! $idempotency->claim($idempotencyKey, $operation)) return $this->json(409, false, 'This Shopify operation has already been submitted.', [], 'SHOPIFY_PROXY_IDEMPOTENCY_REPLAY');
                $claimed = true;
            }
            if ($operation === 'appointments.book') {
                $payload['idempotency_key'] = $key;
            }
            $data = (new ShopifyProxyService())->execute($operation, $context, $payload, $this->request->getFile('document'));
            if ($claimed) $idempotency->complete($idempotencyKey, hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES) ?: ''));
            service('audit')->record('shopify.proxy_request_succeeded', ['operation' => $operation, 'store_domain' => $context['store_domain']]);
            return $this->json($write && $operation === 'patients.register' ? 201 : 200, true, 'Shopify proxy operation completed.', $data);
        } catch (CoreApiException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, $exception->errorCode);
            if ($exception->retryAfter !== null) $this->response->setHeader('Retry-After', (string) $exception->retryAfter);
            if ($operation === 'appointments.availability' && $exception->httpStatus >= 500) {
                $code = $this->availabilityFailureCode($exception);
                $this->logAvailabilityFailure($exception, $code);
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], $code);
            }
            if ($operation === 'appointments.availability' && $exception->httpStatus === 422 && in_array($exception->errorCode, ['VALIDATION_FAILED', 'APPOINTMENT_AVAILABILITY_INVALID'], true)) {
                $message = $exception->errorCode === 'APPOINTMENT_AVAILABILITY_INVALID' ? 'Required availability parameters are missing.' : 'Review the availability fields.';
                return $this->json(422, false, $message, [], 'APPOINTMENT_AVAILABILITY_INVALID', $exception->fields);
            }
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode, $exception->fields);
        } catch (ShopifyProxyAuthenticationException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'authentication');
            if ($operation === 'appointments.availability' && $exception->errorCode === 'SHOPIFY_PROXY_CONFIGURATION_MISSING') {
                $this->logAvailabilityFailure($exception, 'CORE_API_CONFIGURATION_MISSING');
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], 'CORE_API_CONFIGURATION_MISSING');
            }
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode);
        } catch (DatabaseException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'database');
            if ($operation === 'appointments.availability') {
                $this->logAvailabilityFailure($exception, 'DATABASE_UNAVAILABLE');
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], 'DATABASE_UNAVAILABLE');
            }
            log_message('error', 'Shopify proxy boundary failure ' . json_encode(['correlation_id' => $correlationId, 'operation' => $operation, 'exception_class' => get_class($exception), 'category' => 'database', 'route' => $this->safeRoute()], JSON_UNESCAPED_SLASHES));
            return $this->json(503, false, 'The Shopify proxy service is temporarily unavailable.', [], 'SHOPIFY_PROXY_DEPENDENCY_UNAVAILABLE');
        } catch (InvalidArgumentException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'validation');
            service('audit')->record('shopify.proxy_request_rejected', ['operation' => $operation, 'reason' => 'validation']);
            return $this->json(422, false, $exception->getMessage(), [], 'SHOPIFY_PROXY_INVALID_REQUEST', []);
        } catch (Throwable $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'internal');
            $dependency = $operation === 'appointments.availability' ? $this->availabilityDependencyCategory($exception) : null;
            if ($dependency !== null) {
                $code = $this->availabilityFailureCode($exception);
                $this->logAvailabilityFailure($exception, $code);
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], $code);
            }
            log_message('error', 'Shopify proxy boundary failure ' . json_encode(['correlation_id' => $correlationId, 'operation' => $operation, 'exception_class' => get_class($exception), 'category' => 'unexpected', 'route' => $this->safeRoute()], JSON_UNESCAPED_SLASHES));
            $message = $operation === 'appointments.availability'
                ? 'The appointment availability service is temporarily unavailable.'
                : 'The Shopify proxy request could not be processed safely.';
            return $this->json(500, false, $message, [], 'SHOPIFY_PROXY_INTERNAL_ERROR');
        }
    }

    /** @return array<string, mixed> */
    private function requestPayload(): array
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
        if (! str_contains($contentType, 'application/json')) {
            $payload = $this->request->getPost();
            return is_array($payload) ? $payload : [];
        }

        $rawBody = trim((string) $this->request->getBody());
        if ($rawBody === '') {
            throw new CoreApiException(422, 'INVALID_JSON', 'A valid JSON request body is required.');
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new CoreApiException(422, 'INVALID_JSON', 'A valid JSON request body is required.');
        }
        if (! is_array($payload)) {
            throw new CoreApiException(422, 'INVALID_JSON', 'A valid JSON request body is required.');
        }
        return $payload;
    }

    /** @param array<string, string> $fields */
    private function json(int $status, bool $success, string $message, array $data = [], string $code = '', array $fields = []): ResponseInterface
    {
        $body = $success
            ? ['status' => true, 'data' => $data === [] ? (object) [] : $data]
            : ['status' => false, 'error' => ['code' => $code !== '' ? $code : 'SHOPIFY_PROXY_ERROR', 'message' => $message]];
        if (! $success) {
            if (isset($fields['next_action'])) {
                $body['error']['next_action'] = $fields['next_action'];
                unset($fields['next_action']);
            }
            if (isset($fields['required_fields'])) {
                $body['error']['required_fields'] = array_values(array_filter(array_map('trim', explode(',', (string) $fields['required_fields']))));
                unset($fields['required_fields']);
            }
            if ($fields !== []) $body['error']['fields'] = $fields;
            $body['error']['correlation_id'] = $this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable';
        }
        return $this->response($body, $status);
    }

    private function response(array $body, int $status): ResponseInterface
    {
        $contentType = 'application/json; charset=UTF-8';
        $this->logResponse($status, $contentType);
        return $this->response->setStatusCode($status)->setContentType('application/json')->setJSON($body);
    }

    private function begin(string $operation): void
    {
        $this->requestCorrelationId = $this->correlationId();
        $this->requestOperation = $operation;
        log_message('info', 'Shopify proxy request started correlation_id=' . $this->requestCorrelationId . ' route=' . $this->safeRoute() . ' method=' . strtoupper($this->request->getMethod()) . ' operation=' . $operation . ' http_status=START content_type=unknown parameter_names=' . implode(',', $this->requestParameterNames()) . ' timestamp=' . date(DATE_ATOM));
    }

    private function logResponse(int $status, string $contentType): void
    {
        log_message('info', 'Shopify proxy response ready correlation_id=' . ($this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable') . ' route=' . $this->safeRoute() . ' method=' . strtoupper($this->request->getMethod()) . ' operation=' . ($this->requestOperation !== '' ? $this->requestOperation : 'unknown') . ' http_status=' . $status . ' content_type=' . $contentType . ' parameter_names=' . implode(',', $this->requestParameterNames()) . ' timestamp=' . date(DATE_ATOM));
    }

    /** @return list<string> */
    private function requestParameterNames(): array
    {
        $names = array_keys($this->request->getGet());
        $post = $this->request->getPost();
        if (is_array($post)) $names = array_merge($names, array_keys($post));
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json)) $names = array_merge($names, array_keys($json));
        } catch (Throwable) {
            // Request diagnostics must never interfere with proxy handling.
        }
        $names = array_values(array_unique(array_map(static fn ($name): string => preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $name) ?: 'unknown', $names)));
        sort($names);
        return $names;
    }

    private function correlationId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function safeRoute(): string
    {
        $path = $this->request->getUri()->getPath();
        return preg_replace('/[^a-zA-Z0-9_\/.:-]/', '', $path) ?: '/rootins-proxy';
    }

    private function logAvailabilityFailure(Throwable $exception, string $category): void
    {
        log_message('error', 'Shopify proxy availability dependency failure ' . json_encode([
            'correlation_id' => $this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable',
            'operation' => 'appointments.availability',
            'exception_class' => get_class($exception),
            'category' => $category,
            'route' => $this->safeRoute(),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function dependencyCategory(CoreApiException $exception): string
    {
        $code = strtolower($exception->errorCode);
        return str_contains($code, 'rate') || str_contains($code, 'core_api') ? 'service' : 'database';
    }

    private function availabilityFailureCode(Throwable $exception): string
    {
        if ($exception instanceof CoreApiException) {
            return match ($exception->errorCode) {
                'CORE_API_CONFIGURATION_MISSING' => 'CORE_API_CONFIGURATION_MISSING',
                'CORE_API_SCOPE_UNAVAILABLE', 'CLINIC_CONFIGURATION_INVALID', 'CLINIC_NOT_FOUND' => 'CLINIC_CONFIGURATION_INVALID',
                'APPOINTMENT_SERVICE_UNAVAILABLE' => 'APPOINTMENT_SERVICE_UNAVAILABLE',
                default => 'AVAILABILITY_DEPENDENCY_FAILURE',
            };
        }
        if ($exception instanceof DatabaseException || str_contains(strtolower(get_class($exception)), 'database') || str_contains(strtolower(get_class($exception)), 'pdo') || str_contains(strtolower(get_class($exception)), 'mysqli')) return 'DATABASE_UNAVAILABLE';
        if (str_contains(strtolower(get_class($exception)), 'appointment')) return 'APPOINTMENT_SERVICE_UNAVAILABLE';
        return 'AVAILABILITY_DEPENDENCY_FAILURE';
    }

    private function availabilityDependencyCategory(Throwable $exception): ?string
    {
        $class = strtolower(get_class($exception));
        $file = strtolower($exception->getFile());
        if (str_contains($class, 'database') || str_contains($class, 'pdo') || str_contains($class, 'mysqli') || str_contains($file, 'coreapiappointmentservice.php') || str_contains($file, 'coreapiratelimitservice.php')) {
            return str_contains($class, 'database') || str_contains($class, 'pdo') || str_contains($class, 'mysqli') ? 'database' : 'service';
        }
        return null;
    }
}
