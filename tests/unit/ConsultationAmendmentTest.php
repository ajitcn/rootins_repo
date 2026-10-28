<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ConsultationAmendmentTest extends TestCase
{
    public function testCompletedConsultationsRemainReadOnlyAndAmendmentIsSeparate(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $adminRoutes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $adminController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Consultation/Consultation.php');
        self::assertStringContainsString('Completed consultations cannot be modified.', $controller);
        self::assertStringContainsString('Clinical::amendConsultation', $routes);
        self::assertStringContainsString('permission:consultation.edit', $routes);
        self::assertStringContainsString('Consultation::amend', $adminRoutes . $adminController);
    }

    public function testAmendmentRequiresReasonAndPreservesOriginalHistory(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ConsultationAmendmentService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-28-140000_CreateConsultationAmendments.php');
        foreach (['AMENDMENT_REASON_REQUIRED', 'consultation.amendment_requested', 'consultation.amendment_created', 'original_consultation_id', 'revision_number', 'revision_json', 'amendment_reason', 'transBegin', 'transCommit'] as $marker) {
            self::assertStringContainsString($marker, $service . $migration);
        }
        self::assertStringContainsString("where('status', 'completed')", $service);
        self::assertStringContainsString("'status' => 'amended'", $service);
    }

    public function testAmendmentInvalidatesOldOfferAndDoesNotUpdateExistingOrders(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ConsultationAmendmentService.php');
        foreach (['shopify_prescription_cart_pushes', 'whereIn(\'status\', [\'available\', \'cart_pushed\'])', "'status' => 'cancelled'", 'hasExistingOrder', 'pending_order_review', 'medicine_orders', 'shopify_order_imports'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringNotContainsString("->where('status', 'paid')->update", $service);
    }

    public function testNewPrescriptionReferenceAndValidityAreRecalculated(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ConsultationAmendmentService.php');
        foreach (['TMP-RX-', 'RXP-%06d', 'calculateValidUntil', 'durationDays', 'prescription_id'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
    }
}
