<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyConfigurationModel;
use Throwable;

final class ShopifyConfiguration
{
    /** @param array<string, string>|null $values */
    public function __construct(?array $values = null, ?string $source = null)
    {
        $values ??= self::storedValues();
        $this->source = $source ?? (string) ($values['__source'] ?? 'environment');
        unset($values['__source']);
        $this->values = $values;
    }

    /** @var array<string, string> */
    private readonly array $values;
    private readonly string $source;

    public function source(): string
    {
        return $this->source;
    }

    public function storeDomain(): string
    {
        return trim((string) ($this->values['store_domain'] ?? env('SHOPIFY_STORE_DOMAIN', '')));
    }

    public function clientId(): string
    {
        return trim((string) ($this->values['client_id'] ?? env('SHOPIFY_CLIENT_ID', '')));
    }

    public function clientSecret(): string
    {
        return trim((string) ($this->values['client_secret'] ?? env('SHOPIFY_CLIENT_SECRET', '')));
    }

    public function apiVersion(): string
    {
        return trim((string) ($this->values['api_version'] ?? env('SHOPIFY_API_VERSION', '')));
    }

    public function webhookSecret(): string
    {
        return trim((string) ($this->values['webhook_secret'] ?? env('SHOPIFY_WEBHOOK_SECRET', '')));
    }

    public function appUrl(): string
    {
        return trim((string) ($this->values['app_url'] ?? env('SHOPIFY_APP_URL', '')));
    }


    public function encryptionKey(): string
    {
        return trim((string) ($this->values['encryption_key'] ?? env('SHOPIFY_ENCRYPTION_KEY', '')));
    }

    /** Legacy compatibility only; new application-wide configurations do not set this value. */
    public function clinicId(): ?int
    {
        $value = trim((string) ($this->values['legacy_clinic_id'] ?? env('SHOPIFY_CLINIC_ID', '')));

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** @return array{status:string,id:?int,name:?string} */
    public function clinicStatus(): array
    {
        $id = $this->clinicId();
        if ($id === null) return ['status' => 'missing', 'id' => null, 'name' => null];
        try {
            $clinic = db_connect()->table('clinics')->select('id, clinic_name')->where('id', $id)->where('status', 1)->get()->getRowArray();
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'id' => $id, 'name' => null];
        }
        return $clinic === null
            ? ['status' => 'invalid', 'id' => $id, 'name' => null]
            : ['status' => 'active', 'id' => $id, 'name' => (string) $clinic['clinic_name']];
    }

    public function allowAutomaticPatientCreation(): bool
    {
        return filter_var(
            $this->values['auto_create_patients'] ?? env('SHOPIFY_AUTO_CREATE_PATIENTS', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function isConfigured(): bool
    {
        return $this->storeDomain() !== ''
            && $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->apiVersion() !== ''
            && $this->webhookSecret() !== ''
            && $this->appUrl() !== ''
            && $this->encryptionKey() !== '';
    }

    /**
     * Return readiness information without exposing credentials or secrets.
     *
     * @return array<string, mixed>
     */
    public function safeSummary(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'source' => $this->source(),
            'store_domain' => $this->storeDomain(),
            'api_version' => $this->apiVersion(),
            'app_url' => $this->appUrl(),
            'automatic_patient_creation' => $this->allowAutomaticPatientCreation(),
            'clinic' => $this->clinicStatus(),
            'has_client_id' => $this->clientId() !== '',
            'has_client_secret' => $this->clientSecret() !== '',
            'has_webhook_secret' => $this->webhookSecret() !== '',
            'has_encryption_key' => $this->encryptionKey() !== '',
            'client_id_masked' => $this->clientId() !== '' ? '••••••••' : '',
            'webhook_secret_masked' => $this->webhookSecret() !== '' ? '••••••••' : '',
            'encryption_key_masked' => $this->encryptionKey() !== '' ? '••••••••' : '',
        ];
    }

    /** @return array<string, string> */
    private static function storedValues(): array
    {
        try {
            $db = db_connect();
            if (! $db->tableExists('shopify_configurations')) {
                return self::environmentValues();
            }
            $row = (new ShopifyConfigurationModel())->where('status', 1)->orderBy('id', 'DESC')->first();
            if ($row === null) {
                return self::environmentValues();
            }

            return [
                'store_domain' => (string) $row['store_domain'],
                'client_id' => self::decryptStored((string) ($row['client_id_encrypted'] ?? '')),
                'client_secret' => self::decryptStored((string) ($row['client_secret_encrypted'] ?? '')),
                'api_version' => (string) $row['api_version'],
                'webhook_secret' => self::decryptStored((string) $row['webhook_secret_encrypted']),
                'app_url' => (string) $row['app_url'],
                'encryption_key' => self::decryptStored((string) $row['encryption_key_encrypted']),
                'legacy_clinic_id' => (string) ($row['legacy_clinic_id'] ?? ''),
                'auto_create_patients' => (string) $row['auto_create_patients'],
                '__source' => 'database',
            ];
        } catch (Throwable) {
            return self::environmentValues();
        }
    }

    /** @return array<string, string> */
    private static function environmentValues(): array
    {
        return [
            'store_domain' => (string) env('SHOPIFY_STORE_DOMAIN', ''),
            'client_id' => (string) env('SHOPIFY_CLIENT_ID', ''),
            'client_secret' => (string) env('SHOPIFY_CLIENT_SECRET', ''),
            'api_version' => (string) env('SHOPIFY_API_VERSION', ''),
            'webhook_secret' => (string) env('SHOPIFY_WEBHOOK_SECRET', ''),
            'app_url' => (string) env('SHOPIFY_APP_URL', ''),
            'encryption_key' => (string) env('SHOPIFY_ENCRYPTION_KEY', ''),
            'legacy_clinic_id' => (string) env('SHOPIFY_CLINIC_ID', ''),
            'auto_create_patients' => (string) env('SHOPIFY_AUTO_CREATE_PATIENTS', '0'),
            '__source' => 'environment',
        ];
    }

    private static function decryptStored(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            $decoded = base64_decode($value, true);
            $plain = $decoded === false ? false : service('encrypter')->decrypt($decoded, (string) config('Encryption')->key);

            return is_string($plain) ? $plain : '';
        } catch (Throwable) {
            return '';
        }
    }
}
