<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyCustomerLookupException;
use App\Services\Shopify\ShopifyCustomerLookupService;
use PHPUnit\Framework\TestCase;

final class ShopifyCustomerLookupTest extends TestCase
{
    public function testCustomerIdsAreNormalizedToShopifyGraphQlIds(): void
    {
        self::assertSame('gid://shopify/Customer/12345', ShopifyCustomerLookupService::normalizeCustomerId('12345'));
        self::assertSame('gid://shopify/Customer/12345', ShopifyCustomerLookupService::normalizeCustomerId('gid://shopify/Customer/12345'));
    }

    public function testInvalidCustomerIdsUseTheSafeErrorCode(): void
    {
        try {
            ShopifyCustomerLookupService::normalizeCustomerId('customer-123');
            self::fail('Expected an invalid customer ID exception.');
        } catch (ShopifyCustomerLookupException $exception) {
            self::assertSame('SHOPIFY_CUSTOMER_ID_INVALID', $exception->errorCode);
            self::assertNull($exception->publicCustomerId);
        }
    }

    public function testLookupIsReadOnlyScopedAndDoesNotExposeSecretsOrInternalPatientIds(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerLookupService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');

        self::assertStringContainsString('read_customers', $service);
        self::assertStringContainsString("'shopify_customer_id'", $service);
        self::assertStringContainsString("'linked_patient_reference'", $service);
        self::assertStringContainsString('identityMatch', $service);
        self::assertStringContainsString('runtimeConfiguration', $service);
        self::assertStringNotContainsString('patient_id', $service);
        self::assertStringNotContainsString('access_token', $service . $controller);
        self::assertStringNotContainsString('webhook_secret', $service . $controller);
        self::assertStringContainsString("customers/(:any)', 'Integration::customer/", $routes);
        self::assertStringContainsString('permission:shopify.integration.view', $routes);
    }

    public function testCustomerLookupUiLivesOnTheDedicatedCustomersPage(): void
    {
        $main = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify.php');
        $customers = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify_on_demand_apis.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/side_menu.php');

        self::assertStringNotContainsString('Test Customer Details', $main);
        self::assertStringNotContainsString('Get Customer Details', $main);
        self::assertStringContainsString('Get Customer Details', $customers);
        self::assertStringContainsString("\$routes->get('customers', 'Integration::customers'", $routes);
        self::assertStringContainsString("admin/integrations/shopify/on-demand-apis", $menu);
        self::assertStringContainsString("admin/integrations/shopify/webhooks", $menu);
        self::assertStringContainsString('modal-lg modal-dialog-scrollable', $customers);
        self::assertStringContainsString("on-demand-apis', 'Integration::onDemandApis'", $routes);
        self::assertStringContainsString("on-demand-apis/customers/(:any)', 'Integration::customer/", $routes);
        self::assertStringContainsString('Get Shopify Customer Details', $customers);
        self::assertStringContainsString('read_customers', $customers);
        self::assertStringContainsString('customer(id: $id)', $customers);
        self::assertStringContainsString('/admin/integrations/shopify/on-demand-apis/customers/{customer_id}', $customers);
    }
}
