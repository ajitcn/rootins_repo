<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PaymentProviderMasterTest extends TestCase
{
    public function testProviderMasterIsReversibleSeededAndIdempotent(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-110000_CreatePaymentProviders.php');
        foreach (['payment_providers', 'provider_name', 'provider_code', 'provider_type', 'default_currency', 'archived_at', 'cash', 'bank_transfer', "where('provider_code', \$seed['provider_code'])", 'public function down'] as $needle) self::assertStringContainsString($needle, $migration);
        $upiMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-110200_SeedUpiPaymentProvider.php');
        self::assertStringContainsString("'provider_code' => 'upi'", $upiMigration);
    }

    public function testAdminProviderCrudAndPermissionRoutesExist(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/PaymentProvider.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        foreach (['Provider name already exists.', 'Provider code already exists.', 'admin.payment_provider_', 'PaymentProvider::save', 'PaymentProvider::toggle', 'PaymentProvider::archive', 'payment_provider.view', 'payment_provider.manage'] as $needle) self::assertStringContainsString($needle, $controller . $routes);
    }

    public function testSharedModalUsesActiveProviderMasterAndPreservesLegacyTextStorage(): void
    {
        $catalog = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PaymentProviderCatalog.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString("where('status', 1)", $catalog);
        self::assertStringContainsString("where('archived_at', null)", $catalog);
        self::assertStringContainsString('id="paymentProvider"', $modal);
        self::assertStringContainsString('paymentProviders', $modal);
        self::assertStringContainsString('findActive($provider)', $service);
        self::assertStringContainsString("'payment_provider' =>", $service);
    }

    public function testSharedPaymentModalHasOneProviderPerFormAndUsesSharedLayer(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $billingCss = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/billing-refinement.css');
        $billing = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/list.php') . file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/grid.php');
        $orders = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php') . file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/grid.php');
        self::assertSame(1, substr_count($modal, 'id="paymentProvider"'));
        self::assertSame(1, substr_count($modal, 'id="paymentReference"'));
        self::assertStringContainsString('Reference number (optional)', $modal);
        self::assertStringContainsString('Reason / remarks (optional)', $modal);
        self::assertStringContainsString('Select a provider', $modal);
        self::assertStringContainsString('id="paymentReference" placeholder="Enter reference number (optional)"', $modal);
        self::assertStringNotContainsString('id="paymentReference" placeholder="Enter reference number (optional)" required', $modal);
        self::assertStringNotContainsString('Provider / reference number', $modal);
        self::assertStringContainsString('id="refundProvider"', $modal);
        self::assertStringContainsString('z-index: 2060 !important', $billingCss);
        self::assertStringContainsString('z-index: 2050 !important', $billingCss);
        self::assertStringContainsString('pages/billing/_modal', $billing);
        self::assertStringContainsString('pages/billing/_modal', $orders);
    }

    public function testProviderReferenceIsPersistedAndPrefilledFromBothPaymentEntryPoints(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $orderService = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        $billingActions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_actions.php');
        $orderActions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_actions.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');
        $ordersList = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php');

        self::assertStringContainsString("'payment_provider' => \$provider", $service);
        self::assertStringContainsString("payment_providers.provider_name AS payment_provider_name", $service . $orderService);
        self::assertStringContainsString("join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')", $service . $orderService);
        self::assertStringContainsString("data-provider=\"<?= esc((string) (\$row['provider'] ?? ''), 'attr') ?>\"", $billingActions);
        self::assertStringContainsString("data-provider=\"<?= esc(\$paymentProvider, 'attr') ?>\"", $orderActions);
        self::assertStringContainsString("attr('provider')", $script);
        self::assertStringContainsString('data-legacy-provider', $script);
        self::assertStringContainsString('payment_provider_name', $ordersList);
        self::assertStringContainsString("strtolower(trim((string) (\$order['payment_provider'] ?? ''))) === \$provider", $service);
    }

    public function testOrdersUsesBillingPaymentModalAsTheSingleSharedImplementation(): void
    {
        $ordersList = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php');
        $ordersGrid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/grid.php');
        $billingModal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $ordersScripts = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_scripts.php');
        $billingScripts = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_scripts.php');

        foreach ([$ordersList, $ordersGrid] as $ordersPage) {
            self::assertSame(1, substr_count($ordersPage, "pages/billing/_modal"));
            self::assertStringNotContainsString('Provider / reference number', $ordersPage);
            self::assertStringNotContainsString('id="pharmacyPaymentModal"', str_replace("pages/billing/_modal", '', $ordersPage));
        }
        self::assertSame(1, substr_count($ordersScripts, 'billing.js'));
        self::assertSame(1, substr_count($billingScripts, 'billing.js'));
        foreach (['id="pharmacyPaymentModal"', 'id="paymentSummaryOrderStatus"', 'id="paymentSummaryPaymentStatus"', 'id="paymentSummaryDeliveryStatus"', 'id="paymentProvider"', 'id="paymentReference"', 'Reason / remarks (optional)', 'confirmation'] as $field) {
            self::assertStringContainsString($field, $billingModal);
        }
    }
}
