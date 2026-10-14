<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoreApiAdminContractTest extends TestCase
{
    public function testAdminCoreApiRoutesAndPermissionsAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/side_menu.php');

        self::assertStringContainsString("admin/integrations/core-api", $routes);
        self::assertStringContainsString("permission:core_api.view", $routes);
        self::assertStringContainsString("permission:core_api.create", $routes);
        self::assertStringContainsString("permission:core_api.manage", $routes);
        self::assertStringContainsString("admin/integrations/core-api", $menu);
    }

    public function testAdminPageNeverEmbedsStoredCiphertextOrASecret(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/core_api.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/core-api/core-api.js');

        self::assertStringNotContainsString('secret_encrypted', $view);
        self::assertStringNotContainsString('secret_encrypted', $javascript);
        self::assertStringContainsString('json.data.secret', $javascript);
        self::assertStringContainsString('data-toggle="modal"', $view);
    }

    public function testCredentialServiceKeepsSecretInternalToOneTimeProvisioningResponse(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiCredentialService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Integration/CoreApi.php');

        self::assertStringContainsString('base64_encode(service(\'encrypter\')->encrypt', $service);
        self::assertStringContainsString("admin.core_api.credential_created", $controller);
        self::assertStringContainsString("admin.core_api.credential_rotated", $controller);
        self::assertStringContainsString("admin.core_api.credential_revoked", $controller);
        self::assertStringContainsString("admin.core_api.connection_test", $controller);
    }

    public function testAdminEndpointCatalogUsesSharedDefinitionsAndDocumentsPrescriptionDetails(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/core_api.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Integration/CoreApi.php');
        $definitions = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Config/EndpointDefinitions.php');
        $docs = file_get_contents(ROOTPATH . 'docs/CORE-API.md');

        self::assertStringContainsString('EndpointDefinitions::all()', $controller);
        self::assertStringNotContainsString('$endpoints = [', $view);
        foreach (['prescriptions/{public_reference}', 'Retrieve uploaded prescription details', 'public_reference', 'download_url_expires_at', 'PRESCRIPTION_NOT_FOUND'] as $contract) {
            self::assertStringContainsString($contract, $definitions);
            self::assertStringContainsString($contract, $docs);
        }
        self::assertStringContainsString('generate-core-api-docs.php', $docs);
    }
}
