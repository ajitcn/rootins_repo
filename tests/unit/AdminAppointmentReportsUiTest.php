<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminAppointmentReportsUiTest extends CIUnitTestCase
{
    public function testAppointmentReportsKeepFilteringPaginationAndResponsiveUiContracts(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Appointment/Appointment.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/appointment/reports.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/appointment/appointment_reports.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        self::assertStringContainsString("'reports', 'Appointment::reports'", $routes);
        self::assertStringContainsString("'reports-data', 'Appointment::reportsData'", $routes);
        foreach (['rootins_parse_date($dateFromInput)', 'To Date must be on or after From Date.', 'service_name', 'reportSummary', 'pagination'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        foreach (['appointmentReportForm', 'reportDateFrom', 'reportDateTo', 'reportService', 'appointmentReportTable', 'appointmentReportPagination', 'printAppointmentReport'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        foreach (['validateDates', 'To Date must be on or after From Date.', 'report-page-button', 'ri-status--', 'retryAppointmentReport'] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        foreach (['.admin-report-filter-grid', '.admin-report-table', '.admin-report-pagination', '@media (max-width: 575px)', '@media print'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }
}
