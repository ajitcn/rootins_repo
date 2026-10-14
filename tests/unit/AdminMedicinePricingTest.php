<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminMedicinePricingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMedicineFormAndListExposePriceWithFieldError(): void
    {
        $view = file_get_contents($this->root . '/app/Modules/Admin/Views/pages/medicine/index.php');

        foreach (['name="unit_price"', 'unit_priceErr', 'Unit price', 'Unit Price'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        $js = file_get_contents($this->root . '/public/assets/js/admin/medicine/medicine.js');
        self::assertStringContainsString("'unit_price'", $js);
    }

    public function testControllerNormalizesAndValidatesNonNegativeDecimalPrices(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Admin/Controllers/Medicine/Medicine.php');

        foreach (['unit_price', 'cannot be negative', 'number_format((float) $price, 2', 'admin.medicine_price_updated', 'previous_unit_price'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPharmacyUsesDatabasePriceForOrderAndInvoiceItems(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/OrderFulfilmentService.php');

        foreach (['allocation[\'medicine\'][\'unit_price\']', 'lineTotal', 'InvoiceItemModel', 'order:' ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        self::assertStringNotContainsString("getPost('unit_price')", $source);
    }

    public function testZeroPriceIsExplicitlySupported(): void
    {
        $view = file_get_contents($this->root . '/app/Modules/Admin/Views/pages/medicine/index.php');
        self::assertStringContainsString('Zero is allowed for no-charge medicines.', $view);
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        self::assertStringContainsString("?? 0", $source);
    }
}
