<?php

namespace Admin\Controllers\Patient;

use Admin\Models\PatientMedicalHistoryModel;
use Admin\Models\PatientModel;
use App\Controllers\BaseAdminController;

class PatientMedicalHistory extends BaseAdminController
{
    protected PatientMedicalHistoryModel $history;
    protected PatientModel $patient;

    public function __construct()
    {
        $this->history = new PatientMedicalHistoryModel();
        $this->patient = new PatientModel();
    }

    public function index(int $patientId)
    {
        $patient = $this->patient->find($patientId);

        if ($patient === null) {
            return redirect()->to(base_url('admin/patients'))
                ->with('error', 'Patient not found.');
        }

        return view(ADMIN_VIEW . 'pages/patient/medical_history', [
            'patient' => $patient,
            'histories' => $this->history
                ->where('patient_id', $patientId)
                ->orderBy('history_date', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->findAll(),
        ]);
    }

    public function save(int $patientId)
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }

        if ($this->patient->find($patientId) === null) {
            return $this->failure('Patient not found.');
        }

        $validation = $this->validateHistory();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }

        $this->history->insert($this->historyData($patientId));

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Medical history added successfully.',
        ]);
    }

    public function edit(int $patientId, int $id)
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }

        $history = $this->history
            ->where('patient_id', $patientId)
            ->where('id', $id)
            ->first();

        if ($history === null) {
            return $this->failure('Medical history record not found.');
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => '',
            'data'   => $history,
        ]);
    }

    public function update(int $patientId, int $id)
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }

        $history = $this->history
            ->where('patient_id', $patientId)
            ->where('id', $id)
            ->first();

        if ($history === null) {
            return $this->failure('Medical history record not found.');
        }

        $validation = $this->validateHistory();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }

        $this->history->update($id, $this->historyData($patientId));

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Medical history updated successfully.',
        ]);
    }

    private function validateHistory(): array
    {
        $rules = [
            'title' => [
                'label' => 'Title',
                'rules' => 'required|max_length[150]',
            ],
            'details' => [
                'label' => 'Details',
                'rules' => 'required',
            ],
            'history_date' => [
                'label' => 'History Date',
                'rules' => 'permit_empty|valid_date[Y-m-d]',
            ],
        ];

        if ($this->validate($rules)) {
            return [];
        }

        return $this->validator->getErrors();
    }

    private function historyData(int $patientId): array
    {
        $historyDate = trim((string) $this->request->getPost('history_date'));

        return [
            'patient_id'   => $patientId,
            'title'        => trim((string) $this->request->getPost('title')),
            'details'      => trim((string) $this->request->getPost('details')),
            'history_date' => $historyDate === '' ? null : $historyDate,
        ];
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
