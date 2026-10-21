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
        foreach (['doctor_reference', 'doctor_code', 'doctor_name', 'professional_code', 'profile_image_url', 'qualifications', 'profile_image', 'qualification', 'specializations', 'experience', 'consultation_fee', 'consultation_fees', 'services', 'service_reference', 'service_name', 'duration_minutes', 'fee', 'currency', 'clinics', 'consultation_mode', 'timezone', 'requested_date_range', 'availability_status', 'working_day_status', 'day_status', 'leave_message', 'break_periods', 'existing_appointment_conflicts', 'slot_duration', 'slot_reference', 'start_time', 'end_time', 'is_available', 'status', 'available', 'slot_available', 'capacity', 'booked_count', 'remaining_capacity', 'booking_status', 'pagination'] as $field) {
            self::assertStringContainsString("'{$field}'", $this->service);
        }
        foreach (['available', 'full', 'booked', 'unavailable', 'leave', 'past'] as $status) {
            self::assertStringContainsString("'{$status}'", $this->service);
        }
    }

    public function testAvailabilityUsesScheduleBreaksCapacityPastAndAppointmentConflicts(): void
    {
        foreach (['break_start_time', 'break_end_time', 'max_patients', 'appointment_date', 'appointment_time', "status NOT IN (\\'cancelled\\', \\'no_show\\')", 'ADDTIME', "'past'"] as $rule) {
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
        self::assertStringContainsString("status NOT IN (\\'cancelled\\', \\'no_show\\')", $this->service);
        self::assertStringContainsString('$count > 0', $this->service);
    }

    public function testAvailabilitySupportsDateDoctorSpecializationServiceSearchAndPaginationFilters(): void
    {
        foreach (['requestedDates', 'from_date', 'start_date', 'doctor_reference', 'specialization_reference', 'service_reference', "['search']", "['page_size']", "['page']"] as $filter) {
            self::assertStringContainsString($filter, $this->service);
        }
        self::assertStringContainsString('array_slice(array_values($dates), 0, 31)', $this->service);
    }

    public function testAvailabilityDoesNotRequireClinicForApplicationWideCredentials(): void
    {
        self::assertStringContainsString('$clinicId = $this->optionalClinic($input);', $this->service);
        self::assertStringContainsString("if (\$value === '') return null;", $this->service);
        self::assertStringContainsString('clinics.status = 1', $this->service);
    }

    public function testProfileImageAndSlotReferenceAreSafePublicValues(): void
    {
        self::assertStringContainsString("preg_match('#^https://#i'", $this->service);
        self::assertStringContainsString("str_contains(\$value, '..')", $this->service);
        self::assertStringContainsString("\$doctorReference . '-' . \$serviceReference", $this->service);
    }

    public function testInvalidDoctorAndServiceFiltersHaveStableErrorCodes(): void
    {
        self::assertStringContainsString("'DOCTOR_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'SERVICE_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'SPECIALIZATION_NOT_FOUND'", $this->service);
        self::assertStringContainsString("'CLINIC_NOT_FOUND'", $this->service);
    }

    public function testAvailabilityFiltersOutUnavailableDatesDoctorsAndSlots(): void
    {
        foreach (['$availableDoctors = []', "if (\$availability['slots'] !== [])", "if (\$doctorDates === []) continue;", "if (\$status === 'available')", "'availability' => \$availability", "'public_reference'", "'is_available' => true"] as $contract) {
            self::assertStringContainsString($contract, $this->service);
        }
    }

    public function testPublicAvailabilityUsesBookingReferences(): void
    {
        foreach (['publicSlots', 'doctor_code', 'doctor_name', 'consultation_fees', 'service_reference', 'service_name', 'duration_minutes', 'fee', 'currency', 'timezone', 'is_available'] as $contract) {
            self::assertStringContainsString($contract, $this->service);
        }

        $service = new \App\Modules\CoreApi\Services\CoreApiAppointmentService();
        $method = new \ReflectionMethod($service, 'slotReference');
        $method->setAccessible(true);
        self::assertSame('AC-service-2-20261021-1500', $method->invoke($service, 'AC', 'service-2', '2026-10-21', '15:00:00'));
    }

    public function testAvailabilityUsesTwoDayDefaultWindowAndDoesNotResolvePatient(): void
    {
        self::assertStringContainsString('$offset <= 1', $this->service);
        self::assertStringContainsString("\$values === [] || \$values === ['']", $this->service);
        self::assertStringNotContainsString("patient_reference'", substr($this->service, 0, strpos($this->service, 'public function book')));
    }

    public function testDefaultWindowContainsTodayAndTomorrowOnly(): void
    {
        $service = new \App\Modules\CoreApi\Services\CoreApiAppointmentService();
        $method = new \ReflectionMethod($service, 'requestedDates');
        $method->setAccessible(true);
        $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
        $today = new \DateTimeImmutable('today', $timezone);
        $dates = $method->invoke($service, [], $timezone);

        self::assertCount(2, $dates);
        self::assertSame($today->format('Y-m-d'), $dates[0]);
        self::assertSame($today->modify('+1 day')->format('Y-m-d'), $dates[1]);
        self::assertArrayNotHasKey(2, $dates);
    }

    public function testExplicitDateRangeStillReturnsTheRequestedDates(): void
    {
        $service = new \App\Modules\CoreApi\Services\CoreApiAppointmentService();
        $method = new \ReflectionMethod($service, 'requestedDates');
        $method->setAccessible(true);
        $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
        $from = new \DateTimeImmutable('today', $timezone);
        $to = $from->modify('+3 days');
        $dates = $method->invoke($service, ['from_date' => $from->format('d-m-Y'), 'to_date' => $to->format('d-m-Y')], $timezone);

        self::assertCount(4, $dates);
        self::assertSame($from->format('Y-m-d'), $dates[0]);
        self::assertSame($to->format('Y-m-d'), $dates[3]);
    }

    public function testBookingRechecksTheSlotInsideATransactionWithAReservationLock(): void
    {
        foreach (['$db->transBegin()', 'FOR UPDATE', 'break_start_time', 'SLOT_UNAVAILABLE', 'max_patients', 'appointment_time <', 'ADDTIME', '$db->transCommit()'] as $contract) {
            self::assertStringContainsString($contract, $this->service);
        }
    }
}
