<?php

namespace Tests\Unit;

use Pharmacy\Services\TransitionContract;
use PHPUnit\Framework\TestCase;

final class PaymentStatusMasterTest extends TestCase
{
    public function testMigrationSeedsTheExistingPaymentStatusCodesIdempotently(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-100000_CreatePaymentStatuses.php');

        foreach (['payment_statuses', 'status_name', 'status_code', 'description', 'sort_order', 'archived_at', 'where(\'status_code\', $code)', 'if ($existing === null)'] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
        self::assertStringContainsString('public function down()', $migration);
    }

    public function testActiveCatalogFiltersOnlyActiveDatabaseRows(): void
    {
        $catalog = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PaymentStatusCatalog.php');
        self::assertStringContainsString("where('status', 1)", $catalog);
        self::assertStringContainsString("where('archived_at', null)", $catalog);
        self::assertStringContainsString("status_code", $catalog);
    }

    public function testAdminCrudHasDuplicateValidationPermissionsAndAuditEvents(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/PaymentStatus.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $permissions = file_get_contents(ROOTPATH . 'app/Database/Seeds/PermissionSeeder.php');

        foreach (['Status name already exists.', 'Status code already exists.', 'admin.payment_status_', "'created'", "'updated'", "'activated'", "'deactivated'", "'admin.payment_status_archived'"] as $needle) {
            self::assertStringContainsString($needle, $controller);
        }
        foreach (['payment_status.view', 'payment_status.manage', 'PaymentStatus::index', 'PaymentStatus::save', 'PaymentStatus::toggle', 'PaymentStatus::archive'] as $needle) {
            self::assertStringContainsString($needle, $routes . $permissions);
        }

        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/payment_statuses/index.php');
        self::assertSame(1, substr_count($view, 'More Actions'));
        self::assertStringContainsString('dropdown-menu-right', $view);
        self::assertStringContainsString('payment-status-filters', $view);
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/payment_status.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/admin/payment-statuses.css');
        self::assertStringContainsString('cleanupModalArtifacts', $script);
        self::assertStringContainsString("appendTo('body')", $script);
        self::assertStringContainsString('modal-backdrop', $css);
        self::assertStringContainsString('payment-status-modal-open', $css);
    }

    public function testTransitionContractRemainsAuthoritativeAndModalUsesSharedPartial(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('payment_billing', 'pending', 'authorized'));
        self::assertFalse($contract->allows('payment_billing', 'pending', 'paid'));
        self::assertFalse($contract->allows('payment_billing', 'paid', 'pending'));

        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString('paymentStatusCatalog', $script);
        self::assertStringContainsString('Object.keys(paymentStatusCatalog)', $script);
        self::assertStringNotContainsString('(paymentTransitions[current] || []).forEach', $script);
        self::assertStringContainsString('isActive($status)', $service);
        self::assertStringNotContainsString('TransitionContract', $service);
        foreach ([
            'app/Modules/Pharmacy/Views/pages/billing/list.php',
            'app/Modules/Pharmacy/Views/pages/billing/grid.php',
            'app/Modules/Pharmacy/Views/pages/orders/list.php',
            'app/Modules/Pharmacy/Views/pages/orders/grid.php',
        ] as $page) {
            self::assertStringContainsString("pages/billing/_modal", file_get_contents(ROOTPATH . $page));
        }
    }

    public function testExistingPaymentRecordsRemainCodeBasedAndAudited(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-100000_CreatePaymentStatuses.php');

        self::assertStringContainsString('where(\'orders.payment_status\', $filters[\'payment_status\'])', $service);
        self::assertStringContainsString('[\'payment_status\' => $status', $service);
        self::assertStringContainsString("pharmacy.payment_reconciled", $service);
        self::assertStringNotContainsString('medicine_orders', $migration);
    }
}
