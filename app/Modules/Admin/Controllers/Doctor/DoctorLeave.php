<?php namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;
use Admin\Models\Doctor\DoctorLeaveModel; 
use Admin\Models\Doctor\DoctorModel; 

class DoctorLeave extends BaseAdminController
{
    protected DoctorLeaveModel $leave;
    protected DoctorModel $doctor;

    public function __construct()
    {
        $this->leave = new DoctorLeaveModel();
        $this->doctor = new DoctorModel();
    }

    /**
     * List all leaves
     */
    public function index()
    {
        $data['leaves'] = $this->leave->getLeaves(); 

        $data['doctors'] = $this->doctor
                            ->select('id, first_name, last_name')
                            ->findAll();
     

        return view(ADMIN_VIEW.'pages/doctor/leave', $data);
    }

    /**
     * Store new leave
     */
    public function create()
    {
        $rules = [
            'doctor_id'  => [ 
                'label' => 'Doctor',  
                'rules' => 'required|integer'
            ],

            'leave_type' => [
                'label' => 'Leave Type',
                'rules' => 'required|max_length[50]'
            ],
            'start_date' => [
                'label' => 'Start Date',
                'rules' => 'required|valid_date[d-m-Y]|regex_match[/^\d{2}-\d{2}-\d{4}$/]'
            ],
            'end_date'   => [
                'label' => 'End Date',
                'rules' => 'required|valid_date[d-m-Y]|regex_match[/^\d{2}-\d{2}-\d{4}$/]'
            ],

            'status'     => [
                'label' => 'Status',
                'rules' => 'required|in_list[0,1,2]'
            ]
        ];

        if (! $this->validate($rules)) {
            $formErr  =$this->validator->getErrors();
            return $this->response->setJSON([
                'status' => false,
                'msg'   => reset($formErr ),
                'errors' => $formErr 
            ]);
        }

        $data = [
            'doctor_id'  => $this->request->getPost('doctor_id'),
            'leave_type' => $this->request->getPost('leave_type'),
            'start_date' => getYMD($this->request->getPost('start_date')),
            'end_date'   => getYMD($this->request->getPost('end_date')),
            'reason'     => $this->request->getPost('reason'),
            'status'     => $this->request->getPost('status'),
        ];

        $this->leave->insert($data);

        return $this->response->setJSON([
            'status' => true,
            'msg' => 'Leave added successfully.'
        ]);
    }

   

    

    /**
     * update status leave
     */
    public function updateStatus()
    {

        $rules = [
            'leave_id'  => 'required|integer', 
            'status'     => 'required|in_list[0,1,2]',
        ];

        if (! $this->validate($rules)) {
             session()->setFlashdata([
                'status'    => false,
                'msg'       => implode(', ', $this->validator->getErrors())
            ]);

            return redirect()->to('admin/doctor-leaves');
             
        }


        $id = (int)$this->request->getPost('leave_id');
        $status = (int)$this->request->getPost('status');
        $this->leave->update($id, [
            'status' =>  $status
        ]);

        session()->setFlashdata([
            'status'    => true,
            'msg'       => 'Schedule updated successfully.'
        ]);

        return redirect()->to('admin/doctor-leaves');
    }

    /**
     * Delete leave permanently
     */
    public function delete($id)
    {
        $this->leave->delete($id);

        session()->setFlashdata([
            'status'    => true,
            'msg'       => 'Schedule deleted successfully.'
        ]);

        return redirect()->to('admin/doctor-leaves');
    }
}