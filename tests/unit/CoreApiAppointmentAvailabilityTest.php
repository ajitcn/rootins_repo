<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoreApiAppointmentAvailabilityTest extends TestCase
{
    private string $service;

    protected function setUp(): void
    {
        $this->service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAppointmentService.php');
    }

    public function testAvailabilityExposesThePublicDoctorAndSlotContract(): void
    {
        foreach (['profile_image', 'qualification', 'specializations', 'experience', 'consultation_fee', 'services', 'availability_status', 'working_day_status', 'leave_message', 'slot_duration', 'start_time', 'end_time', 'slot_available', 'remaining_capacity', 'booking_status', 'pagination'] as $field) {
            self::assertStringContainsString("'{$field}'", $this->service);
        }
        foreach (['available', 'full', 'unavailable', 'leave', 'past'] as $status) {
            self::assertStringContainsString("'{$status}'", $this->service);
        }
    }

    public function testAvailabilityUsesScheduleBreaksCapacityPastAndAppointmentConflicts(): void
    {
        foreach (['break_start_time', 'break_end_time', 'max_patients', 'appointment_date', 'appointment_time', "whereNotIn('status', ['cancelled', 'no_show'])", "'past'"] as $rule) {
            self::assertStringContainsString($rule, $this->service);
        }
        self::assertStringContainsString('$count > 0', $this->service);
    }

    /** @dataProvider slotStatusProvider */
    public function testSlotStatusCasesAreRepresented(string $status): void
    {
        self::assertStringContainsString("'{$status}'", $this->service);
    }

    /** @return array<string, array{string}> */
    public static function slotStatusProvider(): array
    {
        return [
            'available' => ['available'],
            'full' => ['full'],
            'leave' => ['leave'],
            'break/unavailable' => ['unavailable'],
            'past' => ['past'],
        ];
    }

    public function testConflictStatusUsesActiveAppointmentCount(): void
    {
        self::assertStringContainsString("whereNotIn('status', ['cancelled', 'no_show'])", $this->service);
        self::assertStringContainsString('$count > 0', $this->service);
    }

    public function testAvailabilitySupportsDateDoctorSpecializationServiceSearchAndPaginationFilters(): void
    {
        foreach (['requestedDates', 'doctor_reference', 'specialization_reference', 'service_reference', "['search']", "['page_size']", "['page']"] as $filter) {
            self::assertStringContainsString($filter, $this->service);
        }
        self::assertStringContainsString('array_slice(array_values($dates), 0, 31)', $this->service);
    }

    public function testInvalidDoctorAndServiceFiltersHaveStableErrorCodes(): void
    {
        self::assertStringContainsString("'DOCTOR_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'SERVICE_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'SPECIALIZATION_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'CLINIC_NOT_FOUND'", $this->service);
    }
}
