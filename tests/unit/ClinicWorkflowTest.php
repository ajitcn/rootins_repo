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

    public function testClinicCreatePermissionMigrationIsScopedToAdminAndIdempotent(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-09-100000_EnsureClinicCreatePermissionForAdmin.php');

        self::assertStringContainsString("where('role_code', 'ADMIN')", $migration);
        self::assertStringContainsString("where('permission_key', 'clinic.create')", $migration);
        self::assertStringContainsString('if ($mapping === null)', $migration);
        self::assertStringContainsString("where('role_id', (int) \$role['id'])", $migration);
        self::assertStringContainsString("where('permission_id', (int) \$permission['id'])", $migration);
        self::assertStringContainsString('public function down()', $migration);
        self::assertStringNotContainsString("role_code', 'DOCTOR", $migration);
        self::assertStringNotContainsString("role_code', 'PATIENT", $migration);
        self::assertStringNotContainsString("role_code', 'PHARMACY_ASSISTANT", $migration);
    }

    public function testClinicCreatePermissionRepairCreatesMissingPermissionAndMapsCurrentAdminRole(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-09-110000_EnsureClinicCreatePermissionRecord.php');

        self::assertStringContainsString("where('permission_key', 'clinic.create')", $migration);
        self::assertStringContainsString("'permission_name' => 'Create Clinic'", $migration);
        self::assertStringContainsString("'permission_key' => 'clinic.create'", $migration);
        self::assertStringContainsString("where('role_code', 'ADMIN')", $migration);
        self::assertStringContainsString("where('role_id', (int) \$role['id'])", $migration);
        self::assertStringContainsString("where('permission_id', (int) \$permission['id'])", $migration);
        self::assertStringContainsString('public function down()', $migration);
        self::assertStringNotContainsString("where('role_code', 'DOCTOR')", $migration);
        self::assertStringNotContainsString("where('role_code', 'PATIENT')", $migration);
        self::assertStringNotContainsString("where('role_code', 'PHARMACY_ASSISTANT')", $migration);
    }

    public function testClinicEditPermissionRepairIsScopedToAdminAndEditActionUsesExistingFlow(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-09-120000_EnsureClinicEditPermissionRecord.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/clinic/index.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Clinic/Clinic.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/clinic/clinic.js');

        self::assertStringContainsString("where('permission_key', 'clinic.edit')", $migration);
        self::assertStringContainsString("'permission_name' => 'Edit Clinic'", $migration);
        self::assertStringContainsString("'permission_key' => 'clinic.edit'", $migration);
        self::assertStringContainsString("where('role_code', 'ADMIN')", $migration);
        self::assertStringContainsString('if ($mapping === null)', $migration);
        self::assertStringContainsString('public function down()', $migration);
        foreach (['DOCTOR', 'PATIENT', 'PHARMACY_ASSISTANT', 'RECEPTIONIST', 'TELECALLER'] as $unauthorizedRole) {
            self::assertStringNotContainsString("role_code', '{$unauthorizedRole}'", $migration);
        }

        self::assertStringContainsString("'canEdit' => \$this->hasPermission('clinic.edit')", $controller);
        self::assertStringContainsString("'filter' => 'permission:clinic.edit'", $routes);
        self::assertStringContainsString('Edit clinic', $view);
        self::assertStringContainsString('editClinicForm(', $view);
        self::assertStringContainsString('ROUTES.clinic.edit + clinicId', $script);
        self::assertStringContainsString("ROUTES.clinic.update", $script);
        self::assertStringContainsString("$('#clinicModal').modal('show')", $script);
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
        self::assertStringContainsString('id="clinic-create-button"', $view);
        self::assertStringContainsString('Create New Clinic', $view);
        self::assertStringContainsString('data-target="#clinicModal"', $view);
        self::assertStringContainsString('modal-dialog-scrollable', file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/clinic/modals/_clinic.php'));
        self::assertStringContainsString('! empty($canCreate)', $view);
        foreach (['Clinic', 'Contact', 'Location', 'Operations', 'Status', 'Actions', 'More Actions', 'ri-status--'] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringContainsString('doctors_count', $controller);
        self::assertStringContainsString('patients_count', $controller);
        self::assertStringContainsString('services_count', $controller);
        self::assertStringNotContainsString('class="text-nowrap"><button', $view);
        self::assertSame(1, substr_count($view, '<h1'));
        self::assertStringNotContainsString('class="page-heading"', $view);

        self::assertStringContainsString("hasPermission('clinic.create')", $controller);
        self::assertStringContainsString("\$this->json(403, false, 'You do not have permission to perform this action.')", $controller);

        $styles = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');
        self::assertStringContainsString('#clinicModal .modal-content', $styles);
        self::assertStringContainsString('#clinicModal .modal-body', $styles);
        self::assertStringContainsString('overflow-y: auto', $styles);
        self::assertStringContainsString('max-height: calc(100vh - 2rem)', $styles);
        self::assertStringContainsString('height: auto', $styles);
    }
}
