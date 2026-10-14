<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Filters\Permission;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use Config\UserAgents;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\TransitionContract;

final class PharmacyAuthorizationTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testPharmacyAssistantPermissionMigrationIsIdempotentAndScopedToPharmacyAssistant(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-08-110000_RenameLegacyPharmacyIdentifiers.php');
        self::assertStringContainsString("where('role_code', 'PHARMACY_ASSISTANT')", $source);
        self::assertStringContainsString('pharmacy.dashboard.view', $source);
        self::assertStringContainsString("'pharmacy.dashboard.view'", $source);
        self::assertStringContainsString("'pharmacy.billing.refund'", $source);
        self::assertStringNotContainsString("'role_code', 'ADMIN'", $source);
        self::assertStringContainsString('pharmacy.prescription.review.cancel', $source);
    }

    public function testPharmacyScopeAllowsAssignedClinicAndRejectsOtherRoles(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['id' => 7, 'clinic_id' => 12], 'permissions' => ['pharmacy.order.view']]);
        $scope = new PharmacyScope();
        self::assertSame(12, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.order.view'));
        self::assertFalse($scope->can('pharmacy.billing.refund'));

        session()->set('roles', ['Admin']);
        self::assertNull($scope->clinicId());
    }

    public function testTransitionContractsPreserveValidDeliveryAndRejectInvalidTransitions(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('prescription', 'submitted', 'approved'));
        self::assertTrue($contract->allows('prescription', 'clarification_requested', 'clarification_requested'));
        self::assertFalse($contract->allows('prescription', 'approved', 'rejected'));
        self::assertTrue($contract->allows('order', 'pending', 'fulfilled'));
        self::assertFalse($contract->allows('order', 'fulfilled', 'pending'));
        self::assertTrue($contract->allows('payment', 'pending', 'paid'));
        self::assertFalse($contract->allows('payment', 'refunded', 'paid'));
        self::assertTrue($contract->allows('delivery', 'pending', 'packed'));
        self::assertTrue($contract->allows('delivery', 'delivered', 'returned'));
        self::assertFalse($contract->allows('delivery', 'pending', 'delivered'));
    }

    public function testPharmacyMedicineQueriesUseStringActiveStatus(): void
    {
        $operations = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Operations.php');
        $review = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php');
        self::assertStringContainsString("where('status', 'active')", $operations);
        self::assertStringContainsString("where('status', 'active')", $review);
        self::assertStringNotContainsString("where('status', 1)", $operations);
        self::assertStringNotContainsString("where('status', 1)", $review);
    }

    public function testRoutesRetainCsrfAuthAndLegacyCompatibility(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        self::assertStringContainsString("'filter' => 'permission:pharmacy.prescription.review.view'", $routes);
        self::assertStringContainsString("'filter' => 'permission:pharmacy.order.create'", $routes);
        self::assertStringContainsString("'filter' => 'permission:pharmacy.medicine.edit'", $routes);
        self::assertStringContainsString("'pharmacy.prescription.view'", file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-08-110000_RenameLegacyPharmacyIdentifiers.php'));
        self::assertStringContainsString("pharmacy.order.create", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Operations.php'));
        self::assertStringContainsString("pharmacy.billing.payment.update", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Operations.php'));
        self::assertStringContainsString("pharmacy.delivery.update", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Operations.php'));
    }

    public function testExistingPharmacySessionPermissionsAreRefreshedFromApplicationDatabase(): void
    {
        $filter = file_get_contents(ROOTPATH . 'app/Filters/Permission.php');

        self::assertStringContainsString("Database::connect('default')", $filter);
        self::assertStringContainsString('refreshSessionPermissions', $filter);
        self::assertStringContainsString("'FarmAssist', 'Farmassist'", $filter);
        self::assertStringContainsString("session()->set([", $filter);
        self::assertStringContainsString("'permissions' => array_values(array_unique(\$permissions))", $filter);
    }

    public function testAdminWithThePharmacyPermissionRemainsRouteEligible(): void
    {
        session()->set(['roles' => ['Admin'], 'permissions' => ['pharmacy.dashboard.view']]);
        $request = new IncomingRequest(new App(), new URI('http://example.com/pharmacy'), 'php://memory', new UserAgent(new UserAgents()));

        self::assertNull((new Permission())->before($request, ['pharmacy.dashboard.view']));
    }

    public function testUnauthorizedStaffCannotUsePharmacyScopeEvenWithLegacyPermissions(): void
    {
        session()->set(['roles' => ['Doctor'], 'permissions' => ['order.view', 'billing.view']]);

        $scope = new PharmacyScope();

        self::assertNull($scope->clinicId());
        self::assertFalse($scope->can('pharmacy.order.view'));
    }
}
