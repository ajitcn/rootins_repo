<?php

namespace Farmassist\Controllers;

use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use Admin\Models\MedicineModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
class PrescriptionReview extends BaseController
{
    private const REVIEWABLE_STATUSES = ['draft', 'submitted', 'clarification_requested'];
    private const DECISIONS = ['approve' => 'approved', 'reject' => 'rejected', 'clarification' => 'clarification_requested', 'cancel' => 'cancelled'];
    protected PrescriptionModel $prescription;
    protected PrescriptionItemModel $prescriptionItem;
    protected MedicineModel $medicine;
    public function __construct()
    {
        $this->prescription = new PrescriptionModel();
        $this->prescriptionItem = new PrescriptionItemModel();
        $this->medicine = new MedicineModel();
    }
    public function index(): string|RedirectResponse
    {
        $scope = $this->farmassistScope();
        if ($scope === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $query = $this->prescription->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.notes, prescriptions.status, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->where('prescriptions.clinic_id', $scope['clinic_id'])->where('patients.clinic_id', $scope['clinic_id'])->where('doctors.clinic_id', $scope['clinic_id'])->whereIn('prescriptions.status', self::REVIEWABLE_STATUSES);
        if ($status !== '' && in_array($status, self::REVIEWABLE_STATUSES, true)) {
            $query->where('prescriptions.status', $status);
        }
        if ($search !== '') {
            $query->groupStart()->like('prescriptions.prescription_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->groupEnd();
        }
        $total = (clone $query)->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $prescriptions = $query->orderBy('prescriptions.prescription_date', 'ASC')->orderBy('prescriptions.id', 'ASC')->findAll($pageSize, ($page - 1) * $pageSize);
        $itemsByPrescription = [];
        $prescriptionIds = array_map(static fn(array $prescription): int => (int) $prescription['id'], $prescriptions);
        if ($prescriptionIds !== []) {
            foreach ($this->prescriptionItem->whereIn('prescription_id', $prescriptionIds)->orderBy('id', 'ASC')->findAll() as $item) {
                $itemsByPrescription[(int) $item['prescription_id']][] = $item;
            }
        }
        return view(FARMASSIST_VIEW . 'pages/prescriptions/review', ['prescriptions' => $prescriptions, 'itemsByPrescription' => $itemsByPrescription, 'clinicId' => $scope['clinic_id'], 'filters' => ['search' => $search, 'status' => $status], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }
    public function review(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $scope = $this->farmassistScope();
        if ($scope === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Farmassist clinic scope is not assigned.']);
        }
        $prescriptionId = (int) $this->request->getPost('prescription_id');
        $decision = strtolower(trim((string) $this->request->getPost('decision')));
        if ($prescriptionId <= 0 || !isset(self::DECISIONS[$decision])) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'A valid prescription and review decision are required.', 'errors' => ['prescription_id' => $prescriptionId > 0 ? null : 'Prescription is required.', 'decision' => isset(self::DECISIONS[$decision]) ? null : 'Decision is invalid.']]);
        }
        $targetStatus = self::DECISIONS[$decision];
        $prescription = $this->prescription->select('prescriptions.*, patients.email AS patient_email')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->where('prescriptions.id', $prescriptionId)->where('prescriptions.clinic_id', $scope['clinic_id'])->where('patients.clinic_id', $scope['clinic_id'])->where('doctors.clinic_id', $scope['clinic_id'])->first();
        if ($prescription === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Prescription not found in the assigned clinic.']);
        }
        if ($prescription['status'] === $targetStatus) {
            $this->auditReview($prescriptionId, $scope['clinic_id'], $decision, true);
            return $this->response->setJSON(['status' => true, 'msg' => 'Prescription review was already recorded.']);
        }
        if (!in_array($prescription['status'], self::REVIEWABLE_STATUSES, true)) {
            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'This prescription has already moved out of the review queue.']);
        }
        if ($targetStatus === 'approved') {
            $medicineErrors = $this->validateMedicineMaster($prescriptionId);
            if ($medicineErrors !== []) {
                log_message('info', sprintf('Farmassist prescription review blocked: prescription_id=%d clinic_id=%d reason=medicine_master_validation', $prescriptionId, $scope['clinic_id']));
                return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Prescription cannot be approved until all medicines are active in the medicine master.', 'errors' => $medicineErrors]);
            }
        }
        $updated = $this->prescription->where('id', $prescriptionId)->where('clinic_id', $scope['clinic_id'])->whereIn('status', self::REVIEWABLE_STATUSES)->set(['status' => $targetStatus, 'updated_at' => date('Y-m-d H:i:s')])->update();
        if (!$updated) {
            $current = $this->prescription->where('id', $prescriptionId)->where('clinic_id', $scope['clinic_id'])->first();
            if ($current !== null && $current['status'] === $targetStatus) {
                $this->auditReview($prescriptionId, $scope['clinic_id'], $decision, true);
                return $this->response->setJSON(['status' => true, 'msg' => 'Prescription review was already recorded.']);
            }
            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'Prescription review could not be applied because its status changed.']);
        }
        $this->auditReview($prescriptionId, $scope['clinic_id'], $decision);
        $this->notifyPatient($prescription, $targetStatus);
        return $this->response->setJSON(['status' => true, 'msg' => 'Prescription marked as ' . str_replace('_', ' ', $targetStatus) . '.']);
    }
    private function farmassistScope(): ?array
    {
        if (!in_array('FarmAssist', session('roles') ?? [], true)) {
            return null;
        }
        $sessionUser = session('user') ?? [];
        $clinicId = (int) ($sessionUser['clinic_id'] ?? session('clinic_id') ?? 0);
        return $clinicId > 0 ? ['clinic_id' => $clinicId] : null;
    }
    private function auditReview(int $prescriptionId, int $clinicId, string $decision, bool $duplicate = false): void
    {
        service('audit')->record('farmassist.prescription_review', ['prescription_id' => $prescriptionId, 'clinic_id' => $clinicId, 'decision' => $decision, 'duplicate' => $duplicate]);
        log_message('info', sprintf('Farmassist prescription review: prescription_id=%d clinic_id=%d actor_user_id=%d decision=%s duplicate=%s', $prescriptionId, $clinicId, (int) ((session('user') ?? [])['id'] ?? 0), $decision, $duplicate ? 'true' : 'false'));
    }
    /** @param array<string, mixed> $prescription */
    private function notifyPatient(array $prescription, string $status): void
    {
        $email = trim((string) ($prescription['patient_email'] ?? ''));
        if ($email === '') {
            return;
        }
        service('notification')->dispatch('email', ['address' => $email], 'prescription_review_updated', ['prescription_id' => (int) $prescription['id'], 'status' => $status], 'prescription-review:' . (int) $prescription['id'] . ':' . $status);
    }
    /** @return array<string, string> */
    private function validateMedicineMaster(int $prescriptionId): array
    {
        $items = $this->prescriptionItem->where('prescription_id', $prescriptionId)->orderBy('id', 'ASC')->findAll();
        if ($items === []) {
            return ['items' => 'At least one prescribed medicine is required for approval.'];
        }
        $names = array_values(array_unique(array_filter(array_map(static fn(array $item): string => trim((string) ($item['medicine_name'] ?? '')), $items))));
        if ($names === []) {
            return ['items' => 'Each prescription item must identify a medicine.'];
        }
        $activeNames = [];
        foreach ($this->medicine->select('medicine_name')->where('status', 1)->whereIn('medicine_name', $names)->findAll() as $medicine) {
            $activeNames[strtolower(trim((string) $medicine['medicine_name']))] = true;
        }
        $errors = [];
        foreach ($items as $index => $item) {
            $name = trim((string) ($item['medicine_name'] ?? ''));
            if ($name === '' || !isset($activeNames[strtolower($name)])) {
                $errors['items.' . $index . '.medicine_name'] = 'Medicine is missing or inactive in the medicine master.';
            }
        }
        return $errors;
    }
}
