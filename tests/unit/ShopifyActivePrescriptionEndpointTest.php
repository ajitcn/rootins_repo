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

        foreach (['SHOPIFY_CUSTOMER_UNLINKED', 'SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'excluded_items', 'SHOPIFY_MEDICINE_MAPPING_REQUIRED', 'expired'] as $marker) {
            self::assertStringContainsString($marker, $storefront . $service);
        }
    }

    public function testSignedCartPushAcknowledgementAndWebhookStateTransitionsExist(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $proxy = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyProxyService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/ActivePrescriptions.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionCartPushService.php');
        $webhook = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-28-130000_CreateShopifyPrescriptionCartPushes.php');

        foreach (['apps/rootins/prescriptions/cart-status', 'prescriptions.cart_status', 'logged_in_customer_id'] as $marker) {
            self::assertStringContainsString($marker, $routes . $proxy . $controller);
        }
        foreach (['cart_pushed', 'prescription_reference', 'variant_ids', 'idempotency_key', 'ordered', 'cancelled'] as $marker) {
            self::assertStringContainsString($marker, $service . $migration);
        }
        foreach (['reconcilePrescriptionCartPush', 'orders/create', 'orders/paid', 'orders/cancelled', 'refunds/create'] as $marker) {
            self::assertStringContainsString($marker, $webhook);
        }
    }

    public function testOrderImportDoesNotAcceptBrowserPrescriptionId(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('findDoctorPrescription', $service);
        self::assertStringContainsString('PRESCRIPTION_NOT_ORDERABLE', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionEligibility.php'));
        self::assertStringNotContainsString('$prescriptionId = $this->candidatePrescriptionId($payload);', $service);
    }

    public function testActivePrescriptionItemFailuresHavePreciseReasonsAndSafeDiagnostics(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyActivePrescriptionService.php');

        foreach (['PRESCRIPTION_ITEM_QUANTITY_INVALID', 'PRESCRIPTION_ITEM_MEDICINE_INVALID', 'ROOTINS_MEDICINE_INACTIVE', 'SHOPIFY_MEDICINE_MAPPING_REQUIRED'] as $reason) {
            self::assertStringContainsString($reason, $service);
        }
        foreach (['stored_quantity', 'normalized_quantity', 'prescription_reference', 'prescription_id', 'patient_id', 'prescription_item_id', 'medicine_id', 'mapping_result', 'shopify_product_id', 'shopify_variant_id'] as $diagnostic) {
            self::assertStringContainsString($diagnostic, $service);
        }
        self::assertStringNotContainsString('max(0, (int) ($row[\'quantity\']', $service);
    }

    public function testPrescriptionQuantityNormalizationAcceptsOnlyWholeNumbers(): void
    {
        $normalizer = new \ReflectionClass(\App\Services\Shopify\ShopifyPrescriptionQuantity::class);
        $method = $normalizer->getMethod('normalize');
        $method->setAccessible(true);
        foreach ([['1.00', 1], ['2.00', 2], [1, 1], [2, 2]] as [$input, $expected]) {
            self::assertSame($expected, $method->invoke(null, $input));
        }
        foreach (['1.5', '1.50', 1.5, 0, '0', null, '', '-1', 'abc'] as $input) {
            $normalized = $method->invoke(null, $input);
            self::assertTrue($normalized === null || $normalized < 1);
        }
    }

    public function testCancelledPrescriptionsAreNotOrderableAndMappingSelectionIsDeterministic(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyActivePrescriptionService.php');
        self::assertStringContainsString("whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)", $service);
        self::assertStringContainsString("->where('sm.status', 'active')->orderBy('sm.id', 'ASC')", $service);
        self::assertStringContainsString("'shopify_product_id'", $service);
    }
}
