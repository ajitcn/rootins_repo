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
        foreach (['AMENDMENT_REASON_REQUIRED', 'consultation.amendment_requested', 'consultation.amendment_created', 'original_consultation_id', 'revision_number', 'revision_json', 'original_consultation', 'original_items', 'amendment_reason', 'transBegin', 'transCommit'] as $marker) {
            self::assertStringContainsString($marker, $service . $migration);
        }
        self::assertStringContainsString("where('status', 'completed')", $service);
        self::assertStringContainsString("'status' => 'amended'", $service);
    }

    public function testAmendmentInvalidatesOldOfferAndDoesNotUpdateExistingOrders(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ConsultationAmendmentService.php');
        foreach (['shopify_prescription_cart_pushes', 'whereIn(\'status\', [\'available\', \'cart_pushed\'])', "'status' => 'cancelled'", 'hasExistingOrder', 'AMENDMENT_ORDER_LOCKED', 'medicine_orders', 'shopify_order_imports'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringNotContainsString("->where('status', 'paid')->update", $service);
        self::assertStringContainsString('prescriptionChanged', $service);
        self::assertStringContainsString('consultationUpdate', $service);
    }

    public function testNewPrescriptionReferenceAndValidityAreRecalculated(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ConsultationAmendmentService.php');
        foreach (['TMP-RX-', 'RXP-%06d', 'calculateValidUntil', 'durationDays', 'prescription_id'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
    }

    public function testAmendmentMigrationHasOneAutoColumnAndScopedForeignKeys(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-28-140000_CreateConsultationAmendments.php');
        self::assertSame(1, substr_count($migration, "'auto_increment' => true"));
        self::assertStringContainsString('$this->forge->addPrimaryKey(\'id\')', $migration);
        foreach (['consultations', 'appointments', 'prescriptions', 'doctors', 'clinics', 'users'] as $table) {
            self::assertStringContainsString("'$table'", $migration);
        }
        self::assertStringContainsString("'revision_number' => ['type' => 'INT'", $migration);
        self::assertStringContainsString("'default' => 1", $migration);
    }

    public function testDoctorAmendmentModalUsesExistingAjaxFormAndCleansUp(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        foreach (['clinical-amend-consultation', 'clinical-amendment-modal', 'clinical-amendment-reason', 'doctor/clinical/amend', 'workspaceForm.attr', 'ajaxRequest', 'reload_page', 'complete: function'] as $marker) {
            self::assertStringContainsString($marker, $view);
        }
    }
}
