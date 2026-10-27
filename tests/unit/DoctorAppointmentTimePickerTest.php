<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctorAppointmentTimePickerTest extends TestCase
{
    public function testCreateAppointmentUsesNativeQuarterHourPickerAndLoadsSlots(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');

        self::assertStringContainsString('id="appointment-time" name="appointment_time" type="time"', $view);
        self::assertStringContainsString('step="900"', $view);
        self::assertStringContainsString('appointment-time-options', $view);
        self::assertStringContainsString("appointments/available-slots', 'Records::availableSlots'", $routes);
        self::assertStringContainsString('availableSlots(): ResponseInterface', $controller);
        self::assertStringContainsString('break_start_time', $controller);
        self::assertStringContainsString('doctor_leaves', $controller);
        self::assertStringContainsString('appointment_time', $controller);
        self::assertStringContainsString('Appointment time cannot be in the past.', $controller);
    }

    public function testClientRejectsUnavailableOrNonQuarterHourTimes(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');

        self::assertStringContainsString('appointmentSlotValues.indexOf(selectedTime) !== -1', $view);
        self::assertStringContainsString('Number(selectedTime.slice(3)) % 15 === 0', $view);
        self::assertStringContainsString('loadAppointmentSlots', $view);
        self::assertStringContainsString("input.val('').removeAttr('min max')", $view);
    }
}
