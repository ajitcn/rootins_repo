<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\OrderFulfilmentService;
use Pharmacy\Services\TransitionContract;

final class PharmacyOrderFulfilmentTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testOrderRoutesMenuAndSeparateTemplatesArePresent(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        self::assertStringContainsString("'orders', 'Orders::index'", $routes);
        self::assertStringContainsString("'orders/create', 'Orders::create'", $routes);
        self::assertStringContainsString("'orders/cancel', 'Orders::cancel'", $routes);
        self::assertStringContainsString('permission:pharmacy.order.view', $routes);
        self::assertStringContainsString("'filter' => 'permission:pharmacy.order.create'", $routes);
        self::assertStringContainsString('permission:pharmacy.order.cancel', $routes);
        self::assertStringContainsString('Medicine orders', $menu);
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php');
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/grid.php');
    }

    public function testSharedOrderActionsExposeOnlyScopedExistingWorkflows(): void
    {
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_actions.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/orders.js');
        $sharedActions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/parts/more_actions.php');

        foreach (['View Order', 'Cancel Order', 'View Invoice', 'View Payment', 'View Delivery', 'View Prescription', 'View Audit History', 'pharmacy.order.cancel', 'pharmacy.billing.view', 'pharmacy.delivery.view', 'pharmacy.prescription.review.view', 'pharmacy.audit.view', 'js-order-related'] as $needle) {
            self::assertStringContainsString($needle, $actions);
        }
        self::assertStringContainsString('aria-label="<?= esc($ariaLabel, \'attr\') ?>"', $sharedActions);
        self::assertStringContainsString('aria-controls="<?= esc($actionId, \'attr\') ?>-menu"', $sharedActions);
        self::assertStringContainsString('billing/detail/', $actions);
        self::assertStringContainsString('delivery/detail/', $actions);
        self::assertStringContainsString('pharmacy/prescriptions?search=', $actions);
        self::assertStringContainsString('pharmacy/audit?search=', $actions);
        self::assertStringContainsString('handleFormAjaxError(form, xhr', $script);
        self::assertStringContainsString('cancel_confirmationErr', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_detail_modal.php'));
        self::assertStringNotContainsString('retry', strtolower($actions));
    }

    public function testPharmacyResourcesUseOneSharedMoreActionsShell(): void
    {
        $paths = [
            'pages/prescriptions/_actions.php',
            'pages/inventory/_actions.php',
            'pages/orders/_actions.php',
            'pages/billing/_actions.php',
            'pages/delivery/_actions.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/' . $path);
            self::assertStringContainsString("parts/more_actions", $source);
            self::assertStringContainsString("role=\"menuitem\"", $source);
        }

        self::assertStringContainsString('parts/more_actions', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/dashboard.php'));
        self::assertStringContainsString('more_actions.js', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/audit/index.php'));
        self::assertStringContainsString('more_actions.js', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/notifications/index.php'));

        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/more_actions.js');
        $style = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/more_actions.css');
        $inventoryActions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_actions.php');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');

        self::assertStringContainsString("'class' => 'inventory-actions'", $inventoryActions);
        self::assertStringContainsString('data-display="static"', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/parts/more_actions.php'));
        self::assertStringContainsString('position: fixed !important', $style);
        self::assertStringContainsString('z-index: 1200 !important', $style);
        self::assertStringContainsString('more_actions.css', $layout);
        self::assertStringContainsString('Escape', $script);
        self::assertStringContainsString('ArrowDown', $script);
        self::assertStringContainsString('pharmacyMoreActions', $script);
        self::assertStringContainsString('getBoundingClientRect', $script);
        self::assertStringContainsString("aria-expanded', 'true'", $script);
        self::assertStringContainsString("dropdown('hide')", $script);
    }

    public function testCentralInventoryExposesIndependentFilteredSummaryBuckets(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/MedicineAvailabilityService.php');
        $toolbar = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_toolbar.php');
        $style = file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/inventory.css');

        foreach (['available', 'low_stock', 'near_expiry', 'expired', 'out_of_stock'] as $bucket) {
            self::assertStringContainsString("'$bucket'", $service . $toolbar);
        }

        self::assertStringContainsString('LOW_STOCK_THRESHOLD = 10', $service);
        self::assertStringContainsString('NEAR_EXPIRY_DAYS = 30', $service);
        self::assertStringContainsString('$summaryRows', $service);
        self::assertStringContainsString('pharmacy-inventory-summary-grid', $toolbar . $style);
        self::assertStringNotContainsString('Low / near expiry', $toolbar);
        self::assertStringNotContainsString('Expired / out of stock', $toolbar);
    }

    public function testOrderLabelsUseDedicatedPermissionsRoutesAndSharedActions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_actions.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-100000_AddFarmassistOrderLabelPermissions.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderLabelService.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_labels_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/order_labels.js');

        foreach (['pharmacy.order.label.view', 'pharmacy.order.label.print', 'Orders::labels', 'Orders::printLabels'] as $needle) {
            self::assertStringContainsString($needle, $routes . $migration);
        }

        self::assertStringContainsString('Print Labels', $actions);
        self::assertStringContainsString('pharmacy.order.label.view', $actions);
        self::assertStringContainsString("\$status !== 'cancelled'", $actions);
        self::assertStringContainsString('modal-dialog-scrollable', $modal);
        self::assertStringContainsString('csrf_field()', $modal);
        self::assertStringContainsString('label_type', $modal . $script);
        self::assertStringContainsString('delivery_address_snapshot', $service);
        self::assertStringContainsString('deliveryAddress', $service);
        self::assertStringContainsString('Address not recorded', $service);
        self::assertStringContainsString('patient_locality', $service);
        self::assertStringContainsString('prescription_number', $service);
        self::assertStringContainsString('clinic_contact', $service);
        self::assertStringContainsString('medicine_strength', $service);
        self::assertStringContainsString('medicine_dosage_form', $service);
        self::assertStringContainsString('medicine_unit', $service);
        self::assertStringContainsString('barcode_value', $service);
        self::assertStringContainsString("'sequence'", $service . $script);
        self::assertStringContainsString('pharmacy-print-label-code', $script . file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/order_labels.css'));
        self::assertStringContainsString('44mm', $script . file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/order_labels.css'));
        self::assertStringContainsString('48mm', $script . file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/order_labels.css'));
        self::assertStringContainsString('32mm', $script . file_get_contents(ROOTPATH . 'public/assets/css/pharmacy/order_labels.css'));
        self::assertStringContainsString("where('orders.clinic_id', \$clinicId)", $service);
        self::assertStringContainsString('order_label_preview', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Orders.php'));
        self::assertStringContainsString('order_label_printed', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Orders.php'));
    }

    public function testOrderLabelContractExcludesSensitiveDataAndRejectsCancelledOrders(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderLabelService.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/order_labels.js');

        self::assertStringContainsString("Labels are not available for cancelled orders.", $service);
        self::assertStringContainsString("['pending', 'fulfilled']", $service);
        self::assertStringContainsString('inventory_batch_id', $service);
        self::assertStringNotContainsString('password', strtolower($service . $script));
        self::assertStringNotContainsString('otp', strtolower($service . $script));
        self::assertStringNotContainsString('payment_reference', $service);
        self::assertStringContainsString('frame.contentWindow.print()', $script);
        self::assertStringContainsString('break-inside:avoid', $script);
        self::assertStringNotContainsString('window.open', $script);
    }

    public function testOrderCancelRequiresReasonConfirmationAndKeepsServerContract(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Orders.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_detail_modal.php');
        self::assertStringContainsString('bool $confirmed = false', $service);
        self::assertStringContainsString('Cancellation confirmation is required.', $service);
        self::assertStringContainsString('I confirm this order cancellation', $controller . $modal);
        self::assertStringContainsString('csrf_field()', $modal);
        self::assertStringContainsString("'confirmation' => 'Confirm that you want to cancel this pharmacy order.'", $service);
    }

    public function testOrderServiceUsesClinicScopeIdempotencyLocksAndSafeAllocationRules(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');

        foreach (["where('orders.clinic_id', \$clinicId)", "where('patients.clinic_id', \$clinicId)", "where('prescriptions.clinic_id', \$clinicId)", "idempotency_key", 'FOR UPDATE', "where('status', 'active')", 'expiry_date >=', 'quantity_available > 0', 'ORDER BY expiry_date ASC', 'quantity_available - ?', 'quantity_available + ?'] as $contract) {
            self::assertStringContainsString($contract, $source);
        }
        self::assertStringNotContainsString("where('status', 1)", $source);
        self::assertStringNotContainsString('medicine_inventory_batches WHERE clinic_id', $source);
        self::assertStringNotContainsString("WHERE id = ? AND clinic_id = ? AND status", $source);
    }

    public function testDedicatedPermissionsAndScopeDenyUnauthorizedAccess(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 44], 'permissions' => ['pharmacy.order.view']]);
        $scope = new PharmacyScope();
        self::assertSame(44, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.order.view'));
        self::assertFalse($scope->can('pharmacy.order.create'));
        session()->set('roles', ['Doctor']);
        self::assertNull($scope->clinicId());
    }

    public function testOrderTransitionsAndCancellationReasonAreContracted(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('order', 'pending', 'cancelled'));
        self::assertTrue($contract->allows('order', 'pending', 'fulfilled'));
        self::assertFalse($contract->allows('order', 'fulfilled', 'cancelled'));
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        self::assertStringContainsString('Cancellation reason is required.', $source);
        self::assertStringContainsString("pharmacy.order_cancelled", $source);
        self::assertStringContainsString("'previous_status'", $source);
        self::assertStringContainsString("'new_status'", $source);
    }

    public function testListAndGridUseIdenticalNormalizedFieldsAndPersistFilters(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/grid.php');

        foreach (['order_number', 'patient_name', 'patient_number', 'patient_mobile', 'prescription_number', 'prescription_date', 'medicine_count', 'total_amount', 'status', 'payment_status', 'delivery_status'] as $field) {
            self::assertStringContainsString("order['$field']", $list);
            self::assertStringContainsString("order['$field']", $grid);
        }

        foreach (['search', 'status', 'payment_status', 'delivery_status', 'from', 'to', 'page_size', 'get($pageSize'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        self::assertStringContainsString('dd-mm-yyyy', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_toolbar.php'));
        self::assertStringContainsString('modal-dialog-scrollable', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_detail_modal.php'));
        self::assertStringContainsString('Order status', $list . $grid);
        self::assertStringContainsString('Delivery status', $list . $grid);
        self::assertStringContainsString('order-count-badge', $list . $grid);
        self::assertStringNotContainsString('order-medicine-summary', $list . $grid);
        self::assertStringNotContainsString('Delivery and order status', $grid);
        self::assertStringNotContainsString('DataTable', $service . $list . $grid);

        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/orders.js');
        self::assertStringContainsString("missing ? 'Not recorded'", $script);
        self::assertStringContainsString('order-detail-count', $script);
    }

    public function testCreateContractRequiresApprovedPrescriptionAndNormalizesSafeErrors(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        self::assertStringContainsString("(\$prescription['status'] ?? '') !== 'approved'", $source);
        self::assertStringContainsString("'prescription_id' => 'Prescription must be approved.'", $source);
        self::assertStringContainsString("'items.' . \$index . '.quantity'", $source);
        self::assertStringContainsString('medicine is inactive or missing', $source);
        self::assertStringContainsString('resolveQuantity', $source);
        self::assertStringContainsString('cannot be derived from the prescription', $source);
        self::assertStringContainsString("str_contains(\$frequency, 'twice')", $source);
        self::assertStringContainsString('if ($updated === false)', $source);
        self::assertStringContainsString('$db->affectedRows()', $source);
        self::assertStringContainsString('return $this->result(409', $source);
        self::assertStringContainsString('prescription_id=', $source);
    }

    public function testCreateFormAndAjaxUseTheSinglePharmacyCreateEndpoint(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_create_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/orders.js');
        self::assertStringContainsString("action=\"<?= base_url('pharmacy/orders/create') ?>\"", $modal);
        self::assertStringContainsString('url: form.attr(\'action\')', $script);
        self::assertStringNotContainsString("(window.base_url || window.BASE_URL || '') + 'pharmacy/orders/'", $script);
        self::assertStringContainsString("appBase + '/pharmacy/orders/'", $script);
        self::assertStringContainsString('csrf_field()', $modal);
        self::assertStringContainsString('handleFormAjaxError(form, xhr', $script);
        self::assertStringContainsString('clearFormErrors(form)', $script);
    }

    public function testCentralAllocationUsesApplicationDateAndExactBatchLinkForRelease(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        self::assertStringContainsString("ORDER BY expiry_date ASC, id ASC FOR UPDATE", $source);
        self::assertStringContainsString("'inventory_batch_id' => \$batchId", $source);
        self::assertStringContainsString('Historical order item has an ambiguous inventory batch reference.', $source);
        self::assertStringContainsString('private function today()', $source);
        self::assertStringContainsString('inventory_stock_movements', $source);
    }

    public function testCentralStockCannotBeAdjustedWithoutReasonConfirmationOrBelowZero(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/CentralInventoryService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Inventory.php');

        foreach (['A reason is required.', 'Confirm this central stock adjustment', 'new < 0', 'FOR UPDATE', 'previous_quantity', 'new_quantity'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        self::assertStringContainsString('pharmacy.inventory.batch.adjust', $controller);
        self::assertStringContainsString("status'] ?? 'active')) === 'merged'", $source);
    }
}
