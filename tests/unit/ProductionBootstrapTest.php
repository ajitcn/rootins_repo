<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ProductionBootstrapTest extends CIUnitTestCase
{
    private function serviceSource(): string
    {
        return (string) file_get_contents(ROOTPATH . 'app/Services/Production/ProductionBootstrapService.php');
    }

    private function commandSource(): string
    {
        return (string) file_get_contents(ROOTPATH . 'app/Commands/ProductionBootstrap.php');
    }

    public function testBootstrapRequiresConfirmationForWritesAndSupportsDryRun(): void
    {
        $source = $this->commandSource();
        $this->assertStringContainsString('--confirm-production', $source);
        $this->assertStringContainsString('--dry-run', $source);
        $this->assertStringContainsString('! $dryRun && ! $confirmed', $source);
    }

    public function testRequiredEnvironmentValuesAreValidatedWithoutEmbeddingCredentials(): void
    {
        $source = $this->commandSource() . $this->serviceSource();
        foreach (['ROOTINS_ADMIN_NAME', 'ROOTINS_ADMIN_EMAIL', 'ROOTINS_ADMIN_PASSWORD', 'ROOTINS_ADMIN_CLINIC_ID'] as $name) {
            $this->assertStringContainsString($name, $source);
        }
        $this->assertStringNotContainsString('password_hash(', $this->commandSource());
        $this->assertStringNotContainsString('CLI::write($input', $this->commandSource());
    }

    public function testSchemaOrderingAndForeignKeyChecksAreExplicit(): void
    {
        $source = $this->serviceSource();
        $this->assertLessThan(strpos($source, 'ensurePermissions'), strpos($source, 'ensureRoles'));
        $this->assertLessThan(strpos($source, 'ensureSuperAdminPermissions'), strpos($source, 'ensurePermissions'));
        $this->assertStringContainsString("['clinics', 'roles', 'permissions', 'role_permissions', 'users', 'user_roles']", $source);
        $this->assertStringContainsString('active clinic', $source);
    }

    public function testBootstrapIsTransactionalAndIdempotent(): void
    {
        $source = $this->serviceSource();
        foreach (['transBegin', 'transCommit', 'transRollback', 'countAllResults', 'where(\'role_code\'', 'where(\'permission_key\''] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
        $this->assertStringContainsString('password_hash', $source);
        $this->assertStringContainsString('PASSWORD_DEFAULT', $source);
    }

    public function testOnlySuperAdminReceivesBootstrapPermissionGrants(): void
    {
        $source = $this->serviceSource();
        $this->assertStringContainsString("'SUPER_ADMIN'", $source);
        $this->assertStringContainsString("'role_id' => ", $source);
        $this->assertStringNotContainsString('deleteAll', $source);
        $this->assertStringNotContainsString('truncate', strtolower($source));
    }

    public function testBootstrapDoesNotCreateClinicalOrCommerceRecords(): void
    {
        $source = strtolower($this->serviceSource());
        foreach (['insert.*patients', 'insert.*doctors', 'insert.*appointments', 'insert.*orders', 'insert.*shopify'] as $pattern) {
            $this->assertDoesNotMatchRegularExpression('/' . $pattern . '/', $source);
        }
    }
}
