<?php

namespace App\Services\Shopify;

use CodeIgniter\HTTP\ResponseInterface;

final class ShopifyClient
{
    private const TOKEN_CACHE_PREFIX = 'shopify.client_credentials.';
    private const TOKEN_REFRESH_MARGIN = 60;

    public function __construct(
        private readonly ShopifyConfiguration $configuration,
        private readonly ?ShopifyOperationIdempotencyService $idempotency = null,
        private readonly ?object $httpClient = null,
        private readonly ?object $cacheStore = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function request(string $method, string $path, array $payload = [], ?string $operationKey = null): array
    {
        $this->assertConfigured();
        $operationKey = $operationKey !== null ? trim($operationKey) : null;
        if ($operationKey !== null && $operationKey !== '') {
            $idempotency = $this->idempotency ?? new ShopifyOperationIdempotencyService();
            if (! $idempotency->claim($operationKey, strtoupper($method) . ' ' . $path)) throw new ShopifyIdempotencyException('This Shopify operation has already been submitted.');
        }

        try {
            $response = $this->sendApiRequest($method, $path, $payload, false, $operationKey);
            if ($response->getStatusCode() === 401) {
                $this->forgetCachedToken();
                $response = $this->sendApiRequest($method, $path, $payload, true, $operationKey);
            }
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw $this->providerExceptionForStatus($status, $path);
            }
            $body = json_decode((string) $response->getBody(), true);
            $result = is_array($body) ? $body : [];
            if ($operationKey !== null && $operationKey !== '') ($this->idempotency ?? new ShopifyOperationIdempotencyService())->complete($operationKey, hash('sha256', json_encode($result, JSON_UNESCAPED_SLASHES) ?: ''));
            return $result;
        } catch (ShopifyProviderException $exception) {
            if ($operationKey !== null && $operationKey !== '') ($this->idempotency ?? new ShopifyOperationIdempotencyService())->fail($operationKey, $exception->getMessage());
            throw $exception;
        } catch (\Throwable $exception) {
            if ($operationKey !== null && $operationKey !== '') ($this->idempotency ?? new ShopifyOperationIdempotencyService())->fail($operationKey, 'Provider request failed.');
            throw new ShopifyProviderException('Shopify connection failed.', null, 'network_error', 'network_error', $exception);
        }
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        $shop = $this->request('GET', '/shop.json');
        $remoteDomain = strtolower(trim((string) ($shop['shop']['myshopify_domain'] ?? $shop['shop']['domain'] ?? '')));
        if ($remoteDomain !== '' && $remoteDomain !== strtolower($this->normalizedDomain())) {
            throw new ShopifyProviderException('Shopify store did not match the configured store domain.', 200, 'store_organization_mismatch', 'organization_mismatch');
        }
        $scopes = $this->request('GET', '/oauth/access_scopes.json');
        return ['shop' => $shop['shop'] ?? [], 'access_scopes' => $scopes['access_scopes'] ?? []];
    }

    /** @return array<string, mixed> */
    public function graphql(string $query, array $variables = []): array
    {
        $this->assertConfigured();

        try {
            $response = $this->sendApiRequest('POST', '/graphql.json', ['query' => $query, 'variables' => $variables], false, null);
            if ($response->getStatusCode() === 401) {
                $this->forgetCachedToken();
                $response = $this->sendApiRequest('POST', '/graphql.json', ['query' => $query, 'variables' => $variables], true, null);
            }

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw $this->providerExceptionForStatus($status, '/graphql.json');
            }

            $body = json_decode((string) $response->getBody(), true);
            if (! is_array($body)) {
                throw new ShopifyProviderException('Shopify returned an invalid GraphQL response.', $status, 'graphql_error', 'provider_http');
            }

            $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
            if ($errors !== []) {
                $message = strtolower((string) ($errors[0]['message'] ?? ''));
                if (str_contains($message, 'scope') || str_contains($message, 'access denied') || str_contains($message, 'permission')) {
                    throw new ShopifyProviderException('Shopify customer access is not permitted.', 403, 'missing_scope', 'permission_denied');
                }
                throw new ShopifyProviderException('Shopify returned a GraphQL error.', $status, 'graphql_error', 'provider_http');
            }

            return is_array($body['data'] ?? null) ? $body['data'] : [];
        } catch (ShopifyProviderException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ShopifyProviderException('Shopify connection failed.', null, 'network_error', 'network_error', $exception);
        }
    }

    /** @return array{status:string,expires_at:?int} */
    public function tokenStatus(): array
    {
        $cached = $this->readCachedToken();
        if ($cached === null) return ['status' => 'not_cached', 'expires_at' => null];
        return ['status' => $cached['expires_at'] > time() + self::TOKEN_REFRESH_MARGIN ? 'valid' : 'refresh_due', 'expires_at' => $cached['expires_at']];
    }

    private function sendApiRequest(string $method, string $path, array $payload, bool $forceRefresh, ?string $operationKey): ResponseInterface
    {
        $token = $this->accessToken($forceRefresh);
        $options = ['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-Shopify-Access-Token' => $token], 'timeout' => 10, 'http_errors' => false];
        if ($operationKey !== null && $operationKey !== '') $options['headers']['Idempotency-Key'] = $operationKey;
        if ($payload !== []) $options['json'] = $payload;
        try {
            /** @var ResponseInterface $response */
            return ($this->httpClient ?? service('curlrequest'))->request(strtoupper($method), $this->buildUrl($path), $options);
        } catch (\Throwable $exception) {
            $timeout = str_contains(strtolower(get_class($exception)), 'timeout') || str_contains(strtolower(get_class($exception)), 'connect');
            throw new ShopifyProviderException(
                $timeout ? 'Shopify connection timed out or could not be reached.' : 'Shopify connection failed.',
                null,
                $timeout ? 'network_timeout' : 'network_error',
                $timeout ? 'network_timeout' : 'network_error',
                $exception,
            );
        }
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = $this->readCachedToken();
            if ($cached !== null && $cached['expires_at'] > time() + self::TOKEN_REFRESH_MARGIN) return $cached['access_token'];
        }
        try {
            $response = ($this->httpClient ?? service('curlrequest'))->request('POST', $this->tokenUrl(), ['form_params' => ['grant_type' => 'client_credentials', 'client_id' => $this->configuration->clientId(), 'client_secret' => $this->configuration->clientSecret()], 'headers' => ['Accept' => 'application/json'], 'timeout' => 10, 'http_errors' => false]);
        } catch (\Throwable $exception) {
            throw new ShopifyProviderException('Shopify connection timed out or could not be reached.', null, 'network_timeout', 'network_timeout', $exception);
        }
        $body = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();
        if ($status === 401) throw new ShopifyProviderException('Shopify credentials were rejected.', 401, 'invalid_credentials', 'invalid_credentials');
        if ($status === 403) throw new ShopifyProviderException('Shopify app or store access was denied.', 403, 'organization_mismatch', 'organization_mismatch');
        if ($status < 200 || $status >= 300 || ! is_array($body) || ! is_string($body['access_token'] ?? null) || $body['access_token'] === '') throw $this->providerExceptionForStatus($status);
        $expiresIn = max(60, (int) ($body['expires_in'] ?? 3600));
        $expiresAt = time() + $expiresIn;
        $this->writeCachedToken(['access_token' => $body['access_token'], 'expires_at' => $expiresAt], max(60, $expiresIn - self::TOKEN_REFRESH_MARGIN));
        return $body['access_token'];
    }

    /** @return array{access_token:string,expires_at:int}|null */
    private function readCachedToken(): ?array
    {
        $value = ($this->cacheStore ?? cache())->get($this->cacheKey());
        return is_array($value) && isset($value['access_token'], $value['expires_at']) ? ['access_token' => (string) $value['access_token'], 'expires_at' => (int) $value['expires_at']] : null;
    }

    /** @param array{access_token:string,expires_at:int} $value */
    private function writeCachedToken(array $value, int $ttl): void
    {
        ($this->cacheStore ?? cache())->save($this->cacheKey(), $value, $ttl);
    }

    private function forgetCachedToken(): void
    {
        ($this->cacheStore ?? cache())->delete($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return self::TOKEN_CACHE_PREFIX . hash('sha256', strtolower($this->configuration->storeDomain()) . '|' . $this->configuration->clientId());
    }

    private function assertConfigured(): void
    {
        if (! $this->configuration->isConfigured()) throw new ShopifyConfigurationException('Shopify integration is not configured.', 'missing_configuration');
        if (! preg_match('/^(?:unstable|\d{4}-\d{2})$/', $this->configuration->apiVersion())) throw new ShopifyConfigurationException('Shopify API version is invalid.', 'invalid_api_version');
    }

    private function tokenUrl(): string
    {
        return 'https://' . $this->normalizedDomain() . '/admin/oauth/access_token';
    }

    private function buildUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if (str_starts_with($path, '/oauth/')) return 'https://' . $this->normalizedDomain() . '/admin' . $path;
        return 'https://' . $this->normalizedDomain() . '/admin/api/' . trim($this->configuration->apiVersion(), '/') . $path;
    }

    private function normalizedDomain(): string
    {
        $domain = strtolower(trim($this->configuration->storeDomain()));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        if ($domain === '' || ! preg_match('/^[A-Za-z0-9.-]+$/', $domain)) throw new ShopifyConfigurationException('Shopify integration configuration is invalid.');
        return $domain;
    }

    private function providerExceptionForStatus(int $status, ?string $path = null): ShopifyProviderException
    {
        $organizationRestriction = $status === 403 && $path !== null && str_ends_with('/' . ltrim($path, '/'), '/shop.json');
        $errorCode = match ($status) {
            401 => 'shopify_http_401',
            403 => $organizationRestriction ? 'shopify_organization_restriction' : 'shopify_http_403',
            404 => 'shopify_http_404',
            429 => 'shopify_http_429',
            default => $status >= 500 ? 'shopify_http_5xx' : 'shopify_http_error',
        };

        return new ShopifyProviderException(
            $organizationRestriction
                ? 'Shopify restricted this client-credentials app to its organization store.'
                : 'Shopify returned an unsuccessful response.',
            $status,
            $errorCode,
            $organizationRestriction ? 'organization_mismatch' : 'provider_http',
        );
    }
}
