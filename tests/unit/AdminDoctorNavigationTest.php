<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminDoctorNavigationTest extends CIUnitTestCase
{
    public function testDashboardExposesPermissionAwareDoctorDestinations(): void
    {
        $dashboard = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/dashboard.php');

        self::assertStringContainsString("base_url('admin/doctors')", $dashboard);
        self::assertStringContainsString("base_url('admin/doctor-commissions')", $dashboard);
        self::assertStringContainsString("doctor_commission.view", $dashboard);
    }

    public function testDoctorCommissionUsesExplicitRoutesAndCanonicalStatusClasses(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/commission.php');

        self::assertStringContainsString("\$routes->get('/', 'DoctorCommission::index'", $routes);
        self::assertStringContainsString("\$routes->post('delete/(:num)'", $routes);
        self::assertStringContainsString('ri-status--green', $view);
        self::assertStringContainsString('ri-status--gray', $view);
        self::assertStringContainsString("method=\"post\"", $view);
        self::assertStringNotContainsString('bg-success', $view);
        self::assertStringNotContainsString('bg-primary', $view);
    }
}
