<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GlobalPatientScopeTest extends TestCase
{
    public function testPatientsAreGlobalButClinicalTablesRemainScoped(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-21-160000_MakePatientClinicScopeOptional.php');
        $registration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $doctorPatients = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');

        self::assertStringContainsString("'null' => true", $migration);
        self::assertStringNotContainsString("where('clinic_id', (int) \$patientData['clinic_id'])", $registration);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $records);
        self::assertStringNotContainsString("where('patients.clinic_id', \$context['clinic_id'])", $doctorPatients);
    }

    public function testShopifyIdentityIsStoreCustomerUniqueAndReusesGlobalPatients(): void
    {
        $identity = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyCustomerIdentityService.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-27-120000_ScopeShopifyPatientIdentityKeysByStore.php');

        self::assertStringContainsString("where('shopify_store_domain', \$storeDomain)", $identity);
        self::assertStringContainsString("where('shopify_customer_id', \$customerId)", $identity);
        self::assertStringContainsString('private function activePatientKey', $identity);
        self::assertStringNotContainsString("where('clinic_id', \$clinicId)", $identity);
        self::assertStringContainsString('active_patient_key', $proxy);
        self::assertStringContainsString('migration_conflict', $migration);
    }

    public function testClinicalAccessChecksDoctorAndClinicRatherThanPatientClinic(): void
    {
        $appointment = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');
        $active = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyActivePrescriptionService.php');
        $pharmacy = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyScope.php');

        self::assertStringContainsString("where('clinic_id', \$scope['clinic_id'])", $appointment);
        self::assertStringContainsString("d.clinic_id = p.clinic_id", $active);
        self::assertStringContainsString("where('prescriptions.clinic_id', \$clinicId)", $pharmacy);
        self::assertStringNotContainsString('patients.clinic_id', $appointment . $active . $pharmacy);
    }
}
