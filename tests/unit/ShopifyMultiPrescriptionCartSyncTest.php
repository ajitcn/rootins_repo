<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyMultiPrescriptionCartSyncTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(ROOTPATH . $path);
        self::assertIsString($source);

        return $source;
    }

    public function testActiveEndpointReturnsTheWholeEligiblePrescriptionCollection(): void
    {
        $service = $this->source('app/Services/Shopify/ShopifyActivePrescriptionService.php');
        self::assertStringContainsString("->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)", $service);
        self::assertStringContainsString('->orderBy(\'p.created_at\', \'DESC\')->orderBy(\'p.id\', \'DESC\')->get()->getResultArray()', $service);
        self::assertStringContainsString('foreach ($prescriptions as $prescription)', $service);
        self::assertStringContainsString("'cart_status' => \$cartStatus", $service);
    }

    public function testStorefrontProcessesEachPrescriptionIndependently(): void
    {
        $theme = $this->source('public/assets/js/shopify/active-prescriptions.js');
        foreach (['data.prescriptions || []).forEach', 'cart_status', 'cart_pushed', 'ordered', 'paid', 'expired', 'cancelled', 'cartLineKey', '_rx_reference', 'cart_items_added'] as $marker) {
            self::assertStringContainsString($marker, $theme);
        }
        self::assertStringContainsString("['cancelled', 'expired'].indexOf(prescription.cart_status)", $theme);
        self::assertStringContainsString("prescription.cart_status !== 'available'", $theme);
    }

    public function testDeduplicationUsesPrescriptionReferenceAndVariant(): void
    {
        $theme = $this->source('public/assets/js/shopify/active-prescriptions.js');
        self::assertStringContainsString("return String(reference || '').trim() + '|' + String(variantId || '').trim();", $theme);
        self::assertStringContainsString('existing[cartLineKey(lineReference, variantId)] = true', $theme);
        self::assertStringContainsString('existing[key]', $theme);
        self::assertStringContainsString("properties: { '_rx_reference': reference", $theme);
    }

    public function testDuplicateLoadsAreGuardedAndDiagnosticsIncludePerPrescriptionCounts(): void
    {
        $theme = $this->source('public/assets/js/shopify/active-prescriptions.js');
        foreach (['var syncing = {}', 'syncing[reference]', 'delete syncing[reference]', 'customer_id', 'total_prescriptions', 'eligible_item_count', 'skipped_item_reason', 'added_item_count'] as $marker) {
            self::assertStringContainsString($marker, $theme);
        }
    }
}
