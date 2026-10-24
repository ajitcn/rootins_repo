<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use Admin\Models\Location\TelephoneCountryCode;
use Admin\Services\PatientRegistrationService;
use Admin\Models\Configuration\ServiceModel;
use App\Services\LocationService;
use App\Controllers\BaseController;
use App\Services\Shopify\ShopifyConfiguration;
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
                $data['_appointment'] = ['doctor_id' => $context['doctor_id'], 'appointment_date' => $this->normalizeDate($this->request->getPost('appointment_date')), 'appointment_time' => rootins_parse_time($this->request->getPost('appointment_time')) ?? '', 'service_name' => $this->request->getPost('service_name'), 'appointment_type' => $this->request->getPost('appointment_type') ?: 'in_person', 'reason' => $this->request->getPost('appointment_reason'), 'status' => $this->request->getPost('appointment_status') ?: 'scheduled', 'created_by_type' => 'user', 'booking_source' => 'doctor'];
            }
            $result = (new PatientRegistrationService())->register($data, (int) (session('user')['id'] ?? 0), ['source_type' => 'self', 'source_user_id' => (int) (session('user')['id'] ?? 0)]);
            service('audit')->record('doctor.patient_created', ['patient_id' => $result['id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

            return $this->json(200, true, 'Patient created successfully.', $result);
        } catch (\InvalidArgumentException $e) {
            log_message('warning', 'Doctor patient creation validation failed: ' . $e->getMessage());

            return $this->validation($this->exceptionErrors($e));
        } catch (\Throwable $e) {
            log_message('error', 'Doctor patient creation failed: ' . $e->getMessage() . ' context=' . json_encode([
                'country_code_present' => trim((string) $this->request->getPost('country_code')) !== '',
                'country_code_length' => strlen(trim((string) $this->request->getPost('country_code'))),
                'country_code_manual' => (string) $this->request->getPost('country_code_manual') === '1',
                'mobile_present' => trim((string) $this->request->getPost('mobile_number')) !== '',
                'appointment_requested' => (bool) $this->request->getPost('create_appointment_now'),
            ], JSON_THROW_ON_ERROR));

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
        $db = db_connect();
        $db->transBegin();

        try {
            $data = array_merge(
                $this->patientData($context['clinic_id']),
                (new LocationService())->resolvePatientLocation($this->request->getPost()),
            );
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
                // An omitted mobile means no contact change. Preserve both
                // telephone references and the legacy display value.
                $data['country_code_id'] = $patient['country_code_id'] ?? null;
                $data['telephone_country_code_id'] = $patient['telephone_country_code_id'] ?? null;
                $data['country_code'] = $patient['country_code'] ?? null;
                $data['mobile_normalized'] = $patient['mobile_normalized'] ?? null;
            } else {
                $country = (new PatientRegistrationService())->canonicalizeTelephoneCountryCode($data);

                if ($country === null || empty($country['normalized_code'])) {
                    throw new \InvalidArgumentException('A valid country code is required.');
                }
                $data['telephone_country_code_id'] = (int) $country['id'];
                $data['country_code_id'] = $country['country_id'] !== null
                    ? (int) $country['country_id']
                    : null;
                $data['country_code'] = (string) $country['normalized_code'];
                $data['mobile_normalized'] = preg_replace('/[^0-9]/', '', $mobile);
            }

            if (!(new \Admin\Models\PatientModel())->update($patientId, $data) || $db->transStatus() === false) {
                throw new \RuntimeException('Patient update failed.');
            }

            if (!$db->transCommit()) {
                throw new \RuntimeException('Patient update transaction failed.');
            }
        } catch (\InvalidArgumentException $e) {
            $db->transRollback();
            log_message('warning', 'Doctor patient update validation failed: ' . $e->getMessage());

            return $this->validation($this->exceptionErrors($e));
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
        [$createdFrom, $createdFromError] = $this->patientFilterDate('created_from');
        [$createdTo, $createdToError] = $this->patientFilterDate('created_to');
        $filters = [
            'search' => $search,
            'patient_name' => trim((string) $this->request->getGet('patient_name')),
            'mobile_number' => trim((string) $this->request->getGet('mobile_number')),
            'source_type' => $this->patientFilterValue('source_type', ['self', 'shopify', 'unknown']),
            'status' => $this->patientFilterValue('status', ['0', '1']),
            'country_id' => $this->patientFilterId('country_id'),
            'state_id' => $this->patientFilterId('state_id'),
            'city_id' => $this->patientFilterId('city_id'),
            'locality_id' => $this->patientFilterId('locality_id'),
            'created_from' => trim((string) $this->request->getGet('created_from')),
            'created_to' => trim((string) $this->request->getGet('created_to')),
            'page_size' => $pageSize,
        ];
        $filterErrors = [];

        if ($createdFromError !== null) {
            $filterErrors['created_from'] = $createdFromError;
        }

        if ($createdToError !== null) {
            $filterErrors['created_to'] = $createdToError;
        }

        if ($createdFrom !== null && $createdTo !== null && $createdFrom > $createdTo) {
            $filterErrors['created_from'] = 'Start date cannot be after end date.';
        }

        $query = db_connect()->table('patients')
            ->select('patients.id, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.gender, patients.email, patients.mobile_number, patients.mobile_normalized, patients.country_code, telephone_country_codes.normalized_code AS telephone_country_code, patients.status, patients.source_type, patients.created_at, shopify_patient_identity_links.shopify_customer_id')
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->join('shopify_patient_identity_links', "shopify_patient_identity_links.patient_id = patients.id AND shopify_patient_identity_links.link_status = 'linked'", 'left');
        $this->applyDoctorPatientScope($query, $context);
        $query->groupBy('patients.id');

        if ($search !== '') {
            $query->groupStart()->like('patients.first_name', $search)->orLike('patients.middle_name', $search)->orLike('patients.last_name', $search)->orLike('patients.registration_number', $search)->orLike('patients.email', $search)->orLike('shopify_patient_identity_links.shopify_customer_id', $search)->groupEnd();
        }

        if ($filters['patient_name'] !== '') {
            $query->groupStart()->like('patients.first_name', $filters['patient_name'])->orLike('patients.middle_name', $filters['patient_name'])->orLike('patients.last_name', $filters['patient_name'])->orLike('patients.email', $filters['patient_name'])->orLike('shopify_patient_identity_links.shopify_customer_id', $filters['patient_name'])->groupEnd();
        }

        if ($filters['mobile_number'] !== '') {
            $mobileSearch = preg_replace('/[^0-9]+/', '', $filters['mobile_number']);
            $query->groupStart()->like('patients.mobile_number', $filters['mobile_number'])->orLike('patients.mobile_normalized', $mobileSearch !== '' ? $mobileSearch : $filters['mobile_number'])->groupEnd();
        }

        if ($filters['source_type'] === 'unknown') {
            $query->groupStart()->where('patients.source_type IS NULL', null, false)->orWhereNotIn('patients.source_type', ['self', 'shopify'])->groupEnd();
        } elseif ($filters['source_type'] !== '') {
            $query->where('patients.source_type', $filters['source_type']);
        }

        if ($filters['status'] !== '') {
            $query->where('patients.status', (int) $filters['status']);
        }

        foreach (['country_id', 'state_id', 'city_id', 'locality_id'] as $locationField) {
            if ($filters[$locationField] > 0) {
                $query->where('patients.' . $locationField, $filters[$locationField]);
            }
        }

        if ($createdFrom !== null && $createdFromError === null) {
            $query->where('patients.created_at >=', $createdFrom . ' 00:00:00');
        }

        if ($createdTo !== null && $createdToError === null) {
            $query->where('patients.created_at <=', $createdTo . ' 23:59:59');
        }

        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $patients = $query->orderBy('patients.created_at', 'DESC')->orderBy('patients.id', 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        $location = service('location');
        $countryId = $filters['country_id'];
        $stateId = $filters['state_id'];
        $cityId = $filters['city_id'];
        $services = (new ServiceModel())->forScope($context['clinic_id'])->where('status', 1)->orderBy('name')->findAll();

        return view(DOCTOR_VIEW . 'pages/patients', [
            'patients' => $patients,
            'doctor' => $context['doctor'],
            'countries' => $location->countries(),
            'telephoneCodes' => $location->telephoneCodes(),
            'services' => $services,
            'filterCountries' => $location->countries(),
            'filterStates' => $countryId > 0 ? $location->states($countryId) : [],
            'filterCities' => $stateId > 0 ? $location->cities($stateId) : [],
            'filterLocalities' => $cityId > 0 ? $location->localities($cityId) : [],
            'filters' => $filters + ['page' => $page],
            'filterErrors' => $filterErrors,
            'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total],
        ]);
    }

    public function view(int $patientId): string|RedirectResponse
    {
        $context = $this->context();

        if ($context === null || $patientId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $patientQuery = $db->table('patients')
            ->select('patients.*, telephone_country_codes.normalized_code AS telephone_country_code, COALESCE(telephone_code_countries.name, telephone_country_codes.label) AS telephone_country_name')
            ->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')
            ->join('countries AS telephone_code_countries', 'telephone_code_countries.id = telephone_country_codes.country_id', 'left')
            ->where('patients.id', $patientId);
        $this->applyDoctorPatientScope($patientQuery, $context);
        $patient = $patientQuery->get()->getRowArray();

        if ($patient === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $appointments = $db->table('appointments')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->orderBy('appointment_date', 'DESC')->orderBy('appointment_time', 'DESC')->limit(25)->get()->getResultArray();
        $consultations = $db->table('consultations')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->orderBy('consultation_date', 'DESC')->limit(25)->get()->getResultArray();
        $prescriptions = $db->table('prescriptions')->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->orderBy('prescription_date', 'DESC')->limit(25)->get()->getResultArray();
        service('audit')->record('doctor.patient_view', ['patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

        return view(DOCTOR_VIEW . 'pages/patient', ['patient' => $patient, 'appointments' => $appointments, 'consultations' => $consultations, 'prescriptions' => $prescriptions, 'countries' => service('location')->countries(), 'telephoneCodes' => service('location')->telephoneCodes()]);
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
        $db = db_connect();
        $appointmentPatients = $db->table('appointments')->select('patient_id')->where('doctor_id', $context['doctor_id']);
        $consultationPatients = $db->table('consultations')->select('patient_id')->where('doctor_id', $context['doctor_id']);
        $prescriptionPatients = $db->table('prescriptions')->select('patient_id')->where('doctor_id', $context['doctor_id']);
        $registeredPatients = $db->table('patient_clinic_transfers')->select('patient_id')->where('to_clinic_id', $context['clinic_id'])->where('transferred_by', $context['user_id']);
        if ($db->fieldExists('status', 'patients')) $query->where('patients.status', 1);
        if ($db->fieldExists('deleted_at', 'patients')) $query->where('patients.deleted_at', null);
        $shopifyPatientIds = $this->shopifyVisiblePatientIds($context);
        $query->groupStart()
            ->groupStart()->where('patients.clinic_id', $context['clinic_id'])->orWhereIn('patients.id', $shopifyPatientIds)->groupEnd()
            ->groupStart()->whereIn('patients.id', $appointmentPatients)->orWhereIn('patients.id', $consultationPatients)->orWhereIn('patients.id', $prescriptionPatients)->orWhereIn('patients.id', $registeredPatients)->orWhereIn('patients.id', $shopifyPatientIds)->groupEnd()
            ->groupEnd();
    }

    private function shopifyVisiblePatientIds(array $context)
    {
        $db = db_connect();
        $shopifyClinic = (new ShopifyConfiguration())->clinicStatus();
        if ($db->tableExists('shopify_patient_identity_links')) {
            if ($shopifyClinic['status'] === 'active' && (int) $shopifyClinic['id'] === (int) $context['clinic_id']) {
                return $db->table('shopify_patient_identity_links')->select('patient_id')->where('link_status', 'linked')->where('patient_id IS NOT NULL', null, false);
            }

            return $db->table('patients')->select('id')->where('id', 0);
        }
        if ($db->tableExists('shopify_order_imports')) return $db->table('shopify_order_imports')->select('patient_id')->where('clinic_id', $context['clinic_id'])->where('patient_id IS NOT NULL', null, false);
        return $db->table('patients')->select('id')->where('id', 0);
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

        foreach (['gender' => 20, 'email' => 255, 'postal_code' => 20, 'address' => 1000] as $field => $max) {
            if (strlen(trim((string) $this->request->getPost($field))) > $max) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }

        foreach (['country_name', 'state_name', 'city_name', 'locality_name', 'telephone_country_name'] as $field) {
            if (strlen(trim((string) $this->request->getPost($field))) > 191) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }

        foreach (['country_id', 'state_id', 'city_id', 'locality_id'] as $field) {
            $value = trim((string) $this->request->getPost($field));

            if ($value !== '' && (! ctype_digit($value) || (int) $value < 1)) {
                $errors[$field] = 'Select a valid ' . str_replace('_id', '', str_replace('_', ' ', $field)) . '.';
            }
        }
        $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));

        if ($dateOfBirth === '') {
            $errors['date_of_birth'] = 'Date of birth is required.';
        } else {
            $normalizedDateOfBirth = $this->normalizeDate($dateOfBirth);

            if ($normalizedDateOfBirth === null) {
                $errors['date_of_birth'] = 'Enter a valid date of birth as dd-mm-yyyy.';
            } elseif ($normalizedDateOfBirth > date('Y-m-d')) {
                $errors['date_of_birth'] = 'Date of birth cannot be in the future.';
            }
        }
        $mobile = trim((string) $this->request->getPost('mobile_number'));

        try {
            PatientRegistrationService::normalizeRequiredMobileNumber($mobile);
        } catch (\InvalidArgumentException $e) {
            $errors['mobile_number'] = $e->getMessage();
        }
        $countryCode = trim((string) $this->request->getPost('country_code'));
        $manualCountryCode = (string) $this->request->getPost('country_code_manual') === '1';

        if ($manualCountryCode) {
            $normalizedCountryCode = preg_replace('/^\+/', '', $countryCode);

            if (! preg_match('/^[1-9][0-9]{0,3}$/', $normalizedCountryCode)) {
                $errors['country_code'] = 'Enter a valid numeric country code of 1 to 4 digits.';
            }

            if (trim((string) $this->request->getPost('telephone_country_name')) === '') {
                $errors['telephone_country_name'] = 'Country name is required when entering a code manually.';
            }
        } elseif ($mobile !== ''
            || trim((string) $this->request->getPost('telephone_country_code_id')) !== ''
            || $countryCode !== '') {
            if ($this->countryCodeId() === null) {
                $errors['telephone_country_code_id'] = 'Select a valid telephone country code.';
            }
        }

        if ($this->request->getPost('create_appointment_now')) {
            $date = trim((string) $this->request->getPost('appointment_date'));
            $time = rootins_parse_time($this->request->getPost('appointment_time')) ?? '';
            $service = trim((string) $this->request->getPost('service_name'));

            if ($date === '') {
                $errors['appointment_date'] = 'Appointment date is required.';
            } elseif ($this->normalizeDate($date) === null) {
                $errors['appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
            } elseif ($this->normalizeDate($date) < date('Y-m-d')) {
                $errors['appointment_date'] = 'Appointment date cannot be in the past.';
            }

            if ($time === '') {
                $errors['appointment_time'] = 'A valid appointment time is required.';
            }

            if ($service === '' || strlen($service) > 150) {
                $errors['service_name'] = 'A valid service is required.';
            }

            if (strlen(trim((string) $this->request->getPost('appointment_reason'))) > 1000) {
                $errors['appointment_reason'] = 'Appointment reason is too long.';
            }

            if (!in_array((string) $this->request->getPost('appointment_status'), ['scheduled', 'confirmed'], true)) {
                $errors['appointment_status'] = 'Select a valid appointment status.';
            }
        }

        return $errors;
    }

    private function patientData(int $clinicId): array
    {
        $telephoneCountryCodeId = $this->countryCodeId();
        $telephoneCode = $telephoneCountryCodeId === null
            ? null
            : (new TelephoneCountryCode())->find($telephoneCountryCodeId);
        $postedCountryCode = trim((string) $this->request->getPost('country_code'));
        $manualCountryCode = (string) $this->request->getPost('country_code_manual') === '1';
        $countryCode = $telephoneCode['normalized_code']
            ?? ($manualCountryCode && $postedCountryCode !== ''
                ? preg_replace('/^\+/', '', $postedCountryCode)
                : null);

        return ['clinic_id' => $clinicId, 'first_name' => trim((string) $this->request->getPost('first_name')), 'last_name' => trim((string) $this->request->getPost('last_name')), 'gender' => trim((string) $this->request->getPost('gender')) ?: null, 'date_of_birth' => $this->normalizeDate($this->request->getPost('date_of_birth')), 'email' => trim((string) $this->request->getPost('email')) ?: null, 'mobile_number' => trim((string) $this->request->getPost('mobile_number')) ?: null, 'country_code_id' => $telephoneCode['country_id'] ?? null, 'telephone_country_code_id' => $telephoneCountryCodeId, 'country_code' => $countryCode, 'telephone_country_name' => $manualCountryCode ? trim((string) $this->request->getPost('telephone_country_name')) : null, 'mobile_normalized' => preg_replace('/[^0-9]/', '', (string) $this->request->getPost('mobile_number')), 'address' => trim((string) $this->request->getPost('address')) ?: null, 'country_id' => $this->request->getPost('country_id') ?: null, 'state_id' => $this->request->getPost('state_id') ?: null, 'city_id' => $this->request->getPost('city_id') ?: null, 'locality_id' => $this->request->getPost('locality_id') ?: null, 'postal_code' => trim((string) $this->request->getPost('postal_code')) ?: null, 'country_name' => trim((string) $this->request->getPost('country_name')) ?: null, 'state_name' => trim((string) $this->request->getPost('state_name')) ?: null, 'city_name' => trim((string) $this->request->getPost('city_name')) ?: null, 'locality_name' => trim((string) $this->request->getPost('locality_name')) ?: null, 'status' => 1];
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    }

    /**
     * Convert known service validation failures into the exact form keys used
     * by the Doctor patient create/update forms. Unexpected throwables remain
     * on the safe generic-error path in the public actions.
     *
     * @return array<string, string>
     */
    private function exceptionErrors(\InvalidArgumentException $exception): array
    {
        $message = $exception->getMessage();
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'mobile number')) {
            return ['mobile_number' => $message];
        }

        if (str_contains($lowerMessage, 'date of birth')) {
            return ['date_of_birth' => $message];
        }

        if (str_contains($lowerMessage, 'country code')) {
            return [$this->countryCodeErrorKey() => $message];
        }

        foreach (['country', 'state', 'city', 'locality'] as $field) {
            if (str_contains($lowerMessage, $field)) {
                return [$field . (str_contains($lowerMessage, 'selected') || str_contains($lowerMessage, 'parent') ? '_id' : '_name') => $message];
            }
        }

        if (str_contains($lowerMessage, 'appointment date')) {
            return ['appointment_date' => $message];
        }

        if (str_contains($lowerMessage, 'appointment time') || str_contains($lowerMessage, 'appointment details')) {
            return ['appointment_date' => $message, 'appointment_time' => $message];
        }

        if (str_contains($lowerMessage, 'doctor')) {
            return ['appointment_doctor_id' => $message];
        }

        if (str_contains($lowerMessage, 'service')) {
            return ['service_name' => $message];
        }

        if (str_contains($lowerMessage, 'patient source')) {
            return ['source_type' => $message];
        }

        return ['form_error' => $message];
    }

    private function countryCodeErrorKey(): string
    {
        return (string) $this->request->getPost('country_code_manual') === '1'
            ? 'country_code'
            : 'telephone_country_code_id';
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
        $selectedId = trim((string) $this->request->getPost('telephone_country_code_id'));
        $manual = (string) $this->request->getPost('country_code_manual') === '1';

        if ($manual && $value === '') {
            return null;
        }

        if (! $manual && $selectedId === '' && $value === '') {
            return null;
        }
        $normalizedCode = preg_replace('/^\+/', '', $value);

        if ($manual) {
            if (! preg_match('/^[1-9][0-9]{0,3}$/', $normalizedCode)) {
                return null;
            }
            $row = (new TelephoneCountryCode())
                ->where('normalized_code', $normalizedCode)
                ->where('status', 1)
                ->first();
        } else {
            if ($selectedId === '' && ctype_digit($value)) {
                $selectedId = $value;
            }
            $row = ctype_digit($selectedId)
                ? (new TelephoneCountryCode())
                    ->where('id', (int) $selectedId)
                    ->where('status', 1)
                    ->first()
                : null;
        }

        return $row === null ? null : (int) $row['id'];
    }

    private function patientFilterId(string $field): int
    {
        $value = trim((string) $this->request->getGet($field));

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
    }

    private function patientFilterValue(string $field, array $allowed): string
    {
        $value = trim((string) $this->request->getGet($field));

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function patientFilterDate(string $field): array
    {
        $value = trim((string) $this->request->getGet($field));

        if ($value === '') {
            return [null, null];
        }

        $normalized = $this->normalizeDate($value);

        if ($normalized === null) {
            return [null, 'Enter a valid date as dd-mm-yyyy.'];
        }

        return [$normalized, null];
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $dateErrors = \DateTimeImmutable::getLastErrors();

        if ($date !== false && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }

        return null;
    }
}
