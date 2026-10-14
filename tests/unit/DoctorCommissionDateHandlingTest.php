<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorCommissionDateHandlingTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('common');
    }

    public function testUserFacingDateNormalizesToIsoForCommissionStorage(): void
    {
        self::assertSame('2026-02-28', rootins_parse_date('28-02-2026', false));
        self::assertSame('28-02-2026', rootins_format_date('2026-02-28'));
    }

    public function testLeapYearAndInvalidDatesAreHandledExactly(): void
    {
        self::assertSame('2024-02-29', rootins_parse_date('29-02-2024', false));
        self::assertNull(rootins_parse_date('29-02-2025', false));
        self::assertNull(rootins_parse_date('31-04-2026', false));
    }

    public function testEmptyOptionalDateRemainsEmptyAtBoundary(): void
    {
        self::assertNull(rootins_parse_date('', false));
        self::assertSame('', rootins_format_date(null));
    }

    public function testCommissionControllerValidatesDisplayValueBeforeNormalizing(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/DoctorCommission.php');

        self::assertStringContainsString("'effective_from' => trim((string) \$this->request->getPost('effective_from'))", $controller);
        self::assertStringNotContainsString("'effective_from' => getYMD(", $controller);
        self::assertStringContainsString('$effectiveFrom = rootins_parse_date($data[\'effective_from\'], false);', $controller);
        self::assertStringContainsString('$data = $validation[\'data\'];', $controller);
        self::assertStringContainsString('$data[\'effective_from\'] = $effectiveFrom;', $controller);
        self::assertStringContainsString('$commission[\'effective_from\'] = _dateOnly($commission[\'effective_from\']);', $controller);
    }

    public function testCommissionEditKeepsTheDisplayDateForTheDatepicker(): void
    {
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_commission.js');

        self::assertStringContainsString("setSharedDateValue('#effective_from', commission.effective_from)", $javascript);
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_commission.php');
        self::assertStringContainsString('name="effective_from"', $modal);
        self::assertStringContainsString('data-submit-display-date="true"', $modal);
    }
}
