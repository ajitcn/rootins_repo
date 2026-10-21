<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyClinicalScopeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testShopifyClinicalOperationsDoNotLoadClinicCredential(): void
    {
        $source = file_get_contents($this->root . '/app/Services/Shopify/ShopifyProxyService.php');
        self::assertIsString($source);
        self::assertStringContainsString('$credential = $operation === \'prescriptions.upload\' ? $this->credential() : [];', $source);
        self::assertStringNotContainsString("\$patient['clinic_id']", $source);
    }

    public function testRegistrationDoesNotReturnClinicScopeFailure(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Shopify/Controllers/Proxy.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('PATIENT_CLINIC_SCOPE_INVALID', $source);
        self::assertStringContainsString('SHOPIFY_PROXY_CONFIGURATION_INVALID', $source);
    }

    public function testPatientRegistrationServiceAllowsGlobalShopifyPatients(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Admin/Services/PatientRegistrationService.php');
        self::assertIsString($source);
        self::assertStringContainsString("\$sourceType !== 'shopify' && empty(\$patientData['clinic_id'])", $source);
        self::assertStringContainsString("if ((int) (\$patientData['clinic_id'] ?? 0) > 0", $source);
    }

    public function testDoctorVisibilityUsesClinicalRelationshipsNotPatientClinic(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Doctor/Controllers/Patient.php');
        self::assertIsString($source);
        self::assertStringContainsString('$consultationPatients', $source);
        self::assertStringContainsString('$prescriptionPatients', $source);
        self::assertStringContainsString("whereIn('patients.id', \$appointmentPatients)", $source);
        self::assertStringNotContainsString("\$query->where('patients.clinic_id', \$context['clinic_id'])", $source);
    }

    public function testPatientClinicScopeIsNullableByMigration(): void
    {
        $source = file_get_contents($this->root . '/app/Database/Migrations/2026-10-21-160000_MakePatientClinicScopeOptional.php');
        self::assertIsString($source);
        self::assertStringContainsString("'null' => true", $source);
    }

    public function testAdminPatientReadVisibilityIsGlobal(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Admin/Controllers/Patient/Patient.php');
        self::assertIsString($source);
        $index = substr($source, strpos($source, 'public function index()'), strpos($source, 'public function registration()') - strpos($source, 'public function index()'));
        $viewStart = strpos($source, 'public function view(int $id)');
        $view = substr($source, $viewStart, strpos($source, 'public function patientTimeline', $viewStart) - $viewStart);
        self::assertStringNotContainsString("where('patients.clinic_id', \$this->clinicScope())", $index);
        self::assertStringNotContainsString("where('patients.clinic_id', \$this->clinicScope())", $view);
    }

    public function testAppointmentDerivesClinicFromSelectedDoctorAndUsesBillingError(): void
    {
        $source = file_get_contents($this->root . '/app/Services/Shopify/ShopifyAppointmentIntentService.php');
        self::assertIsString($source);
        self::assertStringContainsString("where('doctor_code', \$doctorReference)->where('status', 1)", $source);
        self::assertStringContainsString("BILLING_CLINIC_UNAVAILABLE", $source);
    }
}
