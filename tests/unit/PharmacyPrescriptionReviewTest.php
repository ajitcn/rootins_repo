<?php

use CodeIgniter\Test\CIUnitTestCase;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\PrescriptionReviewService;
use Pharmacy\Services\TransitionContract;

final class PharmacyPrescriptionReviewTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove(['roles', 'permissions', 'user', 'clinic_id']);
        parent::tearDown();
    }

    public function testViewPermissionAllowAndDenyAreEnforced(): void
    {
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => ['clinic_id' => 1], 'permissions' => ['pharmacy.prescription.review.view']]);
        $scope = new PharmacyScope();
        self::assertSame(1, $scope->clinicId());
        self::assertTrue($scope->can('pharmacy.prescription.review.view'));
        self::assertFalse($scope->can('pharmacy.prescription.review.approve'));
        session()->set('roles', ['Admin']);
        self::assertNull($scope->clinicId());
    }

    public function testReviewQueryContainsClinicIsolationSearchFiltersAndPagination(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PrescriptionReviewService.php');

        foreach (["where('prescriptions.clinic_id'", "where('patients.clinic_id'", "where('doctors.clinic_id'", 'patient_mobile_number', 'prescription_number', 'mobile_normalized', 'doctor_id', 'review_state', 'prescription_date >=', 'prescription_date <=', 'get($pageSize'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('DataTable', $service);
    }

    public function testListAndGridUseTheSameNormalizedRecordsAndReloadToggle(): void
    {
        $list = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/grid.php');
        $toolbar = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_toolbar.php');

        foreach (['patient_name', 'patient_number', 'patient_mobile', 'prescription_date', 'medicine_count', 'status_label', 'review_state', 'review_date_label', 'review_date'] as $field) {
            self::assertStringContainsString("prescription['$field']", $list);
            self::assertStringContainsString("prescription['$field']", $grid);
        }
        self::assertStringContainsString("['view' => 'list'", $toolbar);
        self::assertStringContainsString("['view' => 'grid'", $toolbar);
        self::assertStringContainsString("pages/prescriptions/' . \$view", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php'));
    }

    public function testReviewModalHasPatientDoctorMedicineAndFieldErrors(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_modal.php');
        $toolbar = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_toolbar.php');

        foreach (['review-patient-name', 'review-doctor-name', 'review-prescription-date', 'review-prescription-number', 'review-medicine-rows', 'reasonErr', 'approve_confirmationErr', 'decisionErr'] as $contract) {
            self::assertStringContainsString($contract, $modal);
        }
        self::assertStringContainsString('modal-dialog-scrollable', $modal);
        self::assertStringContainsString('<?= csrf_field() ?>', $modal);
        self::assertStringContainsString('review-header-state', $modal);
        self::assertStringContainsString('review-date', $modal);
        self::assertStringContainsString('Prescription status', $modal);
        self::assertStringContainsString('Review status', $modal);
        self::assertStringContainsString('Medicine names and full instructions are available inside View.', $toolbar);
    }

    public function testDecisionPermissionsReasonsConfirmationAndAuditAreServerSide(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php');

        foreach (['pharmacy.prescription.review.approve', 'pharmacy.prescription.review.reject', 'pharmacy.prescription.review.clarify', 'pharmacy.prescription.review.cancel', 'approve_confirmation', 'A reason is required', 'previous_status', 'new_status', 'reason', 'pharmacy.prescription_review', 'pharmacy.permission_denied'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        self::assertStringContainsString("setStatusCode(422)", $controller);
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_actions.php');
        self::assertStringContainsString("in_array('pharmacy.prescription.review.cancel', " . '$permissions' . ", true)", $actions);
        $rejectCancel = "in_array('pharmacy.prescription.review.reject', " . '$permissions' . ", true)): ?><button class=\"dropdown-item prescription-review-action\" type=\"button\" data-decision=\"cancel\"";
        self::assertStringNotContainsString($rejectCancel, $actions);
        $notifications = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyNotificationService.php');
        self::assertStringNotContainsString('->where(\'event_key\', $eventKey)->first()', $notifications);
        self::assertStringContainsString('firstScoped', $notifications);
    }

    public function testTransitionContractAllowsReviewableActionsAndBlocksFinalReversal(): void
    {
        $contract = new TransitionContract();
        self::assertTrue($contract->allows('prescription', 'draft', 'approved'));
        self::assertTrue($contract->allows('prescription', 'submitted', 'rejected'));
        self::assertTrue($contract->allows('prescription', 'clarification_requested', 'clarification_requested'));
        self::assertTrue($contract->allows('prescription', 'submitted', 'cancelled'));
        self::assertFalse($contract->allows('prescription', 'approved', 'rejected'));
        self::assertFalse($contract->allows('prescription', 'cancelled', 'approved'));
    }

    public function testDateFormattingAndSafeEmptyScopePayload(): void
    {
        $serviceSource = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PrescriptionReviewService.php');
        self::assertStringContainsString("createFromFormat('!d-m-Y'", $serviceSource);
        self::assertStringContainsString("format('d-m-Y')", $serviceSource);
        session()->set(['roles' => ['Pharmacy Assistant'], 'user' => [], 'permissions' => []]);
        $result = (new PrescriptionReviewService(new PharmacyScope()))->list(['view' => 'grid']);
        self::assertSame([], $result['prescriptions']);
        self::assertSame(0, $result['pagination']['total']);
    }
}
