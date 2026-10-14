<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminAppointmentListUiTest extends CIUnitTestCase
{
    public function testAdminAppointmentListUsesGroupedColumnsAndPermissionAwareActions(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
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
        self::assertStringContainsString("wrapper.closest('.admin-appointment-page')", $actionsScript);

        foreach (['admin-appointment-table-wrap', 'table-layout: fixed', 'admin-appointment-indicator--eligible', '@media (max-width: 375px)', '--ri-z-modal-popover'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }
}
