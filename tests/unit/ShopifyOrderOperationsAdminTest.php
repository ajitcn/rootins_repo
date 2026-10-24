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
        foreach (['Total Shopify products', 'Needs Mapping', 'Conflicts', 'Map medicine', 'Apply mapping', 'affected order', 'Prescription required', 'mappingMedicineSelect'] as $text) self::assertStringContainsString($text, $view . $service);
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

    public function testRetryRunsTheCanonicalImporterAndReportsOnlyAPersistedTerminalState(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $adminController = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $pharmacyController = $this->source('app/Modules/Pharmacy/Controllers/ShopifyOrders.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php');

        foreach (['retry_started', 'retry_succeeded', 'retry_failed', 'importExisting', 'before_status', 'after_status', 'Order imported successfully.'] as $marker) {
            self::assertStringContainsString($marker, $service . $adminController . $pharmacyController . $view);
        }
        self::assertStringContainsString("in_array(\$after, ['imported', 'processed'], true)", $service);
        self::assertStringNotContainsString("'msg' => 'Operation completed.'", $view);
    }

    public function testOrderOperationActionsUseJsonPostTimeoutAndGuaranteedCleanup(): void
    {
        $routes = $this->source('app/Modules/Admin/Config/Routes.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $filter = $this->source('app/Filters/ShopifyMappingCsrf.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php');
        $script = $this->source('public/assets/js/admin/shopify/shopify_order_operation_detail.js');

        foreach (['order-operations/(:num)/action', 'order-operations/(:num)/json', 'permission:shopify.order.operations'] as $marker) {
            self::assertStringContainsString($marker, $routes);
        }
        foreach (['operationJson', 'operationError', 'affected_rows', 'SHOPIFY_ORDER_STATUS_INVALID', 'SHOPIFY_ORDER_DATABASE_FAILURE', 'correlation_id', 'target_table', 'sanitized_exception_message'] as $marker) {
            self::assertStringContainsString($marker, $controller);
        }
        self::assertStringContainsString('/admin/integrations/shopify/order-operations/', $filter);
        foreach (['data-shopify-operation-form', 'shopify-order-operation-action-form', 'data-no-global-loader', 'data-details-url', 'shopify_order_operation_detail.js'] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
        foreach (["$(document).on('submit', '.shopify-order-operation-action-form'", 'event.stopImmediatePropagation()', 'method: \'POST\'', 'AbortController', 'The server returned an unexpected HTML response.', 'finally', 'RootinsCsrf', 'setActionLoading', 'hideGlobalPreloader', 'window.location.reload()', 'credentials: \'same-origin\''] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testMedicineImportDetailHidesAppointmentActionAndShowsActualFailure(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_operation_detail.php');

        self::assertStringContainsString("\$import['intent_present'] =", $service);
        self::assertStringContainsString('appointmentIntentPresent', $service);
        self::assertStringContainsString("'status' => 'skipped', 'reason' => 'NO_APPOINTMENT_INTENT'", $service);
        self::assertStringContainsString("if (! empty(\$import['intent_present']))", $view);
        self::assertStringContainsString('Import error', $view);
        self::assertStringContainsString("\$import['error_message'] ?: \$import['last_error']", $view);
        self::assertStringContainsString('Retry validation/import', $view);
        self::assertStringContainsString('Mark ready for import', $view);
        self::assertStringContainsString('Quarantine', $view);
        self::assertStringContainsString('Release quarantine', $view);
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

    public function testProductCatalogueSyncUsesSeparateShopifyConfigurationAndPagination(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $routes = $this->source('app/Modules/Admin/Config/Routes.php');
        $migration = $this->source('app/Database/Migrations/2026-10-22-124000_CreateShopifyProductCatalogue.php');

        foreach (['accessScopes', 'read_products', 'graphql', 'hasNextPage', 'shopify_product_catalogue', 'shopify_variant_catalogue', 'last_seen_at'] as $text) {
            self::assertStringContainsString($text, $service . $migration);
        }
        foreach (['SHOPIFY_PRODUCTS_SCOPE_MISSING', 'SHOPIFY_CONFIGURATION_MISSING', 'SHOPIFY_INVALID_CREDENTIALS', 'SHOPIFY_API_VERSION_INVALID', 'SHOPIFY_RATE_LIMITED', 'SHOPIFY_PRODUCTS_SYNC_FAILED'] as $category) {
            self::assertStringContainsString($category, $service . $controller);
        }
        self::assertStringContainsString('order-mappings/sync', $routes);
        self::assertStringContainsString('last_sync', $service);
        self::assertStringContainsString('No Shopify variants were synchronized yet.', $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php'));
    }

    public function testMappingUpdateUsesStoreScopedVariantValidationAndStructuredErrors(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');

        foreach (['shopify_product_id', 'shopify_variant_id', 'medicine_id', 'store_domain', 'status', 'shopify_variant_catalogue', 'transBegin', 'transCommit', 'transRollback'] as $field) {
            self::assertStringContainsString($field, $service . $view);
        }
        foreach (['SHOPIFY_VARIANT_NOT_FOUND', 'ROOTINS_MEDICINE_NOT_FOUND', 'SHOPIFY_MAPPING_DUPLICATE', 'SHOPIFY_MAPPING_STORE_MISMATCH', 'SHOPIFY_MAPPING_PERMISSION_DENIED', 'SHOPIFY_MAPPING_CSRF_INVALID', 'SHOPIFY_MAPPING_DATABASE_FAILURE'] as $code) {
            self::assertStringContainsString($code, $service . $controller);
        }
        foreach (['error', 'correlation_id', 'error.fields', 'response.ok', 'button.disabled = false', 'data-mapping-errors'] as $marker) {
            self::assertStringContainsString($marker, $controller . $script . $view);
        }
        self::assertStringContainsString('Product mapping saved.', $service);
    }

    public function testMappingAjaxUsesConfiguredCi4CsrfTokenAndRecoversAfterFailure(): void
    {
        $security = $this->source('app/Config/Security.php');
        $filters = $this->source('app/Config/Filters.php');
        $csrfFilter = $this->source('app/Filters/ShopifyMappingCsrf.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        self::assertStringContainsString("csrfProtection = 'cookie'", $security);
        self::assertStringContainsString("tokenName = 'csrf_rtn'", $security);
        self::assertStringContainsString('ShopifyMappingCsrf::class', $filters);
        foreach (['parent::before', 'SHOPIFY_MAPPING_CSRF_INVALID', 'Your session expired. Refresh the page and try again.'] as $marker) {
            self::assertStringContainsString($marker, $csrfFilter);
        }
        foreach (['csrf_token()', 'csrf_hash()', 'credentials', 'same-origin', 'refreshCsrfToken', 'updateCsrf', 'SHOPIFY_MAPPING_CSRF_INVALID'] as $marker) {
            self::assertStringContainsString($marker, $controller . $script);
        }
        self::assertStringNotContainsString('csrf exclusion', strtolower($filters));
    }

    public function testPharmacyRecheckUsesSharedCsrfAndReturnsJsonForFilterFailures(): void
    {
        $filters = $this->source('app/Filters/ShopifyMappingCsrf.php');
        $permissions = $this->source('app/Filters/Permission.php');
        $common = $this->source('public/assets/js/common.js');
        $script = $this->source('public/assets/js/pharmacy/shopify_orders.js');
        $view = $this->source('app/Modules/Pharmacy/Views/pages/shopify_orders/index.php');

        foreach (['/pharmacy/shopify-orders/', 'SHOPIFY_MAPPING_CSRF_INVALID', 'csrf_hash()'] as $marker) {
            self::assertStringContainsString($marker, $filters);
        }
        foreach (['SHOPIFY_MAPPING_PERMISSION_DENIED', 'You do not have permission to recheck mappings.'] as $marker) {
            self::assertStringContainsString($marker, $permissions);
        }
        foreach (['window.RootinsCsrf', 'formData', 'X-CSRF-TOKEN', 'csrf_rtn'] as $marker) {
            self::assertStringContainsString($marker, $common . $script . $view);
        }
        foreach (['credentials: \'same-origin\'', 'body: data', '.finally', 'Your session expired. Refresh the page and try again.'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringNotContainsString("'shopify-orders' => ['csrf']", strtolower($this->source('app/Config/Filters.php')));
    }

    public function testAffectedOrderRecheckReturnsSafeCorrelatedErrorsAndCounts(): void
    {
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');
        foreach (['SHOPIFY_MAPPING_PERMISSION_DENIED', 'SHOPIFY_MAPPING_DATABASE_FAILURE', 'SHOPIFY_MAPPING_RECHECK_FAILED', 'correlation_id', 'orders_checked', 'orders_updated', 'lines_checked', 'lines_mapped', 'lines_unmapped', 'isDatabaseException'] as $marker) {
            self::assertStringContainsString($marker, $controller . $service);
        }
        foreach (['show_loader', 'hide_loader', 'safeServerError', 'data.append(\'shopify_product_id\'', 'event.preventDefault'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringNotContainsString('i.payload_json', $service);
        self::assertStringContainsString('e.payload_json', $service);
    }

    public function testManualMappingUsesOnlyStoreAndVariantIdentityAndQuarantinesInactiveMedicines(): void
    {
        $operations = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $import = $this->source('app/Services/Shopify/ShopifyOrderImportService.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        self::assertStringContainsString("where('shopify_variant_id', \$variantId)", $operations);
        self::assertStringContainsString('$mappingStatus', $operations);
        self::assertStringNotContainsString("\$fields['shopify_product_id']", $operations);
        self::assertStringNotContainsString("\$fields['medicine_id'] = 'Select an active", $operations);
        self::assertStringContainsString("'status' => 'medicine_inactive'", $import);
        self::assertStringContainsString('MEDICINE_INACTIVE', $import);
        self::assertStringContainsString("whereIn('shopify_variant_id', \$this->shopifyIdAliases(\$variantId, 'ProductVariant'))", $import);
        self::assertStringContainsString('Save this Shopify variant to the selected ROOTINS medicine?', $script);
        self::assertStringNotContainsString("where('status', 'active')->first() === null) throw new InvalidArgumentException('Select an active ROOTINS medicine.')", $operations);
    }

    public function testMappingTabsUseCanonicalStatusFiltersAndSaveAlwaysCleansUp(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        foreach (['mapping_status', "'needs'", "'mapped'", "'conflicts'", "'inactive'", 'tab_counts', 'active_mapping_status'] as $marker) {
            self::assertStringContainsString($marker, $service . $view . $script);
        }
        self::assertStringNotContainsString('mapping-fix=1', $view . $script);
        foreach (['fetchWithTimeout', 'MAPPING_REQUEST_TIMEOUT', 'jsonResponse', 'finally', 'credentials', 'same-origin', 'The mapping request returned an invalid server response.'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('refreshWorkspace', $script);
    }

    public function testMappingDatabaseFailuresAreClassifiedAndSchemaRepairIsMigrated(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');
        $migration = $this->source('app/Database/Migrations/2026-10-22-125000_RepairShopifyMedicineMappingIndexes.php');

        foreach (['SHOPIFY_MAPPING_TABLE_MISSING', 'SHOPIFY_MAPPING_COLUMN_MISSING', 'SHOPIFY_MAPPING_FOREIGN_KEY_FAILURE', 'SHOPIFY_MAPPING_DUPLICATE', 'SHOPIFY_MAPPING_DATABASE_FAILURE', 'fieldExists', 'created_at', 'updated_at'] as $marker) {
            self::assertStringContainsString($marker, $service . $controller);
        }
        self::assertStringContainsString('store_domain_status', $migration);
        self::assertStringContainsString('shopify_medicine_mapping_status', $migration);
    }

    public function testMappingModalHasOneSubmitPathAndGuaranteedCleanup(): void
    {
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        self::assertStringContainsString('bindSubmitHandler', $script);
        self::assertSame(0, substr_count($script, "document.addEventListener('submit'"));
        self::assertStringNotContainsString('submitNoReload', $view . $script);
        self::assertStringNotContainsString('function send(', $view . $script);
        foreach (['csrf-token-hash', 'csrfHeaders', 'show.bs.modal', 'shown.bs.modal', 'hide.bs.modal', 'hidden.bs.modal', 'finally', 'modal-backdrop', 'modal-open'] as $marker) {
            self::assertStringContainsString($marker, $view . $script);
        }
    }

    public function testMappingTabsRenderOnlyTheSelectedResultSection(): void
    {
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        foreach (["\$activeMappingStatus === 'needs'", "\$activeMappingStatus === 'mapped'", "\$activeMappingStatus === 'conflicts'", "\$activeMappingStatus === 'inactive'"] as $condition) {
            self::assertStringContainsString($condition, $view);
        }
        self::assertStringContainsString('aria-selected', $view . $script);
        self::assertStringNotContainsString('mapping-fix=1', $view . $script);
        self::assertStringNotContainsString("inactive.remove(); mapped.id", $script);
    }

    public function testMappingActionsUseDelegatedBootstrapCompatibleModalTargets(): void
    {
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');

        foreach (['shopifyMappingModal', 'shopifyIgnoreModal', 'data-toggle', 'data-target', 'wireModalTargets', "modal('show')", 'document.addEventListener(\'click\'', 'mapping-bulk-form', 'console.error', 'modal-backdrop', 'rootins-modal-lock'] as $marker) {
            self::assertStringContainsString($marker, $view . $script);
        }
        self::assertSame(1, substr_count($script, "document.addEventListener('click'"));
        self::assertStringContainsString('bindSubmitHandler', $script);
        self::assertSame(0, substr_count($script, "document.addEventListener('submit'"));
        self::assertStringContainsString('event.preventDefault()', $script);
        self::assertStringContainsString('z-index: 1060', $view . $this->source('public/assets/css/style.css'));
    }

    public function testMedicineSelectorUsesMedicineIdsAndPreservesSelection(): void
    {
        $view = $this->source('app/Modules/Admin/Views/pages/integrations/shopify_order_mappings.php');
        $script = $this->source('public/assets/js/admin/shopify/order-mappings.js');
        $service = $this->source('app/Services/Shopify/ShopifyOrderOperationsService.php');
        $controller = $this->source('app/Modules/Admin/Controllers/Integration/ShopifyOrderOperations.php');

        foreach (['name="medicine_id"', 'id="mappingMedicineSelect"', 'class="form-control btSelect"', 'data-live-search="true"', 'data-live-search-placeholder="Search medicine..."', 'Select ROOTINS medicine'] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
        self::assertStringNotContainsString('mapping-medicine-search', $view . $script);
        self::assertStringNotContainsString('Search ROOTINS medicines', $view . $script);
        self::assertStringNotContainsString('medicine-option', $view . $script . $this->source('public/assets/css/style.css'));
        self::assertStringNotContainsString('data-medicine-results', $view . $script);
        self::assertStringNotContainsString('medicine-results', $view . $script . $this->source('public/assets/css/style.css'));
        foreach (['mappingMedicineSelect', 'initializeMedicineSelector', 'setMedicineValue', "$('#mappingMedicineSelect').btSelect({", 'liveSearch: true', "$('#mappingMedicineSelect').btSelect('destroy')"] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('No active ROOTINS medicines are available.', $view);
        self::assertStringContainsString("where('status', 'active')", $service);
        self::assertStringContainsString("'medicines' => " . '$this->activeMedicines()', $service);
        self::assertStringContainsString('mappingDashboard', $controller);
        foreach (['data-medicines-url', 'responseMedicines', 'payload.data.medicines', 'payload.medicines', 'ROOTINS medicines response was invalid.', 'response_medicine_count', 'rendered_option_count'] as $marker) {
            self::assertStringContainsString($marker, $view . $script);
        }
        self::assertStringContainsString("if (key !== 'medicine_id') setField", $script);
        self::assertStringContainsString("event.target.id === 'mappingMedicineSelect'", $script);
    }
}
