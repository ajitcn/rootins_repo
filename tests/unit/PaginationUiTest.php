<?php

use CodeIgniter\Test\CIUnitTestCase;

final class PaginationUiTest extends CIUnitTestCase
{
    public function testSharedPaginationStylesAndModuleContractsExist(): void
    {
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/rootins-pagination.css');
        self::assertStringContainsString('rootins-pagination.css', $theme);
        foreach (['ri-pagination', 'ri-page-link.is-active', 'ri-page-ellipsis', 'pointer-events: none', '@media (max-width: 575.98px)'] as $contract) {
            self::assertStringContainsString($contract, $styles);
        }
        foreach ([
            'app/Modules/Admin/Views/parts/doctor_pagination.php',
            'app/Modules/Doctor/Views/parts/pagination.php',
            'app/Modules/Pharmacy/Views/parts/prescription_pagination.php',
            'app/Modules/Pharmacy/Views/parts/order_pagination.php',
            'app/Modules/Pharmacy/Views/parts/billing_pagination.php',
            'app/Modules/Pharmacy/Views/parts/inventory_pagination.php',
            'app/Modules/Pharmacy/Views/parts/notification_pagination.php',
            'app/Modules/Pharmacy/Views/parts/audit_pagination.php',
        ] as $file) {
            $source = file_get_contents(ROOTPATH . $file);
            self::assertStringContainsString('ri-pagination', $source, $file);
            self::assertStringContainsString('aria-current="page"', $source, $file);
            self::assertStringContainsString('aria-disabled="true"', $source, $file);
        }
    }
}
