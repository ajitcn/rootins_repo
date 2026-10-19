<?php

namespace App\Services\Shopify;

use Throwable;

final class ShopifyProxyCredentialVerificationService
{
    private const API_VERSION = '2025-01';

    public function __construct(private readonly ?object $httpClient = null) {}

    /** @param array<string, mixed> $runtime @return array{configuration:string,credentials:string,store:string} */
    public function verify(array $runtime): array
    {
        $store = ShopifyProxyConfigurationService::normalizeStoreDomain((string) ($runtime['store_domain'] ?? ''));
        $clientId = trim((string) ($runtime['client_id'] ?? ''));
        $clientSecret = trim((string) ($runtime['client_secret'] ?? ''));
        if ($store === '' || $clientId === '' || $clientSecret === '') {
            throw new ShopifyProxyCredentialVerificationException('The Shopify Core API Proxy configuration is incomplete.', 'SHOPIFY_PROXY_CONFIGURATION_MISSING', 422);
        }

        $http = $this->httpClient ?? service('curlrequest');
        try {
            $tokenResponse = $http->request('POST', 'https://' . $store . '/admin/oauth/access_token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            throw new ShopifyProxyCredentialVerificationException('Shopify credentials could not be verified because the store could not be reached.');
        }

        $tokenStatus = (int) $tokenResponse->getStatusCode();
        $tokenBody = json_decode((string) $tokenResponse->getBody(), true);
        if ($tokenStatus === 403) {
            throw new ShopifyProxyCredentialVerificationException('The configured Shopify store scope could not be verified.', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 403);
        }
        if ($tokenStatus === 401 || $tokenStatus === 400 || ! is_array($tokenBody) || ! is_string($tokenBody['access_token'] ?? null) || trim($tokenBody['access_token']) === '') {
            throw new ShopifyProxyCredentialVerificationException('The Shopify Core API app credentials could not be verified.', 'SHOPIFY_PROXY_CREDENTIALS_INVALID', 422);
        }
        if ($tokenStatus < 200 || $tokenStatus >= 300) {
            throw new ShopifyProxyCredentialVerificationException('Shopify credential verification could not be completed.');
        }

        try {
            $shopResponse = $http->request('GET', 'https://' . $store . '/admin/api/' . self::API_VERSION . '/shop.json', [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Shopify-Access-Token' => (string) $tokenBody['access_token'],
                ],
                'timeout' => 10,
                'http_errors' => false,
            ]);
        } catch (Throwable) {
            throw new ShopifyProxyCredentialVerificationException('Shopify store scope could not be verified because the store could not be reached.');
        }

        $shopStatus = (int) $shopResponse->getStatusCode();
        $shopBody = json_decode((string) $shopResponse->getBody(), true);
        if ($shopStatus === 401) {
            throw new ShopifyProxyCredentialVerificationException('The Shopify Core API app credentials could not be verified.', 'SHOPIFY_PROXY_CREDENTIALS_INVALID', 422);
        }
        if ($shopStatus === 403) {
            throw new ShopifyProxyCredentialVerificationException('The configured Shopify store scope could not be verified.', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 403);
        }
        if ($shopStatus < 200 || $shopStatus >= 300 || ! is_array($shopBody)) {
            throw new ShopifyProxyCredentialVerificationException('Shopify store scope verification could not be completed.');
        }

        $remoteDomain = ShopifyProxyConfigurationService::normalizeStoreDomain((string) ($shopBody['shop']['myshopify_domain'] ?? $shopBody['shop']['domain'] ?? ''));
        if ($remoteDomain === '' || $remoteDomain !== $store) {
            throw new ShopifyProxyCredentialVerificationException('The configured Shopify store scope could not be verified.', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 403);
        }

        return ['configuration' => 'ready', 'credentials' => 'verified', 'store' => $store];
    }
}
