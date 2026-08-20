<?php namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\Doctor\DoctorSpecializationMapModel;

use Admin\Models\Clinic\ClinicModel;

use Admin\Models\Location\City;

use Admin\Models\Master\DoctorSpecializationModel;


class Doctor extends BaseAdminController
{

	protected DoctorModel $doctor;
    protected City $City;
    protected ClinicModel $clinic;
    protected DoctorSpecializationModel $specialization;
    protected DoctorSpecializationMapModel $doctorSpecializationMap;
	public function __construct()
	{
		$this->doctor = new DoctorModel();
        $this->city = new City();
        $this->clinic = new ClinicModel();
        $this->specialization = new DoctorSpecializationModel();
        $this->doctorSpecializationMap = new DoctorSpecializationMapModel();
	}



	public function index()
	{

        $data['doctors'] = $this->doctor->getDoctors();

        //clinic list
        $data['clinics'] = $this->clinic->findAll();

        //specialization list
        $data['specializations'] = $this->specialization->dropdown();


        $data['cities'] = $this->city->dropdown();
 
		return view(ADMIN_VIEW.'pages/doctor/index', $data);
	}




	/**
	 *-----------------------------------------------
	 * Create
	 *-----------------------------------------------
	 */
    public function create()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Invalid request.'
            ]);
        }

        // Validation
        $validation = $this->_doctorValidation();

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }

        $doctorData = [
            'clinic_id'               => (int) $this->request->getPost('clinic_id'),
            'city_id'                 => (int) $this->request->getPost('city_id'),
            'doctor_code'             => trim($this->request->getPost('doctor_code')),
            'first_name'              => trim($this->request->getPost('first_name')),
            'last_name'               => trim($this->request->getPost('last_name')),
            'gender'                  => $this->request->getPost('gender'),
            'dob'                     => $this->request->getPost('dob'),
            'qualification'           => trim($this->request->getPost('qualification')),
            'experience'              => (int) $this->request->getPost('experience'),
            'registration_number'     => trim($this->request->getPost('registration_number')),
            'mobile'                  => trim($this->request->getPost('mobile')),
            'email'                   => trim($this->request->getPost('email')),
            'address'                 => trim($this->request->getPost('address')),
            'consultation_duration'   => (int) $this->request->getPost('consultation_duration'),
            'notes'                   => trim($this->request->getPost('notes')),
            'online_booking'          => $this->request->getPost('online_booking') ? 1 : 0,
            'display_order'           => (int) $this->request->getPost('display_order'),
            'status'                  => (int) $this->request->getPost('status'),
        ];

        $specializations = $this->request->getPost('specialization_ids');

        $db = db_connect();
        $db->transBegin();

        try {

            $this->doctor->insert($doctorData);

            $doctorId = $this->doctor->getInsertID();

            foreach ($specializations as $specializationId) {

                $this->doctorSpecializationMap->insert([
                    'doctor_id'         => $doctorId,
                    'specialization_id' => $specializationId
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save doctor.');
            }

            $db->transCommit();

            return $this->response->setJSON([
                'status' => true,
                'msg'    => 'Doctor created successfully.'
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response->setJSON([
                'status' => false,
                'msg'    => $e->getMessage()
            ]);
        }
    }

	/**
     *
     * Doctor validation
     *
     */
    private function _doctorValidation($doctorId = null)
    {
        $uniqueDoctorCode = $doctorId
            ? 'required|max_length[20]|is_unique[doctors.doctor_code,id,' . $doctorId . ']'
            : 'required|max_length[20]|is_unique[doctors.doctor_code]';

        $uniqueRegistration = $doctorId
            ? 'permit_empty|max_length[100]|is_unique[doctors.registration_number,id,' . $doctorId . ']'
            : 'permit_empty|max_length[100]|is_unique[doctors.registration_number]';

        $rules = [

            'clinic_id' => [
                'label' => 'Clinic',
                'rules' => 'required|integer'
            ],
            'city_id' => [
                'label' => 'City',
                'rules' => 'required|integer'
            ],

            'specialization_ids' => [
                'label' => 'Specialization',
                'rules' => 'required'
            ],

            'doctor_code' => [
                'label' => 'Doctor Code',
                'rules' => $uniqueDoctorCode
            ],

            'first_name' => [
                'label' => 'First Name',
                'rules' => 'required|max_length[100]'
            ],

            'last_name' => [
                'label' => 'Last Name',
                'rules' => 'permit_empty|max_length[100]'
            ],

            'mobile' => [
                'label' => 'Mobile Number',
                'rules' => 'required|numeric|min_length[10]|max_length[15]'
            ],

            'email' => [
                'label' => 'Email',
                'rules' => 'permit_empty|valid_email|max_length[150]'
            ],

            'registration_number' => [
                'label' => 'Registration Number',
                'rules' => $uniqueRegistration
            ],

            'experience' => [
                'label' => 'Experience',
                'rules' => 'permit_empty|integer'
            ],

            'consultation_duration' => [
                'label' => 'Consultation Duration',
                'rules' => 'required|integer'
            ],

            'status' => [
                'label' => 'Status',
                'rules' => 'required|in_list[0,1]'
            ]

        ];

         if (!$this->validate($rules)) {
             return [
                'status' => false,
                'errors' => $this->validator->getErrors(),
            ];
        }

        return [
            'status' => true
        ];

        
    }
 

    /**
     *-------------------------------------------------------------------
     * Doctor data by doctor ID
     *-------------------------------------------------------------------
     */
    public function doctorById($doctorId)
    {
        $doctor = $this->doctor->find($doctorId);
        if ($doctor===null) {
            return $this->response->setJSON([
                'status'    => false,
                'msg'       => 'Data not found!'
             ]);
        }

        $specialization_ids = $this->doctorSpecializationMap
                                            ->getDoctorSpecializations($doctorId);


        return $this->response->setJSON([
                'status'    => true,
                'msg'       => '',
                'data'      => [
                    'doctor'    => $doctor,
                    'specialization_ids'    => $specialization_ids
                ]
            ]);

    }



    /**
     *----------------------------------------------------------------------
     * Update clinic
     *----------------------------------------------------------------------
     */

    public function update()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Invalid request.'
            ]);
        }

        $doctorId = (int) $this->request->getPost('doctor_id');

        $validation = $this->_doctorValidation($doctorId);

        
        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }

        $doctorData = [
            'clinic_id'             => (int)$this->request->getPost('clinic_id'),
            'city_id'               => (int)$this->request->getPost('city_id'),
            'doctor_code'           => trim($this->request->getPost('doctor_code')),
            'first_name'            => trim($this->request->getPost('first_name')),
            'last_name'             => trim($this->request->getPost('last_name')),
            'gender'                => $this->request->getPost('gender'),
            'dob'                   => $this->request->getPost('dob'),
            'qualification'         => trim($this->request->getPost('qualification')),
            'experience'            => (int)$this->request->getPost('experience'),
            'registration_number'   => trim($this->request->getPost('registration_number')),
            'mobile'                => trim($this->request->getPost('mobile')),
            'email'                 => trim($this->request->getPost('email')),
            'address'               => trim($this->request->getPost('address')),
            'consultation_duration' => (int)$this->request->getPost('consultation_duration'),
            'notes'                 => trim($this->request->getPost('notes')),
            'online_booking'        => $this->request->getPost('online_booking') ? 1 : 0,
            'display_order'         => (int)$this->request->getPost('display_order'),
            'status'                => (int)$this->request->getPost('status'),
        ];

        $specializationIds = $this->request->getPost('specialization_ids');

        $db = db_connect();
        $db->transBegin();

        try {

            // Update doctor
            $this->doctor->update($doctorId, $doctorData);

            // Delete old mappings
            $this->doctorSpecializationMap
                ->where('doctor_id', $doctorId)
                ->delete();

            // Insert new mappings
            foreach ($specializationIds as $specializationId) {

                $this->doctorSpecializationMap->insert([
                    'doctor_id'         => $doctorId,
                    'specialization_id' => $specializationId
                ]);
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Unable to update doctor.');
            }

            $db->transCommit();

            return $this->response->setJSON([
                'status' => true,
                'msg'    => 'Doctor updated successfully.'
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response->setJSON([
                'status' => false,
                'msg'    => $e->getMessage()
            ]);
        }
    }


    /**
     *----------------------------------------------------
     * delete
     *---------------------------------------------------
     */
    public function delete($doctorId)
    {
         
        $doctor = $this->doctor->find($doctorId);

        if (!$doctor) {
            session()->setFlashdata([
                'status'    => false,
                'msg'       => 'Doctor not found.'
            ]);

            return redirect()->to('admin/doctors');
        }

        $this->doctor->delete($doctorId);

        session()->setFlashdata([
            'status'    => true,
            'msg'       => 'Doctor deleted successfully.'
        ]);

        return redirect()->to('admin/doctors');
         
    }
	
}