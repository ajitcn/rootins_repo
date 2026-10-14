<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pharmacy\Services\DeliveryProviderCatalog;

final class DeliveryProviderMasterTest extends TestCase
{
    public function testProviderMasterAndOrderReferenceContractsArePresent(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-10-120000_CreateDeliveryProviders.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/DeliveryProvider.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/delivery/_modal.php');
        self::assertStringContainsString("delivery_providers", $migration);
        self::assertStringContainsString("delivery_provider_id", $migration);
        self::assertStringContainsString("provider_name", $controller);
        self::assertStringContainsString("Provider name already exists", $controller);
        self::assertStringContainsString("tracking_url_template", $controller);
        self::assertStringContainsString("findActive", $service);
        self::assertStringContainsString("delivery_provider_name_snapshot", $service);
        self::assertStringContainsString('name="provider_id"', $view);
        self::assertStringContainsString('deliveryTrackingPreview', $view);
    }

    public function testTrackingTemplatesUseAReferencePlaceholder(): void
    {
        $catalog = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/DeliveryProviderCatalog.php');
        self::assertStringContainsString('tracking_reference', $catalog);
        self::assertStringContainsString('rawurlencode', $catalog);
        self::assertStringContainsString("^https?://", $catalog);
    }

    public function testProviderAjaxFormHasSingleCleanupContract(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/delivery_provider.js');
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        self::assertStringContainsString("data-no-global-loader", $script);
        self::assertStringContainsString("form: form[0]", $script);
        self::assertStringContainsString("complete: function", $common);
        self::assertStringContainsString('function rootinsCleanupAjaxRequest', $common);
        self::assertStringContainsString('finally', $common);
        self::assertStringContainsString("removeClass('loading is-loading')", $common);
        self::assertStringContainsString("rootins-submit-snapshot", $common);
        self::assertStringContainsString("hide_loader()", $common);
    }

    public function testTrackingUrlSupportsOptionalPlaceholdersAndEncodedReferences(): void
    {
        $catalog = new DeliveryProviderCatalog();
        self::assertSame('https://track.test/AB%2F12%3Fx', $catalog->trackingUrl('https://track.test/{tracking_reference}', 'AB/12?x'));
        self::assertSame('https://track.test/status', $catalog->trackingUrl('https://track.test/status', 'AB/12?x'));
        self::assertSame('http://track.test/status', $catalog->trackingUrl('http://track.test/status', 'AB/12?x'));
        self::assertNull($catalog->trackingUrl('javascript:alert(1)', 'AB/12?x'));
        self::assertNull($catalog->trackingUrl('', 'AB/12?x'));
    }
}
