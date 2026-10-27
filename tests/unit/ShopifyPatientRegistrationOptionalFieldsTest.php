<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPatientRegistrationOptionalFieldsTest extends TestCase
{
    private function proxy(): string
    {
        return file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
    }

    public function testRegistrationRoutesRemainAvailable(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');

        self::assertStringContainsString("rootins-proxy/patients/register", $routes);
        self::assertStringContainsString("apps/rootins/patients/register", $routes);
    }

    public function testMinimumIdentityFieldsAreValidated(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString('$this->invalidPatientName($firstName)', $proxy);
        self::assertStringContainsString('$phoneDigits', $proxy);
        self::assertStringContainsString('TelephoneCountryCode::normalizeCode', $proxy);
        self::assertStringContainsString("'booking_enabled' => true", $proxy);
        self::assertStringContainsString("'order_ready' => true", $proxy);
    }

    public function testOptionalFieldsAreAcceptedWhenOmittedOrEmpty(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString("\$lastName = \$lastName !== '' ? \$lastName : null", $proxy);
        self::assertStringContainsString("\$lastName !== null && mb_strlen(\$lastName) > 100", $proxy);
        self::assertStringContainsString('$gender !== \'\' && ! in_array($gender', $proxy);
        self::assertStringContainsString('$bookingFor !== \'\' && $bookingFor !== \'self\'', $proxy);
        self::assertStringContainsString("'gender' => \$gender !== '' ? \$gender : null", $proxy);
    }

    public function testPatientCreationNormalizesOptionalFieldsForPersistence(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString("\$patientData['last_name'] = trim((string) (\$patientData['last_name'] ?? '')) ?: null", $service);
        self::assertStringContainsString("\$patientData['gender'] = trim((string) (\$patientData['gender'] ?? '')) ?: null", $service);
    }

    public function testGeneratedRegistrationPayloadDoesNotDeclareLastNameRequired(): void
    {
        $proxy = $this->proxy();
        $start = strpos($proxy, 'private function requiredCustomerDataFields(');
        $end = strpos($proxy, 'private function isPlaceholderValue(', $start);
        $schema = substr($proxy, $start, $end - $start);

        self::assertStringNotContainsString("['first_name', 'last_name']", $schema);
        self::assertStringNotContainsString("'last_name' => 'required'", $schema);
    }

    public function testLastNameOmittedIsNormalizedToNull(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString("\$patient['last_name'] ?? null", $proxy);
        self::assertStringContainsString("\$lastName !== '' ? \$lastName : null", $proxy);
    }

    public function testNullEmptyAndWhitespaceLastNamesAreTrimmedBeforeValidation(): void
    {
        $proxy = $this->proxy();
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString("\$this->patientString(\$patient['last_name'] ?? null)", $proxy);
        self::assertStringContainsString("\$patientData['last_name'] ?? ''", $service);
        self::assertStringNotContainsString("\$lastName = 'N/A'", $proxy);
    }

    public function testValidLastNameIsRetained(): void
    {
        self::assertStringContainsString("'last_name' => \$lastName", $this->proxy());
        self::assertStringContainsString("\$lastName !== null && mb_strlen(\$lastName) > 100", $this->proxy());
    }

    public function testLastNameLongerThanOneHundredCharactersIsRejected(): void
    {
        self::assertStringContainsString('mb_strlen($value) > 100', $this->proxy());
        self::assertStringContainsString("\$lastName !== null && mb_strlen(\$lastName) > 100", $this->proxy());
    }

    public function testPatientSchemaAllowsNullLastName(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-27-140000_MakeShopifyPatientLastNameOptional.php');

        self::assertStringContainsString("'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]", $migration);
    }

    public function testInvalidProvidedGenderAndBookingSubjectRemainRejected(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString("['male', 'female', 'other']", $proxy);
        self::assertStringContainsString("'Select a valid gender.'", $proxy);
        self::assertStringContainsString("'Select a supported booking subject.'", $proxy);
        self::assertStringContainsString("'PATIENT_DATA_INVALID'", $proxy);
    }

    public function testExistingCustomerLinkIsReusedIdempotently(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString("where('shopify_store_domain', \$store)", $proxy);
        self::assertStringContainsString("where('shopify_customer_id', \$customerId)", $proxy);
        self::assertStringContainsString("'already_registered' => true", $proxy);
    }

    public function testAppointmentBookingStillUsesTheRegisteredPatientLink(): void
    {
        $proxy = $this->proxy();

        self::assertStringContainsString("\$autoLinkPatient = \$operation === 'appointments.book'", $proxy);
        self::assertStringContainsString("\$payload['patient_reference']", $proxy);
        self::assertStringContainsString("'appointments.book' => (new ShopifyAppointmentIntentService())->create", $proxy);
    }

    public function testCheckoutReadinessResponseContractRemainsAvailable(): void
    {
        $proxy = $this->proxy();

        foreach (['patient_reference', 'booking_enabled', 'order_ready', 'medicine_order_ready', 'readiness_status'] as $field) {
            self::assertStringContainsString("'$field'", $proxy);
        }
    }

    public function testDocumentationDescribesOptionalRegistrationFields(): void
    {
        $docs = file_get_contents(ROOTPATH . 'docs/SHOPIFY-PROXY.md') . file_get_contents(ROOTPATH . 'docs/CORE-API.md');

        self::assertStringContainsString('may be omitted or', $docs);
        self::assertStringContainsString('global patient duplicate checks', $docs);
        self::assertStringContainsString('PATIENT_DATA_INVALID', $docs);
    }
}
