<?php namespace Admin\Controllers\Clinic;

use App\Controllers\BaseAdminController;

use Admin\Models\Clinic\ClinicModel;

class Clinic extends BaseAdminController
{

	protected ClinicModel $clinic;
	public function __construct()
	{
		$this->clinic = new ClinicModel();
	}

	public function index()
	{
		$data['countries'] = service('location')->countries();
		
		$data['records'] = $this->clinic
		    ->select('
		        clinics.*,
		        countries.name AS country_name,
		        states.name AS state_name,
		        cities.name AS city_name
		    ')
		    ->join('countries', 'countries.id = clinics.country_id', 'left')
		    ->join('states', 'states.id = clinics.state_id', 'left')
		    ->join('cities', 'cities.id = clinics.city_id', 'left')
		    ->orderBy('clinics.clinic_name', 'ASC')
		    ->findAll();

		return view(ADMIN_VIEW.'pages/clinic/index', $data);
	}




	/**
	 *-----------------------------------------------
	 * Create
	 *-----------------------------------------------
	 */

	public function create()
	{
		if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(404);
        }

        $validation = $this->validateClinic();

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }

        try {

            $this->clinic->insert([
                'clinic_name'    => trim($this->request->getPost('clinic_name')),
                'clinic_code'    => trim($this->request->getPost('clinic_code')),
                'contact_person' => trim($this->request->getPost('contact_person')),
                'mobile'         => trim($this->request->getPost('mobile')),
                'email'          => trim($this->request->getPost('email')),
                'country_id'     => (int)$this->request->getPost('country_id'),
                'state_id'       => (int)$this->request->getPost('state_id'),
                'city_id'        => (int)$this->request->getPost('city_id'),
                'address'        => trim($this->request->getPost('address')),
                'pincode'        => trim($this->request->getPost('pincode')),
                'status'         => $this->request->getPost('status'),
                'created_by'     => session('user_id'),
            ]);

            return $this->response->setJSON([
                'status' => true,
                'msg'    => 'Clinic created successfully.'
            ]);

        } catch (\Throwable $e) {

            log_message('error', $e->getMessage());

            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to create clinic.'
            ]);
        }
    }

    /**
     * Clinic Validation
     */
    private function validateClinic($id=null)
    {
        $rules = [

            'clinic_name' => [
                'label' => 'Clinic Name',
                'rules' => 'required|min_length[3]|max_length[150]|is_unique[clinics.clinic_name,id,'.$id.']',
            ],
            'clinic_code' => [
                'label' => 'Clinic Code',
                'rules' => 'required|min_length[2]|max_length[5]|is_unique[clinics.clinic_code,id,'.$id.']',
                'errors'=>[
                    'is_unique' => 'The {field} is already exist.'
                ]
            ],

            'country_id' => [
                'label' => 'Country',
                'rules' => 'required|integer',
            ],

            'state_id' => [
                'label' => 'State',
                'rules' => 'required|integer',
            ],

            'city_id' => [
                'label' => 'City',
                'rules' => 'required|integer',
            ],

            'status' => [
                'label' => 'Status',
                'rules' => 'required|in_list[0,1]',
            ],
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
     * Clinic data by clinic ID
     *-------------------------------------------------------------------
     */
    public function clinicById($clinicId)
    {
        $data = $this->clinic->find($clinicId);
        if ($data===null) {
            return $this->response->setJSON([
                'status'    => false,
                'msg'       => 'Data not found!'
             ]);
        }


        return $this->response->setJSON([
                'status'    => true,
                'msg'       => '',
                'data'      => $data
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
                'status'    => false,
                'msg'       => 'This request is not allowed.'
            ]);
        }

        $clinicId = (int)$this->request->getPost('clinic_id');
        $validation = $this->validateClinic($clinicId);

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }


        try {

            $this->clinic->update($clinicId, [

                'clinic_name'    => trim($this->request->getPost('clinic_name')),
                'clinic_code'    => trim($this->request->getPost('clinic_code')),
                'contact_person' => trim($this->request->getPost('contact_person')),
                'mobile'         => trim($this->request->getPost('mobile')),
                'email'          => trim($this->request->getPost('email')),
                'country_id'     => (int)$this->request->getPost('country_id'),
                'state_id'       => (int)$this->request->getPost('state_id'),
                'city_id'        => (int)$this->request->getPost('city_id'),
                'address'        => trim($this->request->getPost('address')),
                'pincode'        => trim($this->request->getPost('pincode')),
                'status'         => $this->request->getPost('status'),
                 
            ]);

            return $this->response->setJSON([
                'status' => true,
                'msg'    => 'Clinic updated successfully.'
            ]);

        } catch (\Throwable $e) {

            log_message('error', $e->getMessage());

            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to update clinic.'
            ]);
        } 
    }
    


    /**
     *----------------------------------------------------
     * delete
     *---------------------------------------------------
     */
    public function delete($clinicId)
    {
         
        $clinic = $this->clinic->find($clinicId);

        if (!$clinic) {
            session()->setFlashdata([
                'status'    => false,
                'msg'       => 'Clinic not found.'
            ]);

            return redirect()->to('admin/clinics');
        }

        $this->clinic->delete($clinicId);

        session()->setFlashdata([
            'status'    => true,
            'msg'       => 'Clinic deleted successfully.'
        ]);

        return redirect()->to('admin/clinics');
         
    }
	
}