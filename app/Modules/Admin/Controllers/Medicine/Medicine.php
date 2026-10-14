<?php

namespace Admin\Controllers\Medicine;

use Admin\Models\MedicineModel;
use Admin\Models\PrescriptionOptionModel;
use Admin\Services\MedicineLookupService;
use App\Controllers\BaseAdminController;

class Medicine extends BaseAdminController
{
    protected MedicineModel $medicine;

    protected MedicineLookupService $lookups;

    protected PrescriptionOptionModel $prescriptionOptions;

    public function __construct()
    {
        $this->medicine = new MedicineModel();
        $this->lookups = new MedicineLookupService();
        $this->prescriptionOptions = new PrescriptionOptionModel();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->medicine;

        if ($search !== '') {
            $query->groupStart()->like('medicine_name', $search)->orLike('generic_name', $search)->orLike('medicine_code', $search)->orLike('manufacturer', $search)->groupEnd();
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        return view(ADMIN_VIEW . 'pages/medicine/index', ['medicines' => $query->orderBy('medicine_name', 'ASC')->paginate($pageSize, 'medicines'), 'pager' => $this->medicine->pager, 'filters' => ['search' => $search, 'status' => $status, 'page_size' => $pageSize], 'lookupOptions' => $this->lookups->options($this->currentClinic()), 'prescriptionOptionSets' => $this->prescriptionOptionSets(), 'canCreate' => $this->hasPermission('medicine.create'), 'canEdit' => $this->hasPermission('medicine.edit'), 'canDelete' => $this->hasPermission('medicine.delete'), 'canManageLookups' => $this->hasPermission('settings.manage')]);
    }

    public function edit(int $id)
    {
        if (!$this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }
        $medicine = $this->medicine->find($id);

        if ($medicine === null) {
            return $this->failure('Medicine not found.');
        }

        $labels = ['form' => $medicine['dosage_form'] ?? '', 'strength' => $medicine['strength'] ?? '', 'unit' => $medicine['unit'] ?? '', 'manufacturer' => $medicine['manufacturer'] ?? ''];
        foreach (['prescription_duration_id' => ['table' => 'prescription_durations', 'key' => 'prescription_duration'], 'prescription_frequency_id' => ['table' => 'prescription_frequencies', 'key' => 'prescription_frequency'], 'prescription_dose_id' => ['table' => 'prescription_doses', 'key' => 'prescription_dose'], 'prescription_intake_id' => ['table' => 'prescription_intakes', 'key' => 'prescription_intake']] as $field => $definition) {
            $lookupRow = $medicine[$field] ? db_connect()->table($definition['table'])->select('name')->where('id', (int) $medicine[$field])->get()->getRowArray() : null;
            $labels[$definition['key']] = (string) ($lookupRow['name'] ?? '');
        }
        $medicine = array_merge($medicine, $labels);

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $medicine]);
    }

    public function search()
    {
        if (!$this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }
        $term = trim((string) $this->request->getGet('q'));

        if ($term === '') {
            return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => []]);
        }
        $medicines = $this->medicine->select('id, medicine_name, generic_name, medicine_code, strength')->where('status', 'active')->groupStart()->like('medicine_name', $term)->orLike('generic_name', $term)->orLike('medicine_code', $term)->orLike('strength', $term)->groupEnd()->orderBy('medicine_name', 'ASC')->findAll(20);

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $medicines]);
    }

    public function save()
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $errors = $this->validateMedicine();
        $lookupResult = $this->lookups->resolve($this->request->getPost(), true, $this->currentClinic());
        $errors = array_merge($errors, $lookupResult['errors']);
        $prescriptionResult = $this->resolvePrescriptionDefaults();
        $errors = array_merge($errors, $prescriptionResult['errors']);
        $medicineData = $this->medicineData($lookupResult['records'], $prescriptionResult['records'], true);

        if ($errors === [] && $this->medicineNameExists((string) ($medicineData['medicine_name'] ?? ''))) {
            $errors['medicine_name'] = 'A medicine with this name already exists.';
        }

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }
        $db = db_connect();
        $transactionStarted = false;

        try {
            $db->transBegin();
            $transactionStarted = true;
            $medicineId = $this->medicine->insert($medicineData, true);

            if (! $medicineId || $db->transStatus() === false) {
                throw new \RuntimeException('Medicine insert failed.');
            }

            if (! $db->transCommit()) {
                throw new \RuntimeException('Medicine transaction commit failed.');
            }
            $transactionStarted = false;
            $medicine = $this->medicine->find((int) $medicineId);

            if ($medicine === null) {
                throw new \RuntimeException('Created medicine could not be reloaded.');
            }
            $this->audit->record('admin.medicine_created', ['medicine_id' => (int) $medicineId, 'unit_price' => (float) ($medicine['unit_price'] ?? 0)]);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $db->transRollback();
            }
            log_message('error', 'Medicine creation failed: ' . $e->getMessage());

            return $this->failure('Unable to create medicine.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Medicine created successfully.', 'data' => $medicine]);
    }

    public function update(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $existingMedicine = $this->medicine->find($id);

        if ($existingMedicine === null) {
            return $this->failure('Medicine not found.');
        }
        $errors = $this->validateMedicine($id);
        $lookupResult = $this->lookups->resolve($this->request->getPost(), true, $this->currentClinic(), ['form_id' => (int) ($existingMedicine['form_id'] ?? 0), 'strength_id' => (int) ($existingMedicine['strength_id'] ?? 0), 'unit_id' => (int) ($existingMedicine['unit_id'] ?? 0), 'manufacturer_id' => (int) ($existingMedicine['manufacturer_id'] ?? 0), 'status_id' => (int) ($existingMedicine['status_id'] ?? 0), 'prescription_duration_id' => (int) ($existingMedicine['prescription_duration_id'] ?? 0), 'prescription_frequency_id' => (int) ($existingMedicine['prescription_frequency_id'] ?? 0), 'prescription_dose_id' => (int) ($existingMedicine['prescription_dose_id'] ?? 0), 'prescription_intake_id' => (int) ($existingMedicine['prescription_intake_id'] ?? 0)]);
        $errors = array_merge($errors, $lookupResult['errors']);
        $prescriptionResult = $this->resolvePrescriptionDefaults($existingMedicine);
        $errors = array_merge($errors, $prescriptionResult['errors']);

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }
        $medicineData = $this->medicineData($lookupResult['records'], $prescriptionResult['records']);

        if ($this->medicineNameExists((string) ($medicineData['medicine_name'] ?? ''), $id)) {
            return $this->validationFailure(['medicine_name' => 'A medicine with this name already exists.']);
        }

        try {
            if (!$this->medicine->update($id, $medicineData)) {
                throw new \RuntimeException('Medicine update failed.');
            }

            if (round((float) ($existingMedicine['unit_price'] ?? 0), 2) !== round((float) ($medicineData['unit_price'] ?? 0), 2)) {
                $this->audit->record('admin.medicine_price_updated', ['medicine_id' => $id, 'previous_unit_price' => (float) ($existingMedicine['unit_price'] ?? 0), 'new_unit_price' => (float) ($medicineData['unit_price'] ?? 0)]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Medicine update failed: ' . $e->getMessage());

            return $this->failure('Unable to update medicine.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Medicine updated successfully.']);
    }

    public function delete(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }

        if ($this->medicine->find($id) === null) {
            return $this->failure('Medicine not found.');
        }

        try {
            if (!$this->medicine->delete($id)) {
                throw new \RuntimeException('Medicine deletion failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Medicine deletion failed: ' . $e->getMessage());

            return $this->failure('Unable to delete medicine.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Medicine deleted successfully.']);
    }

    private function validateMedicine(?int $id = null): array
    {
        $codeRule = 'permit_empty|max_length[50]|is_unique[medicines.medicine_code';

        if ($id !== null) {
            $codeRule .= ',id,' . $id;
        }
        $codeRule .= ']';
        $rules = ['medicine_name' => ['label' => 'Medicine Name', 'rules' => 'required|max_length[255]'], 'generic_name' => ['label' => 'Generic Name', 'rules' => 'permit_empty|max_length[255]'], 'medicine_code' => ['label' => 'Medicine Code', 'rules' => $codeRule, 'errors' => ['is_unique' => 'The {field} is already in use.']], 'strength' => ['label' => 'Strength', 'rules' => 'permit_empty|max_length[100]'], 'description' => ['label' => 'Description', 'rules' => 'permit_empty']];
        $errors = $this->validate($rules) ? [] : $this->validator->getErrors();
        $price = trim((string) $this->request->getPost('unit_price'));

        if ($price === '') {
            $price = '0';
        }

        if (!preg_match('/^(?:0|[0-9]+)(?:\.[0-9]{1,2})?$/', $price)) {
            $errors['unit_price'] = 'Enter a valid non-negative amount with up to two decimal places.';
        } elseif ((float) $price < 0) {
            $errors['unit_price'] = 'Unit price cannot be negative.';
        } elseif ((float) $price > 9999999999.99) {
            $errors['unit_price'] = 'Unit price is too large.';
        }

        return $errors;
    }

    private function medicineData(array $lookups, array $prescriptionDefaults = [], bool $includePrescriptionDefaults = false): array
    {
        $data = [];

        foreach (['medicine_name', 'generic_name', 'medicine_code', 'strength', 'description'] as $field) {
            $value = trim((string) $this->request->getPost($field));
            $data[$field] = $value === '' ? null : $value;
        }
        $price = trim((string) $this->request->getPost('unit_price'));
        $data['unit_price'] = $price === '' ? 0 : number_format((float) $price, 2, '.', '');
        $data['form_id'] = isset($lookups['form']) ? (int) $lookups['form']['id'] : null;
        $data['dosage_form'] = isset($lookups['form']) ? $lookups['form']['name'] : null;
        $data['strength_id'] = isset($lookups['strength']) ? (int) $lookups['strength']['id'] : null;
        $data['strength'] = isset($lookups['strength']) ? $lookups['strength']['name'] : null;
        $data['unit_id'] = isset($lookups['unit']) ? (int) $lookups['unit']['id'] : null;
        $data['unit'] = isset($lookups['unit']) ? $lookups['unit']['name'] : null;
        $data['manufacturer_id'] = isset($lookups['manufacturer']) ? (int) $lookups['manufacturer']['id'] : null;
        $data['manufacturer'] = isset($lookups['manufacturer']) ? $lookups['manufacturer']['name'] : null;
        $data['status_id'] = (int) ($lookups['status']['id'] ?? 0);
        $data['status'] = (string) ($lookups['status']['code'] ?? '');
        $defaultFields = ['prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note'];
        $defaultsSubmitted = $includePrescriptionDefaults;

        foreach ($defaultFields as $field) {
            if ($this->request->getPost($field) !== null) {
                $defaultsSubmitted = true;

                break;
            }
        }

        if ($defaultsSubmitted) {
            $data['prescription_duration_id'] = $prescriptionDefaults['duration']['id'] ?? null;
            $data['prescription_frequency_id'] = $prescriptionDefaults['frequency']['id'] ?? null;
            $data['prescription_dose_id'] = $prescriptionDefaults['dose']['id'] ?? null;
            $data['prescription_intake_id'] = $prescriptionDefaults['intake']['id'] ?? null;
            $note = trim((string) $this->request->getPost('prescription_note'));
            $data['prescription_note'] = $note === '' ? null : $note;
        }

        return $data;
    }

    /**
     * @return array{records: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    private function resolvePrescriptionDefaults(?array $existingMedicine = null): array
    {
        $records = [];
        $errors = [];
        $fields = [
            'prescription_duration_id' => ['type' => PrescriptionOptionModel::TYPES['duration'], 'key' => 'duration'],
            'prescription_frequency_id' => ['type' => PrescriptionOptionModel::TYPES['frequency'], 'key' => 'frequency'],
            'prescription_dose_id' => ['type' => PrescriptionOptionModel::TYPES['dose'], 'key' => 'dose'],
            'prescription_intake_id' => ['type' => PrescriptionOptionModel::TYPES['intake'], 'key' => 'intake'],
        ];

        foreach ($fields as $field => $definition) {
            $value = trim((string) $this->request->getPost($field));

            if ($value === '') {
                continue;
            }

            if (! ctype_digit($value) || (int) $value < 1) {
                $errors[$field] = 'Select a valid active prescription option.';

                continue;
            }
            $record = $this->prescriptionOptions->activeById($definition['type'], (int) $value, $this->currentClinic());

            if ($record === null && $existingMedicine !== null && (int) $value === (int) ($existingMedicine[$field] ?? 0)) {
                $table = ['duration' => 'prescription_durations', 'frequency' => 'prescription_frequencies', 'dose' => 'prescription_doses', 'intake' => 'prescription_intakes'][$definition['key']];
                $record = db_connect()->table($table)->where('id', (int) $value)->get()->getRowArray() ?: null;
            }

            if ($record === null) {
                $errors[$field] = 'Select a valid active prescription option.';

                continue;
            }
            $records[$definition['key']] = $record;
        }
        $note = trim((string) $this->request->getPost('prescription_note'));

        if (mb_strlen($note) > 2000) {
            $errors['prescription_note'] = 'Prescription note cannot exceed 2000 characters.';
        }

        return ['records' => $records, 'errors' => $errors];
    }

    private function prescriptionOptionSets(): array
    {
        return [
            'duration' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['duration'], $this->currentClinic()),
            'frequency' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['frequency'], $this->currentClinic()),
            'dose' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['dose'], $this->currentClinic()),
            'intake' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['intake'], $this->currentClinic()),
        ];
    }

    private function medicineNameExists(string $name, ?int $excludeId = null): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }
        $db = db_connect();
        $normalizedName = mb_strtolower($name, 'UTF-8');
        $query = $db->table('medicines')->where(
            'LOWER(TRIM(medicine_name)) = ' . $db->escape($normalizedName),
            null,
            false,
        );

        if ($excludeId !== null) {
            $query->where('id !=', $excludeId);
        }

        return $query->countAllResults() > 0;
    }

    private function isAjaxMethod(string $method): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === $method;
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON(['status' => false, 'msg' => $message]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
    }

    private function currentClinic(): ?int
    {
        $id = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);

        return $id ?: null;
    }
}
