<?php

namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;
use Admin\Models\Doctor\DoctorCommissionModel;
use Admin\Models\Doctor\DoctorModel;

class DoctorCommission extends BaseAdminController
{
    protected DoctorCommissionModel $doctorCommission;
    protected DoctorModel $doctor;

    public function __construct()
    {
        $this->doctorCommission = new DoctorCommissionModel();
        $this->doctor = new DoctorModel();
    }

    /**
     * --------------------------------------------------------
     * List
     * --------------------------------------------------------
     */
    public function index()
    {
        $data['commissions'] = $this->doctorCommission
            ->select('
                doctor_commissions.*,
                doctors.first_name,
                doctors.last_name,
                doctors.doctor_code
            ')
            ->join(
                'doctors',
                'doctors.id = doctor_commissions.doctor_id'
            )
            ->orderBy('doctors.first_name', 'ASC')
            ->orderBy('commission_for', 'ASC')
            ->orderBy('effective_from', 'DESC')
            ->findAll();

        $data['doctors'] = $this->doctor
            ->select('id, first_name, last_name')
            ->findAll();

        return view(
            ADMIN_VIEW . 'pages/doctor/commission',
            $data
        );
    }

    /**
     * --------------------------------------------------------
     * Create
     * --------------------------------------------------------
     */
    public function create()
    {
        $data = $this->_getCommissionData();

        $validation = $this->validateCommission($data);

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors' => $validation['errors'],
            ]);
        }

        // Check date overlap
        if ($this->doctorCommission->hasOverlap(
            (int) $data['doctor_id'],
            $data['commission_for']
        )) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'A commission already exists for this doctor, commission type, reference and date range.'
            ]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');

        if (!$this->doctorCommission->insert($data)) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to save doctor commission.'
            ]);
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Doctor commission added successfully.'
        ]);
    }

    /**
     * --------------------------------------------------------
     * Get Commission By ID
     * --------------------------------------------------------
     */
    public function commissionById(int $id)
    {
        $commission = $this->doctorCommission->find($id);

        if (!$commission) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Doctor commission not found.'
            ]);
        }

        $commission['effective_from'] = _dateOnly(
            $commission['effective_from']
        );

        if (!empty($commission['effective_to'])) {
            $commission['effective_to'] = _dateOnly(
                $commission['effective_to']
            );
        }

        return $this->response->setJSON([
            'status' => true,
            'data'   => $commission
        ]);
    }

    /**
     * --------------------------------------------------------
     * Update
     * --------------------------------------------------------
     */
    public function update()
    {
        $id = (int) $this->request->getPost('id');

        if (!$id) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Invalid doctor commission.'
            ]);
        }

        $existing = $this->doctorCommission->find($id);

        if (!$existing) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Doctor commission not found.'
            ]);
        }

        $data = $this->_getCommissionData();

        $validation = $this->validateCommission($data);

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors' => $validation['errors'],
            ]);
        }

        // Ignore current record while checking overlap
        if ($this->doctorCommission->hasOverlap(
            (int) $data['doctor_id'],
            $data['commission_for'],   
            $id
        )) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'A commission already exists for this doctor, commission type, reference and date range.'
            ]);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!$this->doctorCommission->update($id, $data)) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to update doctor commission.'
            ]);
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Doctor commission updated successfully.'
        ]);
    }

    /**
     * --------------------------------------------------------
     * Delete
     * --------------------------------------------------------
     */
    public function delete(int $id)
    {
        $commission = $this->doctorCommission->find($id);

        if (!$commission) {
            session()->setFlashdata([
                'status' => false,
                'msg'    => 'Doctor commission not found.'
            ]);

            return redirect()->to('admin/doctor-commissions');
        }

        if (!$this->doctorCommission->delete($id)) {

            session()->setFlashdata([
                'status' => false,
                'msg'    => 'Unable to delete doctor commission.'
            ]);

        } else {

            session()->setFlashdata([
                'status' => true,
                'msg'    => 'Doctor commission deleted successfully.'
            ]);
        }

        return redirect()->to('admin/doctor-commissions');
    }

    /**
     * --------------------------------------------------------
     * Get Request Data
     * --------------------------------------------------------
     */
    protected function _getCommissionData(): array
    {
        $referenceId = $this->request->getPost('reference_id');

        return [
            'doctor_id'        => (int) $this->request->getPost('doctor_id'),

            'commission_for'   => trim(
                $this->request->getPost('commission_for')
            ),

            'reference_id'     => !empty($referenceId)
                ? (int) $referenceId
                : null,

            'commission_type'  => trim(
                $this->request->getPost('commission_type')
            ),

            'commission_value' => (float) $this->request->getPost(
                'commission_value'
            ),

            'effective_from'   => getYMD(
                $this->request->getPost('effective_from')
            ),

            'effective_to'     => !empty(
                $this->request->getPost('effective_to')
            )
                ? getYMD($this->request->getPost('effective_to'))
                : null,

            'status'           => (int) $this->request->getPost('status'),
        ];
    }

    /**
     * --------------------------------------------------------
     * Validation
     * --------------------------------------------------------
     */
    protected function validateCommission(array $data): array
    {
        $rules = [
            'doctor_id' => [
                'label' => 'Doctor',
                'rules' => 'required|is_natural_no_zero',
            ],

            'commission_for' => [
                'label' => 'Commission For',
                'rules' => 'required|in_list[CONSULTATION,SERVICE,PROCEDURE]',
            ],

            'commission_type' => [
                'label' => 'Commission Type',
                'rules' => 'required|in_list[PERCENTAGE,FIXED]',
            ],

            'commission_value' => [
                'label' => 'Commission Value',
                'rules' => 'required|decimal|greater_than[0]',
            ],

            'effective_from' => [
                'label' => 'Effective From',
                'rules' => 'required|valid_date[d-m-Y]|regex_match[/^\d{2}-\d{2}-\d{4}$/]',
            ],

            'status' => [
                'label' => 'Status',
                'rules' => 'required|in_list[0,1]',
            ],
        ];

        /*
         * Reference ID is required for SERVICE and PROCEDURE.
         */
        if (
            in_array(
                $data['commission_for'],
                ['SERVICE', 'PROCEDURE'],
                true
            )
        ) {
            $rules['reference_id'] = [
                'label' => 'Service / Procedure',
                'rules' => 'required|is_natural_no_zero',
            ];
        }

        /*
         * Effective To is optional, but when provided
         * it must be a valid date.
         */
        if (!empty($data['effective_to'])) {
            $rules['effective_to'] = [
                'label' => 'Effective To',
                'rules' => 'valid_date[d-m-Y]|regex_match[/^\d{2}-\d{2}-\d{4}$/]',
            ];
        }

        if (!$this->validate($rules)) {
            return [
                'status' => false,
                'errors' => $this->validator->getErrors()
            ];
        }

        /*
         * Percentage cannot be greater than 100.
         */
        if (
            $data['commission_type'] === 'PERCENTAGE' &&
            $data['commission_value'] > 100
        ) {
            return [
                'status' => false,
                'errors' => [
                    'commission_value' =>
                        'Percentage commission cannot be greater than 100.'
                ]
            ];
        }

        /*
         * Effective To cannot be before Effective From.
         */
        if (
            !empty($data['effective_to']) &&
            $data['effective_to'] < $data['effective_from']
        ) {
            return [
                'status' => false,
                'errors' => [
                    'effective_to' =>
                        'Effective To must be greater than or equal to Effective From.'
                ]
            ];
        }

        return [
            'status' => true
        ];
    }
}