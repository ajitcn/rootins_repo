<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyActivePrescriptionEndpointTest extends TestCase
{
    public function testCustomerScopedActivePrescriptionContractIsPresent(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/ActivePrescriptions.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyActivePrescriptionService.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');

        self::assertStringContainsString("apps/rootins/prescriptions/active", $routes);
        foreach (['prescriptions.active', 'logged_in_customer_id', 'store_domain', 'customer_id'] as $marker) {
            self::assertStringContainsString($marker, $controller . $service . $proxy);
        }
        foreach (['valid_until', 'whereIn(\'p.status\'', 'shopify_variant_id', 'SHOPIFY_MEDICINE_MAPPING_REQUIRED'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
    }

    public function testOrderImportDoesNotAcceptBrowserPrescriptionId(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('findDoctorPrescription', $service);
        self::assertStringContainsString('PRESCRIPTION_EXPIRED', $service);
        self::assertStringNotContainsString('$prescriptionId = $this->candidatePrescriptionId($payload);', $service);
    }
}
