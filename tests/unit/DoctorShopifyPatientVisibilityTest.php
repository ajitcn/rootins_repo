<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorShopifyPatientVisibilityTest extends CIUnitTestCase
{
    public function testDoctorSearchAndAppointmentScopesAllowAuthorizedShopifyPatients(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $patients = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');

        foreach ([$records, $patients] as $source) {
            self::assertStringContainsString('shopifyVisiblePatientIds', $source);
            self::assertStringContainsString("where('link_status', 'linked')", $source);
            self::assertStringContainsString("where('patients.status', 1)", $source);
            self::assertStringContainsString("where('patients.deleted_at', null)", $source);
            self::assertStringContainsString("new ShopifyConfiguration())->clinicStatus()", $source);
        }
        self::assertStringContainsString("orLike('patients.email', \$search)", $records);
        self::assertStringContainsString("orLike('shopify_patient_identity_links.shopify_customer_id', \$search)", $records);
        self::assertStringContainsString("orLike('shopify_patient_identity_links.shopify_customer_id', \$search)", $patients);
    }

    public function testDoctorAuthorizationStillRequiresAnExistingDoctorScopeRelationship(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $patients = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');

        foreach ([$records, $patients] as $source) {
            self::assertStringContainsString("orWhereIn('patients.id', \$shopifyPatientIds)", $source);
            self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $source);
            self::assertStringContainsString("where('link_status', 'linked')", $source);
        }

        self::assertStringContainsString("whereIn('patients.id', \$appointmentPatients)", $records);
        self::assertStringContainsString("whereIn('patients.id', \$appointmentPatients)", $patients);
        self::assertStringContainsString("orWhereIn('patients.id', \$consultationPatients)", $patients);
        self::assertStringContainsString("orWhereIn('patients.id', \$prescriptionPatients)", $patients);
        self::assertStringContainsString("orWhereIn('patients.id', \$registeredPatients)", $records);
        self::assertStringContainsString("orWhereIn('patients.id', \$registeredPatients)", $patients);
    }
}
