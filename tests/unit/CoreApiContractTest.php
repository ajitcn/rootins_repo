<?php

namespace Tests\Unit;

use App\Modules\CoreApi\Services\CoreApiException;
use PHPUnit\Framework\TestCase;

final class CoreApiContractTest extends TestCase
{
    public function testVersionedCoreApiRoutesAreIndependentFromShopify(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Config/Routes.php');

        self::assertStringContainsString("\$routes->group('api/v1'", $routes);
        self::assertStringContainsString("patients/register", $routes);
        self::assertStringContainsString("appointments/availability", $routes);
        self::assertStringContainsString("appointments/book", $routes);
        self::assertStringContainsString("prescriptions/upload", $routes);
        self::assertStringNotContainsString('Shopify', $routes);
    }

    public function testCoreApiStorageUsesCredentialScopedIdempotency(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-120000_CreateCoreApiFoundation.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiIdempotencyService.php');

        self::assertStringContainsString('core_api_credentials', $migration);
        self::assertStringContainsString("addUniqueKey(['credential_id', 'idempotency_key'])", $migration);
        self::assertStringContainsString("where('credential_id', \$credentialId)", $service);
        self::assertStringContainsString('IDEMPOTENCY_KEY_REUSED', $service);
    }

    public function testApiErrorsHaveStableCodesAndFieldErrors(): void
    {
        $exception = new CoreApiException(422, 'VALIDATION_FAILED', 'Invalid request.', ['date' => 'Date is invalid.']);

        self::assertSame(422, $exception->httpStatus);
        self::assertSame('VALIDATION_FAILED', $exception->errorCode);
        self::assertSame('Date is invalid.', $exception->fields['date']);
    }

    public function testCoreApiAuthenticationUsesTimestampAndHmacHeaders(): void
    {
        $authentication = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAuthenticationService.php');

        self::assertStringContainsString('X-API-Key-Id', $authentication);
        self::assertStringContainsString('X-API-Timestamp', $authentication);
        self::assertStringContainsString('X-API-Signature', $authentication);
        self::assertStringContainsString('hash_hmac', $authentication);
        self::assertStringContainsString('API_TIMESTAMP_INVALID', $authentication);
    }

    public function testCoreApiCredentialAndBusinessScopeAreApplicationWideButDomainScoped(): void
    {
        $model = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Models/CoreApiCredentialModel.php');
        $authentication = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAuthenticationService.php');
        $resolver = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiClinicResolver.php');

        self::assertStringNotContainsString("'clinic_id'", $model);
        self::assertStringNotContainsString("credential['clinic_id']", $authentication);
        self::assertStringContainsString('CLINIC_SCOPE_INVALID', $resolver);
        self::assertStringContainsString('patientClinic', $resolver);
    }

    public function testExistingCredentialScopeMigrationIsReversibleAndPreservesLegacyValues(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-160000_MakeCoreApiCredentialsApplicationWide.php');

        self::assertStringContainsString('legacy_clinic_id', $migration);
        self::assertStringContainsString('UPDATE core_api_credentials SET legacy_clinic_id = clinic_id', $migration);
        self::assertStringContainsString('public function down()', $migration);
    }
}
