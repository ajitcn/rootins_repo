<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorCommissionUiTest extends CIUnitTestCase
{
    public function testCommissionListUsesCanonicalResponsiveUi(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/commission.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_commission.php');

        self::assertStringContainsString('Commission plan list', $view);
        self::assertStringContainsString('data-label="Effective From"', $view);
        self::assertStringContainsString('ri-status--green', $view);
        self::assertStringContainsString('ri-status--gray', $view);
        self::assertStringContainsString('method="post"', $view);
        self::assertStringContainsString('data-submit-display-date="true"', $modal);
        self::assertStringContainsString('id="commission_value"', $modal);
        self::assertStringContainsString('id="doctor_commission_status"', $modal);
        self::assertStringNotContainsString('bg-success', $view);
        self::assertStringNotContainsString('bg-primary', $view);
    }

    public function testCommissionEditorUsesCorrectStatusAndValueControls(): void
    {
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor_commission.js');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/DoctorCommission.php');

        self::assertStringContainsString("$('#doctor_commission_status').val(commission.status)", $javascript);
        self::assertStringContainsString("$('#commissionValuePrefix').html", $javascript);
        self::assertStringContainsString("'commission_value' => trim((string) \$this->request->getPost('commission_value'))", $controller);
        self::assertStringContainsString('$data[\'commission_value\'] = (float) $data[\'commission_value\'];', $controller);
    }
}
