<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorLeaveDateHandlingTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('common');
    }

    public function testLeaveDatesRoundTripBetweenDisplayAndStorageFormats(): void
    {
        self::assertSame('2026-08-20', rootins_parse_date('20-08-2026', false));
        self::assertSame('2026-08-25', rootins_parse_date('25-08-2026', false));
        self::assertSame('20-08-2026', rootins_format_date('2026-08-20'));
        self::assertSame('25-08-2026', rootins_format_date('2026-08-25'));
    }

    public function testLeaveDatesRejectInvalidCalendarValuesAndHandleLeapYears(): void
    {
        self::assertSame('2024-02-29', rootins_parse_date('29-02-2024', false));
        self::assertNull(rootins_parse_date('29-02-2025', false));
        self::assertNull(rootins_parse_date('31-04-2026', false));
        self::assertNull(rootins_parse_date('', false));
        self::assertNull(rootins_parse_date('2026-08-20', false));
    }

    public function testLeaveControllerValidatesRawDisplayDatesBeforeIsoStorage(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/DoctorLeave.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_leave.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_leave.js');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/leave.php');

        self::assertStringContainsString("rootins_parse_date(\$this->request->getPost('start_date'), false)", $controller);
        self::assertStringContainsString("rootins_parse_date(\$this->request->getPost('end_date'), false)", $controller);
        self::assertStringContainsString("\$dateErrors['end_date'] = 'End Date must be greater than or equal to Start Date.'", $controller);
        self::assertStringNotContainsString("'start_date' => getYMD(", $controller);
        self::assertStringContainsString('name="start_date"', $modal);
        self::assertStringContainsString('name="end_date"', $modal);
        self::assertStringContainsString('data-submit-display-date="true"', $modal);
        self::assertStringContainsString("data: \$(this).serialize()", $javascript);
        self::assertStringContainsString('_dateOnly($row[\'start_date\'])', $view);
        self::assertStringContainsString('_dateOnly($row[\'end_date\'])', $view);
    }
}
