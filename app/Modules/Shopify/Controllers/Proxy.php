<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseController;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Services\Shopify\ShopifyOperationIdempotencyService;
use App\Services\Shopify\ShopifyProxyAuthenticationException;
use App\Services\Shopify\ShopifyProxyService;
use App\Services\Shopify\ShopifyPrescriptionUploadValidationException;
use App\Services\Shopify\ShopifyPrescriptionUploadCustomerException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
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
        $this->forceJsonResponse();
        $this->requestCorrelationId = $this->correlationId();
        $this->requestOperation = $operation;
        $correlationId = $this->requestCorrelationId;
        $key = '';
        $claimed = false;
        $idempotencyKey = null;
        $idempotency = null;
        try {
            $this->begin($operation);
            $correlationId = $this->requestCorrelationId;
            $key = trim($this->request->getHeaderLine('Idempotency-Key'));
            if ($write && ($key === '' || strlen($key) > 191)) {
                return $this->json(422, false, 'A valid Idempotency-Key header is required.', [], 'IDEMPOTENCY_KEY_REQUIRED', ['Idempotency-Key' => 'A valid Idempotency-Key header is required.']);
            }
            $queryString = (string) $this->request->getUri()->getQuery();
            $query = (new \App\Services\Shopify\ShopifyProxySignatureService())->query($queryString);
            $context = (new ShopifyProxyService())->verify($operation, $query, $queryString);
            $payload = strtoupper($this->request->getMethod()) === 'GET' ? $this->request->getGet() : $this->requestPayload();
            $file = $operation === 'prescriptions.upload' ? $this->prescriptionFile() : null;
            if ($operation === 'prescriptions.upload') {
                $payload['product_handles'] = $this->normalizeProductHandles($this->request->getPost('product_handles'));
            }
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
            if ($operation === 'prescriptions.upload' && trim((string) ($payload['external_upload_reference'] ?? '')) === '') {
                $payload['external_upload_reference'] = 'RXP-' . strtoupper(substr(hash('sha256', $context['store_domain'] . '|' . $context['customer_id'] . '|' . $key), 0, 32));
            }
            $data = (new ShopifyProxyService())->execute($operation, $context, $payload, $file);
            if ($claimed) $idempotency->complete($idempotencyKey, hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES) ?: ''));
            service('audit')->record('shopify.proxy_request_succeeded', ['operation' => $operation, 'store_domain' => $context['store_domain']]);
            return $this->json($write && $operation === 'patients.register' ? 201 : 200, true, 'Shopify proxy operation completed.', $data);
        } catch (CoreApiException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, $exception->errorCode);
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
            if ($operation === 'patients.register') return $this->registrationCoreApiError($exception);
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode, $exception->fields);
        } catch (ShopifyPrescriptionUploadValidationException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'file_invalid');
            return $this->json(422, false, 'The prescription file is invalid.', [], 'FILE_INVALID', $exception->errors);
        } catch (ShopifyPrescriptionUploadCustomerException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'customer_unlinked');
            return $this->json(403, false, $exception->getMessage(), [], 'SHOPIFY_CUSTOMER_UNLINKED');
        } catch (ShopifyProxyAuthenticationException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'authentication');
            if ($operation === 'patients.register') {
                $code = $exception->errorCode === 'SHOPIFY_PROXY_SIGNATURE_INVALID'
                    ? 'SHOPIFY_PROXY_SIGNATURE_INVALID'
                    : 'SHOPIFY_PROXY_CONFIGURATION_INVALID';
                $status = $code === 'SHOPIFY_PROXY_SIGNATURE_INVALID' ? 401 : 503;
                if ($status === 503) return $this->registrationDependencyError($code);
                return $this->json(401, false, 'The Shopify proxy signature is invalid.', [], $code);
            }
            if ($operation === 'prescriptions.upload' && $exception->errorCode === 'SHOPIFY_PROXY_CONTEXT_MISSING') {
                return $this->json(401, false, 'The Shopify App Proxy context is missing.', [], 'SHOPIFY_PROXY_CONTEXT_MISSING');
            }
            if ($operation === 'appointments.availability' && $exception->errorCode === 'SHOPIFY_PROXY_CONFIGURATION_MISSING') {
                $this->logAvailabilityFailure($exception, 'CORE_API_CONFIGURATION_MISSING');
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], 'CORE_API_CONFIGURATION_MISSING');
            }
            return $this->json($exception->httpStatus, false, $exception->getMessage(), [], $exception->errorCode);
        } catch (DatabaseException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'database');
            if ($operation === 'prescriptions.upload') {
                $this->logBoundaryFailure($exception, 'prescription_dependency');
                return $this->json(503, false, 'Prescription upload could not be processed safely.', [], 'PRESCRIPTION_DEPENDENCY_UNAVAILABLE');
            }
            if ($operation === 'appointments.availability') {
                $this->logAvailabilityFailure($exception, 'DATABASE_UNAVAILABLE');
                return $this->json(503, false, 'The appointment availability service is temporarily unavailable.', [], 'DATABASE_UNAVAILABLE');
            }
            if ($operation === 'patients.register') {
                $this->logBoundaryFailure($exception, 'registration_database');
                return $this->registrationDependencyError('PATIENT_REGISTRATION_DATABASE_UNAVAILABLE');
            }
            log_message('error', 'Shopify proxy boundary failure ' . json_encode(['correlation_id' => $correlationId, 'operation' => $operation, 'exception_class' => get_class($exception), 'category' => 'database', 'route' => $this->safeRoute()], JSON_UNESCAPED_SLASHES));
            return $this->json(503, false, 'The Shopify proxy service is temporarily unavailable.', [], 'SHOPIFY_PROXY_DEPENDENCY_UNAVAILABLE');
        } catch (InvalidArgumentException $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'validation');
            if ($operation === 'patients.register') return $this->json(422, false, 'Patient details are invalid.', [], 'PATIENT_DATA_INVALID');
            service('audit')->record('shopify.proxy_request_rejected', ['operation' => $operation, 'reason' => 'validation']);
            return $this->json(422, false, $exception->getMessage(), [], 'SHOPIFY_PROXY_INVALID_REQUEST', []);
        } catch (Throwable $exception) {
            if ($claimed && $idempotency instanceof ShopifyOperationIdempotencyService) $idempotency->fail($idempotencyKey ?? $key, 'internal');
            if ($operation === 'patients.register') {
                if ($this->isDependencyFailure($exception)) {
                    $this->logBoundaryFailure($exception, 'dependency');
                    return $this->registrationDependencyError('PATIENT_REGISTRATION_DEPENDENCY_UNAVAILABLE');
                }
                $this->logBoundaryFailure($exception, 'unexpected');
                return $this->registrationDependencyError('PATIENT_REGISTRATION_INTERNAL_ERROR');
            }
            if ($operation === 'prescriptions.upload') {
                return $this->json(503, false, 'Prescription upload could not be processed safely.', [], 'PRESCRIPTION_DEPENDENCY_UNAVAILABLE');
            }
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

    private function prescriptionFile(): UploadedFile
    {
        // `file` is the canonical Shopify multipart field. `document` is the
        // existing backward-compatible alias used by older ROOTINS clients.
        if (! str_starts_with(strtolower($this->request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
            throw new CoreApiException(422, 'FILE_INVALID', 'A multipart/form-data prescription upload is required.');
        }
        $file = $this->request->getFile('file');
        $legacy = null;
        if (! $this->validUploadedFile($file)) {
            $legacy = $this->request->getFile('document');
            if ($this->validUploadedFile($legacy)) $file = $legacy;
        }
        if (! $this->validUploadedFile($file)) {
            if ($file instanceof UploadedFile || $legacy instanceof UploadedFile) {
                throw new CoreApiException(422, 'FILE_INVALID', 'The prescription file is invalid.');
            }
            throw new CoreApiException(422, 'FILE_REQUIRED', 'A prescription file is required.');
        }
        return $file;
    }

    private function validUploadedFile(mixed $file): bool
    {
        return $file instanceof UploadedFile
            && $file->isValid()
            && $file->getError() === UPLOAD_ERR_OK
            && ! $file->hasMoved();
    }

    /** @return list<string> */
    private function normalizeProductHandles(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (is_string($value)) {
            $trimmed = trim($value);
            $decoded = json_decode($trimmed, true);
            $parts = preg_split('/\s*,\s*/', $trimmed);
            $value = is_array($decoded) ? $decoded : (is_array($parts) ? $parts : [$trimmed]);
        }
        if (! is_array($value)) throw new CoreApiException(422, 'PRODUCT_HANDLES_INVALID', 'Product handles must be a handle, comma-separated string, or JSON array.');
        $handles = [];
        foreach ($value as $handle) {
            $handle = strtolower(trim((string) $handle));
            if ($handle === '' || preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $handle) !== 1) {
                throw new CoreApiException(422, 'PRODUCT_HANDLES_INVALID', 'Each product handle must contain only letters, numbers, hyphens, or underscores.');
            }
            $handles[] = $handle;
        }
        return array_values(array_unique($handles));
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
        // Shopify App Proxy replaces upstream 5xx responses with the
        // storefront document. Keep the safe error envelope while using a
        // successful transport status for this JSON-only proxy operation.
        $transportStatus = $this->requestOperation === 'patients.register' && $status >= 500 ? 200 : $status;
        $this->logResponse($status, $contentType);
        return $this->response->setStatusCode($transportStatus)->setContentType('application/json')->setJSON($body)->setHeader('Content-Type', $contentType);
    }

    private function forceJsonResponse(): void
    {
        $this->response->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->response->setHeader('Cache-Control', 'no-store');
    }

    private function registrationCoreApiError(CoreApiException $exception): ResponseInterface
    {
        $status = $exception->httpStatus;
        $code = $exception->errorCode;
        $message = 'The Shopify proxy request could not be processed safely.';
        $fields = [];

        if ($code === 'PATIENT_DATA_INVALID' || $code === 'INVALID_JSON') {
            $status = 422;
            $code = 'PATIENT_DATA_INVALID';
            $message = 'Patient details are invalid.';
            $fields = $exception->fields;
        } elseif ($code === 'SHOPIFY_CUSTOMER_LOGIN_REQUIRED') {
            $status = 401;
            $message = 'Shopify customer login is required.';
        } elseif ($code === 'SHOPIFY_CUSTOMER_ID_MISMATCH') {
            $status = 403;
            $message = 'The Shopify customer identity does not match the signed customer.';
        } elseif (in_array($code, ['CORE_API_UNAVAILABLE', 'CORE_API_SCOPE_UNAVAILABLE', 'CORE_API_CONFIGURATION_MISSING'], true)) {
            return $this->registrationDependencyError('SHOPIFY_PROXY_CONFIGURATION_INVALID');
        } elseif (in_array($code, ['PATIENT_REGISTRATION_DATABASE_UNAVAILABLE', 'PATIENT_IDENTITY_LINK_UNAVAILABLE', 'PATIENT_REGISTRATION_DEPENDENCY_UNAVAILABLE', 'PATIENT_REGISTRATION_INTERNAL_ERROR', 'SHOPIFY_PROXY_CONFIGURATION_INVALID'], true)) {
            return $this->registrationDependencyError($code);
        } elseif ($status >= 500) {
            return $this->registrationDependencyError('PATIENT_REGISTRATION_DEPENDENCY_UNAVAILABLE');
        }

        return $this->json($status, false, $message, [], $code, $fields);
    }

    private function registrationDependencyError(string $code): ResponseInterface
    {
        log_message('error', 'Shopify patient registration dependency failure ' . json_encode([
            'correlation_id' => $this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable',
            'operation' => 'patients.register',
            'code' => $code,
            'route' => $this->safeRoute(),
        ], JSON_UNESCAPED_SLASHES));
        return $this->response([
            'status' => false,
            'error' => [
                'code' => $code,
                'correlation_id' => $this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable',
            ],
        ], 503);
    }

    private function logBoundaryFailure(Throwable $exception, string $category): void
    {
        log_message('error', 'Shopify proxy boundary failure ' . json_encode([
            'correlation_id' => $this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable',
            'operation' => $this->requestOperation !== '' ? $this->requestOperation : 'unknown',
            'exception_class' => get_class($exception),
            'category' => $category,
            'route' => $this->safeRoute(),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function isDependencyFailure(Throwable $exception): bool
    {
        $class = strtolower(get_class($exception));
        return $exception instanceof DatabaseException
            || str_contains($class, 'database')
            || str_contains($class, 'pdo')
            || str_contains($class, 'mysqli');
    }

    private function begin(string $operation): void
    {
        if ($this->requestCorrelationId === '') {
            $this->requestCorrelationId = $this->correlationId();
        }
        $this->requestOperation = $operation;
        service('audit')->bindCorrelationId($this->requestCorrelationId);
        log_message('info', 'Shopify proxy request started correlation_id=' . $this->requestCorrelationId . ' route=' . $this->safeRoute() . ' method=' . strtoupper($this->request->getMethod()) . ' operation=' . $operation . ' http_status=START diagnostics=' . json_encode($this->requestDiagnostics(), JSON_UNESCAPED_SLASHES) . ' timestamp=' . date(DATE_ATOM));
    }

    private function logResponse(int $status, string $contentType): void
    {
        log_message('info', 'Shopify proxy response ready correlation_id=' . ($this->requestCorrelationId !== '' ? $this->requestCorrelationId : 'unavailable') . ' route=' . $this->safeRoute() . ' method=' . strtoupper($this->request->getMethod()) . ' operation=' . ($this->requestOperation !== '' ? $this->requestOperation : 'unknown') . ' http_status=' . $status . ' diagnostics=' . json_encode($this->requestDiagnostics(), JSON_UNESCAPED_SLASHES) . ' timestamp=' . date(DATE_ATOM));
    }

    /** @return list<string> */
    private function requestParameterNames(): array
    {
        try {
            $names = [];
            $get = $this->request->getGet();
            if (is_array($get)) $names = array_keys($get);
            $post = $this->request->getPost();
            if (is_array($post)) $names = array_merge($names, array_keys($post));
            if (! str_starts_with(strtolower($this->request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
                $json = $this->request->getJSON(true);
                if (is_array($json)) $names = array_merge($names, array_keys($json));
            }
        } catch (Throwable) {
            // Request diagnostics must never interfere with proxy handling.
            $names = [];
        }
        $names = array_values(array_unique(array_map(static fn ($name): string => preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $name) ?: 'unknown', $names)));
        sort($names);
        return $names;
    }

    /** @return array<string, mixed> */
    private function requestDiagnostics(): array
    {
        $file = $this->request->getFile('file');
        $query = (new \App\Services\Shopify\ShopifyProxySignatureService())->query((string) $this->request->getUri()->getQuery());
        $required = ['shop', 'timestamp', 'signature', 'path_prefix', 'logged_in_customer_id'];
        $contextPresent = count(array_filter($required, static fn (string $name): bool => array_key_exists($name, $query) && ! is_array($query[$name]) && trim((string) $query[$name]) !== '')) === count($required);
        $mime = null;
        if ($file instanceof UploadedFile && is_file($file->getTempName()) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) { $mime = finfo_file($finfo, $file->getTempName()) ?: null; finfo_close($finfo); }
        }
        $post = $this->request->getPost();
        return [
            'content_type' => strtolower($this->request->getHeaderLine('Content-Type')),
            'file_field_present' => $file instanceof UploadedFile,
            'file_valid' => $file instanceof UploadedFile && $file->isValid() && $file->getError() === UPLOAD_ERR_OK && ! $file->hasMoved(),
            'file_size' => $file instanceof UploadedFile ? $file->getSize() : null,
            'file_mime' => $mime,
            'post_field_names' => is_array($post) ? array_values(array_unique(array_map('strval', array_keys($post)))) : [],
            'app_proxy_context_present' => $contextPresent,
        ];
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
