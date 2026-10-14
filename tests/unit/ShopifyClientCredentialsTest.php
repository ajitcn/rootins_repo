<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyClient;
use App\Services\Shopify\ShopifyConfiguration;
use CodeIgniter\HTTP\ResponseInterface;
use PHPUnit\Framework\TestCase;

final class ShopifyClientCredentialsTest extends TestCase
{
    public function testTokenExchangeIsCachedAndUsesClientCredentials(): void
    {
        $cache = new FakeShopifyCache();
        $http = new FakeShopifyHttp([$this->response(200, ['access_token' => 'short-token', 'expires_in' => 3600]), $this->response(200, ['shop' => ['name' => 'Example']]), $this->response(200, ['access_scopes' => [['handle' => 'read_products']]])]);
        $client = new ShopifyClient($this->configuration(), null, $http, $cache);

        $client->testConnection();
        self::assertSame(3, count($http->calls));
        self::assertSame('https://rootins.myshopify.com/admin/oauth/access_token', $http->calls[0]['url']);
        self::assertSame('https://rootins.myshopify.com/admin/api/2025-01/shop.json', $http->calls[1]['url']);
        self::assertSame('client_credentials', $http->calls[0]['options']['form_params']['grant_type']);
        self::assertSame('client-id', $http->calls[0]['options']['form_params']['client_id']);
        self::assertSame('client-secret', $http->calls[0]['options']['form_params']['client_secret']);
        self::assertArrayNotHasKey('X-Shopify-Access-Token', $http->calls[0]['options']['headers']);
        self::assertArrayNotHasKey('encryption_key', $http->calls[0]['options']['form_params']);
    }

    public function testCachedTokenIsReusedUntilRefreshMargin(): void
    {
        $cache = new FakeShopifyCache();
        $http = new FakeShopifyHttp([$this->response(200, ['access_token' => 'cached-token', 'expires_in' => 3600]), $this->response(200, ['ok' => true]), $this->response(200, ['ok' => true])]);
        $client = new ShopifyClient($this->configuration(), null, $http, $cache);

        $client->request('GET', '/shop.json');
        $client->request('GET', '/shop.json');
        self::assertSame(3, count($http->calls));
        self::assertSame('cached-token', $http->calls[1]['options']['headers']['X-Shopify-Access-Token']);
        self::assertSame('cached-token', $http->calls[2]['options']['headers']['X-Shopify-Access-Token']);
    }

    public function testExpiredTokenIsRefreshedAndApi401RetriesOnce(): void
    {
        $cache = new FakeShopifyCache();
        $cache->value = ['access_token' => 'expired-token', 'expires_at' => time() - 1];
        $http = new FakeShopifyHttp([$this->response(200, ['access_token' => 'fresh-token', 'expires_in' => 3600]), $this->response(401, []), $this->response(200, ['access_token' => 'fresh-token-2', 'expires_in' => 3600]), $this->response(200, ['ok' => true])]);
        $client = new ShopifyClient($this->configuration(), null, $http, $cache);

        self::assertSame(['ok' => true], $client->request('GET', '/shop.json'));
        self::assertSame(4, count($http->calls));
        self::assertSame('fresh-token', $http->calls[1]['options']['headers']['X-Shopify-Access-Token']);
        self::assertSame('fresh-token-2', $http->calls[3]['options']['headers']['X-Shopify-Access-Token']);
    }

    public function testTokenRefreshesInsideExpiryMargin(): void
    {
        $cache = new FakeShopifyCache();
        $cache->value = ['access_token' => 'almost-expired', 'expires_at' => time() + 30];
        $http = new FakeShopifyHttp([$this->response(200, ['access_token' => 'refreshed-token', 'expires_in' => 3600]), $this->response(200, ['ok' => true])]);

        (new ShopifyClient($this->configuration(), null, $http, $cache))->request('GET', '/shop.json');
        self::assertSame('refreshed-token', $http->calls[1]['options']['headers']['X-Shopify-Access-Token']);
    }

    public function testWrongStoreResponseIsRejected(): void
    {
        $http = new FakeShopifyHttp([$this->response(200, ['access_token' => 'fresh-token', 'expires_in' => 3600]), $this->response(200, ['shop' => ['myshopify_domain' => 'other.myshopify.com']])]);

        $this->expectExceptionMessage('Shopify store did not match the configured store domain.');
        (new ShopifyClient($this->configuration(), null, $http, new FakeShopifyCache()))->testConnection();
    }

    public function testInvalidCredentialsFailWithoutExposingSecret(): void
    {
        $http = new FakeShopifyHttp([$this->response(401, ['error' => 'invalid_client'])]);
        $client = new ShopifyClient($this->configuration(), null, $http, new FakeShopifyCache());

        try {
            $client->request('GET', '/shop.json');
            self::fail('Expected provider failure.');
        } catch (\Throwable $exception) {
            self::assertSame('Shopify credentials were rejected.', $exception->getMessage());
            self::assertStringNotContainsString('client-secret', $exception->getMessage());
        }
    }

    public function testProviderHttpFailureIsClassifiedWithoutResponseDisclosure(): void
    {
        $http = new FakeShopifyHttp([
            $this->response(200, ['access_token' => 'fresh-token', 'expires_in' => 3600]),
            $this->response(429, ['errors' => 'private provider response']),
        ]);

        try {
            (new ShopifyClient($this->configuration(), null, $http, new FakeShopifyCache()))->request('GET', '/shop.json');
            self::fail('Expected provider failure.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\App\Services\Shopify\ShopifyProviderException::class, $exception);
            self::assertSame(429, $exception->httpStatus);
            self::assertSame('shopify_http_429', $exception->errorCode);
            self::assertStringNotContainsString('private provider response', $exception->getMessage());
        }
    }

    public function testValidTokenButOrganizationRestrictionIsNotReportedAsInvalidCredentials(): void
    {
        $http = new FakeShopifyHttp([
            $this->response(200, ['access_token' => 'valid-token', 'expires_in' => 86400]),
            $this->response(403, ['errors' => 'private organization response']),
        ]);

        try {
            (new ShopifyClient($this->configuration(), null, $http, new FakeShopifyCache()))->request('GET', '/shop.json');
            self::fail('Expected organization restriction.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\App\Services\Shopify\ShopifyProviderException::class, $exception);
            self::assertSame('organization_mismatch', $exception->category);
            self::assertSame('shopify_organization_restriction', $exception->errorCode);
            self::assertNotSame('invalid_credentials', $exception->category);
            self::assertStringNotContainsString('private organization response', $exception->getMessage());
        }
    }

    public function testNetworkTimeoutIsClassifiedWithoutExceptionMessageDisclosure(): void
    {
        $http = new FakeShopifyHttp([], new ShopifyTimeoutException('secret network diagnostic'));

        try {
            (new ShopifyClient($this->configuration(), null, $http, new FakeShopifyCache()))->request('GET', '/shop.json');
            self::fail('Expected provider failure.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\App\Services\Shopify\ShopifyProviderException::class, $exception);
            self::assertSame('network_timeout', $exception->errorCode);
            self::assertStringNotContainsString('secret network diagnostic', $exception->getMessage());
        }
    }

    private function configuration(): ShopifyConfiguration
    {
        return new ShopifyConfiguration(['store_domain' => 'rootins.myshopify.com', 'api_version' => '2025-01', 'client_id' => 'client-id', 'client_secret' => 'client-secret', 'webhook_secret' => 'webhook-secret', 'app_url' => 'https://example.test', 'encryption_key' => 'encryption-key']);
    }

    private function response(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn(json_encode($body));
        return $response;
    }
}

final class FakeShopifyHttp
{
    /** @var list<ResponseInterface> */
    public array $responses;
    /** @var list<array{method:string,url:string,options:array}> */
    public array $calls = [];
    private ?\Throwable $failure;

    public function __construct(array $responses, ?\Throwable $failure = null) { $this->responses = $responses; $this->failure = $failure; }

    public function request(string $method, string $url, array $options): ResponseInterface
    {
        if ($this->failure !== null) throw $this->failure;
        $this->calls[] = compact('method', 'url', 'options');
        return array_shift($this->responses);
    }
}

final class ShopifyTimeoutException extends \RuntimeException
{
}

final class FakeShopifyCache
{
    public ?array $value = null;
    public function get(string $key): mixed { return $this->value; }
    public function save(string $key, mixed $value, int $ttl = 60): bool { $this->value = $value; return true; }
    public function delete(string $key): bool { $this->value = null; return true; }
}
