<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\DeliveryProviderBoundary;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\TransitionContract;

final class PharmacyDeliveryTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testDeliveryRoutesMenuPermissionsAndTemplatesExist(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        self::assertStringContainsString("'delivery', 'Delivery::index'", $routes);
        self::assertStringContainsString("'delivery/update', 'Delivery::update'", $routes);
        self::assertStringContainsString('permission:pharmacy.delivery.view', $routes);
        self::assertStringContainsString('permission:pharmacy.delivery.update', $routes);
        self::assertStringNotContainsString('>Delivery<', $menu);
        self::assertStringContainsString("base_url('pharmacy/orders'", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Delivery.php'));
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/list.php');
        self::assertFileExists(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/grid.php');
    }

    public function testExistingDeliveryTransitionMapIsUsedExactly(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('delivery', 'pending', 'packed'));
        self::assertTrue($contract->allows('delivery', 'pending', 'failed'));
        self::assertTrue($contract->allows('delivery', 'packed', 'dispatched'));
        self::assertTrue($contract->allows('delivery', 'dispatched', 'out_for_delivery'));
        self::assertTrue($contract->allows('delivery', 'out_for_delivery', 'delivered'));
        self::assertTrue($contract->allows('delivery', 'failed', 'returned'));
        self::assertTrue($contract->allows('delivery', 'delivered', 'returned'));
        self::assertFalse($contract->allows('delivery', 'returned', 'pending'));
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');

        foreach (['TransitionContract::DELIVERY', 'Delivery confirmation is required.', 'pharmacy.delivery_updated', 'previous_status', 'new_status', 'tracking_reference', 'reasonValue'] as $required) {
            self::assertStringContainsString($required, $source);
        }
        self::assertStringContainsString('integration_status', $source);
        self::assertStringContainsString('pharmacy.delivery_integration_skipped', $source);
        self::assertStringContainsString('manual_update', $source);
        self::assertStringContainsString('delivery.status.override', $source);
        self::assertStringContainsString('pharmacy_manual_update', $source);
        self::assertStringNotContainsString('return $this->result(503, \'Delivery provider is not configured', $source);
    }

    public function testDeliveryScopeAndPermissionAreServerSide(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 17], 'permissions' => ['pharmacy.delivery.view']]);
        $scope = new PharmacyScope();
        self::assertSame(17, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.delivery.view'));
        self::assertFalse($scope->can('pharmacy.delivery.update'));
        session()->set('roles', ['Doctor']);
        self::assertNull($scope->clinicId());
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');
        self::assertStringContainsString("where('orders.clinic_id', \$clinicId)", $source);
        self::assertStringContainsString("where('patients.clinic_id', \$clinicId)", $source);
    }

    public function testDeliveryMigrationContainsRequiredReversibleMetadata(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-170000_AddDeliveryFieldsToMedicineOrders.php');

        foreach (['delivery_provider', 'tracking_reference', 'delivery_address_snapshot', 'delivery_contact_snapshot', 'dispatched_at', 'delivered_at', 'failure_reason', 'return_reason', 'dropColumn'] as $field) {
            self::assertStringContainsString($field, $migration);
        }
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/MedicineOrderModel.php');
        self::assertStringContainsString('delivery_provider', $model);
        self::assertStringContainsString('tracking_reference', $model);
    }

    public function testProviderBoundaryDoesNotExposeSecretsOrFakeCalls(): void
    {
        $boundary = new DeliveryProviderBoundary();
        self::assertFalse($boundary->isConfigured('manual'));
        self::assertArrayHasKey('configured', $boundary->readiness('internal'));
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryProviderBoundary.php');
        self::assertStringContainsString('DELIVERY_', $source);
        self::assertStringContainsString('Delivery provider is not configured', $source);
        self::assertFalse($boundary->hasAdapter('internal'));
        self::assertStringNotContainsString('curl_exec', $source);
    }

    public function testConfiguredProviderStillRequiresAnAdapterBeforeAnyExternalCall(): void
    {
        putenv('DELIVERY_INTERNAL_API_KEY=unit-test-key');
        putenv('DELIVERY_INTERNAL_API_SECRET=unit-test-secret');

        try {
            $boundary = new DeliveryProviderBoundary();
            self::assertTrue($boundary->isConfigured('internal'));
            self::assertFalse($boundary->hasAdapter('internal'));
        } finally {
            putenv('DELIVERY_INTERNAL_API_KEY');
            putenv('DELIVERY_INTERNAL_API_SECRET');
        }
    }

    public function testDeliveryProviderConfigurationNeverBlocksManualUpdates(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');
        foreach (['isConfigured($provider)', 'hasAdapter($provider)', '\'integration_status\' => $integrationStatus', '\'external_call\' => false', 'No external provider call was made.'] as $required) {
            self::assertStringContainsString($required, $source);
        }
        self::assertStringContainsString('providerConfigured', $source);
        self::assertStringContainsString('adapter_unavailable', $source);
    }

    public function testManualDeliveryStatusSelectorOffersEveryApprovedStatus(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/delivery.js');
        foreach (['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'] as $status) {
            self::assertStringContainsString($status, $script);
        }
        self::assertStringContainsString('allowed.forEach', $script);
        self::assertStringContainsString('statusRank', $script);
    }

    public function testListGridFiltersPaginationModalAndNoDataTables(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/grid.php');

        foreach (['order_number', 'patient_name', 'patient_number', 'order_status', 'payment_status', 'delivery_status', 'tracking_reference', 'dispatched_at', 'delivered_at'] as $field) {
            self::assertStringContainsString("row['$field']", $list);
            self::assertStringContainsString("row['$field']", $grid);
        }
        self::assertStringContainsString('delivery_address', $service);
        self::assertStringContainsString('medicine_count', $service);
        self::assertStringContainsString('failure_reason', $service);
        self::assertStringContainsString('return_reason', $service);

        foreach (['search', 'delivery_status', 'provider', 'from', 'to', 'page_size', 'get($pageSize'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        self::assertStringContainsString('modal-dialog-scrollable', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_modal.php'));
        self::assertStringContainsString('dd-mm-yyyy', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_toolbar.php'));
        self::assertStringNotContainsString('DataTable', $service . $list . $grid);
    }

    public function testDeliveryActionsAndUpdateContractArePermissionAndStateAware(): void
    {
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_actions.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/delivery.js');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');

        foreach (['pharmacy.delivery.view', 'pharmacy.delivery.update', 'pharmacy.order.view', 'pharmacy.billing.view', 'pharmacy.prescription.review.view', 'pharmacy.audit.view', 'View delivery', 'Update delivery status', 'View order', 'View invoice', 'View payment', 'View prescription', 'View audit history'] as $required) {
            self::assertStringContainsString($required, $actions);
        }
        self::assertStringContainsString('<?= csrf_field() ?>', $modal);
        self::assertStringContainsString('modal-dialog-scrollable', $modal);
        self::assertStringContainsString('Change reason is optional', $modal);

        foreach (["const statuses = ['pending'", 'allowed.forEach', 'statusRank'] as $required) {
            self::assertStringContainsString($required, $script);
        }
        self::assertStringContainsString('tracking_reference', $service);
        self::assertStringContainsString('delivery.status.override', $service);
        self::assertStringContainsString('Change reason (optional)', $modal);
        self::assertStringContainsString("prop('required', false)", $script);
        self::assertStringNotContainsString('Reason is required.', $service);
        self::assertStringContainsString('in_array($target, [\'dispatched\', \'out_for_delivery\', \'delivered\']', $service);
        self::assertStringNotContainsString("\$fields['delivery_address_snapshot']", $service);
    }

    public function testDeliveryOverridePermissionMigrationExists(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-10-123000_RenameDeliveryOverridePermissionAndAssignRoles.php');
        self::assertStringContainsString('delivery.status.override', $migration);
        self::assertStringContainsString('role_permissions', $migration);
        self::assertStringContainsString('AUTHORIZED_ROLES', $migration);
        self::assertStringContainsString('transStart', $migration);
        self::assertStringContainsString('transComplete', $migration);
        self::assertStringContainsString('Override Delivery Status', $migration);
    }

    public function testPermissionFilterRefreshesStaleSessionsFromRoleMappings(): void
    {
        $filter = file_get_contents(ROOTPATH . 'app/Filters/Permission.php');
        self::assertStringContainsString('refreshSessionPermissions', $filter);
        self::assertStringContainsString("session()->set([", $filter);
        self::assertStringContainsString('role_permissions', $filter);
        self::assertStringContainsString('delivery.status.override', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php'));
    }
}
