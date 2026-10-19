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

final class ShopifyProxyService
{
    private const OPERATIONS = ['appointments.availability', 'appointments.book', 'patients.register', 'prescriptions.upload'];

    public function verify(string $operation, array $query, string $rawQuery): array
    {
        $configuration = (new ShopifyProxyConfigurationService())->runtime();
        if (! $configuration['enabled']) throw new InvalidArgumentException('Shopify Core API App Proxy is disabled.');
        if (! in_array($operation, $configuration['allowed_operations'], true)) throw new InvalidArgumentException('This Shopify Core API App Proxy operation is disabled.');
        $shop = strtolower(trim((string) ($query['shop'] ?? '')));
        if ($shop === '' || $shop !== $configuration['store_domain'] || ! preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $shop)) throw new InvalidArgumentException('The Shopify Core API app store scope is invalid.');
        $timestamp = (string) ($query['timestamp'] ?? '');
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) throw new InvalidArgumentException('The Shopify proxy request timestamp is invalid or expired.');
        $provided = (string) ($query['signature'] ?? '');
        if ($provided === '' || $configuration['client_secret'] === '') throw new InvalidArgumentException('The Shopify Core API app proxy signature is unavailable.');
        parse_str($rawQuery, $parts);
        unset($parts['signature']);
        ksort($parts);
        $canonical = http_build_query($parts, '', '&', PHP_QUERY_RFC3986);
        $expected = hash_hmac('sha256', $canonical, $configuration['client_secret']);
        if (! hash_equals($expected, $provided)) throw new InvalidArgumentException('The Shopify proxy signature is invalid.');
        return ['store_domain' => $shop, 'customer_id' => trim((string) ($query['logged_in_customer_id'] ?? '')), 'configuration' => $configuration];
    }

    /** @return array<string, mixed> */
    public function execute(string $operation, array $context, array $payload, ?UploadedFile $file = null): array
    {
        $configuration = $context['configuration'];
        $credential = $this->credential();
        $this->rateLimit((int) $credential['id'], (int) $configuration['rate_limit_per_minute']);
        $customerId = (string) $context['customer_id'];
        $link = $customerId !== '' ? (new ShopifyPatientIdentityLinkModel())->where('shopify_store_domain', $context['store_domain'])->where('shopify_customer_id', $customerId)->where('link_status', 'linked')->first() : null;

        if ($operation !== 'patients.register' && $link === null) throw new CoreApiException(403, 'SHOPIFY_CUSTOMER_UNLINKED', 'The Shopify customer is not linked to a ROOTINS patient.');
        if ($link !== null) {
            $payload['patient_reference'] = (string) db_connect()->table('patients')->select('registration_number')->where('id', (int) $link['patient_id'])->where('clinic_id', (int) $credential['clinic_id'])->where('status', 1)->get()->getRow('registration_number');
            if ($payload['patient_reference'] === '') throw new CoreApiException(403, 'SHOPIFY_SCOPE_INVALID', 'The Shopify customer is outside the configured clinic scope.');
            $payload['clinic_id'] = (int) $credential['clinic_id'];
        }
        return match ($operation) {
            'appointments.availability' => (new CoreApiAppointmentService())->availability($credential, $payload + ['clinic_id' => (int) $credential['clinic_id']]),
            'appointments.book' => (new CoreApiAppointmentService())->book($credential, $payload + ['clinic_id' => (int) $credential['clinic_id']]),
            'prescriptions.upload' => $file === null ? throw new CoreApiException(422, 'FILE_REQUIRED', 'A prescription file is required.') : (new CoreApiPrescriptionUploadService())->upload($credential, $payload + ['clinic_id' => (int) $credential['clinic_id']], $file),
            'patients.register' => $this->register($context, $payload, $credential),
            default => throw new InvalidArgumentException('Unsupported Shopify proxy operation.'),
        };
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
