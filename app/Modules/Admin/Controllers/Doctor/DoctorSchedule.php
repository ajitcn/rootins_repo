<?php

namespace Admin\Controllers\Doctor;

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
        $search = trim((string) $this->request->getGet('search'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->doctorSchedule->select('doctor_schedules.*, doctors.doctor_code, doctors.first_name, doctors.last_name')->join('doctors', 'doctors.id = doctor_schedules.doctor_id');
        $this->applyClinicScope($query, 'doctors.clinic_id');

        if ($search !== '') {
            $query->groupStart()->like('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->groupEnd();
        }
        $data['schedules'] = $query->orderBy('doctors.first_name', 'ASC')->orderBy('doctor_schedules.day_of_week', 'ASC')->paginate($pageSize, 'doctor_schedules');
        $data['pager'] = $this->doctorSchedule->pager;
        $data['filters'] = ['search' => $search, 'page_size' => $pageSize];
        $data['doctors'] = $this->scopedDoctors();

        return view(ADMIN_VIEW . 'pages/doctor/schedule', $data);
    }

    /**
     *-----------------------------------------------
     * Create
     *-----------------------------------------------.
     */
    public function create()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        // Validation
        $validation = $this->_scheduleValidation();

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $doctorId = (int) $this->request->getPost('doctor_id');
        if (!$this->doctorIsInScope($doctorId)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'The selected doctor is outside the permitted clinic scope.', 'errors' => ['doctor_id' => 'Select a doctor from the permitted clinic scope.']]);
        }
        $days = $this->request->getPost('day_of_weeks');
        $startTime = $this->request->getPost('start_time');
        $endTime = $this->request->getPost('end_time');

        $timeErrors = $this->validateTimes($startTime, $endTime);
        if ($timeErrors !== []) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($timeErrors), 'errors' => $timeErrors]);
        }
        if (strtotime($startTime) >= strtotime($endTime)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'End Time must be greater than Start Time.', 'errors' => ['end_time' => 'End Time must be greater than Start Time.']]);
        }
        $rows = [];

        foreach ($days as $day) {
            if ($this->doctorSchedule->hasOverlap($doctorId, (int) $day, $startTime, $endTime)) {
                return $this->response->setJSON(['status' => false, 'msg' => 'Schedule overlaps on ' . $this->getDayName($day)]);
            }
            $rows[] = $this->scheduleData($doctorId, (int) $day, $startTime, $endTime);
        }
        $db = db_connect();
        $db->transStart();
        $this->doctorSchedule->insertBatch($rows);
        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to save doctor schedules.']);
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Doctor schedule saved successfully.']);
    }

    /**
     * Get day name from day number.
     *
     * 1 = Monday
     * ...
     * 7 = Sunday
     */
    private function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        return $days[$day] ?? 'Unknown';
    }

    /**
     *
     * Schedule form validation.
     *
     */
    private function _scheduleValidation($scheduleId = null)
    {
        $rules = ['doctor_id' => ['label' => 'Doctor', 'rules' => 'required|is_natural_no_zero'], 'day_of_weeks' => ['label' => 'Day', 'rules' => 'required'], 'shift_name' => ['label' => 'Shift Name', 'rules' => 'permit_empty|max_length[50]'], 'start_time' => ['label' => 'Start Time', 'rules' => 'required|regex_match[/^\\d{2}:\\d{2}(:\\d{2})?$/]'], 'end_time' => ['label' => 'End Time', 'rules' => 'required|regex_match[/^\\d{2}:\\d{2}(:\\d{2})?$/]'], 'break_start_time' => ['label' => 'Break Start', 'rules' => 'permit_empty|regex_match[/^\\d{2}:\\d{2}(:\\d{2})?$/]'], 'break_end_time' => ['label' => 'Break End', 'rules' => 'permit_empty|regex_match[/^\\d{2}:\\d{2}(:\\d{2})?$/]'], 'slot_duration' => ['label' => 'Slot Duration', 'rules' => 'required|integer|greater_than[0]|less_than_equal_to[480]'], 'max_patients' => ['label' => 'Maximum Patients', 'rules' => 'permit_empty|integer|greater_than_equal_to[0]'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]']];

        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }

        return ['status' => true];
    }

    /**
     *-------------------------------------------------------------------
     * Schedule data by schedule ID
     *-------------------------------------------------------------------.
     */
    public function scheduleById($scheduleId)
    {
        $schedule = $this->doctorSchedule->select('doctor_schedules.*')->join('doctors', 'doctors.id = doctor_schedules.doctor_id')->where('doctor_schedules.id', (int) $scheduleId);
        $this->applyClinicScope($schedule, 'doctors.clinic_id');
        $schedule = $schedule->first();

        if ($schedule === null) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Data not found!']);
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $schedule]);
    }

    /**
     *----------------------------------------------------------------------
     * Update clinic
     *----------------------------------------------------------------------.
     */
    public function update()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $scheduleId = (int) $this->request->getPost('schedule_id');
        $validation = $this->_scheduleValidation($scheduleId);

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $doctorId = (int) $this->request->getPost('doctor_id');
        $existing = $this->scheduleInScope($scheduleId);
        if ($existing === null || !$this->doctorIsInScope($doctorId)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'The selected schedule is outside the permitted clinic scope.']);
        }
        $day = $this->request->getPost('day_of_weeks')[0];
        $startTime = $this->request->getPost('start_time');
        $endTime = $this->request->getPost('end_time');

        $timeErrors = $this->validateTimes($startTime, $endTime);
        if ($timeErrors !== []) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($timeErrors), 'errors' => $timeErrors]);
        }
        if (strtotime($startTime) >= strtotime($endTime)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'End Time must be greater than Start Time.', 'errors' => ['end_time' => 'End Time must be greater than Start Time.']]);
        }

        if ($this->doctorSchedule->hasOverlap($doctorId, (int) $day, $startTime, $endTime, $scheduleId)) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Schedule overlaps on ' . $this->getDayName($day)]);
        }
        $inputData = $this->scheduleData($doctorId, (int) $day, $startTime, $endTime);
        $db = db_connect();
        $db->transStart();
        $this->doctorSchedule->update($scheduleId, $inputData);
        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to save doctor schedules.']);
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Doctor schedule updated successfully.']);
    }

    /**
     *----------------------------------------------------
     * delete
     *---------------------------------------------------.
     */
    public function delete($scheduleId)
    {
        $schedule = $this->scheduleInScope((int) $scheduleId);

        if (!$schedule) {
            session()->setFlashdata(['status' => false, 'msg' => 'Schedule not found.']);

            return redirect()->to('admin/doctor-schedules');
        }
        $this->doctorSchedule->delete($scheduleId);
        session()->setFlashdata(['status' => true, 'msg' => 'Schedule deleted successfully.']);

        return redirect()->to('admin/doctor-schedules');
    }

    private function currentClinicId(): int
    {
        return (int) (($this->user['clinic_id'] ?? 0) ?: session('clinic_id') ?: 0);
    }

    private function applyClinicScope($query, string $column): void
    {
        if ($this->currentClinicId() > 0) {
            $query->where($column, $this->currentClinicId());
        }
    }

    private function scopedDoctors(): array
    {
        $query = $this->doctor->select('id, first_name, last_name, doctor_code')->where('status', 1);
        $this->applyClinicScope($query, 'clinic_id');
        return $query->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll();
    }

    private function doctorIsInScope(int $doctorId): bool
    {
        $query = $this->doctor->where('id', $doctorId)->where('status', 1);
        $this->applyClinicScope($query, 'clinic_id');
        return $query->first() !== null;
    }

    private function scheduleInScope(int $scheduleId): ?array
    {
        $query = $this->doctorSchedule->select('doctor_schedules.*')->join('doctors', 'doctors.id = doctor_schedules.doctor_id')->where('doctor_schedules.id', $scheduleId);
        $this->applyClinicScope($query, 'doctors.clinic_id');
        return $query->first() ?: null;
    }

    private function validateTimes(string $start, string $end): array
    {
        $breakStart = trim((string) $this->request->getPost('break_start_time'));
        $breakEnd = trim((string) $this->request->getPost('break_end_time'));
        $errors = [];
        if (($breakStart === '') !== ($breakEnd === '')) {
            $errors['break_end_time'] = 'Enter both break times or leave both blank.';
        } elseif ($breakStart !== '' && (strtotime($breakStart) <= strtotime($start) || strtotime($breakEnd) >= strtotime($end) || strtotime($breakStart) >= strtotime($breakEnd))) {
            $errors['break_start_time'] = 'Break must be inside the working hours.';
        }
        return $errors;
    }

    private function scheduleData(int $doctorId, int $day, string $start, string $end): array
    {
        $breakStart = trim((string) $this->request->getPost('break_start_time'));
        $breakEnd = trim((string) $this->request->getPost('break_end_time'));
        return ['doctor_id' => $doctorId, 'day_of_week' => $day, 'shift_name' => trim((string) $this->request->getPost('shift_name')) ?: null, 'start_time' => $start, 'end_time' => $end, 'break_start_time' => $breakStart !== '' ? $breakStart : null, 'break_end_time' => $breakEnd !== '' ? $breakEnd : null, 'slot_duration' => (int) $this->request->getPost('slot_duration'), 'max_patients' => (int) $this->request->getPost('max_patients'), 'status' => (int) $this->request->getPost('status')];
    }
}
