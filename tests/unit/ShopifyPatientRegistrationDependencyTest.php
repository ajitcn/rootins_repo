<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPatientRegistrationDependencyTest extends TestCase
{
    public function testRegistrationDependenciesHaveSafeStageSpecificCodes(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        $identityMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-140000_CreateShopifyPatientIdentityLinks.php');
        $patientModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PatientModel.php');
        $registration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        foreach ([
            'PATIENT_REGISTRATION_DATABASE_UNAVAILABLE',
            'PATIENT_IDENTITY_LINK_UNAVAILABLE',
            'SHOPIFY_PROXY_CONFIGURATION_INVALID',
            'PATIENT_REGISTRATION_DEPENDENCY_UNAVAILABLE',
            'PATIENT_REGISTRATION_INTERNAL_ERROR',
        ] as $code) {
            self::assertStringContainsString($code, $controller . $service);
        }

        foreach (['shopify_store_domain', 'shopify_customer_id', 'patient_id', 'link_status', 'active_patient_key'] as $column) {
            self::assertStringContainsString("'{$column}'", $identityMigration);
        }
        self::assertStringContainsString("addUniqueKey(['shopify_store_domain', 'shopify_customer_id'])", $identityMigration);
        foreach (['clinic_id', 'first_name', 'last_name', 'telephone_country_code_id', 'mobile_number', 'mobile_normalized', 'source_type', 'source_reference'] as $field) {
            self::assertStringContainsString("'{$field}'", $patientModel . $registration);
        }
        self::assertStringContainsString('$this->db->transRollback();', $registration);
    }

    public function testRegistrationDependencyResponsesContainOnlySafeCodeAndCorrelationId(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');

        self::assertStringContainsString("'correlation_id' => \$this->requestCorrelationId", $controller);
        self::assertStringContainsString("'status' => false", $controller);
        self::assertStringContainsString("private function registrationDependencyError", $controller);
        self::assertStringNotContainsString('$exception->getMessage()', substr($controller, strpos($controller, 'private function registrationDependencyError')));
    }
}
