<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoreApiSecurityAuditTest extends TestCase
{
    public function testIdempotencyClaimsCanBeReleasedButCompletedReplaysRemainStored(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiIdempotencyService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Controllers/V1.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-120000_CreateCoreApiFoundation.php');

        self::assertStringContainsString("where('status', 'processing')", $service);
        self::assertStringContainsString('public function release', $service);
        self::assertStringContainsString('$idempotency->release', $controller);
        self::assertStringContainsString("where('status', 'processing')->delete()", $service);
        self::assertStringContainsString("if ((\$existing['status'] ?? '') === 'completed'", $service);
        self::assertStringContainsString("addUniqueKey(['credential_id', 'idempotency_key'])", $migration);
    }

    public function testCoreApiWritesClaimBeforeBusinessWorkAndCompletesAfterHandlerReturns(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Controllers/V1.php');

        self::assertStringContainsString('$claim = $idempotency->begin', $controller);
        self::assertStringContainsString('$data = $operationHandler($credential, $payload)', $controller);
        self::assertStringContainsString('$idempotency->complete', $controller);
        self::assertStringContainsString('$claimed = true', $controller);
    }

    public function testRateLimitUsesAtomicDatabaseUpsertAndDoesNotUseRacyCacheReadWrite(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiRateLimitService.php');
        $authentication = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAuthenticationService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-130000_CreateCoreApiRateLimits.php');

        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $service);
        self::assertStringContainsString('affectedRows()', $service);
        self::assertStringContainsString("addUniqueKey(['credential_id', 'window_start'])", $migration);
        self::assertStringNotContainsString('cache()->get', $authentication);
        self::assertStringNotContainsString('cache()->save', $authentication);
    }

    public function testRateLimitDenialHasStableRetryInformation(): void
    {
        $authentication = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAuthenticationService.php');
        $filter = file_get_contents(ROOTPATH . 'app/Filters/CoreApiAuthentication.php');
        $exception = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiException.php');

        self::assertStringContainsString("'API_RATE_LIMITED'", $authentication);
        self::assertStringContainsString("'Retry-After'", $filter);
        self::assertStringContainsString('retry_after', $filter);
        self::assertStringContainsString('retryAfter', $exception);
    }
}
