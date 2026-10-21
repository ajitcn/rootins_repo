<?php

namespace App\Services\Shopify;

use Admin\Services\PatientRegistrationService;
use App\Modules\CoreApi\Models\CoreApiCredentialModel;
use App\Modules\CoreApi\Services\CoreApiAppointmentService;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Modules\CoreApi\Services\CoreApiPrescriptionUploadService;
use App\Modules\CoreApi\Services\CoreApiRateLimitService;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use App\Services\Shopify\ShopifyAppointmentIntentService;

final class ShopifyProxyService
{
    private const OPERATIONS = ['appointments.availability', 'appointments.book', 'patients.register', 'prescriptions.upload'];
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function verify(string $operation, array $query, string $rawQuery): array
    {
        $signatureService = new ShopifyProxySignatureService();
        $received = array_keys($query);
        sort($received, SORT_STRING);
        $required = ['shop', 'timestamp', 'signature', 'path_prefix'];
        $missing = array_values(array_diff($required, $received));
        try {
            $configuration = (new ShopifyProxyConfigurationService())->runtime();
        } catch (InvalidArgumentException $exception) {
            $this->logValidationFailure($missing, $received, null, null, 'missing_configuration', false);
            throw new ShopifyProxyAuthenticationException($exception->getMessage(), 'SHOPIFY_PROXY_CONFIGURATION_MISSING');
        }

        $secretPresent = $configuration['client_secret'] !== '';
        if (! $configuration['enabled']) {
            $this->logValidationFailure($missing, $received, null, null, 'disabled', $secretPresent);
            throw new ShopifyProxyAuthenticationException('Shopify Core API App Proxy is disabled.', 'SHOPIFY_PROXY_DISABLED', 403);
        }
        if (! in_array($operation, $configuration['allowed_operations'], true)) {
            $this->logValidationFailure($missing, $received, null, null, 'operation_disabled', $secretPresent);
            throw new ShopifyProxyAuthenticationException('This Shopify Core API App Proxy operation is disabled.', 'SHOPIFY_PROXY_OPERATION_DISABLED', 403);
        }
        if ($missing !== []) {
            $this->logValidationFailure($missing, $received, null, null, 'missing_parameters', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy request is missing required parameters.', 'SHOPIFY_PROXY_PARAMETERS_MISSING');
        }

        $shop = ShopifyProxyConfigurationService::normalizeStoreDomain($this->scalar($query['shop'] ?? ''));
        if (! self::isValidStoreDomain($shop) || $shop !== $configuration['store_domain']) {
            $this->logValidationFailure($missing, $received, $shop, null, 'store_scope', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify Core API app store scope is invalid.', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 403);
        }

        $timestampValue = $this->scalar($query['timestamp'] ?? '');
        $timestamp = ctype_digit($timestampValue) ? (int) $timestampValue : null;
        $timestampAge = $timestamp !== null ? time() - $timestamp : null;
        if ($timestamp === null || abs($timestampAge) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'timestamp_invalid', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy request timestamp is invalid or expired.', 'SHOPIFY_PROXY_TIMESTAMP_INVALID');
        }

        $provided = $query['signature'] ?? '';
        if (! is_string($provided) || $provided === '' || ! $secretPresent) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'signature_unavailable', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify Core API app proxy signature is unavailable.', 'SHOPIFY_PROXY_SIGNATURE_MISSING');
        }

        $canonical = $signatureService->canonical($signatureService->query($rawQuery));
        $expected = hash_hmac('sha256', $canonical, $configuration['client_secret']);
        if (! hash_equals($expected, $provided)) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'signature_invalid', true);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy signature is invalid.', 'SHOPIFY_PROXY_SIGNATURE_INVALID');
        }

        return ['store_domain' => $shop, 'customer_id' => trim($this->scalar($query['logged_in_customer_id'] ?? '')), 'configuration' => $configuration];
    }

    private function scalar(mixed $value): string
    {
        return is_array($value) ? '' : trim((string) $value);
    }

    private static function isValidStoreDomain(string $store): bool
    {
        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $store) === 1;
    }

    private function logValidationFailure(array $missing, array $received, ?string $store, ?int $timestampAge, string $reason, bool $secretPresent): void
    {
        log_message('warning', 'Shopify Core API App Proxy validation failed ' . json_encode([
            'missing_parameter_names' => array_values($missing),
            'received_parameter_names' => array_values($received),
            'store_domain' => $store,
            'timestamp_age_seconds' => $timestampAge,
            'configuration_source' => 'shopify_core_api_proxy_configurations',
            'configuration_status' => $reason === 'missing_configuration' ? 'missing' : 'configured',
            'secret_present' => $secretPresent,
            'reason' => $reason,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function execute(string $operation, array $context, array $payload, ?UploadedFile $file = null): array
    {
        $configuration = $context['configuration'];
        $credential = $this->credential();
        $this->rateLimit((int) $credential['id'], (int) $configuration['rate_limit_per_minute']);
        $patientScoped = in_array($operation, ['appointments.book', 'prescriptions.upload'], true);
        if ($operation === 'appointments.availability') $payload = $this->normalizeAvailabilityPayload($payload);
        if ($operation === 'appointments.book') $payload = $this->normalizeBookingPayload($payload);
        $customerId = (string) $context['customer_id'];
        $link = $patientScoped && $customerId !== '' ? (new ShopifyPatientIdentityLinkModel())->where('shopify_store_domain', $context['store_domain'])->where('shopify_customer_id', $customerId)->where('link_status', 'linked')->first() : null;

        if ($patientScoped && $link === null) throw new CoreApiException(403, 'SHOPIFY_CUSTOMER_UNLINKED', 'The Shopify customer is not linked to a ROOTINS patient.');
        if ($patientScoped && $link !== null) {
            $payload['patient_reference'] = (string) db_connect()->table('patients')->select('registration_number')->where('id', (int) $link['patient_id'])->where('clinic_id', (int) $credential['clinic_id'])->where('status', 1)->get()->getRow('registration_number');
            if ($payload['patient_reference'] === '') throw new CoreApiException(403, 'SHOPIFY_SCOPE_INVALID', 'The Shopify customer is outside the configured clinic scope.');
            $payload['clinic_id'] = (int) $credential['clinic_id'];
            $context['patient_id'] = (int) $link['patient_id'];
            $context['patient_reference'] = $payload['patient_reference'];
        }
        return match ($operation) {
            'appointments.availability' => (new CoreApiAppointmentService())->availability($credential, $payload),
            'appointments.book' => (new ShopifyAppointmentIntentService())->create($context, $credential, $payload),
            'prescriptions.upload' => $file === null ? throw new CoreApiException(422, 'FILE_REQUIRED', 'A prescription file is required.') : (new CoreApiPrescriptionUploadService())->upload($credential, $payload + ['clinic_id' => (int) $credential['clinic_id']], $file),
            'patients.register' => $this->register($context, $payload, $credential),
            default => throw new InvalidArgumentException('Unsupported Shopify proxy operation.'),
        };
    }

    /** @return array<string, mixed> */
    private function normalizeAvailabilityPayload(array $payload): array
    {
        if (! array_key_exists('from_date', $payload) && array_key_exists('date_from', $payload)) {
            $payload['from_date'] = $payload['date_from'];
        }
        if (! array_key_exists('to_date', $payload) && array_key_exists('date_to', $payload)) {
            $payload['to_date'] = $payload['date_to'];
        }
        if (! array_key_exists('from_date', $payload) && ! array_key_exists('to_date', $payload) && ! array_key_exists('date', $payload) && ! array_key_exists('dates', $payload)) {
            $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
            $today = new \DateTimeImmutable('today', $timezone);
            $payload['from_date'] = $today->format('d-m-Y');
            $payload['to_date'] = $today->modify('+30 days')->format('d-m-Y');
        }
        if (! array_key_exists('doctor_reference', $payload) && array_key_exists('doctor_id', $payload)) {
            $doctorId = (int) $payload['doctor_id'];
            $payload['doctor_reference'] = (string) (db_connect()->table('doctors')->select('doctor_code')->where('id', $doctorId)->where('status', 1)->where('online_booking', 1)->get()->getRow('doctor_code') ?? '__invalid__');
        }
        if (! array_key_exists('specialization_reference', $payload) && array_key_exists('specialization_id', $payload)) {
            $payload['specialization_reference'] = 'specialization-' . (int) $payload['specialization_id'];
        }
        if (! array_key_exists('service_reference', $payload) && array_key_exists('service_id', $payload)) {
            $payload['service_reference'] = 'service-' . (int) $payload['service_id'];
        }
        return $payload;
    }

    private function normalizeBookingPayload(array $payload): array
    {
        if (! array_key_exists('doctor_reference', $payload) && array_key_exists('doctor_id', $payload)) {
            $payload['doctor_reference'] = (string) (db_connect()->table('doctors')->select('doctor_code')->where('id', (int) $payload['doctor_id'])->where('status', 1)->where('online_booking', 1)->get(1)->getRow('doctor_code') ?? '');
        }
        if (! array_key_exists('service_reference', $payload) && array_key_exists('service_id', $payload)) {
            $payload['service_reference'] = 'service-' . (int) $payload['service_id'];
        }
        return $payload;
    }

    private function register(array $context, array $payload, array $credential): array
    {
        if ((string) $context['customer_id'] === '') throw new CoreApiException(422, 'SHOPIFY_CUSTOMER_REQUIRED', 'A logged-in Shopify customer is required.');
        if ((new ShopifyPatientIdentityLinkModel())->where('shopify_store_domain', $context['store_domain'])->where('shopify_customer_id', $context['customer_id'])->where('link_status', 'linked')->first() !== null) throw new CoreApiException(409, 'SHOPIFY_CUSTOMER_ALREADY_LINKED', 'The Shopify customer is already registered.');
        $result = (new PatientRegistrationService())->register($payload + ['clinic_id' => (int) $credential['clinic_id']], null, ['source_type' => 'shopify', 'source_reference' => $context['customer_id']]);
        (new ShopifyPatientIdentityLinkModel())->insert(['shopify_store_domain' => $context['store_domain'], 'shopify_customer_id' => $context['customer_id'], 'patient_id' => (int) $result['id'], 'email_snapshot' => trim((string) ($payload['email'] ?? '')), 'mobile_snapshot' => trim((string) ($payload['mobile_number'] ?? '')), 'link_status' => 'linked', 'matched_by' => 'proxy_registration', 'active_patient_key' => $result['registration_number'], 'linked_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('shopify.proxy_patient_registered', ['store_domain' => $context['store_domain'], 'patient_id' => (int) $result['id']]);
        return ['patient_reference' => (string) $result['registration_number']];
    }

    /** @return array<string, mixed> */
    private function credential(): array
    {
        $query = (new CoreApiCredentialModel())->where('status', 'active');
        $credential = $query->orderBy('id', 'ASC')->first();
        if ($credential === null) throw new CoreApiException(503, 'CORE_API_UNAVAILABLE', 'The ROOTINS Core API is not configured for this clinic.');
        unset($credential['secret_encrypted']);
        return $credential;
    }

    private function rateLimit(int $credentialId, int $limit): void
    {
        try { $result = (new CoreApiRateLimitService())->consume($credentialId, $limit); } catch (\Throwable) { throw new CoreApiException(503, 'RATE_LIMITER_UNAVAILABLE', 'The request could not be admitted safely.'); }
        if (! $result['allowed']) throw new CoreApiException(429, 'RATE_LIMITED', 'Too many Shopify proxy requests.', [], $result['retry_after']);
    }
}
