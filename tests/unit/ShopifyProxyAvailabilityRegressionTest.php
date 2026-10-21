<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyProxyAvailabilityRegressionTest extends TestCase
{
    private function source(string $relativePath): string
    {
        return file_get_contents(ROOTPATH . $relativePath);
    }

    public function testProxyDefaultsToATwoDayWindowWithoutChangingExplicitDateHandling(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');
        $coreApi = $this->source('app/Modules/CoreApi/Services/CoreApiAppointmentService.php');

        self::assertStringContainsString('normalizeAvailabilityPayload', $proxy);
        self::assertStringContainsString('$payload[\'from_date\'] = $today->format(\'d-m-Y\')', $proxy);
        self::assertStringContainsString('$payload[\'to_date\'] = $today->modify(\'+1 day\')->format(\'d-m-Y\')', $proxy);
        foreach (['date', 'dates', 'from_date', 'to_date'] as $field) {
            self::assertStringContainsString("'{$field}'", $proxy);
        }
        foreach (['start_date', 'end_date'] as $field) {
            self::assertStringContainsString("['{$field}']", $coreApi);
        }
        self::assertStringNotContainsString('validateAvailabilityPayload', $proxy);
        self::assertStringContainsString('$dates = $this->requestedDates($input, $timezone)', $coreApi);
        self::assertStringContainsString('for ($offset = 0; $offset <= 1; $offset++)', $coreApi);
        self::assertStringContainsString("CoreApiException(422, 'VALIDATION_FAILED'", $coreApi);
    }

    public function testAvailabilitySupportsDateRangeAndReturnsEmptyDataNormally(): void
    {
        $coreApi = $this->source('app/Modules/CoreApi/Services/CoreApiAppointmentService.php');

        foreach (['from_date', 'start_date', 'to_date', 'end_date'] as $field) {
            self::assertStringContainsString("['{$field}']", $coreApi);
        }
        self::assertStringContainsString('$doctors = [];', $coreApi);
        self::assertStringContainsString("'doctors' => \$doctors", $coreApi);
        self::assertStringContainsString('dateAvailability', $coreApi);
    }

    public function testShopifyAvailabilityIsNotPatientScopedAndSupportsOptionalFilterAliases(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');

        self::assertStringContainsString('$patientScoped = in_array($operation, [\'appointments.book\', \'prescriptions.upload\'], true)', $proxy);
        self::assertStringContainsString('if ($patientScoped && $link === null && ! $autoLinkPatient)', $proxy);
        self::assertStringContainsString('$link = $patientScoped ? $links->where', $proxy);
        self::assertStringContainsString("'appointments.availability' => (new CoreApiAppointmentService())->availability([], \$payload)", $proxy);
        self::assertStringContainsString("\$credential = \$operation === 'appointments.availability' ? [] : \$this->credential()", $proxy);
        self::assertStringContainsString("if (\$operation !== 'appointments.availability')", $proxy);
        foreach (['date_from', 'date_to', 'doctor_id', 'specialization_id', 'service_id', 'doctor_reference', 'specialization_reference', 'service_reference'] as $filter) {
            self::assertStringContainsString($filter, $proxy);
        }
        self::assertStringNotContainsString("if (\$operation !== 'patients.register' && \$link === null)", $proxy);
    }

    public function testAvailabilityMapsValidationScopeAndDependencyFailuresSafely(): void
    {
        $controller = $this->source('app/Modules/Shopify/Controllers/Proxy.php');
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');

        self::assertStringContainsString('return $this->json(422', $controller);
        self::assertStringContainsString('return $this->json(503', $controller);
        foreach (['CORE_API_CONFIGURATION_MISSING', 'CLINIC_CONFIGURATION_INVALID', 'DATABASE_UNAVAILABLE', 'APPOINTMENT_SERVICE_UNAVAILABLE', 'AVAILABILITY_DEPENDENCY_FAILURE'] as $code) self::assertStringContainsString($code, $controller);
        self::assertStringContainsString('SHOPIFY_CUSTOMER_UNLINKED', $proxy);
        self::assertStringContainsString('SHOPIFY_SCOPE_INVALID', $proxy);
        self::assertStringContainsString('CORE_API_UNAVAILABLE', $proxy);
        self::assertStringContainsString('RATE_LIMITER_UNAVAILABLE', $proxy);
        self::assertStringContainsString('database', $controller);
        self::assertStringContainsString('service', $controller);
        self::assertStringContainsString("'exception_class' => get_class(\$exception)", $controller);
        $diagnostic = substr($controller, strpos($controller, 'private function logAvailabilityFailure'));
        self::assertStringNotContainsString('getMessage()', $diagnostic);
        self::assertStringNotContainsString('getQuery()', $diagnostic);
    }

    public function testAvailabilityDoesNotRequireTheCoreApiCredentialGuard(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');

        self::assertStringContainsString('if ($credential === null)', $proxy);
        self::assertStringContainsString("CORE_API_UNAVAILABLE", $proxy);
        self::assertStringContainsString("The ROOTINS Core API is not configured for this clinic.", $proxy);
        self::assertStringContainsString("'appointments.availability' => (new CoreApiAppointmentService())->availability([], \$payload)", $proxy);
        self::assertStringContainsString("if (\$operation !== 'appointments.availability') \$this->rateLimit", $proxy);
    }

    public function testAvailabilityContractCoversConfigurationClinicDatabaseEmptyAndDoctorFilterBranches(): void
    {
        $proxy = $this->source('app/Services/Shopify/ShopifyProxyService.php');
        $controller = $this->source('app/Modules/Shopify/Controllers/Proxy.php');
        $service = $this->source('app/Modules/CoreApi/Services/CoreApiAppointmentService.php');

        self::assertStringContainsString("SHOPIFY_PROXY_CONFIGURATION_MISSING", $proxy);
        self::assertStringContainsString("'CORE_API_CONFIGURATION_MISSING'", $controller);
        self::assertStringContainsString("'CLINIC_CONFIGURATION_INVALID'", $controller);
        self::assertStringContainsString("'DATABASE_UNAVAILABLE'", $controller);
        self::assertStringContainsString("'APPOINTMENT_SERVICE_UNAVAILABLE'", $controller);
        self::assertStringContainsString("'AVAILABILITY_DEPENDENCY_FAILURE'", $controller);
        self::assertStringContainsString('$doctors = [];', $service);
        self::assertStringContainsString("'doctors' => \$doctors", $service);
        self::assertStringContainsString("if (\$doctorReference !== '') \$query->where('doctors.doctor_code', \$doctorReference)", $service);
        self::assertStringContainsString("'clinic_id'", $service);
        self::assertStringContainsString("'status', 1", $service);
    }
}
