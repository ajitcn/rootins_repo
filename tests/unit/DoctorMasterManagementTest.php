<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorMasterManagementTest extends CIUnitTestCase
{
    public function testMasterRoutesAndPermissionsAreExplicitlyProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-19-121000_AddDoctorMasterPermissions.php');

        foreach (['admin/doctor-languages', 'admin/popular-treatments', 'doctor_language.view', 'doctor_language.manage', 'popular_treatment.view', 'popular_treatment.manage', "filter' => 'auth'"] as $contract) {
            self::assertStringContainsString($contract, $routes . $migration);
        }
        self::assertStringContainsString("\$routes->post('save'", $routes);
        self::assertStringContainsString("\$routes->post('toggle/(:num)'", $routes);
    }

    public function testDatabaseDesignHasReversibleMappingsAndUniqueness(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-19-120000_CreateDoctorLanguagesAndPopularTreatments.php');

        foreach (['doctor_languages', 'popular_treatments', 'doctor_language_map', 'doctor_treatment_map', 'addUniqueKey', 'addForeignKey', 'dropTable'] as $contract) {
            self::assertStringContainsString($contract, $migration);
        }
    }

    public function testDoctorCrudUsesActiveIdsAndTransactionalMappingReplacement(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/Doctor.php');

        foreach (['validateDoctorMasters', "where('status', 1)", 'transBegin', 'replaceDoctorLanguages', 'replaceDoctorTreatments', 'language_ids_present', 'treatment_ids_present'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        self::assertStringNotContainsString("getPost('language_name')", $controller);
        self::assertStringNotContainsString("getPost('treatment_name')", $controller);
    }

    public function testDoctorUiLoadsAndDisplaysMasterSelections(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_doctor.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor.js');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorModel.php');

        foreach (['name="language_ids[]"', 'name="treatment_ids[]"', 'class="form-control btSelect"', 'data-live-search="true"', 'data-actions-box="true"', 'multiple'] as $contract) {
            self::assertStringContainsString($contract, $modal);
        }
        self::assertStringContainsString('response.data.language_ids', $script);
        self::assertStringContainsString('response.data.treatment_ids', $script);
        self::assertStringContainsString('languages', $list . $model);
        self::assertStringContainsString('popular_treatments', $list . $model);
    }

    public function testMasterUiUsesCsrfAndReEnablesButtons(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/master.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_master.js');

        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('clearFormErrors', $script);
        self::assertStringContainsString('complete:', $script);
        self::assertStringContainsString('button.prop(\'disabled\', false)', $script);
    }

    public function testMasterModalUsesBootstrapTargetsAndLoadsAfterLibraries(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/master.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_master.js');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/layout.php');
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');

        self::assertSame(1, substr_count($view, 'id="doctorMasterModal"'));
        self::assertSame(1, substr_count($view, 'id="doctorMasterForm"'));
        self::assertStringContainsString('data-target="#doctorMasterModal"', $view);
        self::assertStringContainsString("section('scripts')", $view);
        self::assertStringContainsString("renderSection('scripts')", $theme);
        self::assertStringContainsString("$(modal).modal('hide')", $script);
        self::assertStringContainsString('doctor-master-modal', file_get_contents(ROOTPATH . 'public/assets/css/style.css'));
    }
}
