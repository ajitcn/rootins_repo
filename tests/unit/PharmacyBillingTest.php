<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\BillingService;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\PaymentProviderBoundary;
use Pharmacy\Services\TransitionContract;

final class PharmacyBillingTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testBillingRouteMenuTemplatesAndPermissionsExist(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        self::assertStringContainsString("'billing', 'Billing::index'", $routes);
        self::assertStringContainsString("'billing/reconcile', 'Billing::reconcile'", $routes);
        self::assertStringContainsString("'billing/refund', 'Billing::refund'", $routes);
        self::assertStringContainsString('permission:pharmacy.billing.view', $routes);
        self::assertStringContainsString('permission:pharmacy.billing.payment.update', $routes);
        self::assertStringContainsString('permission:pharmacy.billing.refund', $routes);
        self::assertStringContainsString('Billing &amp; payments', $menu);
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/list.php');
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/grid.php');
    }

    public function testStrictPaymentTransitionsAndRequiredInputsAreDefined(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('payment_billing', 'pending', 'authorized'));
        self::assertTrue($contract->allows('payment_billing', 'pending', 'failed'));
        self::assertTrue($contract->allows('payment_billing', 'authorized', 'paid'));
        self::assertTrue($contract->allows('payment_billing', 'paid', 'refunded'));
        self::assertTrue($contract->allows('payment_billing', 'failed', 'pending'));
        self::assertFalse($contract->allows('payment_billing', 'pending', 'paid'));
        self::assertFalse($contract->allows('payment_billing', 'refunded', 'pending'));
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');

        foreach (['previous_status', 'new_status', 'amount', 'reference', 'Payment provider is invalid.', 'Provider is required.'] as $contractText) {
            self::assertStringContainsString($contractText, $source);
        }
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        self::assertStringContainsString('id="paymentReference" placeholder="Enter reference number (optional)"', $modal);
        self::assertStringNotContainsString('id="paymentReference" placeholder="Enter reference number (optional)" required', $modal);
        self::assertStringContainsString('id="paymentReason" rows="2"', $modal);
        self::assertStringContainsString('id="pharmacyPaymentForm" novalidate', $modal);
        self::assertStringContainsString('Object.keys(paymentStatusCatalog)', file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js'));
    }

    public function testBillingScopeAndPermissionsAreServerSide(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 8], 'permissions' => ['pharmacy.billing.view']]);
        $scope = new PharmacyScope();
        self::assertSame(8, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.billing.view'));
        self::assertFalse($scope->can('pharmacy.billing.payment.update'));
        session()->set('roles', ['Doctor']);
        self::assertNull($scope->clinicId());
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString("where('orders.clinic_id', \$clinicId)", $source);
        self::assertStringContainsString("where('patients.clinic_id', \$clinicId)", $source);
    }

    public function testInvoiceLinkageMissingInvoiceAndAuthoritativeAmountAreHandled(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString("join('invoices', 'invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', 'left')", $source);
        self::assertStringContainsString("'invoice_number' => (string) (\$row['invoice_number'] ?? 'Unavailable')", $source);
        self::assertStringContainsString("'amount' => \$amount", $source);
        self::assertStringContainsString('invoice_items', $source);
        self::assertStringContainsString('invoices_order_id_unique', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-160000_AddOrderLinkToInvoices.php'));
    }

    public function testManualReconciliationAndRefundNeverExposeProviderSecrets(): void
    {
        $boundary = new PaymentProviderBoundary();
        self::assertFalse($boundary->isConfigured('manual'));
        self::assertArrayHasKey('configured', $boundary->readiness('cashfree'));
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PaymentProviderBoundary.php') . file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString('KEY_SECRET', $source);
        self::assertStringNotContainsString('password', strtolower($source));
        self::assertStringContainsString('Provider is not configured', $source);
        self::assertStringContainsString("'reconciliation_source' => 'manual'", $source);
        self::assertStringContainsString('Saved as manual payment reconciliation; no external provider call was made.', $source);
        self::assertStringContainsString("'external_call' => false", $source);
        self::assertStringContainsString('pharmacy.billing.payment.update', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Billing.php'));
        self::assertStringContainsString('pharmacy.billing.refund', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Billing.php'));
    }

    public function testListGridFiltersPaginationModalAndNoDataTables(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/grid.php');

        foreach (['order_number', 'invoice_number', 'patient_name', 'patient_number', 'payment_reference', 'provider', 'payment_status', 'delivery_status', 'order_status', 'date_error', 'page_size', 'get($pageSize'] as $field) {
            self::assertStringContainsString($field, $service);
        }

        foreach (['order_number', 'patient_name', 'invoice_number', 'amount', 'payment_status', 'delivery_status'] as $field) {
            self::assertStringContainsString("row['$field']", $list);
            self::assertStringContainsString("row['$field']", $grid);
        }
        self::assertStringContainsString('modal-dialog-scrollable', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php'));
        self::assertStringContainsString('dd-mm-yyyy', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_toolbar.php'));
        self::assertStringContainsString('displayDate', $service);
        self::assertStringContainsString('Payment Status', $grid);
        self::assertStringContainsString('Delivery Status', $grid);
        self::assertStringContainsString('To date cannot be earlier', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_toolbar.php'));
        self::assertStringNotContainsString('DataTable', $service . $list . $grid);
    }

    public function testBillingActionsUseOnePermissionAndStatusAwareDropdown(): void
    {
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_actions.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');

        foreach (['View Invoice', 'View Payment', 'View Order', 'Change payment status', 'Refund', 'View Audit History', 'pharmacy.billing.view', 'pharmacy.billing.payment.update', 'pharmacy.billing.refund', 'pharmacy.order.view', 'pharmacy.audit.view', 'payment-status'] as $needle) {
            self::assertStringContainsString($needle, $actions);
        }
        self::assertStringContainsString('in_array($paymentStatus, [\'pending\', \'authorized\', \'failed\'], true)', $actions);
        self::assertStringContainsString('$paymentStatus === \'paid\'', $actions);
        self::assertStringContainsString("'paid_amount'", $service);
        self::assertStringContainsString("'refunded_amount'", $service);
        self::assertStringNotContainsString('DataTable', $actions);
    }

    public function testBillingFormsHaveCsrfFieldErrorsAndLoaderSafeAjaxHandling(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');
        self::assertSame(2, substr_count($modal, '<?= csrf_field() ?>'));

        foreach (['payment_statusErr', 'providerErr', 'referenceErr', 'reasonErr', 'confirmationErr', 'amountErr'] as $field) {
            self::assertStringContainsString($field, $modal);
        }

        foreach (['handleFormAjaxError', 'always', 'aria-busy', 'modal-dialog-scrollable'] as $needle) {
            self::assertStringContainsString($needle, $script . $modal);
        }
        self::assertStringContainsString('pharmacyBillingRelatedModal', $modal);
    }

    public function testBillingDetailAndRelatedModalsShareVisibleStackingLayer(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/billing-refinement.css');

        foreach (['pharmacyBillingDetailModal', 'pharmacyBillingRelatedModal', 'View Payment', 'View Order'] as $needle) {
            self::assertStringContainsString($needle, $modal . file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_actions.php'));
        }
        foreach (['#pharmacyBillingDetailModal, #pharmacyBillingRelatedModal', 'normalizeBillingModalLayer', 'pointerEvents', '2050', 'rgba(31, 41, 64, .24)'] as $needle) {
            self::assertStringContainsString($needle, $script . $styles);
        }
        self::assertStringContainsString('.pharmacy-billing-detail-modal .modal-content', $styles);
        self::assertStringContainsString('.pharmacy-billing-related-modal .modal-content', $styles);
        self::assertStringContainsString('overflow-y: auto', $styles);
    }

    public function testInvoiceScopeAndPaymentStatusRemainTransactionallyLinked(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        self::assertStringContainsString('invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', $service);
        self::assertStringContainsString("['payment_status' => \$status, 'updated_at'", $service);
        self::assertStringContainsString('Duplicate refunds are not allowed.', $service);
        self::assertStringContainsString('$db->transBegin()', $service);
    }
}
