<?php

namespace Admin\Controllers\Consultation;

use Admin\Models\AppointmentModel;
use Admin\Models\Clinic\ClinicModel;
use Admin\Models\ConsultationModel;
use Admin\Models\Doctor\DoctorModel;
use Admin\Models\PatientModel;
use App\Controllers\BaseAdminController;

class Consultation extends BaseAdminController
{
    protected ConsultationModel $consultation;

    protected AppointmentModel $appointment;

    protected PatientModel $patient;

    protected DoctorModel $doctor;

    protected ClinicModel $clinic;

    public function __construct()
    {
        $this->consultation = new ConsultationModel();
        $this->appointment = new AppointmentModel();
        $this->patient = new PatientModel();
        $this->doctor = new DoctorModel();
        $this->clinic = new ClinicModel();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $clinicId = (int) $this->request->getGet('clinic_id');
        $doctorId = (int) $this->request->getGet('doctor_id');
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = rootins_parse_date($dateFromInput);
        $dateTo = rootins_parse_date($dateToInput);
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->consultation->select('
                consultations.*,
                patients.registration_number,
                patients.mobile_number AS patient_mobile,
                patients.source_type AS patient_source,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                clinics.clinic_name,
                appointments.appointment_number,
                appointments.appointment_date,
                appointments.appointment_time,
                appointments.service_name,
                appointments.queue_position,
                appointments.status AS appointment_status,
                appointments.appointment_type
            ')->join('patients', 'patients.id = consultations.patient_id')->join('doctors', 'doctors.id = consultations.doctor_id')->join('clinics', 'clinics.id = consultations.clinic_id')->join('appointments', 'appointments.id = consultations.appointment_id', 'left');

        if ($search !== '') {
            $query->groupStart()->like('consultations.consultation_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.mobile_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }

        if (in_array($status, ['draft', 'in_progress', 'completed', 'cancelled'], true)) {
            $query->where('consultations.status', $status);
        }

        if ($clinicId > 0) {
            $query->where('consultations.clinic_id', $clinicId);
        }

        if ($doctorId > 0) {
            $query->where('consultations.doctor_id', $doctorId);
        }

        if ($dateFrom !== null) {
            $query->where('consultations.consultation_date >=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('consultations.consultation_date <=', $dateTo);
        }
        $consultations = $query->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.consultation_time', 'DESC')->paginate($pageSize, 'consultations');

        return view(ADMIN_VIEW . 'pages/consultation/index', ['consultations' => $consultations, 'pager' => $this->consultation->pager, 'filters' => ['search' => $search, 'status' => $status, 'clinic_id' => $clinicId ?: '', 'doctor_id' => $doctorId ?: '', 'date_from' => $dateFrom ? rootins_format_date($dateFrom) : $dateFromInput, 'date_to' => $dateTo ? rootins_format_date($dateTo) : $dateToInput, 'page_size' => $pageSize], 'patients' => $this->patient->select('id, registration_number, first_name, middle_name, last_name')->where('status', 1)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(), 'doctors' => $this->doctor->select('id, first_name, last_name, doctor_code, clinic_id')->where('status', 1)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(), 'clinics' => $this->clinic->dropdown(), 'appointments' => $this->appointment->select('appointments.id, appointments.appointment_number, appointments.patient_id, appointments.doctor_id, appointments.clinic_id, appointments.appointment_date, appointments.appointment_time')->whereNotIn('appointments.status', ['cancelled', 'no_show'])->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->findAll(), 'canCreate' => $this->hasPermission('consultation.create'), 'canEdit' => $this->hasPermission('consultation.edit'), 'canDelete' => $this->hasPermission('consultation.delete')]);
    }

    public function view(int $id)
    {
        $consultation = $this->consultationDetails($id);

        if ($consultation === null) {
            return redirect()->to('admin/consultations')->with('error', 'Consultation not found.');
        }

        return view(ADMIN_VIEW . 'pages/consultation/view', ['consultation' => $consultation, 'canEdit' => $this->hasPermission('consultation.edit'), 'canDelete' => $this->hasPermission('consultation.delete'), 'canCreatePrescription' => $this->hasPermission('prescription.create')]);
    }

    public function edit(int $id)
    {
        if (!$this->isAjaxMethod('GET')) {
            return $this->invalidRequest();
        }
        $consultation = $this->consultation->find($id);

        if ($consultation === null) {
            return $this->failure('Consultation not found.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $consultation]);
    }

    public function save()
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $validation = $this->validateConsultation();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $references = $this->validateReferences();

        if ($references !== []) {
            return $this->validationFailure($references);
        }
        $data = $this->consultationData();
        $data['consultation_number'] = 'TMP-CON-' . bin2hex(random_bytes(8));
        $db = db_connect();
        $db->transBegin();

        try {
            $consultationId = $this->consultation->insert($data);

            if (!$consultationId) {
                throw new \RuntimeException('Consultation insert failed.');
            }
            $consultationNumber = sprintf('CON-%06d', (int) $consultationId);

            if (!$this->consultation->update($consultationId, ['consultation_number' => $consultationNumber, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Consultation number assignment failed.');
            }

            if (!$db->transCommit()) {
                throw new \RuntimeException('Consultation transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Consultation creation failed: ' . $e->getMessage());

            return $this->failure('Unable to create consultation.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Consultation created successfully.', 'data' => ['id' => (int) $consultationId, 'consultation_number' => $consultationNumber]]);
    }

    public function update(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $existing = $this->consultation->find($id);

        if ($existing === null) {
            return $this->failure('Consultation not found.');
        }

        if ($existing['status'] === 'completed') {
            return $this->failure('Completed consultations cannot be modified.');
        }
        $newStatus = (string) $this->request->getPost('status');

        if (!$this->isValidStatusTransition((string) $existing['status'], $newStatus)) {
            return $this->validationFailure(['status' => 'This consultation status transition is not allowed.']);
        }
        $validation = $this->validateConsultation();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $references = $this->validateReferences();

        if ($references !== []) {
            return $this->validationFailure($references);
        }
        $data = $this->consultationData();
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            if (!$this->consultation->update($id, $data)) {
                throw new \RuntimeException('Consultation update failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Consultation update failed: ' . $e->getMessage());

            return $this->failure('Unable to update consultation.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Consultation updated successfully.']);
    }

    public function delete(int $id)
    {
        if (!$this->isAjaxMethod('POST')) {
            return $this->invalidRequest();
        }
        $consultation = $this->consultation->find($id);

        if ($consultation === null) {
            return $this->failure('Consultation not found.');
        }

        if ($consultation['status'] === 'completed') {
            return $this->failure('Completed consultations cannot be deleted.');
        }

        if (!$this->consultation->delete($id)) {
            return $this->failure('Unable to delete consultation.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Consultation deleted successfully.']);
    }

    private function validateConsultation(): array
    {
        $rules = ['patient_id' => ['label' => 'Patient', 'rules' => 'required|is_natural_no_zero'], 'doctor_id' => ['label' => 'Doctor', 'rules' => 'required|is_natural_no_zero'], 'clinic_id' => ['label' => 'Clinic', 'rules' => 'required|is_natural_no_zero'], 'consultation_date' => ['label' => 'Consultation Date', 'rules' => 'required'], 'consultation_time' => ['label' => 'Consultation Time', 'rules' => 'permit_empty|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'], 'chief_complaint' => ['label' => 'Chief Complaint', 'rules' => 'permit_empty'], 'clinical_notes' => ['label' => 'Clinical Notes', 'rules' => 'permit_empty'], 'diagnosis' => ['label' => 'Diagnosis', 'rules' => 'permit_empty'], 'treatment_plan' => ['label' => 'Treatment Plan', 'rules' => 'permit_empty'], 'diagnostic_tests' => ['label' => 'Diagnostic Tests', 'rules' => 'permit_empty'], 'diet_plan' => ['label' => 'Diet Plan', 'rules' => 'permit_empty'], 'follow_up_date' => ['label' => 'Follow-up Date', 'rules' => 'permit_empty'], 'appointment_id' => ['label' => 'Appointment', 'rules' => 'permit_empty|is_natural_no_zero'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[draft,in_progress,completed,cancelled]']];

        if (! $this->validate($rules)) {
            return $this->validator->getErrors();
        }

        $errors = [];
        if (rootins_parse_date($this->request->getPost('consultation_date')) === null) {
            $errors['consultation_date'] = 'Enter a valid consultation date as dd-mm-yyyy.';
        }
        if (trim((string) $this->request->getPost('follow_up_date')) !== '' && rootins_parse_date($this->request->getPost('follow_up_date')) === null) {
            $errors['follow_up_date'] = 'Enter a valid follow-up date as dd-mm-yyyy.';
        }

        return $errors;
    }

    private function validateReferences(): array
    {
        $patientId = (int) $this->request->getPost('patient_id');
        $doctorId = (int) $this->request->getPost('doctor_id');
        $clinicId = (int) $this->request->getPost('clinic_id');
        $appointmentId = $this->nullableInteger('appointment_id');
        $errors = [];
        $patient = $this->patient->where('id', $patientId)->where('status', 1)->first();
        $doctor = $this->doctor->where('id', $doctorId)->where('status', 1)->first();
        $clinic = $this->clinic->where('id', $clinicId)->where('status', 1)->first();

        if ($patient === null) {
            $errors['patient_id'] = 'The selected patient is invalid or inactive.';
        }

        if ($doctor === null) {
            $errors['doctor_id'] = 'The selected doctor is invalid or inactive.';
        }

        if ($clinic === null) {
            $errors['clinic_id'] = 'The selected clinic is invalid or inactive.';
        }

        if ($doctor !== null && (int) $doctor['clinic_id'] !== $clinicId) {
            $errors['doctor_id'] = 'The selected doctor is not assigned to the selected clinic.';
        }

        if ($appointmentId !== null) {
            $appointment = $this->appointment->find($appointmentId);

            if ($appointment === null) {
                $errors['appointment_id'] = 'The selected appointment is invalid.';
            } elseif ((int) $appointment['patient_id'] !== $patientId || (int) $appointment['doctor_id'] !== $doctorId || (int) $appointment['clinic_id'] !== $clinicId) {
                $errors['appointment_id'] = 'The appointment does not match the selected patient, doctor, and clinic.';
            }
        }

        return $errors;
    }

    private function consultationData(): array
    {
        return ['patient_id' => (int) $this->request->getPost('patient_id'), 'doctor_id' => (int) $this->request->getPost('doctor_id'), 'clinic_id' => (int) $this->request->getPost('clinic_id'), 'appointment_id' => $this->nullableInteger('appointment_id'), 'consultation_date' => rootins_parse_date($this->request->getPost('consultation_date')) ?? '', 'consultation_time' => $this->nullableString('consultation_time'), 'chief_complaint' => $this->nullableString('chief_complaint'), 'clinical_notes' => $this->nullableString('clinical_notes'), 'diagnosis' => $this->nullableString('diagnosis'), 'treatment_plan' => $this->nullableString('treatment_plan'), 'diagnostic_tests' => $this->nullableString('diagnostic_tests'), 'diet_plan' => $this->nullableString('diet_plan'), 'follow_up_date' => rootins_parse_date($this->request->getPost('follow_up_date')), 'status' => (string) $this->request->getPost('status')];
    }

    private function consultationDetails(int $id): ?array
    {
        return $this->consultation->select('
                consultations.*,
                patients.registration_number,
                patients.mobile_number AS patient_mobile,
                patients.email AS patient_email,
                patients.source_type AS patient_source,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                clinics.clinic_name,
                appointments.appointment_number,
                appointments.appointment_date,
                appointments.appointment_time,
                appointments.service_name,
                appointments.queue_position,
                appointments.status AS appointment_status,
                appointments.appointment_type
            ')->join('patients', 'patients.id = consultations.patient_id')->join('doctors', 'doctors.id = consultations.doctor_id')->join('clinics', 'clinics.id = consultations.clinic_id')->join('appointments', 'appointments.id = consultations.appointment_id', 'left')->find($id);
    }

    private function isValidStatusTransition(string $from, string $to): bool
    {
        $transitions = ['draft' => ['draft', 'in_progress', 'completed', 'cancelled'], 'in_progress' => ['in_progress', 'completed', 'cancelled'], 'completed' => ['completed'], 'cancelled' => ['cancelled']];

        return in_array($to, $transitions[$from] ?? [], true);
    }

    private function isAjaxMethod(string $method): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === $method;
    }

    private function nullableInteger(string $field): ?int
    {
        $value = $this->request->getPost($field);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON(['status' => false, 'msg' => $message]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
    }
}
