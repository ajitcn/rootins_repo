<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AppointmentUiWorkflowTest extends CIUnitTestCase
{
    public function testAppointmentPagesKeepAuthorizationAndStandardizedActions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $admin = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/index.php');
        $queue = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment_queue.js');
        $doctorActions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');
        $patient = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/appointments.php');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        self::assertStringContainsString("permission:appointment.view", $routes);
        self::assertStringContainsString("permission:appointment.edit", $routes);
        self::assertStringContainsString('More Actions', $admin);
        self::assertStringContainsString('ri-status ri-status--', $admin);
        self::assertStringContainsString('modal-dialog-scrollable ri-scroll-modal', $admin);
        self::assertStringContainsString('ri-actions-menu', $queue);
        self::assertStringContainsString('rootinsDateToIso', $queue);
        self::assertStringContainsString('ri-status--', $queue);
        self::assertStringContainsString('consultationDatePassed', $doctorActions);
        self::assertStringContainsString('modal-dialog-scrollable', $patient);
        self::assertStringContainsString('table td.ri-status-cell', $css);
        self::assertStringContainsString('@media (max-width: 375px)', $css);
    }
}
