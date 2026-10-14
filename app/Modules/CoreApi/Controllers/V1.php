<?php

namespace App\Modules\CoreApi\Controllers;

use Admin\Services\PatientRegistrationService;
use App\Controllers\BaseController;
use App\Modules\CoreApi\Services\CoreApiAppointmentService;
use App\Modules\CoreApi\Services\CoreApiClinicResolver;
use App\Modules\CoreApi\Services\CoreApiContext;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Modules\CoreApi\Services\CoreApiIdempotencyService;
use App\Modules\CoreApi\Services\CoreApiPrescriptionUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class V1 extends BaseController
{
    public function register(): ResponseInterface
    {
        return $this->write('patient.register', function (array $credential, array $payload): array {
            $errors = [];
            foreach (['first_name', 'last_name', 'country_code', 'mobile_number', 'date_of_birth'] as $field) {
                if (trim((string) ($payload[$field] ?? '')) === '') {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                }
            }
            if ($errors !== []) {
                throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the patient fields.', $errors);
            }

            $payload['clinic_id'] = (new CoreApiClinicResolver())->activeClinicId($payload['clinic_id'] ?? 0);
            unset($payload['source_type']);
            try {
                $result = (new PatientRegistrationService())->register($payload, null, ['source_type' => 'shopify', 'source_reference' => trim((string) ($payload['source_reference'] ?? ''))]);
            } catch (\InvalidArgumentException $exception) {
                throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the patient fields.', ['patient' => $exception->getMessage()]);
            }
            service('audit')->record('core_api.patient_registered', ['clinic_id' => (int) $payload['clinic_id'], 'patient_id' => (int) $result['id']]);

            return ['patient_reference' => (string) $result['registration_number']];
        });
    }

    public function availability(): ResponseInterface
    {
        try {
            $credential = CoreApiContext::get();
            if ($credential === null) {
                throw new CoreApiException(401, 'API_AUTH_REQUIRED', 'API authentication is required.');
            }
            $this->logRequest('appointments.availability', ['query_keys' => array_keys($this->request->getGet())]);

            return $this->respond(200, true, 'Appointment availability loaded.', (new CoreApiAppointmentService())->availability($credential, $this->request->getGet()));
        } catch (CoreApiException $exception) {
            return $this->error($exception);
        } catch (Throwable $exception) {
            return $this->unexpected('appointments.availability', $exception);
        }
    }

    public function book(): ResponseInterface
    {
        return $this->write('appointment.book', function (array $credential, array $payload): array {
            return (new CoreApiAppointmentService())->book($credential, $payload);
        });
    }

    public function upload(): ResponseInterface
    {
        $credentialId = 0;
        $key = '';
        $claimed = false;
        $idempotency = null;

        try {
            $credential = CoreApiContext::get();
            if ($credential === null) {
                throw new CoreApiException(401, 'API_AUTH_REQUIRED', 'API authentication is required.');
            }
            $credentialId = (int) $credential['id'];
            $key = trim($this->request->getHeaderLine('Idempotency-Key'));
            $file = $this->request->getFile('document');
            $payload = $this->request->getPost();
            $requestHash = hash('sha256', json_encode([$payload, $file?->getClientName(), $file?->getSize()], JSON_UNESCAPED_SLASHES));
            $idempotency = new CoreApiIdempotencyService();
            $claim = $idempotency->begin($credentialId, $key, $requestHash);
            $claimed = true;
            if ($claim['replay']) {
                return $this->response->setStatusCode($claim['status'])->setJSON($claim['body']);
            }
            if ($file === null) {
                throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the upload fields.', ['document' => 'A prescription file is required.']);
            }
            $data = (new CoreApiPrescriptionUploadService())->upload($credential, $payload, $file);
            $body = ['status' => true, 'data' => $data];
            $idempotency->complete($credentialId, $key, 201, $body);
            $this->logRequest('prescriptions.upload', ['field_keys' => array_keys($payload), 'file_present' => true]);

            return $this->response->setStatusCode(201)->setJSON($body);
        } catch (CoreApiException $exception) {
            if ($claimed && $idempotency !== null) {
                $idempotency->release($credentialId, $key);
            }
            return $this->error($exception);
        } catch (Throwable $exception) {
            if ($claimed && $idempotency !== null) {
                $idempotency->release($credentialId, $key);
            }
            return $this->unexpected('prescriptions.upload', $exception);
        }
    }

    public function prescriptionDetails(string $reference): ResponseInterface
    {
        try {
            $credential = CoreApiContext::get();
            if ($credential === null) {
                throw new CoreApiException(401, 'API_AUTH_REQUIRED', 'API authentication is required.');
            }
            $this->logRequest('prescriptions.details', ['reference_present' => trim($reference) !== '']);

            return $this->respond(200, true, 'Prescription details loaded.', (new CoreApiPrescriptionUploadService())->details($credential, $reference));
        } catch (CoreApiException $exception) {
            return $this->error($exception);
        } catch (Throwable $exception) {
            return $this->unexpected('prescriptions.details', $exception);
        }
    }

    /** @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $operation */
    private function write(string $operation, callable $operationHandler, bool $jsonBody = true): ResponseInterface
    {
        $credentialId = 0;
        $key = '';
        $claimed = false;
        $idempotency = null;

        try {
            $credential = CoreApiContext::get();
            if ($credential === null) {
                throw new CoreApiException(401, 'API_AUTH_REQUIRED', 'API authentication is required.');
            }
            $credentialId = (int) $credential['id'];
            $payload = $jsonBody ? ($this->request->getJSON(true) ?: []) : [];
            if (! is_array($payload)) {
                throw new CoreApiException(422, 'INVALID_JSON', 'A valid JSON request body is required.');
            }
            $key = trim($this->request->getHeaderLine('Idempotency-Key'));
            $requestHash = hash('sha256', (string) $this->request->getBody());
            $idempotency = new CoreApiIdempotencyService();
            $claim = $idempotency->begin($credentialId, $key, $requestHash);
            $claimed = true;
            if ($claim['replay']) {
                return $this->response->setStatusCode($claim['status'])->setJSON($claim['body']);
            }
            $data = $operationHandler($credential, $payload);
            $body = ['status' => true, 'data' => $data];
            $status = $operation === 'patient.register' ? 201 : 200;
            $idempotency->complete($credentialId, $key, $status, $body);
            $this->logRequest($operation, ['field_keys' => array_keys($payload)]);

            return $this->response->setStatusCode($status)->setJSON($body);
        } catch (CoreApiException $exception) {
            if ($claimed && $idempotency !== null) {
                $idempotency->release($credentialId, $key);
            }
            return $this->error($exception);
        } catch (Throwable $exception) {
            if ($claimed && $idempotency !== null) {
                $idempotency->release($credentialId, $key);
            }
            return $this->unexpected($operation, $exception);
        }
    }

    /** @param array<string, mixed> $data */
    private function respond(int $status, bool $success, string $message, array $data = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => $success, 'data' => $data, 'message' => $message]);
    }

    private function error(CoreApiException $exception): ResponseInterface
    {
        $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
        if ($exception->fields !== []) {
            $error['fields'] = $exception->fields;
        }
        if ($exception->retryAfter !== null) {
            $error['retry_after'] = $exception->retryAfter;
        }

        $response = $this->response->setStatusCode($exception->httpStatus);
        if ($exception->retryAfter !== null) {
            $response->setHeader('Retry-After', (string) $exception->retryAfter);
        }

        return $response->setJSON(['status' => false, 'error' => $error]);
    }

    private function unexpected(string $operation, Throwable $exception): ResponseInterface
    {
        log_message('error', 'Core API operation failed: operation=' . $operation . ' file=' . $exception->getFile() . ' line=' . $exception->getLine());
        service('audit')->record('core_api.operation_failed', ['operation' => $operation]);

        return $this->response->setStatusCode(500)->setJSON(['status' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'The request could not be processed safely.']]);
    }

    /** @param array<string, mixed> $context */
    private function logRequest(string $operation, array $context): void
    {
        service('audit')->record('core_api.request', array_merge(['operation' => $operation], $context));
    }
}
