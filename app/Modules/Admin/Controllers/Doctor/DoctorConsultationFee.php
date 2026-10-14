<?php

namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;
use Admin\Models\Doctor\DoctorConsultationFeeModel;
use Admin\Models\Doctor\DoctorModel;

class DoctorConsultationFee extends BaseAdminController
{
    protected DoctorConsultationFeeModel $doctorConsultationFee;

    protected DoctorModel $doctor;

    public function __construct()
    {
        $this->doctorConsultationFee = new DoctorConsultationFeeModel();
        $this->doctor = new DoctorModel();
    }

    /**
     * --------------------------------------------------------
     * List
     * --------------------------------------------------------.
     */
    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->doctorConsultationFee->select('doctor_consultation_fees.*, doctors.first_name, doctors.last_name, doctors.doctor_code')->join('doctors', 'doctors.id = doctor_consultation_fees.doctor_id');

        if ($this->clinicScope() > 0) {
            $query->where('doctors.clinic_id', $this->clinicScope());
        }

        if ($search !== '') {
            $query->groupStart()->like('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->groupEnd();
        }

        if ($status === '0' || $status === '1') {
            $query->where('doctor_consultation_fees.status', (int) $status);
        }
        $data['fees'] = $query->orderBy('doctors.first_name', 'ASC')->orderBy('effective_from', 'DESC')->paginate($pageSize, 'doctor_fees');
        $data['pager'] = $this->doctorConsultationFee->pager;
        $data['filters'] = ['search' => $search, 'status' => $status, 'page_size' => $pageSize];
        $doctorQuery = $this->doctor->select('id, first_name, last_name');

        if ($this->clinicScope() > 0) {
            $doctorQuery->where('clinic_id', $this->clinicScope());
        }
        $data['doctors'] = $doctorQuery->findAll();

        return view(ADMIN_VIEW . 'pages/doctor/consultation_fee', $data);
    }

    /**
     * --------------------------------------------------------
     * Create
     * --------------------------------------------------------.
     */
    public function create()
    {
        $data = $this->_getFeeData();
        $validation = $this->validateFee($data);

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }

        $data = $validation['data'];

        if (!$this->doctorInScope((int) $data['doctor_id'])) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Doctor is not available in your permitted scope.']);
        }

        // Check date overlap
        if ($this->doctorConsultationFee->hasOverlap((int) $data['doctor_id'], $data['consultation_type'], $data['consultation_mode'])) {
            return $this->response->setJSON(['status' => false, 'msg' => 'A consultation fee already exists for this doctor, consultation type, mode and date range.']);
        }
        $data['created_at'] = date('Y-m-d H:i:s');

        if (!$this->doctorConsultationFee->insert($data)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to save consultation fee.']);
        }
        $this->audit->record('admin.doctor_fee_created', ['doctor_id' => (int) $data['doctor_id']]);

        return $this->response->setJSON(['status' => true, 'msg' => 'Consultation fee added successfully.']);
    }

    /**
     * --------------------------------------------------------
     * Get Fee By ID
     * --------------------------------------------------------.
     */
    public function feeById(int $id)
    {
        $fee = $this->doctorConsultationFee->find($id);

        if (!$fee) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Consultation fee not found.']);
        }
        $fee['effective_from'] = _dateOnly($fee['effective_from']);

        return $this->response->setJSON(['status' => true, 'data' => $fee]);
    }

    /**
     * --------------------------------------------------------
     * Update
     * --------------------------------------------------------.
     */
    public function update()
    {
        $id = (int) $this->request->getPost('id');

        if (!$id) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Invalid consultation fee.']);
        }
        $existing = $this->doctorConsultationFee->find($id);

        if (!$existing) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Consultation fee not found.']);
        }

        if (!$this->doctorInScope((int) $existing['doctor_id'])) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Consultation fee not found.']);
        }
        $data = $this->_getFeeData();
        $validation = $this->validateFee($data);

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }

        $data = $validation['data'];

        if (!$this->doctorInScope((int) $data['doctor_id'])) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Doctor is not available in your permitted scope.']);
        }

        // Ignore current record while checking overlap
        if ($this->doctorConsultationFee->hasOverlap((int) $data['doctor_id'], $data['consultation_type'], $data['consultation_mode'], $id)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'A consultation fee already exists for this doctor, consultation type, mode and date range.']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!$this->doctorConsultationFee->update($id, $data)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to update consultation fee.']);
        }
        $this->audit->record('admin.doctor_fee_updated', ['fee_id' => $id, 'doctor_id' => (int) $data['doctor_id']]);

        return $this->response->setJSON(['status' => true, 'msg' => 'Consultation fee updated successfully.']);
    }

    /**
     * --------------------------------------------------------
     * Delete
     * --------------------------------------------------------.
     */
    public function delete(int $id)
    {
        $fee = $this->doctorConsultationFee->find($id);

        if (!$fee) {
            session()->setFlashdata(['status' => false, 'msg' => 'Consultation fee not found.']);

            return redirect()->to('admin/doctor-fees');
        }

        if (!$this->doctorInScope((int) $fee['doctor_id'])) {
            return redirect()->to('admin/doctor-fees')->with('error', 'Consultation fee not found.');
        }

        if (!$this->doctorConsultationFee->delete($id)) {
            session()->setFlashdata(['status' => false, 'msg' => 'Unable to delete consultation fee.']);
        } else {
            $this->audit->record('admin.doctor_fee_deleted', ['fee_id' => $id, 'doctor_id' => (int) $fee['doctor_id']]);
            session()->setFlashdata(['status' => true, 'msg' => 'Consultation fee deleted successfully.']);
        }

        return redirect()->to('admin/doctor-fees');
    }

    /**
     * --------------------------------------------------------
     * Get Request Data
     * --------------------------------------------------------.
     */
    protected function _getFeeData(): array
    {
        return ['doctor_id' => (int) $this->request->getPost('doctor_id'), 'consultation_type' => trim($this->request->getPost('consultation_type')), 'consultation_mode' => trim($this->request->getPost('consultation_mode')), 'fee' => trim((string) $this->request->getPost('fee')), 'effective_from' => trim((string) $this->request->getPost('effective_from')), 'effective_to' => null, 'status' => (int) $this->request->getPost('status')];
    }

    /**
     * --------------------------------------------------------
     * Validation
     * --------------------------------------------------------.
     */
    protected function validateFee(array $data): array
    {
        $rules = ['doctor_id' => ['label' => 'Doctor', 'rules' => 'required|is_natural_no_zero'], 'consultation_type' => ['label' => 'Consultation Type', 'rules' => 'required|in_list[NEW,FOLLOWUP,ALL]'], 'consultation_mode' => ['label' => 'Consultation Mode', 'rules' => 'required|in_list[OFFLINE,ONLINE,HOME_VISIT]'], 'fee' => ['label' => 'Fee', 'rules' => 'required|decimal|greater_than[0]'], 'effective_from' => ['label' => 'Effective From', 'rules' => 'required|valid_date[d-m-Y]|regex_match[/^\d{2}-\d{2}-\d{4}$/]'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]']];

        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }

        $effectiveFrom = rootins_parse_date($data['effective_from'], false);
        if ($effectiveFrom === null) {
            return ['status' => false, 'errors' => ['effective_from' => 'Effective From must be a valid date in dd-mm-yyyy format.']];
        }

        $data['fee'] = (float) $data['fee'];
        $data['effective_from'] = $effectiveFrom;

        return ['status' => true, 'data' => $data];
    }

    private function clinicScope(): int
    {
        return (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
    }

    private function doctorInScope(int $doctorId): bool
    {
        $doctor = $this->doctor->find($doctorId);

        return $doctor !== null && ($this->clinicScope() === 0 || (int) $doctor['clinic_id'] === $this->clinicScope());
    }
}
