<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyConfigurationModel;
use App\Services\Shopify\ShopifyConfigurationException;
use InvalidArgumentException;
use Throwable;

final class ShopifyConfigurationService
{
    public function __construct(private readonly ?ShopifyConfigurationModel $model = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function safeList(): array
    {
        if (! db_connect()->tableExists('shopify_configurations')) {
            return [];
        }
        $rows = ($this->model ?? new ShopifyConfigurationModel())
            ->orderBy('shopify_configurations.id', 'DESC')
            ->findAll();

        return array_map(fn (array $row): array => $this->safeRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function safeFind(int $id): ?array
    {
        if (! db_connect()->tableExists('shopify_configurations')) {
            return null;
        }
        $row = ($this->model ?? new ShopifyConfigurationModel())
            ->where('shopify_configurations.id', $id)
            ->first();

        return $row === null ? null : $this->safeRow($row);
    }

    /** @return array<string, mixed> */
    public function save(array $input, ?int $id, int $userId): array
    {
        $existing = $id !== null ? ($this->model ?? new ShopifyConfigurationModel())->find($id) : null;
        if ($id !== null && $existing === null) {
            throw new InvalidArgumentException('Shopify configuration was not found.');
        }

        $data = $this->validated($input, $existing);
        $now = date('Y-m-d H:i:s');
        $data['updated_by'] = $userId > 0 ? $userId : null;
        $data['updated_at'] = $now;

        $model = $this->model ?? new ShopifyConfigurationModel();
        if ($existing === null) {
            $data['created_by'] = $userId > 0 ? $userId : null;
            $data['created_at'] = $now;
            $data['status'] = 1;
            if (! $model->insert($data)) {
                throw new \RuntimeException('Shopify configuration could not be saved.');
            }
            $id = (int) $model->getInsertID();
        } elseif (! $model->update((int) $existing['id'], $data)) {
            throw new \RuntimeException('Shopify configuration could not be updated.');
        }

        $saved = $this->safeFind((int) $id);
        if ($saved === null) {
            throw new \RuntimeException('Shopify configuration could not be loaded after saving.');
        }

        return $saved;
    }

    public function rotateSecret(int $id, string $secretType, string $secretValue, int $userId): array
    {
        $row = ($this->model ?? new ShopifyConfigurationModel())->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Shopify configuration was not found.');
        }
        $field = match ($secretType) {
            'client_id' => 'client_id_encrypted',
            'client_secret' => 'client_secret_encrypted',
            'webhook_secret' => 'webhook_secret_encrypted',
            'encryption_key' => 'encryption_key_encrypted',
            default => throw new InvalidArgumentException('Select a valid Shopify secret to rotate.'),
        };
        $secretValue = trim($secretValue);
        if (strlen($secretValue) < 16 || strlen($secretValue) > 4096) {
            throw new InvalidArgumentException('The replacement credential must be between 16 and 4096 characters.');
        }

        $model = $this->model ?? new ShopifyConfigurationModel();
        if (! $model->update($id, [$field => $this->encrypt($secretValue), 'updated_by' => $userId > 0 ? $userId : null, 'updated_at' => date('Y-m-d H:i:s')])) {
            throw new \RuntimeException('Shopify secret could not be rotated.');
        }

        return $this->safeFind($id) ?? [];
    }

    public function runtime(?int $id = null): ShopifyConfiguration
    {
        if (! db_connect()->tableExists('shopify_configurations')) {
            $environment = new ShopifyConfiguration(self::environmentValues(), 'environment');
            if (! $environment->isConfigured()) {
                throw new ShopifyConfigurationException('Shopify configuration is missing.', 'missing_configuration');
            }

            return $environment;
        }
        $model = $this->model ?? new ShopifyConfigurationModel();
        $query = $model->where('status', 1);
        if ($id !== null) {
            $query->where('id', $id);
        } else {
            $query->orderBy('id', 'DESC');
        }
        $row = $query->first();

        if ($row === null) {
            throw new ShopifyConfigurationException('Shopify configuration is missing.', 'missing_configuration');
        }

        return new ShopifyConfiguration($this->decryptedValues($row), 'database');
    }

    /** @return array<string, mixed> */
    private function validated(array $input, ?array $existing): array
    {
        $domain = $this->normalizeStoreDomain((string) ($input['store_domain'] ?? ''));
        $version = trim((string) ($input['api_version'] ?? ''));
        if (! preg_match('/^(?:unstable|\d{4}-\d{2})$/', $version)) {
            throw new InvalidArgumentException('Enter a valid Shopify API version such as 2025-01.');
        }
        $clinicInput = trim((string) ($input['clinic_id'] ?? ($existing['legacy_clinic_id'] ?? '')));
        if (! ctype_digit($clinicInput) || (int) $clinicInput < 1) {
            throw new InvalidArgumentException('Enter a clinic ID for the Shopify integration.');
        }
        $clinicId = (int) $clinicInput;
        $clinic = db_connect()->table('clinics')->select('id')->where('id', $clinicId)->where('status', 1)->get()->getRowArray();
        if ($clinic === null) {
            throw new InvalidArgumentException('The configured clinic does not exist or is inactive.');
        }
        $appUrl = trim((string) ($input['app_url'] ?? ''));
        if (filter_var($appUrl, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Enter a valid Shopify app URL.');
        }
        $appHost = strtolower(trim((string) parse_url($appUrl, PHP_URL_HOST)));
        if ($appHost === 'localhost' || $appHost === '127.0.0.1' || $appHost === '::1' || filter_var($appHost, FILTER_VALIDATE_IP) !== false && filter_var($appHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Shopify webhooks require a publicly reachable app URL.');
        }
        $data = [
            'legacy_clinic_id' => $clinicId,
            'store_domain' => $domain,
            'api_version' => $version,
            'app_url' => $appUrl,
            'auto_create_patients' => ! empty($input['auto_create_patients']) ? 1 : 0,
        ];
        foreach ([
            'client_id' => 'client_id_encrypted',
            'client_secret' => 'client_secret_encrypted',
            'webhook_secret' => 'webhook_secret_encrypted',
            'encryption_key' => 'encryption_key_encrypted',
        ] as $inputField => $dbField) {
            $secret = trim((string) ($input[$inputField] ?? ''));
            if ($secret !== '') {
                $minimumLength = $inputField === 'client_id' ? 1 : 16;
                if (strlen($secret) < $minimumLength || strlen($secret) > 4096) {
                    throw new InvalidArgumentException($inputField === 'client_id' ? 'Shopify client ID must be between 1 and 4096 characters.' : 'Shopify credential fields must be between 16 and 4096 characters.');
                }
                $data[$dbField] = $this->encrypt($secret);
            } elseif ($existing === null) {
                throw new InvalidArgumentException('All Shopify secret fields are required when creating a configuration.');
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function safeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'scope' => 'application',
            'clinic_id' => (int) ($row['legacy_clinic_id'] ?? 0),
            'store_domain' => (string) $row['store_domain'],
            'client_id' => $this->decrypt((string) ($row['client_id_encrypted'] ?? '')),
            'api_version' => (string) $row['api_version'],
            'app_url' => (string) $row['app_url'],
            'auto_create_patients' => (bool) $row['auto_create_patients'],
            'status' => (int) $row['status'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'has_webhook_secret' => (string) ($row['webhook_secret_encrypted'] ?? '') !== '',
            'has_encryption_key' => (string) ($row['encryption_key_encrypted'] ?? '') !== '',
            'client_id_masked' => (string) ($row['client_id_encrypted'] ?? '') !== '' ? '••••••••' : '',
            'has_client_id' => (string) ($row['client_id_encrypted'] ?? '') !== '',
            'has_client_secret' => (string) ($row['client_secret_encrypted'] ?? '') !== '',
            'webhook_secret_masked' => (string) ($row['webhook_secret_encrypted'] ?? '') !== '' ? '••••••••' : '',
            'encryption_key_masked' => (string) ($row['encryption_key_encrypted'] ?? '') !== '' ? '••••••••' : '',
        ];
    }

    /** @return array<string, string> */
    private function decryptedValues(array $row): array
    {
        return [
            'store_domain' => (string) $row['store_domain'],
            'client_id' => $this->decrypt((string) ($row['client_id_encrypted'] ?? '')),
            'client_secret' => $this->decrypt((string) ($row['client_secret_encrypted'] ?? '')),
            'api_version' => (string) $row['api_version'],
            'webhook_secret' => $this->decrypt((string) $row['webhook_secret_encrypted']),
            'app_url' => (string) $row['app_url'],
            'encryption_key' => $this->decrypt((string) $row['encryption_key_encrypted']),
            'legacy_clinic_id' => (string) ($row['legacy_clinic_id'] ?? ''),
            'auto_create_patients' => (string) $row['auto_create_patients'],
        ];
    }

    private function encrypt(string $value): string
    {
        $key = (string) config('Encryption')->key;
        if ($key === '') {
            throw new \RuntimeException('Application encryption is not configured.');
        }

        return base64_encode(service('encrypter')->encrypt($value, $key));
    }

    private function decrypt(string $value): string
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

    private function normalizeStoreDomain(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            throw new InvalidArgumentException('Enter the Shopify store domain.');
        }
        if (preg_match('#^https?://#i', $value)) {
            $parsed = parse_url($value);
            $value = is_array($parsed) ? (string) ($parsed['host'] ?? '') : '';
        } else {
            $value = preg_replace('#^//#', '', $value) ?? $value;
            $value = (string) (explode('/', $value, 2)[0] ?? $value);
        }
        $value = rtrim(trim($value), '.');
        if (! preg_match('/^(?=.{3,255}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $value) || ! str_ends_with($value, '.myshopify.com')) {
            throw new InvalidArgumentException('Enter a valid Shopify store domain such as rootins.myshopify.com.');
        }

        return $value;
    }
}
