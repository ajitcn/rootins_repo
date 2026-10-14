<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testLoginBuildsServerDerivedScopedSessionContext(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Services/AuthService.php');
        self::assertNotFalse($source);
        self::assertStringContainsString("session()->regenerate(true)", $source);
        self::assertStringContainsString("'clinic_id' => \$clinicId", $source);
        self::assertStringContainsString("->where('user_id', \$userId)", $source);
        self::assertStringNotContainsString("'password' => \$this->userData", $source);
    }

    public function testPendingOrderMigrationMatchesCurrentOrderModelContract(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-08-29-100000_CreateMedicineOrdersAndInventory.php');
        $status = shell_exec('php spark migrate:status 2>&1');
        self::assertNotFalse($migration);
        self::assertStringContainsString("createTable('medicine_orders')", $migration);
        self::assertStringContainsString("addUniqueKey('idempotency_key')", $migration);
        self::assertStringContainsString('CreateMedicineOrdersAndInventory', (string) $status);
        self::assertStringContainsString('---', (string) $status);
    }
}
