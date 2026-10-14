<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminAppointmentCalendarUiTest extends CIUnitTestCase
{
    public function testCalendarKeepsRoutesDateContractsAndReadableEventDetails(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/calendar.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment_calendar.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        self::assertStringContainsString('$routes->get(\'calendar\', \'Appointment::calendar\'', $routes);
        self::assertStringContainsString('$routes->get(\'calendar-data\', \'Appointment::calendarData\'', $routes);
        self::assertStringContainsString("'calendarToday'", $controller);
        self::assertStringContainsString("'calendarTimezone'", $controller);
        foreach (['appointments.service_name', 'appointments.queue_position', 'doctors.doctor_code', 'patients.mobile_number AS patient_mobile', 'orLike(\'appointments.service_name\'', 'orLike(\'doctors.doctor_code\'', 'date_from', 'date_to', 'new \\DateTimeZone'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        foreach (['appointment-calendar-view', 'appointmentCalendarDate', 'appointmentCalendarDateFrom', 'appointmentCalendarDateTo', 'appointmentCalendarSearch', 'appointmentCalendarStatus', 'appointmentCalendarClinic', 'appointmentCalendarDoctor', 'appointmentCalendarDetailsModal', 'modal-dialog-scrollable', 'dd-mm-yyyy'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        foreach (['startOfWeek', 'parseDisplayDate', 'appointment_time', 'service_name', 'queue_position', 'ri-status--', 'appointmentCalendarDetailsModal', 'appointmentCalendarFilters', 'ROUTES.appointments.calendarData', 'state.today', "css('z-index', '1090')"] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        self::assertStringNotContainsString("window.location.href = ROUTES.appointments.view", $script);
        foreach (['.ri-calendar-filters', '.appointment-calendar-month-grid', '.appointment-calendar-week-grid', '.appointment-calendar-event', 'overflow-x: auto', '.appointment-calendar-detail-grid', '@media (max-width: 375px)', '@media (max-width: 320px)', 'grid-template-columns: minmax(0, 2fr)', 'max-width: 100%', 'z-index: 1090'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }
}
