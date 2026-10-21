<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyProxySignatureService;
use PHPUnit\Framework\TestCase;

final class ShopifyProxySetupTest extends TestCase
{
    public function testProxyRoutesAndSecurityContractsAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        self::assertStringContainsString("rootins-proxy/appointments/availability", $routes);
        self::assertStringContainsString("rootins-proxy/health", $routes);
        self::assertStringContainsString("rootins-proxy/prescriptions/upload", $routes);
        self::assertStringNotContainsString("shopify-proxy/appointments/availability", $routes);
        $filters = file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        self::assertStringContainsString("'api/v1/*'", $filters);
        self::assertStringContainsString("'rootins-proxy/*'", $filters);
        self::assertStringContainsString("'integrations/shopify/webhooks/*'", $filters);
        self::assertStringContainsString("/public/rootins-proxy", file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php'));
        self::assertStringContainsString("proxy-setup/rotate-secret", $routes);
        self::assertStringContainsString("proxy-setup/enable", $routes);
        self::assertStringContainsString("proxy-setup/disable", $routes);
        foreach (['Idempotency-Key', 'ShopifyProxyService', 'CoreApiRateLimitService', 'ShopifyOperationIdempotencyService', 'logged_in_customer_id'] as $contract) self::assertStringContainsString($contract, $proxy . file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php'));
        self::assertStringContainsString('shopify_core_api_proxy_configurations', file_get_contents(ROOTPATH . 'app/Modules/Shopify/Models/ShopifyCoreApiProxyConfigurationModel.php'));
        self::assertStringNotContainsString('new ShopifyConfiguration()', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php'));
    }

    public function testProxySetupDoesNotRenderSecrets(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_proxy_setup.php');
        self::assertStringNotContainsString("['client_secret']", $view);
        self::assertStringNotContainsString("['client_id']", $view);
        self::assertStringContainsString('Shopify Webhooks / Order Integration', $view);
        self::assertStringContainsString('Shopify Core API App Proxy', $view);
    }

    public function testAppProxyCanonicalizationUsesDecodedSortedConcatenatedValues(): void
    {
        $rawQuery = 'extra=1&extra=2&shop=demo.myshopify.com&logged_in_customer_id=1&path_prefix=%2Fapps%2Frootins&timestamp=1317327555&signature=ignored';
        $service = new ShopifyProxySignatureService();
        $query = $service->query($rawQuery);

        self::assertSame('1', $query['extra'][0]);
        self::assertSame('2', $query['extra'][1]);
        self::assertSame('extra=1,2logged_in_customer_id=1path_prefix=/apps/rootinsshop=demo.myshopify.comtimestamp=1317327555', $service->canonical($query));
        self::assertSame('7cb66ee1adaba44d98b85cc53b9988346f8989a3af0fd40b33bd1f3292ba1be6', hash_hmac('sha256', $service->canonical($query), 'test-app-proxy-secret'));

        $proxyService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        self::assertStringNotContainsString('http_build_query', $proxyService);
        self::assertStringContainsString("hash_hmac('sha256'", $proxyService);
    }

    public function testProxyResponsesUseSafeJsonContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        $proxyService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        self::assertStringContainsString("['status' => true, 'data' => \$data === [] ? (object) [] : \$data]", $controller);
        self::assertStringContainsString("['status' => false, 'error' => ['code'", $controller);
        self::assertStringContainsString("setContentType('application/json')", $controller);
        self::assertStringContainsString("application/json; charset=UTF-8", $controller);
        self::assertStringContainsString("private function forceJsonResponse", $controller);
        self::assertStringContainsString("private function registrationCoreApiError", $controller);
        self::assertStringContainsString("SHOPIFY_CUSTOMER_ID_MISMATCH", $controller);
        self::assertStringContainsString("SHOPIFY_CUSTOMER_LOGIN_REQUIRED", $controller);
        self::assertStringContainsString("PATIENT_DATA_INVALID", $controller);
        self::assertStringContainsString("SHOPIFY_PROXY_CONFIGURATION_INVALID", $controller);
        self::assertStringContainsString("SHOPIFY_PROXY_DEPENDENCY_UNAVAILABLE", $controller);
        self::assertStringContainsString('APPOINTMENT_AVAILABILITY_INVALID', $controller);
        self::assertStringContainsString('SHOPIFY_PROXY_INTERNAL_ERROR', $controller);
        self::assertStringContainsString('correlation_id=', $controller);
        self::assertStringContainsString('Shopify proxy request started', $controller);
        self::assertStringContainsString('Shopify proxy response ready', $controller);
        self::assertStringContainsString('ROOTINS Shopify Core API Proxy is reachable.', $controller);
        self::assertStringContainsString('Shopify proxy availability dependency failure', $controller);
        foreach (['CORE_API_CONFIGURATION_MISSING', 'CLINIC_CONFIGURATION_INVALID', 'DATABASE_UNAVAILABLE', 'APPOINTMENT_SERVICE_UNAVAILABLE', 'AVAILABILITY_DEPENDENCY_FAILURE'] as $code) self::assertStringContainsString($code, $controller);
        self::assertStringContainsString('SHOPIFY_PROXY_SIGNATURE_INVALID', $proxyService);
        self::assertStringContainsString('SHOPIFY_PROXY_STORE_SCOPE_INVALID', $proxyService);
        self::assertStringContainsString('normalizeAvailabilityPayload', $proxyService);
    }

    public function testRegistrationBoundaryCannotRenderToolbarOrRawFailures(): void
    {
        $filters = file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');

        self::assertStringNotContainsString("'toolbar',", $filters);
        self::assertStringContainsString("'toolbar' => ['except' => ['rootins-proxy/*']]", $filters);
        self::assertStringContainsString('$this->forceJsonResponse();', $controller);
        self::assertStringContainsString('$this->begin($operation);', $controller);
        self::assertStringContainsString("'SHOPIFY_PROXY_INTERNAL_ERROR'", $controller);
        self::assertStringContainsString("'SHOPIFY_PROXY_DEPENDENCY_UNAVAILABLE'", $controller);
        self::assertStringNotContainsString('$this->json(500, false, $exception->getMessage()', $controller);
    }

    public function testProxyConnectionVerifiesIsolatedCredentialsAndKeepsErrorsSafe(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');
        $verifier = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyCredentialVerificationService.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/integrations/shopify-proxy-setup.js');

        foreach (['SHOPIFY_PROXY_CONFIGURATION_READY', 'SHOPIFY_PROXY_CREDENTIALS_INVALID', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 'SHOPIFY_PROXY_CONNECTION_FAILED', 'SHOPIFY_PROXY_CONFIGURATION_MISSING'] as $category) {
            self::assertStringContainsString($category, $controller . $verifier);
        }
        self::assertStringContainsString('/admin/oauth/access_token', $verifier);
        self::assertStringContainsString("'grant_type' => 'client_credentials'", $verifier);
        self::assertStringNotContainsString('log_message(\'error\', $clientSecret', $verifier);
        self::assertStringNotContainsString("['status' => false, 'msg' => 'Shopify App Proxy configuration is ready.'", $controller);
        self::assertStringContainsString('Verifying Shopify Core API app credentials', $javascript);
        self::assertStringContainsString('new FormData(form)', $javascript);
        self::assertStringContainsString('d.error && d.error.message', $javascript);
        self::assertStringContainsString("getPost('core_api_client_id')", $controller);
        self::assertStringContainsString("getPost('core_api_client_secret')", $controller);
    }

    public function testCoreApiProxyCredentialPathsCannotFallbackToWebhookConfiguration(): void
    {
        $isolatedFiles = [
            file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php'),
            file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyCredentialVerificationService.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Shopify/Models/ShopifyCoreApiProxyConfigurationModel.php'),
        ];
        foreach ($isolatedFiles as $source) {
            self::assertStringNotContainsString('shopify_configurations', $source);
            self::assertStringNotContainsString('ShopifyConfiguration', $source);
            self::assertStringNotContainsString('SHOPIFY_CLIENT_ID', $source);
            self::assertStringNotContainsString('SHOPIFY_CLIENT_SECRET', $source);
            self::assertStringNotContainsString('SHOPIFY_WEBHOOK_SECRET', $source);
            self::assertStringNotContainsString('webhook_secret', $source);
        }

        $proxyService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $executeStart = strpos($proxyService, 'public function execute');
        $availabilityStart = strpos($proxyService, "'appointments.availability' =>", $executeStart);
        self::assertIsInt($executeStart);
        self::assertIsInt($availabilityStart);
        $executePrefix = substr($proxyService, $executeStart, $availabilityStart - $executeStart);
        self::assertStringNotContainsString('ShopifyConfiguration', $executePrefix);
        self::assertStringNotContainsString('shopify_configurations', $executePrefix);

        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');
        $start = strpos($controller, 'public function testProxyConnection');
        $end = strpos($controller, 'public function rotateProxySecret', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $proxyTestController = substr($controller, $start, $end - $start);
        self::assertStringContainsString('ShopifyProxyConfigurationService', $proxyTestController);
        self::assertStringNotContainsString('ShopifyConfiguration', $proxyTestController);
        self::assertStringNotContainsString('ShopifyClient', $proxyTestController);

        self::assertStringContainsString('elseif ($row === null) throw new InvalidArgumentException(\'The Core API client ID is required', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php'));
        self::assertStringContainsString('elseif ($row === null) throw new InvalidArgumentException(\'The Core API client secret is required', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyConfigurationService.php'));
    }
}
