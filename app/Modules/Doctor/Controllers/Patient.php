<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use Admin\Models\Location\Country;
use Admin\Services\PatientRegistrationService;
use Admin\Models\AdminMasterRecordModel;
use App\Services\LocationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RedirectResponse;
class Patient extends BaseController
{
    public function create(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $errors = $this->patientErrors();
        if ($errors !== []) {
            return $this->validation($errors);
        }
        try {
            $data = $this->patientData($context['clinic_id']);
            if ($this->request->getPost('create_appointment_now')) {
                $data['_appointment'] = ['doctor_id' => $context['doctor_id'], 'appointment_date' => $this->request->getPost('appointment_date'), 'appointment_time' => $this->request->getPost('appointment_time'), 'service_name' => $this->request->getPost('service_name'), 'appointment_type' => $this->request->getPost('appointment_type') ?: 'in_person', 'reason' => $this->request->getPost('appointment_reason'), 'status' => $this->request->getPost('appointment_status') ?: 'scheduled', 'created_by_type' => 'user', 'booking_source' => 'doctor'];
            }
            $result = (new PatientRegistrationService())->register($data, (int) (session('user')['id'] ?? 0), ['source_type' => 'doctor', 'source_user_id' => (int) (session('user')['id'] ?? 0)]);
            service('audit')->record('doctor.patient_created', ['patient_id' => $result['id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);
            return $this->json(200, true, 'Patient created successfully.', $result);
        } catch (\Throwable $e) {
            log_message('error', 'Doctor patient creation failed: ' . $e->getMessage());
            return $this->json(500, false, 'Patient could not be created safely.');
        }
    }
    public function update(int $patientId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $patient = $this->scopedPatient($patientId, $context);
        if ($patient === null) {
            return $this->json(404, false, 'Patient not found in the assigned clinic.');
        }
        $errors = $this->patientErrors();
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $data = array_merge($this->patientData($context['clinic_id']), (new LocationService())->resolvePatientLocation($this->request->getPost()));
        unset($data['clinic_id']);
        $postedEmail = trim((string) $this->request->getPost('email'));
        if ($postedEmail === '') {
            // The deployed patients schema requires email. The compact Doctor edit form does not edit it, so preserve it.
            $data['email'] = (string) ($patient['email'] ?? '');
        }
        if (trim((string) ($data['date_of_birth'] ?? '')) === '') {
            // Keep the existing required date for older clients that submit the compact form.
            $data['date_of_birth'] = $patient['date_of_birth'] ?? null;
        }
        $mobile = trim((string) ($data['mobile_number'] ?? ''));
        if ($mobile === '') {
            $data['country_code_id'] = $patient['country_code_id'] ?? null;
            $data['country_code'] = $patient['country_code'] ?? null;
        } else {
            $country = (new Country())->find((int) ($data['country_code_id'] ?? 0));
            if ($country === null || empty($country['phone_code'])) {
                return $this->json(422, false, 'A valid country code is required.');
            }
            $data['country_code'] = $country['phone_code'];
        }
        $data['mobile_normalized'] = $mobile === '' ? null : preg_replace('/[^0-9]/', '', $mobile);
        $db = db_connect();
        $db->transBegin();
        try {
            if (!(new \Admin\Models\PatientModel())->update($patientId, $data) || $db->transStatus() === false) {
                throw new \RuntimeException('Patient update failed.');
            }
            if (!$db->transCommit()) {
                throw new \RuntimeException('Patient update transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Doctor patient update failed: ' . $e->getMessage());
            return $this->json(500, false, 'Patient could not be updated safely.');
        }
        service('audit')->record('doctor.patient_updated', ['patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);
        return $this->json(200, true, 'Patient updated successfully.');
    }
    public function index(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $search = trim((string) $this->request->getGet('search'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        $query = db_connect()->table('patients')->select('patients.id, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.gender, patients.mobile_number, patients.status')->where('patients.clinic_id', $context['clinic_id']);
        $this->applyDoctorPatientScope($query, $context);
        if ($search !== '') {
            $query->groupStart()->like('patients.first_name', $search)->orLike('patients.middle_name', $search)->orLike('patients.last_name', $search)->orLike('patients.registration_number', $search)->groupEnd();
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $patients = $query->orderBy('patients.first_name')->orderBy('patients.last_name')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        $services = (new AdminMasterRecordModel())->where('master_type', 'services')->groupStart()->where('clinic_id', $context['clinic_id'])->orWhere('clinic_id', null)->groupEnd()->where('status', 1)->orderBy('name')->findAll();
        return view(DOCTOR_VIEW . 'pages/patients', ['patients' => $patients, 'doctor' => $context['doctor'], 'countries' => service('location')->countries(), 'services' => $services, 'filters' => ['search' => $search, 'page' => $page, 'page_size' => $pageSize], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }
    public function view(int $patientId): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null || $patientId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $patientQuery = $db->table('patients')->where('id', $patientId);
        $this->applyDoctorPatientScope($patientQuery, $context);
        $patient = $patientQuery->get()->getRowArray();
        if ($patient === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $appointments = $db->table('appointments')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->orderBy('appointment_date', 'DESC')->orderBy('appointment_time', 'DESC')->limit(25)->get()->getResultArray();
        $consultations = $db->table('consultations')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->orderBy('consultation_date', 'DESC')->limit(25)->get()->getResultArray();
        $prescriptions = $db->table('prescriptions')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->orderBy('prescription_date', 'DESC')->limit(25)->get()->getResultArray();
        service('audit')->record('doctor.patient_view', ['patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);
        return view(DOCTOR_VIEW . 'pages/patient', ['patient' => $patient, 'appointments' => $appointments, 'consultations' => $consultations, 'prescriptions' => $prescriptions, 'countries' => service('location')->countries()]);
    }
    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $userId = (int) (session('user')['id'] ?? 0);
        $user = (new User())->select('id, doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();
        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return null;
        }
        return ['user_id' => $userId, 'doctor_id' => (int) $doctor['id'], 'clinic_id' => (int) $doctor['clinic_id'], 'doctor' => $doctor];
    }
    private function scopedPatient(int $patientId, array $context): ?array
    {
        $query = db_connect()->table('patients')->where('id', $patientId);
        $this->applyDoctorPatientScope($query, $context);
        return $query->get()->getRowArray();
    }
    private function applyDoctorPatientScope($query, array $context): void
    {
        $appointmentPatients = db_connect()->table('appointments')->select('patient_id')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id']);
        $registeredPatients = db_connect()->table('patient_clinic_transfers')->select('patient_id')->where('to_clinic_id', $context['clinic_id'])->where('transferred_by', $context['user_id']);
        $query->where('patients.clinic_id', $context['clinic_id'])->groupStart()->whereIn('patients.id', $appointmentPatients)->orWhereIn('patients.id', $registeredPatients)->groupEnd();
    }
    private function patientErrors(): array
    {
        $errors = [];
        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
            $value = trim((string) $this->request->getPost($field));
            if ($value === '' || strlen($value) > 100) {
                $errors[$field] = $label . ' is required and must be 100 characters or fewer.';
            }
        }
        foreach (['middle_name' => 100, 'gender' => 20, 'mobile_number' => 30, 'email' => 255, 'address' => 1000] as $field => $max) {
            if (strlen(trim((string) $this->request->getPost($field))) > $max) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }
        foreach (['country_name', 'state_name', 'city_name', 'locality_name'] as $field) {
            if (strlen(trim((string) $this->request->getPost($field))) > 191) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }
        if (trim((string) $this->request->getPost('mobile_number')) !== '' && (int) $this->request->getPost('country_code') <= 0) {
            $errors['country_code'] = 'Country code is required when a mobile number is entered.';
        }
        if (trim((string) $this->request->getPost('mobile_number')) !== '' && $this->countryCodeId() === null) {
            $errors['country_code'] = 'Select or enter a valid telephone country code.';
        }
        if ($this->request->getPost('create_appointment_now')) {
            $date = trim((string) $this->request->getPost('appointment_date'));
            $time = trim((string) $this->request->getPost('appointment_time'));
            $service = trim((string) $this->request->getPost('service_name'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors['appointment_date'] = 'A valid appointment date is required.';
            }
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                $errors['appointment_time'] = 'A valid appointment time is required.';
            }
            if ($service === '' || strlen($service) > 150) {
                $errors['service_name'] = 'A valid service is required.';
            }
            if (!in_array((string) $this->request->getPost('appointment_status'), ['scheduled', 'confirmed'], true)) {
                $errors['appointment_status'] = 'Select a valid appointment status.';
            }
        }
        return $errors;
    }
    private function patientData(int $clinicId): array
    {
        $countryCodeId = $this->countryCodeId();
        $country = $countryCodeId === null ? null : (new Country())->find($countryCodeId);
        return ['clinic_id' => $clinicId, 'first_name' => trim((string) $this->request->getPost('first_name')), 'middle_name' => trim((string) $this->request->getPost('middle_name')) ?: null, 'last_name' => trim((string) $this->request->getPost('last_name')), 'gender' => trim((string) $this->request->getPost('gender')) ?: null, 'date_of_birth' => trim((string) $this->request->getPost('date_of_birth')) ?: null, 'email' => trim((string) $this->request->getPost('email')) ?: null, 'mobile_number' => trim((string) $this->request->getPost('mobile_number')) ?: null, 'country_code_id' => $countryCodeId, 'country_code' => $country['phone_code'] ?? null, 'mobile_normalized' => preg_replace('/[^0-9]/', '', (string) $this->request->getPost('mobile_number')), 'address' => trim((string) $this->request->getPost('address')) ?: null, 'country_id' => $this->request->getPost('country_id') ?: null, 'state_id' => $this->request->getPost('state_id') ?: null, 'city_id' => $this->request->getPost('city_id') ?: null, 'locality_id' => $this->request->getPost('locality_id') ?: null, 'postal_code' => trim((string) $this->request->getPost('postal_code')) ?: null, 'country_name' => trim((string) $this->request->getPost('country_name')) ?: null, 'state_name' => trim((string) $this->request->getPost('state_name')) ?: null, 'city_name' => trim((string) $this->request->getPost('city_name')) ?: null, 'locality_name' => trim((string) $this->request->getPost('locality_name')) ?: null, 'status' => 1];
    }
    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
    }
    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];
        if ($data !== []) {
            $payload['data'] = $data;
        }
        return $this->response->setStatusCode($code)->setJSON($payload);
    }
    private function countryCodeId(): ?int
    {
        $value = trim((string) $this->request->getPost('country_code'));
        if ($value === '') {
            return null;
        }
        $countryModel = new Country();
        $row = ctype_digit($value)
            ? $countryModel->where('id', (int) $value)->where('status', 1)->first()
            : $countryModel->where('phone_code', ltrim($value, '+'))->where('status', 1)->first();
        return $row === null ? null : (int) $row['id'];
    }
}
