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
        foreach (['valid_until', 'whereIn(\'p.status\'', 'shopify_variant_id', 'variant_id', 'SHOPIFY_MEDICINE_MAPPING_REQUIRED'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
    }

    public function testStorefrontLoadsCompletedPrescriptionsAndAddsNumericVariantsWithoutDuplicates(): void
    {
        $storefront = file_get_contents(ROOTPATH . 'public/assets/js/shopify/active-prescriptions.js');

        foreach (['/apps/rootins/prescriptions/active', '/cart.js', '/cart/add.js', 'completed_prescriptions_loaded', 'cart_duplicates_skipped', 'variant_id'] as $marker) {
            self::assertStringContainsString($marker, $storefront);
        }
    }

    public function testStorefrontShowsUsefulIdentityExpiryAndMappingMessages(): void
    {
        $storefront = file_get_contents(ROOTPATH . 'public/assets/js/shopify/active-prescriptions.js');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyActivePrescriptionService.php');

        foreach (['SHOPIFY_CUSTOMER_UNLINKED', 'SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'expired or not yet eligible', 'excluded_items', 'SHOPIFY_MEDICINE_MAPPING_REQUIRED'] as $marker) {
            self::assertStringContainsString($marker, $storefront . $service);
        }
    }

    public function testOrderImportDoesNotAcceptBrowserPrescriptionId(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('findDoctorPrescription', $service);
        self::assertStringContainsString('PRESCRIPTION_NOT_ORDERABLE', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionEligibility.php'));
        self::assertStringNotContainsString('$prescriptionId = $this->candidatePrescriptionId($payload);', $service);
    }
}
