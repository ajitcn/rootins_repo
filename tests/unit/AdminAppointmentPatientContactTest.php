<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminAppointmentPatientContactTest extends CIUnitTestCase
{
    public function testAdminAppointmentContactQueryUsesTheLivePatientSchemaAndStableAliases(): void
    {
        $patientMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-08-27-100000_CreatePatients.php');
        $patientReferenceMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-160000_AddNormalizedMobileReferences.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
        $listView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/index.php');
        $detailView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/view.php');
        $calendarScript = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment_calendar.js');

        self::assertStringContainsString("'mobile_number'", $patientMigration);
        self::assertStringContainsString("'mobile_normalized'", $patientReferenceMigration);
        self::assertStringNotContainsString("'mobile'", $patientMigration);
        self::assertStringContainsString('patients.mobile_number AS patient_mobile', $controller);
        self::assertStringContainsString('patients.email AS patient_email', $controller);
        self::assertStringContainsString("orLike('patients.mobile_number', \$search)", $controller);
        self::assertStringNotContainsString('patients.' . 'mobile AS patient_mobile', $controller);
        self::assertStringContainsString("\$appointment['patient_mobile']", $listView);
        self::assertStringContainsString('$patientMobile', $listView);
        self::assertStringContainsString('$patientMobile', $detailView);
        self::assertStringContainsString('$patientEmail', $detailView);
        self::assertStringContainsString('event.patient_mobile', $calendarScript);
    }
}
