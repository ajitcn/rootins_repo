<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyOrderImportTest extends TestCase
{
    public function testPhaseFiveRoutesPermissionsAndTablesAreExplicit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-170000_CreateShopifyOrderImport.php');
        $lineRepair = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-23-170000_RepairShopifyOrderLineIndexes.php');
        $permissions = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-06-171000_AddShopifyOrderImportPermissions.php');

        self::assertStringContainsString("orders/create", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php'));
        self::assertStringContainsString("orders/import", $routes);
        self::assertStringContainsString("orders/reconcile", $routes);
        self::assertStringContainsString('shopify.order.import', $permissions);
        self::assertStringContainsString('shopify.order.reconcile', $permissions);

        foreach (['shopify_order_imports', 'shopify_order_import_lines', 'shopify_medicine_mappings', 'shopify_order_id', 'shopify_line_item_id'] as $field) {
            self::assertStringContainsString($field, $migration);
        }
        self::assertStringContainsString('shopify_product_id_shopify_variant_id', $lineRepair);
        self::assertStringContainsString("store_domain, shopify_order_id, shopify_line_item_id", $lineRepair);
        self::assertStringContainsString('shopify_order_line_identity', $lineRepair);
        self::assertStringContainsString('ADD INDEX', $lineRepair);
        self::assertStringContainsString('shopify_order_line_import', $lineRepair);
        self::assertStringContainsString('hasLeadingNonUniqueIndex', $lineRepair);
        self::assertStringContainsString('fieldExists', $lineRepair);
        self::assertStringContainsString('isset($this->indexes()', $lineRepair);
        self::assertStringContainsString('hasIndex($this->indexes()', $lineRepair);
        self::assertStringContainsString('public function down', $lineRepair);
        self::assertStringNotContainsString('FOREIGN_KEY_CHECKS', $lineRepair);
    }

    public function testImportUsesIdentityAndControlledMappingBeforeFulfilment(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('linkedCustomer', $service);
        self::assertStringContainsString('storeLinesAndResolveMappings', $service);
        self::assertStringContainsString('approvedPrescriptionMatches', $service);
        self::assertStringContainsString('new OrderFulfilmentService', $service);
        self::assertStringNotContainsString("where('medicine_name'", $service);
        self::assertStringNotContainsString('unit_price', $service);
    }

    public function testImportRecordsSafeStagesAndSpecificFailureCategories(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $failure = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportFailure.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        foreach (['payload_parsing', 'order_identity_resolution', 'customer_patient_resolution', 'order_creation_update', 'line_item_import', 'shopify_product_variant_extraction', 'medicine_mapping_lookup', 'prescription_reference_lookup', 'payment_state_mapping', 'order_status_update', 'audit_history_write'] as $stage) {
            self::assertStringContainsString($stage, $service . $controller);
        }
        foreach (['SHOPIFY_ORDER_PAYLOAD_INVALID', 'SHOPIFY_ORDER_IDENTITY_FAILURE', 'SHOPIFY_ORDER_LINE_IMPORT_FAILURE', 'SHOPIFY_PRODUCT_MAPPING_FAILURE', 'SHOPIFY_PATIENT_LINK_FAILURE', 'SHOPIFY_ORDER_DATABASE_FAILURE', 'SHOPIFY_PRESCRIPTION_LINK_FAILURE'] as $category) {
            self::assertStringContainsString($category, $failure . $service);
        }
        self::assertStringNotContainsString('getFile()', $controller);
    }

    public function testMedicinePaymentReusesTheImportedOrderIdentityAndSkipsAppointments(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        foreach (['findPharmacyOrderByShopifyIdentity', 'external_store_domain', 'external_order_id', 'external_identity_recovered', 'paymentStage', 'internal_import_reference', 'internal_pharmacy_order_reference'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('appointment_finalization_skipped', $controller);
        self::assertStringContainsString('NO_APPOINTMENT_INTENT', $controller);
        $medicineBranch = substr($controller, strpos($controller, "if (\$intentReference === '')"), strpos($controller, 'appointment_payment_received') - strpos($controller, "if (\$intentReference === '')"));
        self::assertStringContainsString('processEvent($eventId, false', $medicineBranch);
        self::assertStringNotContainsString('finalizePaid($event, $payload)', $medicineBranch);
    }

    public function testMedicinePaymentUsesReturnedImportIdAndStoreScopedFallback(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $failure = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportFailure.php');

        foreach ([
            'internal_import_id',
            'updateImportedPaymentRecord',
            'import_record_updated_no_pharmacy_order',
            'store_scoped_import_record_recovered',
        ] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('ORDER_IMPORT_RECORD_NOT_FOUND', $failure . $service);
        self::assertStringNotContainsString('where(\'shopify_line_item_id\', $shopifyOrderId)', $service);
        self::assertStringNotContainsString('where(\'shopify_order_number\', $shopifyOrderId)', $service);
        self::assertStringContainsString('WHERE store_domain = ? AND shopify_order_id = ?', $service);
        self::assertStringContainsString('paymentLookupDiagnostic', $service);
        self::assertStringContainsString("'import_row_exists' => \$record !== null", $service);
        self::assertStringContainsString("'current_import_status' => \$record['status'] ?? null", $service);
        self::assertStringContainsString("'lookup_type' => \$lookupType", $service);
    }

    public function testPaidOrderRegressionFlowsRemainRetryableAndIdempotent(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        // orders/create -> orders/paid and duplicate orders/paid both reuse
        // the import ID returned by upsertImport and the same payment method.
        self::assertStringContainsString('$importId = (int) $import[\'id\'];', $service);
        self::assertStringContainsString("'internal_import_id' => \$importId", $service);
        self::assertStringContainsString('processEvent($eventId, false', $controller);
        self::assertStringContainsString("\$paymentTarget !== null && \$paymentTarget !== (string) \$order['payment_status']", $service);
        self::assertStringContainsString('ORDER_IMPORT_RECORD_NOT_FOUND', $controller);
        self::assertStringContainsString('SHOPIFY_ORDER_IMPORT_STATUS_INVALID', $controller);

        // orders/paid before orders/create must still enter the same importer,
        // which creates the import row before payment update.
        $paidBranch = substr($controller, strpos($controller, "if (\$topic === 'orders/paid'") );
        self::assertStringContainsString('processPaidEvent', $paidBranch);
        self::assertStringContainsString('new ShopifyOrderImportService())->processEvent', $controller);
    }

    public function testFailedImportIsRecoveredByPaidRetryWithoutCreatingAnotherImport(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString('WHERE store_domain = ? AND shopify_order_id = ?', $service);
        self::assertStringNotContainsString('WHERE store_domain = ? AND shopify_order_id = ? AND status', $service);
        foreach (['pending', 'failed', 'ready_to_import', 'processed', 'paid', 'retry_count', 'payment_retry', 'payment_processed', 'FOR UPDATE'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString("'status' => 'processed'", $service);
        self::assertStringContainsString('Shopify import cannot be paid from status:', $service);
    }

    public function testMedicinePaymentIsIdempotentAndRemainsAppointmentIndependent(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        self::assertStringContainsString('$paymentTarget !== null', $service);
        self::assertStringContainsString('financial_status', $service);
        self::assertStringContainsString('$paymentTarget', $service);
        self::assertStringContainsString('intent_present', $controller);
        self::assertStringContainsString('appointment_finalization_skipped', $controller);
    }

    public function testLineImportRepairsReducedPaidPayloadsAndUsesLineIdentity(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        foreach (['line_items_present', 'line_item_count', 'line_item_identity_resolution', 'product_id_extraction', 'variant_id_extraction', 'mapping_resolution', 'line_item_database_upsert', 'line_item_status_update'] as $stage) {
            self::assertStringContainsString($stage, $service);
        }
        foreach (['lineItemsNeedHydration', 'line_items_reused', 'store_domain', 'shopify_order_id', 'shopify_line_item_id', 'where(\'store_domain\', $store)', 'where(\'shopify_order_id\', $orderId)'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringNotContainsString("where('shopify_line_item_id', \$variantId)", $service);
    }

    public function testReusableProductVariantValuesAreNotLineIdentity(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-23-170000_RepairShopifyOrderLineIndexes.php');

        self::assertStringContainsString("where('store_domain', \$store)", $service);
        self::assertStringContainsString("where('shopify_order_id', \$orderId)", $service);
        self::assertStringContainsString("where('shopify_line_item_id', \$lineId)", $service);
        self::assertStringNotContainsString("where('shopify_product_id', \$productId)->where('shopify_variant_id', \$variantId)", $service);
        self::assertStringContainsString('Same product/variant values remain reusable across orders', $migration);
    }

    public function testWebhookOrderTopicsUseExistingHmacAndEventIdempotencyPipeline(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        $eventService = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyWebhookEventService.php');

        foreach (['orders/create', 'orders/updated', 'orders/cancelled', 'refunds/create', 'fulfillments/create', 'fulfillments/update'] as $topic) {
            self::assertStringContainsString($topic, $controller);
        }
        self::assertStringContainsString('isValid($rawBody, $signature)', $controller);
        self::assertStringContainsString('external_event_id', $eventService);
        self::assertStringContainsString('replacePayload', $eventService);
    }

    public function testSensitiveShopifyFieldsAreNotStoredByTheOrderPipeline(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        foreach (['access_token', 'password', 'otp', 'webhook_secret'] as $secret) {
            self::assertStringNotContainsString($secret, strtolower($service));
        }
    }
}
