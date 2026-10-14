<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\MedicineAvailabilityService;

final class PharmacyInventoryTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testInventoryRouteAndMenuRequireInventoryPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $menu = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/layout.php');
        self::assertStringContainsString("'inventory', 'Inventory::index'", $routes);
        self::assertStringContainsString('permission:pharmacy.inventory.view', $routes);
        self::assertStringContainsString('pharmacy.inventory.view', $menu);
        self::assertStringContainsString('Central medicine stock', $menu);
        self::assertStringContainsString("'inventory/adjust', 'Inventory::adjust'", $routes);
        self::assertStringContainsString("'inventory/create', 'Inventory::create'", $routes);
        self::assertStringContainsString('permission:pharmacy.inventory.batch.create', $routes);
    }

    public function testScopeRequiresPharmacyRoleAndClinic(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 9], 'permissions' => ['pharmacy.inventory.view']]);
        $scope = new PharmacyScope();
        self::assertSame(9, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.inventory.view'));

        session()->set('roles', ['Admin']);
        self::assertNull($scope->clinicId());
    }

    public function testAvailabilityQueryUsesCentralActiveMedicineStockWithoutClinicLeakage(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/MedicineAvailabilityService.php');

        foreach (["where('medicines.status', 'active')", 'batches.status', 'batches.expiry_date', 'batches.quantity_available', 'out_of_stock', 'expired'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('batches.clinic_id', $service);
        self::assertStringNotContainsString('filter_batches.clinic_id', $service);
    }

    public function testSearchFiltersAndPaginationAreServerSide(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/MedicineAvailabilityService.php');

        foreach (['medicine_name', 'batch_number', 'batches.status', 'availability', 'expiry_state', 'medicine_id', 'page_size', 'get($pageSize'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('DataTable', $service);
    }

    public function testListAndGridUseTheSameNormalizedRowContractAndViewToggle(): void
    {
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/grid.php');
        $toolbar = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_toolbar.php');

        foreach (['medicine_name', 'batch_number', 'expiry_display', 'quantity_available', 'availability_label'] as $field) {
            self::assertStringContainsString("row['$field']", $list);
            self::assertStringContainsString("row['$field']", $grid);
        }
        self::assertStringContainsString("['view' => 'list'", $toolbar);
        self::assertStringContainsString("['view' => 'grid'", $toolbar);
        self::assertStringContainsString("pages/inventory/' .", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Inventory.php'));
        self::assertStringContainsString("'view' => (string)", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Inventory.php'));
    }

    public function testDateFormattingAndEmptyStateAreDefined(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/MedicineAvailabilityService.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/grid.php');
        self::assertStringContainsString("format('d-m-Y')", $service);
        self::assertStringContainsString('pharmacy-inventory-empty', $list);
        self::assertStringContainsString('pharmacy-inventory-empty', $grid);
        self::assertStringContainsString('inventory-row-near-expiry', $list);
        self::assertStringContainsString('inventory-card-near-expiry', $grid);
        self::assertStringContainsString('modal-dialog-scrollable', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_detail_modal.php'));
        self::assertStringContainsString('assets/js/pharmacy/inventory.js', file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_scripts.php'));
    }

    public function testEmptyScopeReturnsSafeEmptyPayload(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => [], 'permissions' => []]);
        $result = (new MedicineAvailabilityService(new PharmacyScope()))->search(['view' => 'grid']);
        self::assertSame([], $result['rows']);
        self::assertSame(0, $result['total']);
    }

    public function testCentralStockMigrationAndAdjustmentContractArePresent(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-02-130000_CentralizeMedicineInventory.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/CentralInventoryService.php');
        $permissions = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-08-110000_RenameLegacyPharmacyIdentifiers.php');
        self::assertStringContainsString('inventory_stock_movements', $migration);
        self::assertStringContainsString('SUM(quantity_available)', $migration);
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringContainsString('new < 0', $service);
        self::assertStringContainsString('pharmacy.inventory_stock_adjusted', $service);

        foreach (['pharmacy.inventory.batch.create', 'pharmacy.inventory.batch.update', 'pharmacy.inventory.batch.adjust', 'pharmacy.inventory.batch.delete'] as $permission) {
            self::assertStringContainsString($permission, $permissions);
        }
    }

    public function testCreateBatchContractUsesCentralFieldsAndExactValidationTargets(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/CentralInventoryService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Inventory.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/inventory/_detail_modal.php');

        foreach (['function create(', 'medicine_id', 'batch_number', 'expiry_date', 'quantity', 'status', 'FOR UPDATE', 'inventory_stock_movements', 'transBegin', 'transCommit', 'inventory_batch_created'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString("pharmacy.inventory.batch.create", $controller);
        self::assertStringContainsString("inventory/create", $modal);

        foreach (['medicine_idErr', 'batch_numberErr', 'expiry_dateErr', 'quantityErr', 'statusErr', 'btSelect', 'datepicker', 'dd-mm-yyyy', 'data-submit-display-date="true"'] as $contract) {
            self::assertStringContainsString($contract, $modal);
        }
        self::assertStringContainsString('modal-dialog-centered modal-lg', $modal);
        self::assertStringContainsString('form-group col-md-6', $modal);
        self::assertStringContainsString('form-group col-12', $modal);
        self::assertStringNotContainsString('id="pharmacy-inventory-create" tabindex="-1" role="dialog" aria-labelledby="pharmacy-inventory-create-title" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"', $modal);
        self::assertSame(1, preg_match_all('/\bname="expiry_date"/', $modal));
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/inventory.js');
        self::assertStringContainsString("field.name !== 'expiry_date'", $javascript);
        self::assertStringContainsString("name: 'expiry_date', value: String($('#inventory-create-expiry').val()", $javascript);
        self::assertStringContainsString('if (xhr.status === 422)', $javascript);
        self::assertStringContainsString('bindFormError(form, response.errors || {})', $javascript);
        self::assertStringContainsString("status === 'timeout'", $javascript);
        self::assertStringContainsString("status === 'abort'", $javascript);
        self::assertStringContainsString("createFromFormat('!d-m-Y'", $service);
        self::assertStringContainsString('$date->format(\'Y-m-d\')', $service);
        self::assertStringNotContainsString("'clinic_id' =>", $service);
    }

    public function testDoctorAndPatientContractsDoNotExposeCentralStockFields(): void
    {
        $doctor = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $patient = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/portal.php');
        self::assertStringContainsString("select('id, medicine_name, generic_name, medicine_code, strength')", $doctor);
        self::assertStringNotContainsString('quantity_available', $doctor);
        self::assertStringNotContainsString("'batch'", $patient);
        self::assertStringNotContainsString("item.batch", $patient);
        self::assertStringContainsString("select('medicine_name, quantity')", file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Portal.php'));
    }
}
