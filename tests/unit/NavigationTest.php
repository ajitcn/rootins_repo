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
        foreach (['Doctor', 'Patient', 'Farmassist'] as $module) {
            $layout = file_get_contents(ROOTPATH . 'app/Modules/' . $module . '/Views/layout.php');
            self::assertNotFalse($layout);
            self::assertStringContainsString('aria-label=', $layout);
            self::assertStringContainsString("base_url('system/logout')", $layout);
        }
    }
    public function testFarmassistMenuLinksMatchPermissionProtectedRoutes(): void
    {
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Views/layout.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Config/Routes.php');
        self::assertStringContainsString("prescription.view", $menu);
        self::assertStringContainsString("permission:prescription.view", $routes);
        self::assertStringContainsString("permission:medicine.create", $routes);
        self::assertStringContainsString("permission:medicine.edit", $routes);
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
        self::assertStringContainsString('return match ? `${match[3]}-${match[2]}-${match[1]}` : \'\'', $theme);
        self::assertStringContainsString("input[type=\"date\"]", $theme);
    }
}
