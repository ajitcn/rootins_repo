<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyConfigurationManagementTest extends TestCase
{
    public function testConfigurationStorageUsesDedicatedEncryptedSecretColumns(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-150000_CreateShopifyConfigurations.php');
        $credentialMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-14-130000_AddShopifyClientCredentials.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfigurationService.php');

        self::assertStringContainsString("createTable('shopify_configurations')", $migration);
        foreach (['client_id_encrypted', 'client_secret_encrypted', 'webhook_secret_encrypted', 'encryption_key_encrypted'] as $field) {
            self::assertStringContainsString($field, $field === 'webhook_secret_encrypted' || $field === 'encryption_key_encrypted' ? $migration : $credentialMigration);
        }
        self::assertStringContainsString('base64_encode(service(\'encrypter\')->encrypt', $service);
        self::assertStringContainsString("'client_id_masked'", $service);
        self::assertStringNotContainsString("'admin_api_access_token' => " . '$row', $service);
    }

    public function testManagementRoutesRequireTheDedicatedPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        self::assertStringContainsString("configuration/save', 'Integration::saveConfiguration'", $routes);
        self::assertStringContainsString("configuration/rotate-secret', 'Integration::rotateSecret'", $routes);
        self::assertStringContainsString("['filter' => 'permission:shopify.integration.manage']", $routes);
        self::assertStringContainsString('shopify.configuration_created', $controller);
        self::assertStringContainsString('shopify.configuration_updated', $controller);
        self::assertStringContainsString('shopify.configuration_secret_rotated', $controller);
    }

    public function testAdminPageUsesWriteOnlySecretFieldsAndNeverRendersPlaintextSecrets(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        foreach (['client_id', 'client_secret', 'webhook_secret', 'encryption_key'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view);
        }
        self::assertStringContainsString('type="password"', $view);
        self::assertStringContainsString('client_id_masked', $view);
        self::assertStringNotContainsString("'admin_api_access_token' =>", $controller);
        self::assertStringNotContainsString("'webhook_secret' =>", $controller);
        self::assertStringNotContainsString("'encryption_key' =>", $controller);
    }

    public function testValidationAndSafeFailureAuditsAreDefined(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfigurationService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');

        self::assertStringContainsString('valid Shopify store domain', $service);
        self::assertStringContainsString('valid Shopify API version', $service);
        self::assertStringContainsString('valid public HTTPS Shopify app URL', $service);
        self::assertStringContainsString('must not contain credentials, query strings, fragments, or tokens', $service);
        self::assertStringNotContainsString("'clinic_id' => \$clinicId", $service);
        self::assertStringContainsString("'scope' => 'application'", $service);
        self::assertStringContainsString('shopify.configuration_save_failed', $controller);
        self::assertStringContainsString('shopify.configuration_secret_rotation_failed', $controller);
        self::assertStringContainsString('shopify.connection_test_failed', $controller);
        self::assertStringNotContainsString('json_encode($this->request->getPost', $controller);
    }

    public function testClinicAndPublicWebhookEndpointValidationAreDefined(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfigurationService.php');

        self::assertStringContainsString("where('status', 1)", $service);
        self::assertStringContainsString('configured clinic does not exist or is inactive', $service);
        self::assertStringContainsString("appHost === 'localhost'", $service);
        self::assertStringContainsString('publicly reachable app URL', $service);
    }

    public function testShopifyConfigurationIsApplicationWideWithReversibleLegacyPreservation(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-161000_MakeShopifyConfigurationApplicationWide.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify.php');

        self::assertStringContainsString('legacy_clinic_id', $migration);
        self::assertStringContainsString('public function down()', $migration);
        self::assertStringContainsString('shopify-config-clinic-id', $view);
        self::assertStringContainsString('name="clinic_id"', $view);
        self::assertStringContainsString('name="app_url"', $view);
        self::assertStringContainsString('public HTTPS URL of the ROOTINS application', $view);
        self::assertStringContainsString('shopify-config-webhook-url', $view);
    }

    public function testRuntimeLoadingDistinguishesDatabaseAndEnvironmentSources(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfigurationService.php');
        $configuration = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfiguration.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Integration.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify.php');

        self::assertStringContainsString("new ShopifyConfiguration(\$this->decryptedValues(\$row), 'database')", $service);
        self::assertStringContainsString("'missing_configuration'", $service);
        self::assertStringContainsString("'__source' => 'database'", $configuration);
        self::assertStringContainsString("'__source' => 'environment'", $configuration);
        self::assertStringContainsString("'code' => \$reason", $controller);
        self::assertStringContainsString('SHOPIFY_CONFIGURATION_MISSING', $controller);
        self::assertStringContainsString('connectionDiagnosticData', $controller);
        self::assertStringContainsString('configuration_source', $controller);
        self::assertStringContainsString('ROOTINS encryption key, clinic ID, API version, or app URL', $controller);
        self::assertStringContainsString('same Shopify organization', $view);
    }

    public function testStoreDomainNormalizationRemovesSchemeAndPaths(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyConfigurationService.php');

        self::assertStringContainsString('parse_url($value)', $service);
        self::assertStringContainsString("explode('/', \$value, 2)", $service);
        self::assertStringContainsString("str_ends_with(\$value, '.myshopify.com')", $service);

        $normalizer = new \ReflectionMethod(\App\Services\Shopify\ShopifyConfigurationService::class, 'normalizeStoreDomain');
        $normalizer->setAccessible(true);
        $configurationService = new \App\Services\Shopify\ShopifyConfigurationService();
        self::assertSame('rootins.myshopify.com', $normalizer->invoke($configurationService, 'https://rootins.myshopify.com/admin/'));
        self::assertSame('rootins.myshopify.com', $normalizer->invoke($configurationService, 'rootins.myshopify.com/'));
    }
}
