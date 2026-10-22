<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionProxyUploadFieldTest extends TestCase
{
    public function testCanonicalMultipartFieldAndBackwardAliasAreExplicit(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        self::assertIsString($source);
        self::assertStringContainsString("getFile('file')", $source);
        self::assertStringContainsString("getFile('document')", $source);
        foreach (['isValid()', 'getError()', 'hasMoved()', 'FILE_REQUIRED', 'UPLOAD_ERR_OK', 'correlation_id'] as $contract) self::assertStringContainsString($contract, $source);
    }

    public function testProductHandleFormsAndUploadMimeContractsAreCovered(): void
    {
        $proxy = file_get_contents(ROOTPATH . 'app/Modules/Shopify/Controllers/Proxy.php');
        $upload = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyPrescriptionUploadService.php');
        self::assertIsString($proxy);
        self::assertIsString($upload);
        foreach (['json_decode', 'preg_split', 'product_handles', 'PRODUCT_HANDLES_INVALID'] as $contract) self::assertStringContainsString($contract, $proxy);
        foreach (['application/pdf', 'image/jpeg', 'image/png', 'MAX_BYTES = 10485760', 'finfo_open(FILEINFO_MIME_TYPE)'] as $contract) self::assertStringContainsString($contract, $upload);
    }

    public function testDeveloperRequestUsesRequiredFieldAndHeader(): void
    {
        $docs = file_get_contents(ROOTPATH . 'docs/SHOPIFY_PRESCRIPTION_WORKFLOW.md');
        self::assertStringContainsString('file: <binary>', $docs);
        self::assertStringContainsString('product_handles: dummy-product-02', $docs);
        self::assertStringContainsString('Idempotency-Key: <unique-uuid>', $docs);
    }
}
