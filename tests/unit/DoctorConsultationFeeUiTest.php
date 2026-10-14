<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorConsultationFeeUiTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('common');
    }

    public function testFeeDateBoundaryUsesDisplayInputAndIsoStorage(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/DoctorConsultationFee.php');

        self::assertSame('2026-08-20', rootins_parse_date('20-08-2026', false));
        self::assertSame('20-08-2026', rootins_format_date('2026-08-20'));
        self::assertStringContainsString("'effective_from' => trim((string) \$this->request->getPost('effective_from'))", $controller);
        self::assertStringNotContainsString("'effective_from' => getYMD(", $controller);
        self::assertStringContainsString('$data = $validation[\'data\'];', $controller);
        self::assertStringContainsString('$data[\'effective_from\'] = $effectiveFrom;', $controller);
    }

    public function testFeeUiUsesCanonicalThemeAndProtectedActions(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/consultation_fee.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_fee.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_fee.js');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');

        self::assertStringContainsString('ri-status--green', $view);
        self::assertStringContainsString('ri-status--gray', $view);
        self::assertStringContainsString('data-submit-display-date="true"', $modal);
        self::assertStringContainsString("$('#consultation_fee_status').val(fee.status)", $javascript);
        self::assertStringContainsString("method=\"post\"", $view);
        self::assertStringContainsString("\$routes->post('delete/(:num)'", $routes);
        self::assertStringNotContainsString('bg-success', $view);
        self::assertStringNotContainsString('bg-primary', $view);
    }

    public function testFeeDateValidationCoversLeapAndInvalidDates(): void
    {
        self::assertSame('2024-02-29', rootins_parse_date('29-02-2024', false));
        self::assertNull(rootins_parse_date('29-02-2025', false));
        self::assertNull(rootins_parse_date('31-04-2026', false));
        self::assertNull(rootins_parse_date('', false));
    }
}
