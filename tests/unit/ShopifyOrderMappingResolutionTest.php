<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyOrderMappingResolutionTest extends TestCase
{
    public function testOrderImportUsesVariantFirstProductFallbackAndNeverLineItemIdentity(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString("where('shopify_variant_id', \$variantId)", $service);
        self::assertStringContainsString("where('shopify_product_id', \$productId)", $service);
        self::assertStringContainsString("where('shopify_variant_id', null)", $service);
        self::assertStringNotContainsString("where('shopify_line_item_id', \$variantId)", $service);
        self::assertStringContainsString("'mapping_reason' => \$resolution['reason']", $service);
        self::assertStringContainsString('prescriptionRequiredForOrder', $service);
        self::assertStringContainsString('shopify_mapping_ignores', $service);
    }

    public function testReconciliationUpdatesExistingLineIdentityAndRechecksAfterMappingSave(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');
        $operations = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderOperationsService.php');

        self::assertStringContainsString("where('import_id', \$importId)->where('shopify_line_item_id', \$lineId)->first()", $service);
        self::assertStringContainsString('$model->update((int) $existing[\'id\'], $data)', $service);
        self::assertStringNotContainsString("->where('shopify_line_item_id', \$lineId)->delete()", $service);
        self::assertStringContainsString("recheckMappings(['store_domain' => \$store, 'shopify_product_id' => \$productId, 'shopify_variant_id' => \$variantId])", $operations);
        self::assertStringContainsString("'recheck_mapping'", $operations);
        self::assertStringContainsString("'verified' => true", $operations);
    }

    public function testDiagnosticsContainOnlyMappingIdentifiersAndResolution(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyOrderImportService.php');

        self::assertStringContainsString("'Shopify mapping resolution order='", $service);
        self::assertStringContainsString("' lookup=' . \$resolution['lookup_key']", $service);
        self::assertStringContainsString("' result=' . \$resolution['result']", $service);
        self::assertStringNotContainsString('customer_email', substr($service, strpos($service, 'Shopify mapping resolution order=')));
        self::assertStringNotContainsString('access_token', substr($service, strpos($service, 'Shopify mapping resolution order=')));
    }
}
