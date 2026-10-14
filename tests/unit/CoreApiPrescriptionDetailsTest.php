<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoreApiPrescriptionDetailsTest extends TestCase
{
    private string $routes;
    private string $service;

    protected function setUp(): void
    {
        $this->routes = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Config/Routes.php');
        $this->service = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiPrescriptionUploadService.php');
    }

    public function testAuthorizedPrescriptionDetailsRouteUsesCoreApiAuthenticationAndPublicReference(): void
    {
        self::assertStringContainsString("prescriptions/(:segment)", $this->routes);
        self::assertStringContainsString('CoreApiContext::get()', file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Controllers/V1.php'));
        foreach (['prescription_reference', 'document_reference', 'patient_reference', 'original_filename', 'file_type', 'file_size', 'download_url', 'download_url_expires_at'] as $field) {
            self::assertStringContainsString("'{$field}'", $this->service);
        }
    }

    /** @dataProvider statusProvider */
    public function testPrescriptionStatusValuesAreSupported(string $status): void
    {
        self::assertStringContainsString("'{$status}'", $this->service);
    }

    /** @return array<string, array{string}> */
    public static function statusProvider(): array
    {
        return [
            'uploaded' => ['uploaded'],
            'under review' => ['under_review'],
            'accepted' => ['accepted'],
            'rejected' => ['rejected'],
            'clarification required' => ['clarification_required'],
        ];
    }

    public function testUnauthorizedAndMissingDocumentsUseStableNotFoundScope(): void
    {
        self::assertStringContainsString("'PRESCRIPTION_NOT_FOUND'", $this->service);
        self::assertStringContainsString("where('documents.source_type', 'core_api')", $this->service);
        self::assertStringContainsString('patients.id = documents.patient_id AND patients.clinic_id = documents.clinic_id', $this->service);
        self::assertStringContainsString("where('patients.status', 1)", $this->service);
    }

    public function testExpiredDownloadUrlsAreNotGenerated(): void
    {
        self::assertStringContainsString('retention_expires_at', $this->service);
        self::assertStringContainsString("'download_url' => " . '$downloadUrl', $this->service);
        self::assertStringContainsString('$downloadUrl === null', $this->service);
    }

    public function testStorageKeysAndInternalIdentifiersAreNotReturned(): void
    {
        $details = substr($this->service, strpos($this->service, 'public function details'));
        self::assertStringNotContainsString("'file_path' =>", $details);
        self::assertStringNotContainsString("'patient_id' =>", $details);
        self::assertStringNotContainsString("'clinic_id' =>", $details);
    }
}
