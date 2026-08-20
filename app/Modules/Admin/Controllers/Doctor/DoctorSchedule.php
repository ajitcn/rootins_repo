<?php namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;

use Admin\Models\Doctor\DoctorScheduleModel; 
use Admin\Models\Doctor\DoctorModel; 

class DoctorSchedule extends BaseAdminController
{

	protected DoctorScheduleModel $doctorSchedule;
    protected DoctorModel $doctor;
    
	public function __construct()
	{
		$this->doctorSchedule = new DoctorScheduleModel();
        $this->doctor = new DoctorModel();
	}



	public function index()
	{

        $data['schedules'] = $this->doctorSchedule->getSchedules(); 
        $data['doctors'] = $this->doctor->getDoctors(); 
		return view(ADMIN_VIEW.'pages/doctor/schedule', $data);
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
        $validation = $this->_scheduleValidation();

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }

        $doctorId = (int) $this->request->getPost('doctor_id');
        $days     = $this->request->getPost('day_of_weeks');

        $startTime = $this->request->getPost('start_time');
        $endTime   = $this->request->getPost('end_time');

        if (strtotime($startTime) >= strtotime($endTime)) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'End Time must be greater than Start Time.'
            ]);
        }
 

        $rows = [];

        foreach ($days as $day) {

            if ($this->doctorSchedule->hasOverlap(
                $doctorId,
                (int)$day,
                $startTime,
                $endTime
            )) {
                return $this->response->setJSON([
                    'status' => false,
                    'msg'    => 'Schedule overlaps on '.$this->getDayName($day)
                ]);
            }

            $rows[] = [
                'doctor_id'     => $doctorId,
                'day_of_week'   => (int)$day,
                'shift_name'    => trim($this->request->getPost('shift_name')),
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'slot_duration' => (int)$this->request->getPost('slot_duration'),
                'max_patients'  => (int)$this->request->getPost('max_patients'),
                'status'        => (int)$this->request->getPost('status'),
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
        }




        $db = db_connect();
        $db->transStart();

        $this->doctorSchedule->insertBatch($rows);

        $db->transComplete();
 
        if (! $db->transStatus()) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to save doctor schedules.'
            ]);
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Doctor schedule saved successfully.'
        ]);
    }

    /**
     * Get day name from day number
     *
     * 1 = Monday
     * ...
     * 7 = Sunday
     */
    private function getDayName(int $day): string
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return $days[$day] ?? 'Unknown';
    }

	/**
     *
     * Schedule form validation
     *
     */
    private function _scheduleValidation($scheduleId = null)
    {
         
        $rules = [

                'doctor_id' => [
                    'label' => 'Doctor',
                    'rules' => 'required|is_natural_no_zero'
                ],

                'day_of_weeks' => [
                    'label' => 'Day',
                    'rules' => 'required'
                ],

                'shift_name' => [
                    'label' => 'Shift Name',
                    'rules' => 'permit_empty|max_length[50]'
                ],

                'start_time' => [
                    'label' => 'Start Time',
                    'rules' => 'required'
                ],

                'end_time' => [
                    'label' => 'End Time',
                    'rules' => 'required'
                ],

                'slot_duration' => [
                    'label' => 'Slot Duration',
                    'rules' => 'required|integer|greater_than[0]'
                ],

                'max_patients' => [
                    'label' => 'Maximum Patients',
                    'rules' => 'permit_empty|integer|greater_than_equal_to[0]'
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
     * Schedule data by schedule ID
     *-------------------------------------------------------------------
     */
    public function scheduleById($scheduleId)
    {
        $schedule = $this->doctorSchedule->find($scheduleId);
        if ($schedule===null) {
            return $this->response->setJSON([
                'status'    => false,
                'msg'       => 'Data not found!'
             ]);
        }

         

        return $this->response->setJSON([
                'status'    => true,
                'msg'       => '',
                'data'      => $schedule
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

        $scheduleId = (int) $this->request->getPost('schedule_id');
        $validation = $this->_scheduleValidation($scheduleId);

        if ($validation['status'] !== true) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => reset($validation['errors']),
                'errors'    => $validation['errors'],
            ]);
        }


        $doctorId = (int) $this->request->getPost('doctor_id');
        $day     = $this->request->getPost('day_of_weeks')[0];

        $startTime = $this->request->getPost('start_time');
        $endTime   = $this->request->getPost('end_time');

        if (strtotime($startTime) >= strtotime($endTime)) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'End Time must be greater than Start Time.'
            ]);
        }
 

        if ($this->doctorSchedule->hasOverlap(
            $doctorId,
            (int)$day,
            $startTime,
            $endTime,
            $scheduleId
        )) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Schedule overlaps on '.$this->getDayName($day)
            ]);
        }

        $inputData = [
            'doctor_id'     => $doctorId,
            'day_of_week'   => (int)$day,
            'shift_name'    => trim($this->request->getPost('shift_name')),
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'slot_duration' => (int)$this->request->getPost('slot_duration'),
            'max_patients'  => (int)$this->request->getPost('max_patients'),
            'status'        => (int)$this->request->getPost('status'),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
   

        $db = db_connect();
        $db->transStart();

        $this->doctorSchedule->update($scheduleId, $inputData);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setJSON([
                'status' => false,
                'msg'    => 'Unable to save doctor schedules.'
            ]);
        }

        return $this->response->setJSON([
            'status' => true,
            'msg'    => 'Doctor schedule updated successfully.'
        ]);

        

         
    }


    /**
     *----------------------------------------------------
     * delete
     *---------------------------------------------------
     */
    public function delete($scheduleId)
    {
         
        $schedule = $this->doctorSchedule->find($scheduleId);

        if (!$schedule) {
            session()->setFlashdata([
                'status'    => false,
                'msg'       => 'Schedule not found.'
            ]);

            return redirect()->to('admin/doctor-schedules');
        }

        $this->doctorSchedule->delete($scheduleId);

        session()->setFlashdata([
            'status'    => true,
            'msg'       => 'Schedule deleted successfully.'
        ]);

        return redirect()->to('admin/doctor-schedules');
         
    }
	
}