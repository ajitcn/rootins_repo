<?php

namespace Admin\Controllers\Prescription;

use Admin\Models\ConsultationModel;
use Admin\Models\Clinic\ClinicModel;
use Admin\Models\Doctor\DoctorModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use App\Controllers\BaseAdminController;
use App\Services\Common\PrescriptionPdfService;

class Prescription extends BaseAdminController
{
    protected PrescriptionModel $prescription;

    protected PrescriptionItemModel $prescriptionItem;

    protected ConsultationModel $consultation;

    protected PrescriptionPdfService $prescriptionPdf;

    public function __construct()
    {
        $this->prescription = new PrescriptionModel();
        $this->prescriptionItem = new PrescriptionItemModel();
        $this->consultation = new ConsultationModel();
        $this->prescriptionPdf = new PrescriptionPdfService();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $clinicId = (int) $this->request->getGet('clinic_id');
        $doctorId = (int) $this->request->getGet('doctor_id');
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = rootins_parse_date($dateFromInput);
        $dateTo = rootins_parse_date($dateToInput);
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->prescription->select('
                prescriptions.*,
                consultations.consultation_number,
                consultations.consultation_date,
                consultations.consultation_time,
                patients.registration_number,
                patients.mobile_number AS patient_mobile,
                patients.source_type AS patient_source,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                clinics.clinic_name,
                appointments.appointment_number,
                appointments.service_name,
                appointments.queue_position,
                appointments.status AS appointment_status
            ')->join('consultations', 'consultations.id = prescriptions.consultation_id')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->join('clinics', 'clinics.id = prescriptions.clinic_id')->join('appointments', 'appointments.id = consultations.appointment_id', 'left');

        if ($search !== '') {
            $query->groupStart()->like('prescriptions.prescription_number', $search)->orLike('consultations.consultation_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.mobile_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }

        if (in_array($status, ['draft', 'pending', 'submitted', 'completed', 'approved', 'cancelled', 'rejected'], true)) {
            $query->where('prescriptions.status', $status);
        }

        if ($clinicId > 0) {
            $query->where('prescriptions.clinic_id', $clinicId);
        }

        if ($doctorId > 0) {
            $query->where('prescriptions.doctor_id', $doctorId);
        }

        if ($dateFrom !== null) {
            $query->where('prescriptions.prescription_date >=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('prescriptions.prescription_date <=', $dateTo);
        }
        $prescriptions = $query->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->paginate($pageSize, 'prescriptions');

        return view(ADMIN_VIEW . 'pages/prescription/index', ['prescriptions' => $prescriptions, 'pager' => $this->prescription->pager, 'filters' => ['search' => $search, 'status' => $status, 'clinic_id' => $clinicId ?: '', 'doctor_id' => $doctorId ?: '', 'date_from' => $dateFrom ? rootins_format_date($dateFrom) : $dateFromInput, 'date_to' => $dateTo ? rootins_format_date($dateTo) : $dateToInput, 'page_size' => $pageSize], 'clinics' => (new ClinicModel())->dropdown(), 'doctors' => (new DoctorModel())->select('id, first_name, last_name, doctor_code')->where('status', 1)->orderBy('first_name')->orderBy('last_name')->findAll(), 'consultations' => $this->consultation->select('
                    consultations.id,
                    consultations.consultation_number,
                    consultations.consultation_date,
                    patients.registration_number,
                    patients.first_name AS patient_first_name,
                    patients.last_name AS patient_last_name,
                    doctors.first_name AS doctor_first_name,
                    doctors.last_name AS doctor_last_name,
                    clinics.clinic_name
                ')->join('patients', 'patients.id = consultations.patient_id')->join('doctors', 'doctors.id = consultations.doctor_id')->join('clinics', 'clinics.id = consultations.clinic_id')->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.id', 'DESC')->findAll(), 'canCreate' => $this->hasPermission('prescription.create'), 'canEdit' => $this->hasPermission('prescription.edit'), 'canDelete' => $this->hasPermission('prescription.delete'), 'canMedicineLookup' => $this->hasPermission('medicine.view')]);
    }

    public function view(int $id)
    {
        $prescription = $this->prescriptionDetails($id);

        if ($prescription === null) {
            session()->setFlashdata(['status' => false, 'msg' => 'Prescription not found.']);

            return redirect()->to('admin/prescriptions');
        }

        return view(ADMIN_VIEW . 'pages/prescription/view', ['prescription' => $prescription, 'items' => $this->prescriptionItem->where('prescription_id', $id)->orderBy('id', 'ASC')->findAll(), 'canEdit' => $this->hasPermission('prescription.edit'), 'canDelete' => $this->hasPermission('prescription.delete'), 'canDocuments' => $this->hasPermission('patient.view')]);
    }

    public function pdf(int $id)
    {
        $prescription = $this->prescriptionDetails($id);

        if ($prescription === null) {
            return $this->response->setStatusCode(404)->setBody('Prescription not found.');
        }

        if ($prescription['status'] !== 'completed') {
            return $this->response->setStatusCode(403)->setBody('Only completed prescriptions can be printed.');
        }
        $items = $this->prescriptionItem->where('prescription_id', $id)->orderBy('id', 'ASC')->findAll();
        $pdf = $this->prescriptionPdf->generate($prescription, $items);
        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $prescription['prescription_number']);

        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'inline; filename="' . $fileName . '.pdf"')->setBody($pdf);
    }

    public function edit(int $id)
    {
        if (!$this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }
        $prescription = $this->prescription->find($id);

        if ($prescription === null) {
            return $this->failure('Prescription not found.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => ['prescription' => $prescription, 'items' => $this->prescriptionItem->where('prescription_id', $id)->orderBy('id', 'ASC')->findAll()]]);
    }

    public function save()
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $validation = $this->validatePrescription();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $consultation = $this->selectedConsultation();

        if ($consultation === null) {
            return $this->validationFailure(['consultation_id' => 'The selected consultation is invalid.']);
        }
        $items = $this->validatedItems();

        if (isset($items['errors'])) {
            return $this->validationFailure($items['errors']);
        }
        $data = $this->prescriptionData($consultation);
        $data['prescription_number'] = 'TMP-RX-' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $db = db_connect();
        $db->transBegin();

        try {
            $prescriptionId = $this->prescription->insert($data);

            if (!$prescriptionId) {
                throw new \RuntimeException('Prescription insert failed.');
            }
            $prescriptionNumber = sprintf('RXP-%06d', (int) $prescriptionId);

            if (!$this->prescription->update($prescriptionId, ['prescription_number' => $prescriptionNumber, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Prescription number assignment failed.');
            }
            $this->insertItems((int) $prescriptionId, $items['data'], $now);

            if (!$db->transCommit()) {
                throw new \RuntimeException('Prescription transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Prescription creation failed: ' . $e->getMessage());

            return $this->failure('Unable to create prescription.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Prescription created successfully.', 'data' => ['id' => (int) $prescriptionId, 'prescription_number' => $prescriptionNumber]]);
    }

    public function update(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $existing = $this->prescription->find($id);

        if ($existing === null) {
            return $this->failure('Prescription not found.');
        }

        if ($existing['status'] === 'completed') {
            return $this->failure('Completed prescriptions cannot be modified.');
        }
        $validation = $this->validatePrescription();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $consultation = $this->selectedConsultation();

        if ($consultation === null) {
            return $this->validationFailure(['consultation_id' => 'The selected consultation is invalid.']);
        }
        $items = $this->validatedItems();

        if (isset($items['errors'])) {
            return $this->validationFailure($items['errors']);
        }
        $data = $this->prescriptionData($consultation);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db = db_connect();
        $db->transBegin();

        try {
            if (!$this->prescription->update($id, $data)) {
                throw new \RuntimeException('Prescription update failed.');
            }
            $this->prescriptionItem->where('prescription_id', $id)->delete();
            $this->insertItems($id, $items['data'], date('Y-m-d H:i:s'));

            if (!$db->transCommit()) {
                throw new \RuntimeException('Prescription transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Prescription update failed: ' . $e->getMessage());

            return $this->failure('Unable to update prescription.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Prescription updated successfully.']);
    }

    public function delete(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $prescription = $this->prescription->find($id);

        if ($prescription === null) {
            return $this->failure('Prescription not found.');
        }

        if ($prescription['status'] === 'completed') {
            return $this->failure('Completed prescriptions cannot be deleted.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $this->prescriptionItem->where('prescription_id', $id)->delete();

            if (!$this->prescription->delete($id) || !$db->transCommit()) {
                throw new \RuntimeException('Prescription deletion failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Prescription deletion failed: ' . $e->getMessage());

            return $this->failure('Unable to delete prescription.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Prescription deleted successfully.']);
    }

    private function validatePrescription(): array
    {
        $rules = ['consultation_id' => ['label' => 'Consultation', 'rules' => 'required|is_natural_no_zero'], 'prescription_date' => ['label' => 'Prescription Date', 'rules' => 'required'], 'notes' => ['label' => 'Notes', 'rules' => 'permit_empty'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[draft,completed]']];

        if (! $this->validate($rules)) {
            return $this->validator->getErrors();
        }

        return rootins_parse_date($this->request->getPost('prescription_date')) === null ? ['prescription_date' => 'Enter a valid prescription date as dd-mm-yyyy.'] : [];
    }

    private function selectedConsultation(): ?array
    {
        $id = (int) $this->request->getPost('consultation_id');

        return $id > 0 ? $this->consultation->find($id) : null;
    }

    private function validatedItems(): array
    {
        $postedItems = $this->request->getPost('items');

        if (!is_array($postedItems) || $postedItems === []) {
            return ['errors' => ['items' => 'At least one prescription item is required.']];
        }
        $items = [];
        $errors = [];

        foreach ($postedItems as $index => $item) {
            if (!is_array($item)) {
                $errors['items'] = 'Prescription items are invalid.';

                continue;
            }
            $medicineName = trim((string) ($item['medicine_name'] ?? ''));
            $quantity = trim((string) ($item['quantity'] ?? ''));

            if ($medicineName === '') {
                $errors["items.{$index}.medicine_name"] = 'Medicine name is required.';
            } elseif (mb_strlen($medicineName) > 255) {
                $errors["items.{$index}.medicine_name"] = 'Medicine name cannot exceed 255 characters.';
            }

            if ($quantity !== '' && (!is_numeric($quantity) || (float) $quantity < 0 || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $quantity))) {
                $errors["items.{$index}.quantity"] = 'Quantity must be a non-negative number with up to 8 whole digits and 2 decimals.';
            }
            $items[] = ['medicine_name' => $medicineName, 'dosage' => $this->itemString($item, 'dosage', 100, $errors, $index), 'frequency' => $this->itemString($item, 'frequency', 100, $errors, $index), 'duration' => $this->itemString($item, 'duration', 50, $errors, $index), 'duration_unit' => $this->itemString($item, 'duration_unit', 30, $errors, $index), 'instructions' => $this->itemText($item, 'instructions', $errors, $index), 'quantity' => $quantity === '' ? null : $quantity, 'notes' => $this->itemText($item, 'notes', $errors, $index)];
        }

        return $errors === [] ? ['data' => $items] : ['errors' => $errors];
    }

    private function itemString(array $item, string $field, int $maxLength, array &$errors, $index): ?string
    {
        $value = trim((string) ($item[$field] ?? ''));

        if ($value !== '' && mb_strlen($value) > $maxLength) {
            $errors["items.{$index}.{$field}"] = ucfirst(str_replace('_', ' ', $field)) . " cannot exceed {$maxLength} characters.";
        }

        return $value === '' ? null : $value;
    }

    private function itemText(array $item, string $field, array &$errors, $index): ?string
    {
        $value = trim((string) ($item[$field] ?? ''));

        if (mb_strlen($value) > 65535) {
            $errors["items.{$index}.{$field}"] = ucfirst($field) . ' is too long.';
        }

        return $value === '' ? null : $value;
    }

    private function prescriptionData(array $consultation): array
    {
        return ['consultation_id' => (int) $consultation['id'], 'patient_id' => (int) $consultation['patient_id'], 'doctor_id' => (int) $consultation['doctor_id'], 'clinic_id' => (int) $consultation['clinic_id'], 'prescription_date' => rootins_parse_date($this->request->getPost('prescription_date')) ?? '', 'notes' => $this->nullableString('notes'), 'status' => (string) $this->request->getPost('status')];
    }

    private function insertItems(int $prescriptionId, array $items, string $timestamp): void
    {
        foreach ($items as &$item) {
            $item['prescription_id'] = $prescriptionId;
            $item['created_at'] = $timestamp;
            $item['updated_at'] = $timestamp;
        }
        unset($item);

        if ($this->prescriptionItem->insertBatch($items) === false) {
            throw new \RuntimeException('Prescription item insert failed.');
        }
    }

    private function prescriptionDetails(int $id): ?array
    {
        return $this->prescription->select('
                prescriptions.*,
                consultations.consultation_number,
                consultations.consultation_date,
                consultations.consultation_time,
                patients.registration_number,
                patients.email AS patient_email,
                patients.source_type AS patient_source,
                patients.mobile_number AS patient_mobile,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                doctors.qualification,
                doctors.registration_number AS doctor_registration_number,
                doctors.mobile AS doctor_mobile,
                clinics.clinic_name,
                clinics.clinic_code,
                clinics.address AS clinic_address,
                clinics.mobile AS clinic_mobile,
                clinics.email AS clinic_email,
                clinics.pincode AS clinic_pincode,
                appointments.appointment_number,
                appointments.appointment_date,
                appointments.appointment_time,
                appointments.service_name,
                appointments.queue_position,
                appointments.status AS appointment_status,
                appointments.appointment_type
            ')->join('consultations', 'consultations.id = prescriptions.consultation_id')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->join('clinics', 'clinics.id = prescriptions.clinic_id')->join('appointments', 'appointments.id = consultations.appointment_id', 'left')->find($id);
    }

    private function isAjaxMethod(string $method): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === $method;
    }

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON(['status' => false, 'msg' => $message]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
    }
}
