<?php

namespace Admin\Controllers\Medicine;

use Admin\Models\MedicineModel;
use App\Controllers\BaseAdminController;

class Medicine extends BaseAdminController
{
    protected MedicineModel $medicine;

    public function __construct()
    {
        $this->medicine = new MedicineModel();
    }

    public function index()
    {
        return view(ADMIN_VIEW . 'pages/medicine/index', [
            'medicines' => $this->medicine
                ->orderBy('medicine_name', 'ASC')
                ->findAll(),
            'canCreate' => $this->hasPermission('medicine.create'),
            'canEdit'   => $this->hasPermission('medicine.edit'),
            'canDelete' => $this->hasPermission('medicine.delete'),
        ]);
    }

    public function edit(int $id)
    {
        if (! $this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }

        $medicine = $this->medicine->find($id);

        if ($medicine === null) {
            return $this->failure('Medicine not found.');
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => '',
            'data'   => $medicine,
        ]);
    }

    public function search()
    {
        if (! $this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }

        $term = trim((string) $this->request->getGet('q'));

        if ($term === '') {
            return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => []]);
        }

        $medicines = $this->medicine
            ->select('id, medicine_name, generic_name, medicine_code, strength')
            ->where('status', 'active')
            ->groupStart()
                ->like('medicine_name', $term)
                ->orLike('generic_name', $term)
                ->orLike('medicine_code', $term)
                ->orLike('strength', $term)
            ->groupEnd()
            ->orderBy('medicine_name', 'ASC')
            ->findAll(20);

        return $this->response->setJSON([
            'status' => true,
            'msg'    => '',
            'data'   => $medicines,
        ]);
    }

    public function save()
    {
        if (! $this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }

        $errors = $this->validateMedicine();

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        try {
            if (! $this->medicine->insert($this->medicineData())) {
                throw new \RuntimeException('Medicine insert failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Medicine creation failed: ' . $e->getMessage());

            return $this->failure('Unable to create medicine.');
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Medicine created successfully.',
        ]);
    }

    public function update(int $id)
    {
        if (! $this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }

        if ($this->medicine->find($id) === null) {
            return $this->failure('Medicine not found.');
        }

        $errors = $this->validateMedicine($id);

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        try {
            if (! $this->medicine->update($id, $this->medicineData())) {
                throw new \RuntimeException('Medicine update failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Medicine update failed: ' . $e->getMessage());

            return $this->failure('Unable to update medicine.');
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Medicine updated successfully.',
        ]);
    }

    public function delete(int $id)
    {
        if (! $this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }

        if ($this->medicine->find($id) === null) {
            return $this->failure('Medicine not found.');
        }

        try {
            if (! $this->medicine->delete($id)) {
                throw new \RuntimeException('Medicine deletion failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Medicine deletion failed: ' . $e->getMessage());

            return $this->failure('Unable to delete medicine.');
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Medicine deleted successfully.',
        ]);
    }

    private function validateMedicine(?int $id = null): array
    {
        $codeRule = 'permit_empty|max_length[50]|is_unique[medicines.medicine_code';

        if ($id !== null) {
            $codeRule .= ',id,' . $id;
        }

        $codeRule .= ']';

        $rules = [
            'medicine_name' => [
                'label' => 'Medicine Name',
                'rules' => 'required|max_length[255]',
            ],
            'generic_name' => [
                'label' => 'Generic Name',
                'rules' => 'permit_empty|max_length[255]',
            ],
            'medicine_code' => [
                'label'  => 'Medicine Code',
                'rules'  => $codeRule,
                'errors' => ['is_unique' => 'The {field} is already in use.'],
            ],
            'dosage_form' => [
                'label' => 'Dosage Form',
                'rules' => 'permit_empty|max_length[100]',
            ],
            'strength' => [
                'label' => 'Strength',
                'rules' => 'permit_empty|max_length[100]',
            ],
            'manufacturer' => [
                'label' => 'Manufacturer',
                'rules' => 'permit_empty|max_length[255]',
            ],
            'description' => [
                'label' => 'Description',
                'rules' => 'permit_empty',
            ],
            'status' => [
                'label' => 'Status',
                'rules' => 'required|max_length[30]',
            ],
        ];

        return $this->validate($rules) ? [] : $this->validator->getErrors();
    }

    private function medicineData(): array
    {
        $data = [];

        foreach (['medicine_name', 'generic_name', 'medicine_code', 'dosage_form', 'strength', 'manufacturer', 'description', 'status'] as $field) {
            $value = trim((string) $this->request->getPost($field));
            $data[$field] = $value === '' && $field !== 'status' ? null : $value;
        }

        return $data;
    }

    private function isAjaxMethod(string $method): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === $method;
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setJSON([
            'status' => false,
            'msg'    => reset($errors),
            'errors' => $errors,
        ]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON([
            'status' => false,
            'msg'    => $message,
        ]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON([
            'status' => false,
            'msg'    => 'Invalid request.',
        ]);
    }
}
