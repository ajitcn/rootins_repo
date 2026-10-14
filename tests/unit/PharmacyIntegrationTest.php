<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\PharmacyDashboardService;
use Pharmacy\Services\PharmacyScope;

final class PharmacyIntegrationTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testDashboardUsesPermissionAwareClinicScopedKpis(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 21], 'permissions' => ['pharmacy.dashboard.view', 'pharmacy.order.view']]);
        self::assertSame(21, (new PharmacyScope())->clinicId());
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyDashboardService.php');
        self::assertStringContainsString("where('o.clinic_id',\$clinic)", $service);
        self::assertStringContainsString("'pending', 'authorized', 'paid', 'failed', 'refunded'", $service);
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/dashboard.php');
        self::assertStringContainsString('Pending prescription reviews', $view);
        self::assertStringContainsString('Pending payments', $view);
        self::assertStringContainsString('Active deliveries', $view);
        self::assertStringContainsString('Refresh', $view);
        self::assertStringNotContainsString('delivery-action', $view);
        self::assertStringNotContainsString('payment-action', $view);
    }

    public function testDashboardAnalyticsContractIncludesScopedFiltersAndBoundedActivity(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyDashboardService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/dashboard.php');
        foreach (['order_status', 'payment_status', 'delivery_status', 'prescription_status', 'where(\'o.clinic_id\'', 'get(8)', 'groupBy', 'd-m-Y', 'manual_reconciliations', 'paymentProviders', 'stockAlerts'] as $contract) {
            self::assertStringContainsString($contract, $service . $view);
        }
        self::assertStringNotContainsString('dashboardRange', $view);
        self::assertStringNotContainsString('name="range"', $view);
        self::assertStringNotContainsString('Date range', $view);
        foreach (['Latest orders', 'Latest prescription reviews', 'Latest payments', 'Latest deliveries', 'Stock alerts', 'No records in the selected range'] as $section) {
            self::assertStringContainsString($section, $view);
        }
        self::assertStringNotContainsString('DataTable', $service . $view);
    }

    public function testAuditTrailIsReadOnlyScopedPaginatedAndUsesDisplayDates(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Audit.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/audit/index.php');
        self::assertStringContainsString("where('clinic_id', \$clinicId)", $controller);
        self::assertStringContainsString('findAll($pageSize', $controller);
        self::assertStringContainsString("format('d-m-Y')", $controller);
        self::assertStringContainsString('fa-solid fa-circle-exclamation', $view);
        self::assertStringContainsString('modal-body', $view);
        self::assertStringNotContainsString('delete-audit', strtolower($view));
        self::assertStringContainsString('audit.css', $view);
    }

    public function testPharmacyAuditCoverageAndCrossWorkflowStatusVisibilityExist(): void
    {
        $sources = implode("\n", [
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Orders.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Billing.php'),
            file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Delivery.php'),
        ]);

        foreach (['pharmacy.prescription_review', 'pharmacy.order_created', 'pharmacy.order_cancelled', 'pharmacy.payment_reconciled', 'pharmacy.delivery_updated', 'pharmacy.permission_denied', 'pharmacy.provider_not_configured'] as $event) {
            self::assertStringContainsString($event, $sources);
        }

        foreach (['payment_status', 'delivery_status', 'status'] as $field) {
            self::assertStringContainsString($field, implode("\n", [file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php'), file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php'), file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php')]));
        }
    }

    public function testNotificationsUseDedicatedPharmacyRoutesAndPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        self::assertStringContainsString("'notifications', 'Notification::index'", $routes);
        self::assertStringContainsString("'notifications/read-all', 'Notification::readAll'", $routes);
        self::assertStringContainsString('pharmacy.notification.view', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-08-110000_RenameLegacyPharmacyIdentifiers.php'));
    }

    public function testPharmacyPagesUseSharedAjaxAndNoDataTables(): void
    {
        $root = ROOTPATH . 'app/Modules/Pharmacy';
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                $files[] = file_get_contents($file->getPathname());
            }
        }
        $source = implode("\n", $files);
        self::assertStringNotContainsString('DataTable', $source);
        self::assertStringContainsString('ajaxRequest', file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js'));
        self::assertStringContainsString('ajaxRequest', file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/delivery.js'));
        self::assertStringContainsString('pharmacy-dashboard-kpis', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/dashboard.php'));
    }

    public function testInnerPagesUseTheSharedResponsiveUiContract(): void
    {
        $templates = [
            'pages/prescriptions/list.php',
            'pages/prescriptions/grid.php',
            'pages/inventory/list.php',
            'pages/inventory/grid.php',
            'pages/orders/list.php',
            'pages/orders/grid.php',
            'pages/billing/list.php',
            'pages/billing/grid.php',
            'pages/delivery/list.php',
            'pages/delivery/grid.php',
            'pages/audit/index.php',
            'pages/notifications/index.php',
        ];

        foreach ($templates as $template) {
            self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/' . $template);
        }

        $layout = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        $shared = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/shared.css');
        self::assertStringContainsString('assets/css/pharmacy/shared.css', $layout);

        foreach (['pharmacy-page-header', 'pharmacy-filter-card', 'pharmacy-content-card', 'modal-dialog-scrollable', '#242424', '@media(max-width:767.98px)', 'dropdown-menu'] as $rule) {
            self::assertStringContainsString($rule, $shared);
        }

        foreach (['prescriptions', 'inventory', 'orders', 'billing', 'delivery'] as $feature) {
            $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/' . $feature . '/list.php');
            $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/' . $feature . '/grid.php');
            self::assertTrue(str_contains($list, 'pharmacy-page') || str_contains($list, 'pharmacy-' . $feature . '-page'));
            self::assertTrue(str_contains($grid, 'pharmacy-page') || str_contains($grid, 'pharmacy-' . $feature . '-page'));
            self::assertStringContainsString('$this->extend(PHARMACY_VIEW . \'layout\')', $list);
            self::assertStringContainsString('$this->extend(PHARMACY_VIEW . \'layout\')', $grid);
            self::assertStringNotContainsString('get_defined_vars()', $list);
            self::assertStringNotContainsString('get_defined_vars()', $grid);
        }

        $audit = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/audit/index.php');
        $notifications = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/notifications/index.php');
        self::assertStringContainsString('modal-body', $audit);
        self::assertStringContainsString('notification-inbox', $notifications);
        self::assertStringContainsString('modal-dialog-scrollable', $notifications);
    }
}
