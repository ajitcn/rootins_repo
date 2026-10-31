<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyCoreApiProxyConfigurationModel;
use InvalidArgumentException;

final class ShopifyProxyConfigurationService
{
    private const OPERATIONS = ['appointments.availability', 'appointments.book', 'patients.register', 'prescriptions.upload', 'prescriptions.active', 'prescriptions.cart_status', 'orders.status', 'hair_analysis.submit', 'hair_analysis.status'];

    public function __construct(private readonly ?ShopifyCoreApiProxyConfigurationModel $model = null) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $row = $this->row();
        $store = self::normalizeStoreDomain((string) ($row['store_domain'] ?? 'rootins.myshopify.com'));
        $prefix = (string) ($row['proxy_prefix'] ?? 'apps');
        $subpath = (string) ($row['proxy_subpath'] ?? 'rootins');
        $backend = $this->normalizeBackendUrl((string) ($row['backend_url'] ?? 'https://rootins.newrootssoftware.in/public/rootins-proxy'));
        return ['configured' => $row !== null, 'app_name' => (string) ($row['app_name'] ?? 'Shopify Core API App Proxy'), 'store_domain' => $store, 'client_id_masked' => ! empty($row['client_id_encrypted']) ? 'Configured (server-side)' : 'Not configured', 'has_client_id' => ! empty($row['client_id_encrypted']), 'has_client_secret' => ! empty($row['client_secret_encrypted']), 'proxy_enabled' => (bool) ($row['enabled'] ?? false), 'proxy_prefix' => $prefix, 'proxy_subpath' => $subpath, 'proxy_backend_url' => $backend, 'proxy_allowed_operations' => $this->operations($row), 'proxy_rate_limit_per_minute' => max(1, (int) ($row['rate_limit_per_minute'] ?? 60)), 'storefront_proxy_url' => 'https://' . $store . '/' . $prefix . '/' . $subpath, 'backend_proxy_url' => $backend];
    }

    /** @return array<string, mixed> */
    public function runtime(): array
    {
        $row = $this->row();
        if ($row === null) throw new InvalidArgumentException('The Shopify Core API App Proxy is not configured.');
        return ['id' => (int) $row['id'], 'app_name' => (string) $row['app_name'], 'store_domain' => self::normalizeStoreDomain((string) $row['store_domain']), 'client_id' => $this->decrypt((string) $row['client_id_encrypted']), 'client_secret' => $this->decrypt((string) $row['client_secret_encrypted']), 'enabled' => (bool) $row['enabled'], 'allowed_operations' => $this->operations($row), 'rate_limit_per_minute' => max(1, (int) $row['rate_limit_per_minute'])];
    }

    /** @return array<string, mixed> */
    public function save(array $input, int $userId): array
    {
        $model = $this->model ?? new ShopifyCoreApiProxyConfigurationModel();
        $row = $this->row();
        $appName = trim((string) ($input['core_api_app_name'] ?? 'Shopify Core API App Proxy'));
        $store = self::normalizeStoreDomain((string) ($input['core_api_store_domain'] ?? ''));
        $prefix = trim((string) ($input['core_api_proxy_prefix'] ?? 'apps'));
        $subpath = trim((string) ($input['core_api_proxy_subpath'] ?? 'rootins'));
        $backend = $this->normalizeBackendUrl((string) ($input['core_api_backend_url'] ?? ''));
        $operations = array_values(array_intersect((array) ($input['allowed_core_api_operations'] ?? []), self::OPERATIONS));
        $limit = (int) ($input['proxy_rate_limit_per_minute'] ?? 60);
        if ($appName === '' || mb_strlen($appName) > 120) throw new InvalidArgumentException('Enter a valid Core API app name.');
        if (! self::isValidStoreDomain($store)) throw new InvalidArgumentException('Enter a valid Core API Shopify store domain.');
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $prefix) || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $subpath)) throw new InvalidArgumentException('Proxy prefix and subpath may contain lowercase letters, numbers, hyphens, and underscores only.');
        $parts = parse_url($backend);
        if (filter_var($backend, FILTER_VALIDATE_URL) === false || ! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['query'], $parts['fragment'], $parts['user'], $parts['pass'])) throw new InvalidArgumentException('Enter a public HTTPS Core API backend URL without credentials or query parameters.');
        if (rtrim((string) ($parts['path'] ?? ''), '/') !== '/public/rootins-proxy') throw new InvalidArgumentException('The Core API backend URL must end with /public/rootins-proxy.');
        if ($operations === []) throw new InvalidArgumentException('Select at least one Core API operation.');
        if ($limit < 1 || $limit > 10000) throw new InvalidArgumentException('Rate limit must be between 1 and 10000 requests per minute.');
        $data = ['app_name' => $appName, 'store_domain' => $store, 'proxy_prefix' => $prefix, 'proxy_subpath' => $subpath, 'backend_url' => $backend, 'enabled' => ! empty($input['core_api_proxy_enabled']) ? 1 : 0, 'allowed_operations' => json_encode($operations, JSON_UNESCAPED_SLASHES), 'rate_limit_per_minute' => $limit, 'updated_by' => $userId > 0 ? $userId : null, 'updated_at' => date('Y-m-d H:i:s')];
        $clientId = trim((string) ($input['core_api_client_id'] ?? ''));
        $clientSecret = trim((string) ($input['core_api_client_secret'] ?? ''));
        if ($clientId !== '') { if (strlen($clientId) > 4096) throw new InvalidArgumentException('The Core API client ID is too long.'); $data['client_id_encrypted'] = $this->encrypt($clientId); } elseif ($row === null) throw new InvalidArgumentException('The Core API client ID is required when creating the Core API App Proxy configuration.');
        if ($clientSecret !== '') { if (strlen($clientSecret) < 16 || strlen($clientSecret) > 4096) throw new InvalidArgumentException('The Core API client secret must be between 16 and 4096 characters.'); $data['client_secret_encrypted'] = $this->encrypt($clientSecret); } elseif ($row === null) throw new InvalidArgumentException('The Core API client secret is required when creating the Core API App Proxy configuration.');
        if ($row === null) { $data['created_by'] = $userId > 0 ? $userId : null; $data['created_at'] = date('Y-m-d H:i:s'); $model->insert($data); } else $model->update((int) $row['id'], $data);
        service('audit')->record('shopify.core_api_proxy_configuration_updated', ['enabled' => ! empty($input['core_api_proxy_enabled']), 'operations' => $operations]);
        return $this->summary();
    }

    public function rotateSecret(string $secret, int $userId): array
    {
        $row = $this->row(); $secret = trim($secret);
        if ($row === null) throw new InvalidArgumentException('Configure the Core API App Proxy before rotating its secret.');
        if (strlen($secret) < 16 || strlen($secret) > 4096) throw new InvalidArgumentException('The Core API client secret must be between 16 and 4096 characters.');
        ($this->model ?? new ShopifyCoreApiProxyConfigurationModel())->update((int) $row['id'], ['client_secret_encrypted' => $this->encrypt($secret), 'updated_by' => $userId > 0 ? $userId : null, 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('shopify.core_api_proxy_secret_rotated', ['configuration_id' => (int) $row['id']]);
        return $this->summary();
    }

    /** @return array<string, mixed> */
    public function setEnabled(bool $enabled, int $userId): array
    {
        $row = $this->row();
        if ($row === null) throw new InvalidArgumentException('Configure the Core API App Proxy before changing its status.');
        ($this->model ?? new ShopifyCoreApiProxyConfigurationModel())->update((int) $row['id'], ['enabled' => $enabled ? 1 : 0, 'updated_by' => $userId > 0 ? $userId : null, 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->record($enabled ? 'shopify.core_api_proxy_enabled' : 'shopify.core_api_proxy_disabled', ['configuration_id' => (int) $row['id']]);
        return $this->summary();
    }

    private function row(): ?array { return ($this->model ?? new ShopifyCoreApiProxyConfigurationModel())->orderBy('id', 'DESC')->first(); }
    /** @return list<string> */ private function operations(?array $row): array { $values = json_decode((string) ($row['allowed_operations'] ?? ''), true); return array_values(array_intersect(is_array($values) ? $values : self::OPERATIONS, self::OPERATIONS)); }
    private function encrypt(string $value): string { return base64_encode(service('encrypter')->encrypt($value, config('Encryption')->key)); }
    private function decrypt(string $value): string { try { $decoded = base64_decode($value, true); $plain = $decoded === false ? false : service('encrypter')->decrypt($decoded, config('Encryption')->key); return is_string($plain) ? $plain : ''; } catch (\Throwable) { return ''; } }

    private function normalizeBackendUrl(string $backend): string
    {
        return rtrim(trim($backend), '/');
    }

    public static function normalizeStoreDomain(string $store): string
    {
        return strtolower(rtrim(trim($store), '.'));
    }

    private static function isValidStoreDomain(string $store): bool
    {
        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $store) === 1;
    }
}
