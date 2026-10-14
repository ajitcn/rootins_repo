<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    public function testAdminMenuContainsOnlyValidActionLinks(): void
    {
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/side_menu.php');
        $routes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        self::assertNotFalse($menu);
        self::assertNotFalse($routes);
        self::assertStringNotContainsString('href="#"', $menu);
        self::assertStringContainsString("system/logout', 'UserAuth::logout", $routes);
        self::assertStringContainsString("base_url('system/login')", file_get_contents(ROOTPATH . 'app/Controllers/UserAuth.php'));
        self::assertStringContainsString("session('isLoggedIn')", file_get_contents(ROOTPATH . 'app/Controllers/UserAuth.php'));
    }

    public function testAdminClinicManagementUsesTheAuthorizedClinicRouteOnce(): void
    {
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/side_menu.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        self::assertSame(1, substr_count($menu, 'Clinic Management'));
        self::assertStringContainsString("base_url('admin/clinics')", $menu);
        self::assertStringContainsString("permission:clinic.view", $routes);
    }

    public function testAdminMenuContainsTheCompleteGroupedDestinationSet(): void
    {
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/side_menu.php');

        foreach (['Dashboard', 'Clinic Management', 'Doctor Management', 'Patient Management', 'Appointments', 'Billing and Invoices', 'Users', 'Roles', 'Permissions', 'Role-Permission Assignment', 'User-Role Assignment', 'Audit Logs', 'Doctor Specializations', 'Departments', 'Services', 'Consultation Fee Templates', 'Commission Templates', 'Payment Modes', 'Tax Rates', 'Medicine Master', 'General Settings', 'Clinic Settings', 'Email/SMS/WhatsApp Settings', 'Notification Settings', 'Invoice and Prescription Templates', 'Backup and Restore', 'System Logs'] as $label) {
            self::assertStringContainsString($label, $menu);
        }
        self::assertStringContainsString("?planned=", $menu);
        self::assertStringNotContainsString('href="#"', $menu);
    }

    public function testPanelLayoutsExposeDistinctAccessibleNavigation(): void
    {
        foreach (['Doctor', 'Patient', 'Pharmacy'] as $module) {
            $layout = file_get_contents(ROOTPATH . 'app/Modules/' . $module . '/Views/layout.php');
            self::assertNotFalse($layout);
            self::assertStringContainsString('aria-label=', $layout);
            self::assertStringContainsString('logout', $layout);
        }
        $pharmacyLayout = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        self::assertStringContainsString('>Pharmacy<', $pharmacyLayout);
        self::assertStringContainsString('Pharmacy panel', $pharmacyLayout);
        self::assertStringNotContainsString('>Farmassist<', $pharmacyLayout);
    }

    public function testLegacyPharmacyRoleUsesCurrentDisplayLabels(): void
    {
        $helper = file_get_contents(ROOTPATH . 'app/Helpers/common_helper.php');
        $seeder = file_get_contents(ROOTPATH . 'app/Database/Seeds/RoleSeeder.php');

        self::assertStringContainsString('function display_role_label', $helper);
        self::assertStringContainsString("'Pharmacy Assistant'", $helper);
        self::assertStringContainsString("'role_code' => 'PHARMACY_ASSISTANT'", $seeder);
        self::assertStringContainsString("'role_name' => 'Pharmacy Assistant'", $seeder);
    }

    public function testPharmacyMenuLinksMatchPermissionProtectedRoutes(): void
    {
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');

        foreach (['pharmacy', 'prescriptions', 'inventory', 'orders', 'billing', 'delivery', 'audit', 'notifications'] as $path) {
            self::assertStringContainsString("base_url('pharmacy" . ($path === 'pharmacy' ? "')" : '/' . $path . "')"), $menu);
        }
        self::assertStringContainsString("pharmacy.dashboard.view", $menu);
        self::assertStringContainsString("pharmacy.prescription.review.view", $menu);
        self::assertStringContainsString("permission:pharmacy.dashboard.view", $routes);
        self::assertStringContainsString("permission:pharmacy.prescription.review.view", $routes);
        self::assertStringContainsString("permission:pharmacy.inventory.view", $routes);
        self::assertStringContainsString("permission:pharmacy.order.view", $routes);
        self::assertStringContainsString("permission:pharmacy.billing.view", $routes);
        self::assertStringContainsString("permission:pharmacy.delivery.view", $routes);
        self::assertStringContainsString("permission:pharmacy.audit.view", $routes);
        self::assertStringContainsString("permission:pharmacy.notification.view", $routes);
        self::assertStringNotContainsString("return redirect()->to(base_url('pharmacy'))->with('error'", implode('', glob(ROOTPATH . 'app/Modules/Pharmacy/Controllers/*.php')));
        self::assertStringContainsString("permission:pharmacy.order.create", $routes);
        self::assertStringContainsString("'farmassist/(:any)'", $routes);
        self::assertStringContainsString('LegacyRedirect::path', $routes);
    }

    public function testPharmacyMenuDestinationsResolveToTheirOwnControllers(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');

        foreach ([
            "get('/', 'Operations::index'",
            "get('prescriptions', 'PrescriptionReview::index'",
            "get('inventory', 'Inventory::index'",
            "get('orders', 'Orders::index'",
            "get('billing', 'Billing::index'",
            "get('delivery', 'Delivery::index'",
            "get('audit', 'Audit::index'",
            "get('notifications', 'Notification::index'",
        ] as $destination) {
            self::assertStringContainsString($destination, $routes);
        }

        foreach ([
            'Operations.php' => 'function index',
            'PrescriptionReview.php' => 'function index',
            'Inventory.php' => 'function index',
            'Orders.php' => 'function index',
            'Billing.php' => 'function index',
            'Delivery.php' => 'function index',
            'Audit.php' => 'function index',
            'Notification.php' => 'function index',
        ] as $controller => $method) {
            self::assertStringContainsString($method, file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/' . $controller));
        }
        $controllers = implode('', array_map('file_get_contents', glob(ROOTPATH . 'app/Modules/Pharmacy/Controllers/*.php')));
        self::assertStringNotContainsString("redirect()->to(base_url('pharmacy'))->with('error'", $controllers);
    }

    public function testPharmacyListServicesNormalizeMissingViewAndPageSizeSafely(): void
    {
        foreach (['MedicineAvailabilityService.php', 'OrderFulfilmentService.php', 'BillingService.php', 'DeliveryService.php', 'PrescriptionReviewService.php'] as $service) {
            $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/' . $service);
            self::assertStringContainsString("(string) (\$input['view'] ?? 'list')", $source);
            self::assertStringContainsString("in_array(\$requestedView, ['list', 'grid'], true)", $source);
        }
        $notifications = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyNotificationService.php');
        self::assertStringContainsString("\$requestedPageSize = (int) (\$input['page_size'] ?? 15)", $notifications);
        self::assertStringContainsString("in_array(\$requestedPageSize, [10, 15, 25, 50], true)", $notifications);
    }

    public function testDoctorNavigationRoutesDeclarePermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        self::assertStringContainsString('permission:doctor.view', $routes);
        self::assertStringContainsString('permission:appointment.view', $routes);
        self::assertStringContainsString('permission:consultation.view', $routes);
    }

    public function testSharedNavigationProvidesCollapseTooltipsAndGroupedPanelFallback(): void
    {
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('sidebarCollapseToggle', $theme);
        self::assertStringContainsString('rootins-sidebar-collapsed', $theme);
        self::assertStringContainsString("setAttribute('title', label)", $theme);
        self::assertStringContainsString('sidebar-section-title', $theme);
        self::assertStringContainsString('sidebar-collapsed', $styles);
        self::assertStringContainsString('focus-visible', $styles);
    }

    public function testSharedUiSystemAndPatientLargeSelectorArePresent(): void
    {
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        $patientPortal = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/portal.php');
        self::assertNotFalse($styles);
        self::assertNotFalse($theme);
        self::assertNotFalse($patientPortal);
        self::assertStringContainsString('--ri-primary:', $styles);
        self::assertStringContainsString('Skip to main content', $theme);
        self::assertStringContainsString('aria-live="polite"', $theme);
        self::assertStringContainsString('id="patient-doctor"', $patientPortal);
        self::assertStringContainsString('data-live-search="true"', $patientPortal);
    }

    public function testSharedDatepickerDisplaysLocalFormatAndSubmitsIsoValues(): void
    {
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        self::assertStringContainsString("format: 'dd-mm-yyyy'", $theme);
        self::assertStringContainsString('window.setSharedDateValue', $theme);
        self::assertStringContainsString('window.rootinsDateToIso', $theme);
        self::assertStringContainsString('return match && isValid(match[3], match[2], match[1])', $theme);
        self::assertStringContainsString("input[type=\"date\"]", $theme);
    }

    public function testSharedAjaxAlwaysFinalizesTheGlobalPreloader(): void
    {
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        self::assertStringContainsString('window.RootinsPreloader', $common);
        self::assertStringContainsString('timeout: 30000', $common);
        self::assertStringContainsString('request.complete = defaults.complete', $common);
        self::assertStringContainsString('finally {', $common);
        self::assertStringContainsString("$(window).on('pageshow.rootinsPreloader load.rootinsPreloader'", $common);
        self::assertStringContainsString("$(document).on('submit.rootinsPreloader'", $common);
    }
}
