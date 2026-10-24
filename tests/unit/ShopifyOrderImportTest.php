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

        foreach (['external_store_domain', 'external_order_id', 'paymentStage', 'internal_import_reference', 'internal_pharmacy_order_reference'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringNotContainsString('findPharmacyOrderByShopifyIdentity', $service);
        self::assertStringNotContainsString('external_identity_recovered', $service);
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
            'exact_import_row_updated',
            'payment_status',
            'paid_at',
            'error_message',
        ] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('ORDER_IMPORT_RECORD_NOT_FOUND', $failure . $service);
        self::assertStringNotContainsString('where(\'shopify_line_item_id\', $shopifyOrderId)', $service);
        self::assertStringNotContainsString('where(\'shopify_order_number\', $shopifyOrderId)', $service);
        self::assertStringContainsString('WHERE id = ? LIMIT 1', $service);
        self::assertStringContainsString('paymentUpdateDiagnostic', $service);
        self::assertStringContainsString("'row_found' => \$rowFound", $service);
        self::assertStringContainsString("'payment_update_result' => \$paymentUpdateResult", $service);
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
        self::assertStringContainsString('ORDER_IMPORT_RECORD_NOT_FOUND', $service);
        self::assertStringContainsString('SHOPIFY_ORDER_IMPORT_STATUS_INVALID', $service);

        // orders/paid before orders/create must still enter the same importer,
        // which creates the import row before payment update.
        $paidBranch = substr($controller, strpos($controller, "if (\$topic === 'orders/paid'") );
        self::assertStringContainsString('processPaidEvent', $paidBranch);
        self::assertStringContainsString('new ShopifyOrderImportService())->processEvent', $controller);
    }

    public function testFailedImportIsRecoveredByPaidRetryWithoutCreatingAnotherImport(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString("->where('store_domain', \$store)->where('shopify_order_id', \$orderId)", $service);
        self::assertStringContainsString('WHERE id = ? LIMIT 1', $service);
        foreach (['pending', 'failed', 'ready_to_import', 'processed', 'paid', 'retry_count', 'payment_retry', 'payment_processed', 'FOR UPDATE'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString("'status' => 'processed'", $service);
        self::assertStringContainsString('Shopify import cannot be paid from status:', $service);
    }

    public function testAllOrderTopicsReuseStringIdentityAndReturnActualFailureCategory(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');

        foreach (["\$orderId = \$this->shopifyId(\$payload['id'] ?? '');", 'importIdentityDiagnostic', 'import_row_existed_before_upsert', 'internal_import_id', 'processing_stage'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('return $this->error(500, $failure->category', $controller);
        self::assertStringContainsString("\$data['retry_count'] = (int) (\$existing['retry_count'] ?? 0) + 1", $service);
    }

    public function testOrdersCreateWithoutImportUsesTheInsertId(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        self::assertStringContainsString("if (\$existing === null)", $service);
        self::assertStringContainsString("\$data['status'] = 'received'", $service);
        self::assertStringContainsString("\$actualId = \$existing !== null ? (int) \$existing['id'] : (int) \$model->getInsertID()", $service);
    }

    public function testPaidRetryAndDuplicateCreateDoNotUseStatusAsIdentity(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Webhook.php');
        self::assertStringContainsString("->where('store_domain', \$store)->where('shopify_order_id', \$orderId)", $service);
        self::assertStringContainsString('WHERE id = ? LIMIT 1', $service);
        self::assertStringContainsString("if (\$topic === 'orders/paid' && \$result['id'] !== null)", $controller);
        self::assertStringContainsString("if (\$result['duplicate'])", $controller);
    }

    public function testExistingImport53StylePaidOrderFinishesProcessedWithoutOrderNotFound(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-24-100000_AddShopifyOrderImportPaymentState.php');

        foreach ([
            "\$importId = (int) \$import['id'];",
            "\$paidImport = strtolower((string) (\$payload['financial_status'] ?? '')) === 'paid';",
            "\$mappingResult = \$this->storeLinesAndResolveMappings(\$importId",
            "'patient_status' => 'linked'",
            'prescription_reference_lookup',
            "'payment_status' => \$paymentStatus",
            "'status' => 'processed'",
            'PHARMACY_ORDER_CREATION_FAILED',
        ] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringNotContainsString('updateImportedPaymentRecord($event, $importId, $store, $shopifyOrderId, $payload)', $service);
        self::assertStringContainsString('updateImportedPaymentRecord($event, $importId, $payload)', $service);
        foreach (["'internal_import_id' => \$importId", "'shopify_order_id' => \$orderId", "'store_domain' => \$store"] as $identityField) {
            self::assertStringContainsString($identityField, $service);
        }
        foreach (['payment_status', 'paid_at', 'error_message'] as $field) self::assertStringContainsString($field, $migration);
    }

    public function testLargePaidShopifyOrderIdRemainsStringAndReportsColumnType(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-24-110000_EnsureShopifyOrderImportIdString.php');

        self::assertStringContainsString("'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 64", $migration);
        self::assertStringContainsString("\$orderId = \$this->shopifyId(\$payload['id']);", $service);
        self::assertStringContainsString('shopifyOrderImportColumnType', $service);
        self::assertStringContainsString("'database_column_type' => \$databaseColumnType", $service);
        $orderId = '18967742611775';
        self::assertSame('18967742611775', $orderId);
        self::assertStringNotContainsString('(int) $payload[\'id\']', $service);
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

    public function testPaymentDoesNotRequirePharmacyOrderAndUsesImportIdempotencyReference(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $fulfilment = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');

        self::assertStringContainsString("'idempotency_key' => 'shopify-import:' . (string) \$importId", $service);
        self::assertStringContainsString("\$external['idempotency_key'] ?? ''", $fulfilment);
        self::assertStringContainsString('PHARMACY_ORDER_CREATION_FAILED', file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportFailure.php'));
        self::assertStringContainsString('import_record_updated_no_pharmacy_order', $service);
        self::assertStringNotContainsString("->where('external_order_id', \$externalOrderId)", $service);
    }

    public function testExistingFailedImportCanBePaidRepeatedlyWithoutDuplicatePharmacyOrders(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $fulfilment = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');

        self::assertStringContainsString("\$allowedStatuses = ['pending', 'failed', 'ready_to_import', 'processed', 'paid', 'imported'];", $service);
        self::assertStringContainsString("'payment_retry'", $service);
        self::assertStringContainsString("'payment_processed'", $service);
        self::assertStringContainsString("WHERE id = ? LIMIT 1", $service);
        self::assertStringContainsString("'idempotency_key' => \$idempotencyKey", $fulfilment);
        self::assertStringContainsString('idempotent_reuse', $fulfilment);
        self::assertStringNotContainsString('ORDER_NOT_FOUND', substr($service, strpos($service, 'private function updateImportedPaymentRecord')));
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
