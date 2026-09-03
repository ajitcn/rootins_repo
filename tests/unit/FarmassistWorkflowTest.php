<?php

namespace Tests\Unit;

use Farmassist\Controllers\Operations;
use PHPUnit\Framework\TestCase;
final class FarmassistWorkflowTest extends TestCase
{
    public function testDeliveryTransitionsAllowOnlyTheDocumentedForwardPath(): void
    {
        $transitions = (new \ReflectionClass(Operations::class))->getConstant('DELIVERY_TRANSITIONS');
        self::assertContains('packed', $transitions['pending']);
        self::assertContains('dispatched', $transitions['packed']);
        self::assertContains('out_for_delivery', $transitions['dispatched']);
        self::assertContains('delivered', $transitions['out_for_delivery']);
        self::assertNotContains('delivered', $transitions['pending']);
        self::assertSame([], $transitions['returned']);
    }
    public function testProviderBoundaryContainsOnlyApprovedCandidates(): void
    {
        $providers = (new \ReflectionClass(Operations::class))->getConstant('PAYMENT_PROVIDERS');
        self::assertContains('cashfree', $providers);
        self::assertContains('razorpay', $providers);
        self::assertNotContains('shopify', $providers);
    }
    public function testReviewActionsIncludeCancellationAndRoutesArePermissionProtected(): void
    {
        $reviewSource = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Controllers/PrescriptionReview.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Config/Routes.php');
        self::assertMatchesRegularExpression("/'cancel'\\s*=>\\s*'cancelled'/", $reviewSource);
        self::assertStringContainsString('prescription.edit', $routes);
        self::assertStringContainsString('medicine.create', $routes);
        self::assertStringContainsString('medicine.edit', $routes);
    }
    public function testOrderCreationPreservesPrescriptionValuesAndChecksExpiry(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Controllers/Operations.php');
        self::assertStringContainsString("'dosage' => \$item['dosage'] ?? null", $source);
        self::assertStringContainsString("'instructions' => \$item['instructions'] ?? null", $source);
        self::assertStringContainsString("->where('expiry_date >=', date('Y-m-d'))", $source);
        self::assertStringContainsString("'idempotency_key' => \$idempotencyKey", $source);
    }
    public function testAuditViewerIsReadOnlyScopedAndPaginated(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Controllers/Audit.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Farmassist/Views/pages/audit/index.php');
        self::assertStringContainsString("audit', 'Audit::index", $routes);
        self::assertStringContainsString('permission:audit.view', $routes);
        self::assertStringContainsString("where('clinic_id', \$clinicId)", $controller);
        self::assertStringContainsString('findAll($pageSize', $controller);
        self::assertStringContainsString('fa-circle-exclamation', $view);
        self::assertStringNotContainsString('delete-audit', strtolower($view));
    }
}
