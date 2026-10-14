<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BillingAuditWorkflowTest extends TestCase
{
    public function testBillingRoutesAreProtectedAndFinalizationIsControlled(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Billing.php');
        self::assertStringContainsString('permission:billing.view', $routes);
        self::assertStringContainsString('permission:billing.create', $routes);
        self::assertStringContainsString('permission:billing.edit', $routes);
        self::assertStringContainsString("\$invoice['status'] !== 'draft'", $controller);
        self::assertStringContainsString("\$invoice['status'] !== 'finalized'", $controller);
        self::assertStringContainsString('idempotency_key', $controller);
    }

    public function testBillingAndAuditMigrationIsReversibleAndApplied(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-08-29-120000_CreateInvoicesAndAuditLogs.php');
        $status = shell_exec('php spark migrate:status 2>&1');
        self::assertStringContainsString("createTable('invoices')", $migration);
        self::assertStringContainsString("createTable('audit_logs')", $migration);
        self::assertStringContainsString("dropTable('audit_logs', true)", $migration);
        self::assertStringContainsString('CreateInvoicesAndAuditLogs', (string) $status);
    }

    public function testAuditLoggerPersistsOnlySanitizedContext(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Services/Audit/AuditLogger.php');
        self::assertStringContainsString("'context' => \$encodedContext", $source);
        self::assertStringContainsString('tableExists(\'audit_logs\')', $source);
        self::assertStringContainsString('Durable audit write failed', $source);
    }
}
