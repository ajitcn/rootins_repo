<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Services\Shopify\ShopifyOperationIdempotencyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifyProxyService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
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
        if ($write && ($key === '' || strlen($key) > 191)) return $this->json(422, false, 'A valid Idempotency-Key header is required.', [], 'SHOPIFY_PROXY_IDEMPOTENCY_KEY_INVALID');
        $claimed = false;
        try {
            $queryString = (string) $this->request->getUri()->getQuery();
            $query = (new \App\Services\Shopify\ShopifyProxySignatureService())->query($queryString);
            $context = (new ShopifyProxyService())->verify($operation, $query, $queryString);
            $payload = strtoupper($this->request->getMethod()) === 'GET' ? $this->request->getGet() : ($this->request->getJSON(true) ?: $this->request->getPost());
            if (! is_array($payload)) $payload = [];
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
                $this->logAvailabilityFailure($exception, $this->dependencyCategory($exception));
            }
            if ($operation === 'appointments.availability' && $exception->httpStatus === 422 && in_array($exception->errorCode, ['VALIDATION_FAILED', 'APPOINTMENT_AVAILABILITY_INVALID'], true)) {
                $message = $exception->errorCode === 'APPOINTMENT_AVAILABILITY_INVALID' ? 'Required availability parameters are missing.' : 'Review the availability fields.';
                return $this->json(422, false, $message, [], 'APPOINTMENT_AVAILABILITY_INVALID');
            }
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode);
        } catch (ShopifyProxyAuthenticationException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'authentication');
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode);
        } catch (InvalidArgumentException $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'validation');
            service('audit')->record('shopify.proxy_request_rejected', ['operation' => $operation, 'reason' => 'validation']);
            return $this->json(422, false, $exception->getMessage(), [], 'SHOPIFY_PROXY_INVALID_REQUEST');
        } catch (Throwable $exception) {
            if ($claimed) $idempotency->fail($idempotencyKey ?? $key, 'internal');
            $dependency = $operation === 'appointments.availability' ? $this->availabilityDependencyCategory($exception) : null;
            if ($dependency !== null) {
                $this->logAvailabilityFailure($exception, $dependency);
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], 'SHOPIFY_PROXY_AVAILABILITY_UNAVAILABLE');
            }
            log_message('error', 'Shopify proxy boundary failure route=' . $this->safeRoute() . ' operation=' . $operation . ' exception=' . get_class($exception) . ' file=' . basename($exception->getFile()) . ' line=' . $exception->getLine() . ' correlation_id=' . $correlationId);
            $message = $operation === 'appointments.availability'
                ? 'The appointment availability service is temporarily unavailable.'
                : 'The Shopify proxy request could not be processed safely.';
            return $this->json(500, false, $message, [], 'SHOPIFY_PROXY_INTERNAL_ERROR');
        }
    }

    private function json(int $status, bool $success, string $message, array $data = [], string $code = ''): ResponseInterface
    {
        $body = $success
            ? ['status' => true, 'data' => $data === [] ? (object) [] : $data]
            : ['status' => false, 'error' => ['code' => $code !== '' ? $code : 'SHOPIFY_PROXY_ERROR', 'message' => $message]];
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
        log_message('error', 'Shopify proxy availability dependency failure correlation_id=' . ($this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable') . ' operation=appointments.availability exception=' . get_class($exception) . ' file=' . basename($exception->getFile()) . ' line=' . $exception->getLine() . ' category=' . $category);
    }

    private function dependencyCategory(CoreApiException $exception): string
    {
        $code = strtolower($exception->errorCode);
        return str_contains($code, 'rate') || str_contains($code, 'core_api') ? 'service' : 'database';
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
