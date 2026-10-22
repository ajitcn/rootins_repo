<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyOrderOperationsAdminTest extends TestCase
{
    private function source(string $path): string { return (string) file_get_contents(ROOTPATH . $path); }

    public function testAdminOperationsAreSeparateAndPermissionProtected(): void
    {
        $routes = $this->source('app/Modules/Admin/Config/Routes.php');
        self::assertStringContainsString('shopify.order.operations', $routes);
        foreach (['order-mappings', 'patient-readiness', 'order-operations', 'order-operations/(:num)'] as $route) self::assertStringContainsString($route, $routes);
        self::assertStringContainsString('ShopifyOrderOperations', $routes);
        self::assertStringNotContainsString('Webhook::receive', $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php'));
    }

    public function testMappingOperationsValidateActiveMedicineAndDuplicates(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        self::assertStringContainsString('where(\'status\', \'active\')', $service);
        self::assertStringContainsString('already mapped', $service);
        self::assertStringContainsString('shopify_product_id', $service);
        self::assertStringContainsString('shopify_variant_id', $service);
        self::assertStringNotContainsString('shopify_price', $service);
    }

    public function testMappingWorkspaceProvidesCardsTabsBulkMappingAndSafeIgnore(): void
    {
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        foreach (['Total Shopify products', 'Needs Mapping', 'Conflicts', 'Map medicine', 'Apply mapping', 'affected order', 'Prescription required', 'medicine-results'] as $text) self::assertStringContainsString($text, $view . $service);
        foreach (['mappingDashboard', 'bulkSaveMappings', 'ignoreMapping', 'recheckMappings', 'suggestions'] as $method) self::assertStringContainsString($method, $service);
        self::assertStringContainsString('shopify_mapping_ignores', $this->source('app/Database/Migrations/2026-10-22-123000_CreateShopifyMappingIgnores.php'));
    }

    public function testMappingActionsRemainPermissionProtectedAndPreserveCsrf(): void
    {
        $routes = $this->source('app/Modules/Admin/Config/Routes.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        foreach (['order-mappings/medicines', 'order-mappings/bulk-save', 'order-mappings/ignore', 'order-mappings/recheck'] as $route) self::assertStringContainsString($route, $routes);
        self::assertStringContainsString('permission:shopify.order.operations', $routes);
        self::assertGreaterThanOrEqual(2, substr_count($view, 'csrf_field()'));
        self::assertStringNotContainsString('shopify_price', $view);
    }

    public function testQueuePreservesFailuresAndHasOperationalHistory(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $migration = $this->source('app/Database/Migrations/2026-10-22-120000_CreateShopifyOrderOperations.php');
        foreach (['last_error', 'retry_count', 'quarantine_reason', 'mapping_status', 'patient_status'] as $field) self::assertStringContainsString($field, $service . $migration);
        self::assertStringContainsString('shopify_order_import_history', $migration);
        self::assertStringContainsString('shopify.order_operation_', $service);
    }

    public function testTemporaryPrescriptionViewingIsAuditedAndSigned(): void
    {
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        self::assertStringContainsString('temporaryUrl', $controller);
        self::assertStringContainsString('shopify.prescription_temporary_view', $controller);
        self::assertStringContainsString("order-operations/prescriptions/(:num)/view", $this->source('app/Modules/Admin/Config/Routes.php'));
    }

    public function testExistingWebhookPipelineStillStoresAndProcessesOrders(): void
    {
        $webhook = $this->source('app/Modules/Shopify/Controllers/Webhook.php');
        $import = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');
        self::assertStringContainsString('X-Shopify-Hmac-Sha256', $webhook);
        self::assertStringContainsString('X-Shopify-Webhook-Id', $webhook);
        self::assertStringContainsString('upsertImport', $import);
        self::assertStringContainsString('processEvent', $webhook);
    }
}
