<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorDirectoryUiTest extends CIUnitTestCase
{
    public function testDoctorsTableUsesGroupedResponsiveColumnsAndGlobalStatuses(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorModel.php');

        foreach (['Doctor', 'Contact', 'Professional Details', 'Availability', 'Status', 'Actions'] as $heading) {
            self::assertStringContainsString('>' . $heading . '<', $view);
        }
        self::assertStringContainsString('More Actions', $view);
        self::assertStringContainsString("\$can('doctor_fee.view')", $view);
        self::assertStringContainsString("\$can('doctor_commission.view')", $view);
        self::assertStringContainsString("\$can('doctor_document.view')", $view);
        self::assertStringContainsString("\$can('doctor_schedule.view')", $view);
        self::assertStringContainsString("\$can('doctor_leave.view')", $view);
        self::assertStringContainsString("\$can('doctor.delete')", $view);
        self::assertStringNotContainsString('aria-label="View ', $view);
        self::assertStringContainsString('ri-status--<?=', $view);
        self::assertStringContainsString("? 'green' : 'gray'", $view);
        self::assertStringContainsString("? 'orange' : 'green'", $view);
        self::assertStringContainsString('data-label="Professional Details"', $view);
        self::assertStringContainsString('data-label="Availability"', $view);
        self::assertStringContainsString("base_url('admin/doctors/view/'", $view);
        self::assertStringContainsString('consultation_fee', $model);
        self::assertStringContainsString('schedule_summary', $model);
        self::assertStringContainsString('has_upcoming_leave', $model);
    }

    public function testDoctorDirectoryPreservesExistingFilterAndPaginationContracts(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/Doctor.php');

        foreach (['doctorFilterModal', 'doctor-filter-search', 'doctor-filter-status', 'doctor-page-size', 'Doctor pagination', 'editDoctorForm'] as $contract) {
            self::assertStringContainsString($contract, $view . $controller);
        }
        self::assertStringContainsString('getDoctorsPage', $controller);
        self::assertStringNotContainsString('table-striped', $view);
    }
}
