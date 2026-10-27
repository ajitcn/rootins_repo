<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyAppointmentTest extends TestCase
{
    public function testPhaseThreeRoutesAndExternalBookingContractAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Appointment.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringContainsString('integrations/shopify/appointments/availability', $routes);
        self::assertStringContainsString('integrations/shopify/appointments/book', $routes);
        self::assertStringContainsString('appointments/(:num)/cancel', $routes);
        self::assertStringContainsString('appointments/(:num)/reschedule', $routes);
        self::assertStringContainsString("\$routes->post('integrations/shopify/appointments/book'", $routes);
        self::assertStringContainsString("'booking_source' => 'shopify'", $service);
        self::assertStringContainsString("'external_reference' => \$externalReference", $service);
        self::assertStringContainsString("'idempotency_key' => \$idempotencyKey", $service);
        self::assertStringContainsString('setStatusCode($status)', $controller);
    }

    public function testBookingMigrationHasExternalUniquenessAndOwnershipKeys(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-150000_CreateShopifyAppointmentBookings.php');

        foreach (['shopify_store_domain', 'external_reference', 'idempotency_key', 'patient_id', 'clinic_id', 'appointment_id', 'status'] as $field) {
            self::assertStringContainsString("'{$field}'", $migration);
        }

        self::assertStringContainsString("addUniqueKey(['shopify_store_domain', 'external_reference']", $migration);
        self::assertStringContainsString("addUniqueKey(['shopify_store_domain', 'idempotency_key']", $migration);
        self::assertStringContainsString("addForeignKey('patient_id', 'patients'", $migration);
        self::assertStringContainsString("addForeignKey('clinic_id', 'clinics'", $migration);
        self::assertStringContainsString("addForeignKey('appointment_id', 'appointments'", $migration);
    }

    public function testAvailabilityAndBookingDoNotAcceptClientOwnershipOrStatus(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Appointment.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringNotContainsString("\$payload['patient_id']", $controller);
        self::assertStringNotContainsString("\$payload['clinic_id']", $controller);
        self::assertStringNotContainsString("\$payload['status']", $controller);
        self::assertStringContainsString("->where('patient_id', \$scope['patient_id'])", $service);
        self::assertStringContainsString("->where('clinic_id', \$scope['clinic_id'])", $service);
        self::assertStringContainsString("->where('status', 1)", $service);
    }

    public function testHandoffCanBeVerifiedForAvailabilityButConsumedForBooking(): void
    {
        $handoff = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyHandoffService.php');
        $appointment = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringContainsString('public function verify(string $token)', $handoff);
        self::assertStringContainsString('$handoffService->consume($handoffToken)', $appointment);
        self::assertStringContainsString('public function availability', $appointment);
        self::assertStringContainsString('public function book', $appointment);
        self::assertStringContainsString('public function cancel', $appointment);
        self::assertStringContainsString('public function reschedule', $appointment);
    }

    public function testDateAndSlotRulesAreServerSideAndNoSensitiveResponseFieldsAreUsed(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringContainsString("private const DATE_FORMAT = 'd-m-Y'", $service);
        self::assertStringContainsString('DoctorLeaveModel', $service);
        self::assertStringContainsString('whereIn(\'status\', self::ACTIVE_APPOINTMENT_STATUSES)', $service);
        self::assertStringContainsString("'state' => \$state", $service);
        self::assertStringNotContainsString('password', $service);
        self::assertStringNotContainsString('otp', strtolower($service));
        self::assertStringNotContainsString('access_token', $service);
    }
}
