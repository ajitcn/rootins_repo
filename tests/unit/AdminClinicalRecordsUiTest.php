<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminClinicalRecordsUiTest extends CIUnitTestCase
{
    public function testClinicalRecordsKeepRoutesPermissionsAndGroupedUiContracts(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $consultationController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Consultation/Consultation.php');
        $prescriptionController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Prescription/Prescription.php');
        $consultation = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/consultation/index.php');
        $consultationView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/consultation/view.php');
        $prescription = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/prescription/index.php');
        $prescriptionView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/prescription/view.php');
        $filter = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/parts/clinical_filter.php');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        foreach (['permission:consultation.view', 'permission:consultation.create', 'permission:prescription.view', 'permission:prescription.create'] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }
        foreach (['patients.mobile_number AS patient_mobile', 'rootins_parse_date($dateFromInput)', 'appointments.service_name', 'diagnostic_tests', 'diet_plan'] as $contract) {
            self::assertStringContainsString($contract, $consultationController);
        }
        foreach (['patients.mobile_number AS patient_mobile', 'appointments.service_name', 'rootins_parse_date($dateFromInput)'] as $contract) {
            self::assertStringContainsString($contract, $prescriptionController);
        }
        foreach ([$consultation, $prescription] as $page) {
            self::assertStringContainsString('admin-clinical-page', $page);
            self::assertStringContainsString('admin-clinical-table', $page);
            self::assertStringContainsString('More Actions', $page);
            self::assertStringContainsString('ri-status ri-status--', $page);
            self::assertStringContainsString('modal-dialog-scrollable ri-scroll-modal', $page);
        }
        foreach ([$consultationView, $prescriptionView] as $detail) {
            self::assertStringContainsString('clinical-detail-card', $detail);
            self::assertStringContainsString('clinical-wrap', $detail);
            self::assertStringContainsString('More Actions', $detail);
        }
        self::assertStringContainsString('modal-dialog-scrollable modal-lg', $filter);
        self::assertStringContainsString('dd-mm-yyyy', $filter);
        foreach (['.admin-clinical-table', '.ri-actions-menu', '.clinical-detail-grid', '@media (max-width: 375px)', '@media (max-width: 320px)'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }
}
