<?php

namespace Admin\Controllers\Patient;

use App\Controllers\BaseAdminController;
use Admin\Models\AppointmentModel;
use Admin\Models\Clinic\ClinicModel;
use Admin\Models\ConsultationModel;
use Admin\Models\Location\City;
use Admin\Models\Location\Country;
use Admin\Models\Location\State;
use Admin\Models\PatientDocumentModel;
use Admin\Models\PatientMedicalHistoryModel;
use Admin\Models\PatientModel;
use Admin\Models\PrescriptionModel;
use Admin\Services\PatientRegistrationService;
use App\Services\LocationService;
class Patient extends BaseAdminController
{
    protected PatientModel $patient;
    protected PatientRegistrationService $registrationService;
    public function __construct()
    {
        $this->patient = new PatientModel();
        $this->registrationService = new PatientRegistrationService($this->patient);
    }
    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->patient->select('
                patients.*,
                clinics.clinic_name,
                countries.name AS country_name,
                states.name AS state_name,
                cities.name AS city_name,
                localities.name AS locality_name
            ')->join('clinics', 'clinics.id = patients.clinic_id', 'left')->join('countries', 'countries.id = patients.country_id', 'left')->join('states', 'states.id = patients.state_id', 'left')->join('cities', 'cities.id = patients.city_id', 'left')->join('localities', 'localities.id = patients.locality_id', 'left');
        if ($search !== '') {
            $query->groupStart()->like('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('patients.registration_number', $search)->orLike('patients.mobile_number', $search)->orLike('patients.email', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }
        if ($status === '0' || $status === '1') {
            $query->where('patients.status', (int) $status);
        }
        if ($this->clinicScope() > 0) {
            $query->where('patients.clinic_id', $this->clinicScope());
        }
        $data['patients'] = $query->orderBy('patients.first_name', 'ASC')->orderBy('patients.last_name', 'ASC')->paginate($pageSize, 'patients');
        $data['pager'] = $this->patient->pager;
        $data['filters'] = ['search' => $search, 'status' => $status, 'page_size' => $pageSize];
        $data['countries'] = service('location')->countries();
        $data['clinics'] = $this->scopedClinics();
        $data['canCreate'] = $this->hasPermission('patient.create');
        $data['canEdit'] = $this->hasPermission('patient.edit');
        return view(ADMIN_VIEW . 'pages/patient/index', $data);
    }
    public function registration()
    {
        return view(ADMIN_VIEW . 'pages/patient/registration', ['countries' => service('location')->countries(), 'clinics' => $this->scopedClinics()]);
    }
    public function view(int $id)
    {
        $query = $this->patient->select('
                patients.*,
                clinics.clinic_name,
                countries.name AS country_name,
                states.name AS state_name,
                cities.name AS city_name,
                localities.name AS locality_name
            ')->join('clinics', 'clinics.id = patients.clinic_id', 'left')->join('countries', 'countries.id = patients.country_id', 'left')->join('states', 'states.id = patients.state_id', 'left')->join('cities', 'cities.id = patients.city_id', 'left')->join('localities', 'localities.id = patients.locality_id', 'left')->where('patients.id', $id);
        if ($this->clinicScope() > 0) {
            $query->where('patients.clinic_id', $this->clinicScope());
        }
        $patient = $query->first();
        if ($patient === null) {
            return redirect()->to(base_url('admin/patients'))->with('error', 'Patient not found.');
        }
        return view(ADMIN_VIEW . 'pages/patient/view', ['patient' => $patient, 'timeline' => $this->patientTimeline($id)]);
    }
    private function patientTimeline(int $patientId): array
    {
        $timeline = [];
        $doctorName = "CONCAT_WS(' ', doctors.first_name, doctors.last_name)";
        foreach ((new PatientMedicalHistoryModel())->where('patient_id', $patientId)->orderBy('history_date', 'DESC')->orderBy('created_at', 'DESC')->findAll() as $history) {
            $timeline[] = ['occurred_at' => $history['history_date'] ?: $history['created_at'], 'type' => 'Medical History', 'title' => $history['title'], 'summary' => $history['details'], 'url' => base_url('admin/patients/' . $patientId . '/medical-history')];
        }
        foreach ((new PatientDocumentModel())->where('patient_id', $patientId)->orderBy('created_at', 'DESC')->findAll() as $document) {
            $timeline[] = ['occurred_at' => $document['created_at'], 'type' => 'Patient Document', 'title' => $document['title'], 'summary' => trim(($document['document_type'] ?? '') . ' · ' . ($document['file_name'] ?? '')), 'url' => base_url('admin/patients/' . $patientId . '/documents/download/' . (int) $document['id'])];
        }
        foreach ((new AppointmentModel())->select('appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.reason_for_visit, appointments.status, ' . $doctorName . ' AS doctor_name')->join('doctors', 'doctors.id = appointments.doctor_id', 'left')->where('appointments.patient_id', $patientId)->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->findAll() as $appointment) {
            $timeline[] = ['occurred_at' => trim($appointment['appointment_date'] . ' ' . ($appointment['appointment_time'] ?? '00:00:00')), 'type' => 'Appointment', 'title' => 'Appointment ' . $appointment['appointment_number'], 'summary' => $appointment['reason_for_visit'] ?: ucwords(str_replace('_', ' ', $appointment['status'])), 'doctor' => $appointment['doctor_name'], 'appointment_no' => $appointment['appointment_number'], 'url' => base_url('admin/appointments'), 'link_permission' => 'appointment.view'];
        }
        foreach ((new ConsultationModel())->select('consultations.id, consultations.consultation_number, consultations.consultation_date, consultations.consultation_time, consultations.chief_complaint, consultations.diagnosis, appointments.appointment_number, ' . $doctorName . ' AS doctor_name')->join('doctors', 'doctors.id = consultations.doctor_id', 'left')->join('appointments', 'appointments.id = consultations.appointment_id', 'left')->where('consultations.patient_id', $patientId)->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.consultation_time', 'DESC')->findAll() as $consultation) {
            $summary = $consultation['diagnosis'] ?: $consultation['chief_complaint'];
            $timeline[] = ['occurred_at' => trim($consultation['consultation_date'] . ' ' . ($consultation['consultation_time'] ?? '00:00:00')), 'type' => 'Consultation', 'title' => 'Consultation ' . $consultation['consultation_number'], 'summary' => $summary, 'doctor' => $consultation['doctor_name'], 'appointment_no' => $consultation['appointment_number'], 'consultation_no' => $consultation['consultation_number'], 'url' => base_url('admin/consultations/view/' . (int) $consultation['id']), 'link_permission' => 'consultation.view'];
        }
        foreach ((new PrescriptionModel())->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.status, prescriptions.notes, consultations.consultation_number, ' . $doctorName . ' AS doctor_name')->join('consultations', 'consultations.id = prescriptions.consultation_id', 'left')->join('doctors', 'doctors.id = prescriptions.doctor_id', 'left')->where('prescriptions.patient_id', $patientId)->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->findAll() as $prescription) {
            $timeline[] = ['occurred_at' => $prescription['prescription_date'], 'type' => 'Prescription', 'title' => 'Prescription ' . $prescription['prescription_number'], 'summary' => $prescription['notes'], 'doctor' => $prescription['doctor_name'], 'consultation_no' => $prescription['consultation_number'], 'prescription_no' => $prescription['prescription_number'], 'url' => base_url('admin/prescriptions/view/' . (int) $prescription['id']), 'link_permission' => 'prescription.view'];
        }
        usort($timeline, static function (array $left, array $right): int {
            return strcmp((string) $right['occurred_at'], (string) $left['occurred_at']);
        });
        foreach ($timeline as &$record) {
            $record['can_view'] = empty($record['link_permission']) || $this->hasPermission($record['link_permission']);
        }
        unset($record);
        return $timeline;
    }
    public function register()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $validation = $this->validateRegistration();
        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        try {
            $result = $this->registrationService->register($this->getRegistrationData(), isset($this->user['id']) ? (int) $this->user['id'] : null);
            $this->audit->record('admin.patient_created', ['patient_id' => (int) $result['id'], 'clinic_id' => (int) $this->request->getPost('clinic_id')]);
            return $this->response->setJSON(['status' => true, 'msg' => 'Patient registered successfully.', 'data' => $result]);
        } catch (\Throwable $e) {
            log_message('error', 'Patient registration failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to register patient.']);
        }
    }
    public function save()
    {
        return $this->register();
    }
    public function edit(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $patient = $this->patient->find($id);
        if ($patient === null || !$this->inClinicScope((int) ($patient['clinic_id'] ?? 0))) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Patient not found.']);
        }
        $data = array_intersect_key($patient, array_flip(['id', 'clinic_id', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'email', 'country_code', 'country_code_id', 'mobile_number', 'address', 'country_id', 'state_id', 'city_id', 'locality_id', 'postal_code', 'status']));
        if (!empty($patient['clinic_id'])) {
            $clinic = (new ClinicModel())->find((int) $patient['clinic_id']);
            if ($clinic !== null) {
                $data['clinic_name'] = $clinic['clinic_name'];
            }
        }
        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $data]);
    }
    public function update(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $existingPatient = $this->patient->find($id);
        if ($existingPatient === null || !$this->inClinicScope((int) ($existingPatient['clinic_id'] ?? 0))) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Patient not found.']);
        }
        $validation = $this->validatePatient($id);
        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        try {
            $db = db_connect();
            $db->transBegin();
            $patientData = array_merge($this->getPatientData(), (new LocationService())->resolvePatientLocation($this->request->getPost()));
            $patientData['clinic_id'] = (int) $existingPatient['clinic_id'];
            if ($this->patient->update($id, $patientData) === false) {
                throw new \RuntimeException('Patient update failed.');
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Patient update transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.patient_updated', ['patient_id' => $id, 'clinic_id' => (int) $existingPatient['clinic_id'], 'status' => (int) ($patientData['status'] ?? 0)]);
            return $this->response->setJSON(['status' => true, 'msg' => 'Patient updated successfully.']);
        } catch (\Throwable $e) {
            if (isset($db) && $db->transStatus() !== false) {
                $db->transRollback();
            }
            log_message('error', 'Patient update failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to update patient.']);
        }
    }
    public function status(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $patient = $this->patient->find($id);
        if ($patient === null || !$this->inClinicScope((int) ($patient['clinic_id'] ?? 0))) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Patient not found.']);
        }
        $status = (int) $this->request->getPost('status');
        if (!in_array($status, [0, 1], true)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Invalid patient status.']);
        }
        try {
            if (!$this->patient->update($id, ['status' => $status])) {
                throw new \RuntimeException('Patient status update failed.');
            }
            $this->audit->record('admin.patient_status_changed', ['patient_id' => $id, 'clinic_id' => (int) $patient['clinic_id'], 'status' => $status]);
            return $this->response->setJSON(['status' => true, 'msg' => $status === 1 ? 'Patient activated successfully.' : 'Patient deactivated successfully.']);
        } catch (\Throwable $e) {
            log_message('error', 'Patient status update failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Patient status could not be changed safely.']);
        }
    }
    private function validatePatient(?int $patientId = null): array
    {
        $rules = ['first_name' => ['label' => 'First Name', 'rules' => 'required|max_length[100]'], 'middle_name' => ['label' => 'Middle Name', 'rules' => 'permit_empty|max_length[100]'], 'last_name' => ['label' => 'Last Name', 'rules' => 'required|max_length[100]'], 'gender' => ['label' => 'Gender', 'rules' => 'permit_empty|max_length[20]'], 'date_of_birth' => ['label' => 'Date of Birth', 'rules' => 'permit_empty|valid_date[Y-m-d]'], 'email' => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[255]'], 'country_code' => ['label' => 'Country Code', 'rules' => 'required'], 'mobile_number' => ['label' => 'Mobile Number', 'rules' => 'required|regex_match[/^[0-9][0-9 ()-]{6,19}$/]'], 'address' => ['label' => 'Address', 'rules' => 'permit_empty'], 'clinic_id' => ['label' => 'Clinic', 'rules' => 'permit_empty|integer|is_not_unique[clinics.id]'], 'country_id' => ['label' => 'Country', 'rules' => 'permit_empty|integer|is_not_unique[countries.id]'], 'state_id' => ['label' => 'State', 'rules' => 'permit_empty|integer|is_not_unique[states.id]'], 'city_id' => ['label' => 'City', 'rules' => 'permit_empty|integer|is_not_unique[cities.id]'], 'locality_id' => ['label' => 'Locality', 'rules' => 'permit_empty|integer|is_not_unique[localities.id]'], 'postal_code' => ['label' => 'Postal Code', 'rules' => 'permit_empty|max_length[20]'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]']];
        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }
        $countryCodeId = $this->countryCodeId();
        if ($countryCodeId === null) {
            return ['status' => false, 'errors' => ['country_code' => 'Select or enter a valid telephone country code.']];
        }
        $countryCode = (string) ((new Country())->find($countryCodeId)['phone_code'] ?? '');
        $mobileNumber = trim((string) $this->request->getPost('mobile_number'));
        if ($countryCode !== '' && $mobileNumber !== '') {
            $duplicateQuery = $this->patient->where('country_code', $countryCode)->where('mobile_number', $mobileNumber);
            if ($patientId !== null) {
                $duplicateQuery->where('id !=', $patientId);
            }
            if ($duplicateQuery->first() !== null) {
                return ['status' => false, 'errors' => ['mobile_number' => 'The country code and mobile number combination is already registered.']];
            }
        }
        $countryId = $this->nullableInteger('country_id');
        $stateId = $this->nullableInteger('state_id');
        $cityId = $this->nullableInteger('city_id');
        $localityId = $this->nullableInteger('locality_id');
        $errors = [];
        if ($countryId !== null && $stateId !== null) {
            $stateExists = (new State())->where('id', $stateId)->where('country_id', $countryId)->first();
            if ($stateExists === null) {
                $errors['state_id'] = 'The selected state does not belong to the selected country.';
            }
        }
        if ($stateId !== null && $cityId !== null) {
            $cityExists = (new City())->where('id', $cityId)->where('state_id', $stateId)->first();
            if ($cityExists === null) {
                $errors['city_id'] = 'The selected city does not belong to the selected state.';
            }
        }
        if ($cityId !== null && $localityId !== null && (new \Admin\Models\Location\Locality())->where('id', $localityId)->where('city_id', $cityId)->first() === null) {
            $errors['locality_id'] = 'The selected locality does not belong to the selected city.';
        }
        if ($errors !== []) {
            return ['status' => false, 'errors' => $errors];
        }
        return ['status' => true];
    }
    private function validateRegistration(): array
    {
        $rules = ['first_name' => ['label' => 'First Name', 'rules' => 'required|max_length[100]'], 'middle_name' => ['label' => 'Middle Name', 'rules' => 'permit_empty|max_length[100]'], 'last_name' => ['label' => 'Last Name', 'rules' => 'required|max_length[100]'], 'gender' => ['label' => 'Gender', 'rules' => 'permit_empty|max_length[20]'], 'date_of_birth' => ['label' => 'Date of Birth', 'rules' => 'required|valid_date[Y-m-d]'], 'email' => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[255]'], 'country_code' => ['label' => 'Country Code', 'rules' => 'required'], 'mobile_number' => ['label' => 'Mobile Number', 'rules' => 'required|regex_match[/^[0-9][0-9 ()-]{6,19}$/]'], 'address' => ['label' => 'Address', 'rules' => 'permit_empty'], 'clinic_id' => ['label' => 'Clinic', 'rules' => 'required|integer'], 'country_id' => ['label' => 'Country', 'rules' => 'permit_empty|integer|is_not_unique[countries.id]'], 'state_id' => ['label' => 'State', 'rules' => 'permit_empty|integer|is_not_unique[states.id]'], 'city_id' => ['label' => 'City', 'rules' => 'permit_empty|integer|is_not_unique[cities.id]'], 'locality_id' => ['label' => 'Locality', 'rules' => 'permit_empty|integer|is_not_unique[localities.id]'], 'postal_code' => ['label' => 'Postal Code', 'rules' => 'permit_empty|max_length[20]']];
        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }
        $countryCodeId = $this->countryCodeId();
        if ($countryCodeId === null) {
            return ['status' => false, 'errors' => ['country_code' => 'Select or enter a valid telephone country code.']];
        }
        $countryCode = (string) ((new Country())->find($countryCodeId)['phone_code'] ?? '');
        $mobileNumber = trim((string) $this->request->getPost('mobile_number'));
        $clinicId = (int) $this->request->getPost('clinic_id');
        $errors = [];
        $country = (new Country())->where('phone_code', $countryCode)->where('status', 1)->first();
        if ($country === null) {
            $errors['country_code'] = 'The selected country code is invalid.';
        }
        $clinic = (new ClinicModel())->where('id', $clinicId)->where('status', 1)->first();
        if ($clinic === null) {
            $errors['clinic_id'] = 'The selected clinic is invalid.';
        } elseif (!$this->inClinicScope($clinicId)) {
            $errors['clinic_id'] = 'The selected clinic is not in your permitted scope.';
        }
        $duplicate = $this->patient->where('country_code', $countryCode)->where('mobile_number', $mobileNumber)->first();
        if ($duplicate !== null) {
            $errors['mobile_number'] = 'The country code and mobile number combination is already registered.';
        }
        $countryId = $this->nullableInteger('country_id');
        $stateId = $this->nullableInteger('state_id');
        $cityId = $this->nullableInteger('city_id');
        $localityId = $this->nullableInteger('locality_id');
        if ($countryId !== null && $stateId !== null) {
            $stateExists = (new State())->where('id', $stateId)->where('country_id', $countryId)->first();
            if ($stateExists === null) {
                $errors['state_id'] = 'The selected state does not belong to the selected country.';
            }
        }
        if ($stateId !== null && $cityId !== null) {
            $cityExists = (new City())->where('id', $cityId)->where('state_id', $stateId)->first();
            if ($cityExists === null) {
                $errors['city_id'] = 'The selected city does not belong to the selected state.';
            }
        }
        if ($cityId !== null && $localityId !== null && (new \Admin\Models\Location\Locality())->where('id', $localityId)->where('city_id', $cityId)->first() === null) {
            $errors['locality_id'] = 'The selected locality does not belong to the selected city.';
        }
        return $errors === [] ? ['status' => true] : ['status' => false, 'errors' => $errors];
    }
    private function getRegistrationData(): array
    {
        return ['clinic_id' => (int) $this->request->getPost('clinic_id'), 'first_name' => trim((string) $this->request->getPost('first_name')), 'middle_name' => $this->nullableString('middle_name'), 'last_name' => trim((string) $this->request->getPost('last_name')), 'gender' => $this->nullableString('gender'), 'date_of_birth' => trim((string) $this->request->getPost('date_of_birth')), 'email' => trim((string) $this->request->getPost('email')), 'country_code_id' => $this->countryCodeId(), 'country_code' => (new Country())->find($this->countryCodeId())['phone_code'] ?? null, 'mobile_normalized' => preg_replace('/[^0-9]/', '', (string) $this->request->getPost('mobile_number')), 'mobile_number' => trim((string) $this->request->getPost('mobile_number')), 'address' => $this->nullableString('address'), 'country_id' => $this->nullableInteger('country_id'), 'state_id' => $this->nullableInteger('state_id'), 'city_id' => $this->nullableInteger('city_id'), 'locality_id' => $this->nullableInteger('locality_id'), 'country_name' => $this->nullableString('country_name'), 'state_name' => $this->nullableString('state_name'), 'city_name' => $this->nullableString('city_name'), 'locality_name' => $this->nullableString('locality_name'), 'postal_code' => $this->nullableString('postal_code'), 'status' => 1];
    }
    private function getPatientData(): array
    {
        return ['clinic_id' => $this->nullableInteger('clinic_id'), 'first_name' => trim((string) $this->request->getPost('first_name')), 'middle_name' => $this->nullableString('middle_name'), 'last_name' => trim((string) $this->request->getPost('last_name')), 'gender' => $this->nullableString('gender'), 'date_of_birth' => $this->nullableString('date_of_birth'), 'email' => $this->nullableString('email'), 'country_code_id' => $this->countryCodeId(), 'country_code' => (new Country())->find($this->countryCodeId())['phone_code'] ?? null, 'mobile_normalized' => preg_replace('/[^0-9]/', '', (string) $this->request->getPost('mobile_number')), 'mobile_number' => $this->nullableString('mobile_number'), 'address' => $this->nullableString('address'), 'country_id' => $this->nullableInteger('country_id'), 'state_id' => $this->nullableInteger('state_id'), 'city_id' => $this->nullableInteger('city_id'), 'locality_id' => $this->nullableInteger('locality_id'), 'country_name' => $this->nullableString('country_name'), 'state_name' => $this->nullableString('state_name'), 'city_name' => $this->nullableString('city_name'), 'locality_name' => $this->nullableString('locality_name'), 'postal_code' => $this->nullableString('postal_code'), 'status' => (int) $this->request->getPost('status')];
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
    private function countryCodeId(): ?int
    {
        $value = trim((string) $this->request->getPost('country_code'));
        $row = ctype_digit($value) ? (new Country())->where('id', (int) $value)->where('status', 1)->first() : (new Country())->where('phone_code', '+' . ltrim($value, '+'))->where('status', 1)->first();
        return $row === null ? null : (int) $row['id'];
    }
    private function clinicScope(): int
    {
        return (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
    }
    private function inClinicScope(int $clinicId): bool
    {
        return $this->clinicScope() === 0 || $this->clinicScope() === $clinicId;
    }
    private function scopedClinics(): array
    {
        $query = (new ClinicModel())->where('status', 1)->orderBy('clinic_name', 'ASC');
        if ($this->clinicScope() > 0) {
            $query->where('id', $this->clinicScope());
        }
        return $query->findAll();
    }
}
