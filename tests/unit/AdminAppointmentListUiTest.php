<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminAppointmentListUiTest extends CIUnitTestCase
{
    public function testAdminAppointmentListUsesGroupedColumnsAndPermissionAwareActions(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
        $leaveModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorLeaveModel.php');
        $scheduleModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorScheduleModel.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/index.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment.js');
        $actionsScript = file_get_contents(ROOTPATH . 'public/assets/js/actions-menu.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        foreach (['doctors.doctor_code', 'AppointmentConsultationEligibility', 'consultation_eligible', 'canConsult', 'canBilling'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }

        foreach (['>Patient</th>', '>Doctor</th>', '>Appointment</th>', '>Status</th>', '>Actions</th>', 'admin-appointment-col-patient', 'admin-appointment-col-actions', 'admin-appointment-primary', 'admin-appointment-date', 'admin-appointment-queue', 'ri-status ri-status--', 'cancel-appointment', 'Billing / payment', 'ri-actions-menu'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }

        self::assertStringNotContainsString('PATIENT / NUMBER / MOBILE', $view);
        self::assertStringNotContainsString('SERVICE / CLINIC', $view);
        self::assertStringContainsString("ROUTES.appointments.queueStatus + id", $script);
        self::assertStringContainsString("status: 'cancelled'", $script);
        self::assertStringContainsString('type="time"', $view);
        self::assertStringContainsString('step="900"', $view);
        self::assertStringContainsString('ROUTES.appointments.availableSlots', $script);
        self::assertStringContainsString('validateAppointmentTime', $script);
        self::assertStringContainsString("wrapper.closest('.admin-appointment-page')", $actionsScript);

        foreach (['admin-appointment-table-wrap', 'table-layout: fixed', 'admin-appointment-indicator--eligible', '@media (max-width: 375px)', '--ri-z-modal-popover'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }

    public function testAppointmentEditAndRescheduleActionsUseDelegatedBodyLevelModals(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/index.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        foreach (['data-appointment-id=', 'data-target="#appointmentModal"', 'data-target="#appointmentRescheduleModal"', 'id="appointmentRescheduleModal"', 'id="appointmentRescheduleForm"'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }

        foreach (["off('click.rootinsAppointments'", 'portalAppointmentModals', "attr('data-appointment-id')", 'hidden.bs.modal.rootinsAppointments', 'setBusy'] as $contract) {
            self::assertStringContainsString($contract, $script);
        }

        self::assertStringContainsString('.modal-backdrop { z-index: 1040; }', $css);
        self::assertStringContainsString('.modal { z-index: 1050; }', $css);
    }

    public function testAppointmentAvailabilityUsesScheduleLeaveBreakAndBookingRules(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment.js');
        $leaveModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorLeaveModel.php');
        $scheduleModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorScheduleModel.php');

        self::assertStringContainsString("'available-slots', 'Appointment::availableSlots'", $routes);
        foreach (['break_start_time', 'intervalBlockedByLeave', 'intervalBlockedByBreak', 'intervalBooked', 'Appointment time cannot be in the past.', 'appointment_time'] as $marker) {
            self::assertStringContainsString($marker, $controller);
        }
        self::assertStringContainsString('doctor_leaves', $leaveModel);
        self::assertStringContainsString('doctor_schedules', $scheduleModel);
        foreach (['appointment_date', 'doctor_id', 'duration_minutes', 'appointment_id', 'clearAppointmentSlots', 'The previous time is no longer available'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }
}
