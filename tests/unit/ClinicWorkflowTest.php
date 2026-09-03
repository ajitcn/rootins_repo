<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
final class ClinicWorkflowTest extends TestCase
{
    public function testClinicRoutesProtectReadsAndWritesWithClinicPermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        self::assertStringContainsString("'filter' => 'permission:clinic.view'", $routes);
        self::assertStringContainsString("'filter' => 'permission:clinic.create'", $routes);
        self::assertStringContainsString("'filter' => 'permission:clinic.edit'", $routes);
        self::assertStringContainsString("'filter' => 'permission:clinic.delete'", $routes);
        self::assertStringContainsString("'view/(:num)'", $routes);
    }
    public function testClinicControllerHasValidationDuplicateHandlingAuditAndSafeErrors(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Clinic/Clinic.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/clinic/index.php');
        self::assertStringContainsString("'clinic_name' => 'required|min_length[3]|max_length[150]'", $controller);
        self::assertStringContainsString('Clinic name is already registered.', $controller);
        self::assertStringContainsString('Clinic code is already registered.', $controller);
        self::assertStringContainsString("admin.clinic_created", $controller);
        self::assertStringContainsString("admin.clinic_updated", $controller);
        self::assertStringContainsString("admin.clinic_status_changed", $controller);
        self::assertStringContainsString('Clinic could not be created safely.', $controller);
        self::assertStringContainsString('id="clinics-table"', $view);
        self::assertStringContainsString("admin/clinics/view/", $view);
        self::assertSame(1, substr_count($view, '<h1'));
        self::assertStringNotContainsString('class="page-heading"', $view);
    }
}
