<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ServiceIndependentAvailabilityTest extends TestCase
{
    private string $availability;
    private string $booking;

    protected function setUp(): void
    {
        $this->availability = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAppointmentService.php');
        $this->booking = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentIntentService.php');
    }

    public function testAvailabilityIsDoctorScheduleBasedAndDoesNotRequireAService(): void
    {
        self::assertStringContainsString("'availability_mode' => 'doctor_schedule'", $this->availability);
        self::assertStringContainsString("'service_required_for_booking' => true", $this->availability);
        self::assertStringContainsString("'slot_duration_minutes' => 15", $this->availability);
        self::assertStringContainsString('$service = null;', $this->availability);
        self::assertStringContainsString("'service_reference' => 'service-0'", $this->availability);
        self::assertStringContainsString("! in_array(\$slotParts['service_reference'], ['service-0', \$serviceReference], true)", $this->booking);
    }

    public function testAvailabilityRetainsScheduleConflictAndHoldRules(): void
    {
        foreach (['doctor_schedules', 'DoctorLeaveModel', 'break_start_time', 'break_end_time', 'appointment_date', 'shopify_appointment_intents', "'pending_payment','paid'", 'max_patients', "'past'"] as $rule) {
            self::assertStringContainsString($rule, $this->availability);
        }
    }

    public function testBookingStillRequiresServiceAndValidatesDurationAndFeeOnTheServer(): void
    {
        foreach (['service_reference', 'slot_reference', 'idempotency_key', 'consent', 'serviceDuration', 'SERVICE_SLOT_INCOMPATIBLE', '$this->amount(', "'duration_minutes' => \$duration"] as $rule) {
            self::assertStringContainsString($rule, $this->booking);
        }
        self::assertStringNotContainsString("'amount' => " . '$input', $this->booking);
        self::assertStringNotContainsString("'duration_minutes' => " . '$input', $this->booking);
    }

    public function testGenericSlotReferenceIsAcceptedOnlyForTheSelectedDoctorDateAndTime(): void
    {
        self::assertStringContainsString("\$slotParts['doctor_reference'] !== \$doctorReference", $this->booking);
        self::assertStringContainsString("\$slotParts['date'] !== \$date", $this->booking);
        self::assertStringContainsString("\$slotParts['start_time'] !== substr((string) \$time, 0, 5)", $this->booking);
        self::assertStringContainsString("['service-0', \$serviceReference]", $this->booking);
    }
}
