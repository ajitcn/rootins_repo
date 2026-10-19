<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyProxyCredentialVerificationException;
use App\Services\Shopify\ShopifyProxyCredentialVerificationService;
use CodeIgniter\HTTP\ResponseInterface;
use PHPUnit\Framework\TestCase;

final class ShopifyProxyCredentialVerificationTest extends TestCase
{
    public function testValidCoreApiCredentialsAreVerifiedAgainstTheConfiguredStore(): void
    {
        $http = new FakeShopifyProxyCredentialHttp([
            $this->response(200, ['access_token' => 'temporary-token', 'expires_in' => 3600]),
            $this->response(200, ['shop' => ['myshopify_domain' => 'rootins.myshopify.com']]),
        ]);

        $result = (new ShopifyProxyCredentialVerificationService($http))->verify($this->runtime());

        self::assertSame(['configuration' => 'ready', 'credentials' => 'verified', 'store' => 'rootins.myshopify.com'], $result);
        self::assertSame('POST', $http->calls[0]['method']);
        self::assertSame('https://rootins.myshopify.com/admin/oauth/access_token', $http->calls[0]['url']);
        self::assertSame('client_credentials', $http->calls[0]['options']['form_params']['grant_type']);
        self::assertSame('proxy-client-id', $http->calls[0]['options']['form_params']['client_id']);
        self::assertSame('proxy-client-secret', $http->calls[0]['options']['form_params']['client_secret']);
        self::assertSame('https://rootins.myshopify.com/admin/api/2025-01/shop.json', $http->calls[1]['url']);
    }

    public function testInvalidClientIdReturnsSafeCredentialError(): void
    {
        $this->assertInvalidCredentials(401, ['error' => 'private-invalid-client-id']);
    }

    public function testInvalidClientSecretReturnsSafeCredentialError(): void
    {
        $this->assertInvalidCredentials(401, ['error' => 'private-invalid-client-secret']);
    }

    public function testWrongStoreScopeReturnsDedicatedSafeError(): void
    {
        $http = new FakeShopifyProxyCredentialHttp([
            $this->response(200, ['access_token' => 'temporary-token']),
            $this->response(200, ['shop' => ['myshopify_domain' => 'other.myshopify.com'], 'private' => 'response-body']),
        ]);

        try {
            (new ShopifyProxyCredentialVerificationService($http))->verify($this->runtime());
            self::fail('Expected store scope failure.');
        } catch (ShopifyProxyCredentialVerificationException $exception) {
            self::assertSame('SHOPIFY_PROXY_STORE_SCOPE_INVALID', $exception->errorCode);
            self::assertSame(403, $exception->httpStatus);
            self::assertStringNotContainsString('response-body', $exception->getMessage());
        }
    }

    public function testTimeoutReturnsSafeConnectionError(): void
    {
        $http = new FakeShopifyProxyCredentialHttp([], new \RuntimeException('secret timeout details'));

        try {
            (new ShopifyProxyCredentialVerificationService($http))->verify($this->runtime());
            self::fail('Expected connection failure.');
        } catch (ShopifyProxyCredentialVerificationException $exception) {
            self::assertSame('SHOPIFY_PROXY_CONNECTION_FAILED', $exception->errorCode);
            self::assertSame(503, $exception->httpStatus);
            self::assertStringNotContainsString('secret timeout details', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $body */
    private function assertInvalidCredentials(int $status, array $body): void
    {
        $http = new FakeShopifyProxyCredentialHttp([$this->response($status, $body)]);

        try {
            (new ShopifyProxyCredentialVerificationService($http))->verify($this->runtime());
            self::fail('Expected invalid credential failure.');
        } catch (ShopifyProxyCredentialVerificationException $exception) {
            self::assertSame('SHOPIFY_PROXY_CREDENTIALS_INVALID', $exception->errorCode);
            self::assertSame(422, $exception->httpStatus);
            self::assertStringNotContainsString('private-', $exception->getMessage());
            self::assertStringNotContainsString('proxy-client-secret', $exception->getMessage());
        }
    }

    /** @return array<string, string> */
    private function runtime(): array
    {
        return ['store_domain' => 'rootins.myshopify.com', 'client_id' => 'proxy-client-id', 'client_secret' => 'proxy-client-secret'];
    }

    /** @param array<string, mixed> $body */
    private function response(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn(json_encode($body));
        return $response;
    }
}

final class FakeShopifyProxyCredentialHttp
{
    /** @var list<ResponseInterface> */
    private array $responses;
    /** @var list<array{method:string,url:string,options:array}> */
    public array $calls = [];

    public function __construct(array $responses, private readonly ?\Throwable $failure = null)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $options): ResponseInterface
    {
        if ($this->failure !== null) throw $this->failure;
        $this->calls[] = compact('method', 'url', 'options');
        return array_shift($this->responses);
    }
}
