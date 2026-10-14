<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PatientSourceTrackingTest extends CIUnitTestCase
{
    public function testSupportedLabelsAndUnknownFallbackAreStable(): void
    {
        self::assertSame('Self', patient_source_label('self'));
        self::assertSame('Shopify', patient_source_label('shopify'));
        self::assertSame('Unknown', patient_source_label(null));
        self::assertSame('Unknown', patient_source_label('admin'));
    }

    public function testRegistrationServiceDoesNotTrustSubmittedSource(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString('$source[\'source_type\'] ?? \'self\'', $service);
        self::assertStringContainsString('unset($patientData[\'source_type\'])', $service);
        self::assertStringContainsString("['self', 'shopify']", $service);
        self::assertStringNotContainsString('$patientData[\'source_type\'] ?? \'admin\'', $service);
    }

    public function testAdminDoctorAndCoreApiSelectSourcesServerSide(): void
    {
        $admin = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Patient/Patient.php');
        $doctor = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $coreApi = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Controllers/V1.php');

        self::assertStringContainsString("['source_type' => 'self'", $admin);
        self::assertStringContainsString("['source_type' => 'self'", $doctor);
        self::assertStringContainsString('unset($payload[\'source_type\'])', $coreApi);
        self::assertStringContainsString("['source_type' => 'shopify'", $coreApi);
    }

    public function testExistingPatientUpdatesDoNotOverwriteSource(): void
    {
        $admin = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Patient/Patient.php');
        $doctor = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-07-140000_NormalizePatientRegistrationSource.php');

        self::assertStringNotContainsString("'source_type' =>", substr($admin, strpos($admin, 'public function update')));
        $doctorUpdate = substr($doctor, strpos($doctor, 'public function update'), strpos($doctor, 'public function index') - strpos($doctor, 'public function update'));
        self::assertStringNotContainsString("'source_type' =>", $doctorUpdate);
        self::assertStringContainsString("'null' => true", $migration);
        self::assertStringContainsString('Existing values are intentionally preserved', $migration);
    }

    public function testAdminDoctorAndClinicalDisplayContractsExist(): void
    {
        $files = [
            ROOTPATH . 'app/Modules/Admin/Views/pages/patient/index.php',
            ROOTPATH . 'app/Modules/Admin/Views/pages/patient/view.php',
            ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php',
            ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php',
            ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/index.php',
            ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/view.php',
            ROOTPATH . 'app/Modules/Doctor/Views/pages/appointment.php',
            ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php',
        ];

        foreach ($files as $file) {
            $view = file_get_contents($file);
            self::assertStringContainsString('patient_source_label', $view, $file);
            self::assertStringContainsString('patient_source_badge_class', $view, $file);
        }

        $doctorFilters = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        self::assertStringContainsString("'self' => 'Self'", $doctorFilters);
        self::assertStringContainsString("'shopify' => 'Shopify'", $doctorFilters);
        self::assertStringContainsString("'unknown' => 'Unknown'", $doctorFilters);
    }
}
