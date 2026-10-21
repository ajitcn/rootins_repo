<?php

namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;
use Admin\Models\Doctor\DoctorModel;
use Admin\Models\Doctor\DoctorSpecializationMapModel;
use Admin\Models\Doctor\DoctorLanguageMapModel;
use Admin\Models\Doctor\DoctorTreatmentMapModel;
use Admin\Models\Clinic\ClinicModel;
use Admin\Models\Location\City;
use Admin\Models\Location\TelephoneCountryCode;
use Admin\Models\Master\DoctorSpecializationModel;
use Admin\Models\Master\DoctorLanguageModel;
use Admin\Models\Master\PopularTreatmentModel;

class Doctor extends BaseAdminController
{
    protected DoctorModel $doctor;

    protected City $city;

    protected ClinicModel $clinic;

    protected DoctorSpecializationModel $specialization;

    protected DoctorSpecializationMapModel $doctorSpecializationMap;

    protected DoctorLanguageMapModel $doctorLanguageMap;

    protected DoctorTreatmentMapModel $doctorTreatmentMap;

    protected DoctorLanguageModel $language;

    protected PopularTreatmentModel $popularTreatment;

    public function __construct()
    {
        $this->doctor = new DoctorModel();
        $this->city = new City();
        $this->clinic = new ClinicModel();
        $this->specialization = new DoctorSpecializationModel();
        $this->doctorSpecializationMap = new DoctorSpecializationMapModel();
        $this->doctorLanguageMap = new DoctorLanguageMapModel();
        $this->doctorTreatmentMap = new DoctorTreatmentMapModel();
        $this->language = new DoctorLanguageModel();
        $this->popularTreatment = new PopularTreatmentModel();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $statusValue = (string) $this->request->getGet('status');
        $status = $statusValue === '0' || $statusValue === '1' ? (int) $statusValue : null;
        $requestedPageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($requestedPageSize, [10, 15, 25, 50], true) ? $requestedPageSize : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        $clinicScope = $this->clinicScope();
        $list = $this->doctor->getDoctorsPage($search, $status, $page, $pageSize, $clinicScope);
        $data['doctors'] = $list['records'];
        $data['pagination'] = $list['pagination'];
        $data['filters'] = ['search' => $search, 'status' => $statusValue, 'page_size' => $pageSize];
        //clinic list
        $clinicQuery = $this->clinic->where('status', 1);

        if ($clinicScope > 0) {
            $clinicQuery->where('id', $clinicScope);
        }
        $data['clinics'] = $clinicQuery->orderBy('clinic_name', 'ASC')->findAll();
        //specialization list
        $data['specializations'] = $this->specialization->dropdown();
        $data['languages'] = $this->language->dropdown();
        $data['popularTreatments'] = $this->popularTreatment->dropdown();
        $data['cities'] = $this->city->dropdown();
        $data['telephoneCodes'] = (new TelephoneCountryCode())->dropdown();

        return view(ADMIN_VIEW . 'pages/doctor/index', $data);
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
        $validation = $this->_doctorValidation();

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $doctorData = ['clinic_id' => (int) $this->request->getPost('clinic_id'), 'city_id' => (int) $this->request->getPost('city_id'), 'doctor_code' => trim($this->request->getPost('doctor_code')), 'first_name' => trim($this->request->getPost('first_name')), 'last_name' => trim($this->request->getPost('last_name')), 'gender' => $this->request->getPost('gender'), 'dob' => $this->request->getPost('dob'), 'qualification' => trim($this->request->getPost('qualification')), 'experience' => (int) $this->request->getPost('experience'), 'registration_number' => trim($this->request->getPost('registration_number')), 'mobile' => trim($this->request->getPost('mobile')), 'email' => trim($this->request->getPost('email')), 'address' => trim($this->request->getPost('address')), 'consultation_duration' => (int) $this->request->getPost('consultation_duration'), 'notes' => trim($this->request->getPost('notes')), 'online_booking' => $this->request->getPost('online_booking') ? 1 : 0, 'display_order' => (int) $this->request->getPost('display_order'), 'status' => (int) $this->request->getPost('status')];
        $telephoneCode = $this->telephoneCode();
        $doctorData['telephone_country_code_id'] = $telephoneCode['id'];
        $doctorData['country_code_id'] = $telephoneCode['country_id'] ?? null;
        $doctorData['mobile_normalized'] = service('telephoneContact')->normalizedMobile($doctorData['mobile']);
        $specializations = $this->normaliseIds($this->request->getPost('specialization_ids'));
        $languages = $this->normaliseIds($this->request->getPost('language_ids'));
        $treatments = $this->normaliseIds($this->request->getPost('treatment_ids'));
        $scopeError = $this->validateAssignments((int) $doctorData['clinic_id'], (int) $doctorData['city_id'], $specializations);

        if ($scopeError !== null) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $scopeError]);
        }
        $masterError = $this->validateDoctorMasters($languages, $treatments);
        if ($masterError !== null) return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $masterError]);
        $db = db_connect();
        $db->transBegin();

        try {
            if (!$this->doctor->insert($doctorData)) {
                throw new \RuntimeException('Doctor create failed.');
            }
            $doctorId = $this->doctor->getInsertID();

            foreach ($specializations as $specializationId) {
                $this->doctorSpecializationMap->insert(['doctor_id' => $doctorId, 'specialization_id' => $specializationId]);
            }
            $this->replaceDoctorMaps($doctorId, $languages, $treatments);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save doctor.');
            }
            $db->transCommit();
            $this->audit->record('admin.doctor_created', ['doctor_id' => $doctorId, 'clinic_id' => (int) $doctorData['clinic_id']]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Doctor created successfully.']);
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->response->setJSON(['status' => false, 'msg' => 'Doctor could not be created safely.']);
        }
    }

    /**
     *
     * Doctor validation.
     *
     */
    private function _doctorValidation($doctorId = null)
    {
        $uniqueDoctorCode = $doctorId ? 'required|max_length[20]|is_unique[doctors.doctor_code,id,' . $doctorId . ']' : 'required|max_length[20]|is_unique[doctors.doctor_code]';
        $uniqueRegistration = $doctorId ? 'permit_empty|max_length[100]|is_unique[doctors.registration_number,id,' . $doctorId . ']' : 'permit_empty|max_length[100]|is_unique[doctors.registration_number]';
        $rules = ['clinic_id' => ['label' => 'Clinic', 'rules' => 'required|is_natural_no_zero'], 'city_id' => ['label' => 'City', 'rules' => 'required|is_natural_no_zero'], 'specialization_ids' => ['label' => 'Specialization', 'rules' => 'required'], 'doctor_code' => ['label' => 'Doctor Code', 'rules' => $uniqueDoctorCode], 'first_name' => ['label' => 'First Name', 'rules' => 'required|max_length[100]'], 'last_name' => ['label' => 'Last Name', 'rules' => 'permit_empty|max_length[100]'], 'mobile' => ['label' => 'Mobile Number', 'rules' => 'required|numeric|min_length[10]|max_length[15]'], 'telephone_country_code_id' => ['label' => 'Country Code', 'rules' => 'permit_empty|integer'], 'country_code' => ['label' => 'Country Code', 'rules' => 'permit_empty|max_length[5]'], 'telephone_country_name' => ['label' => 'Country Name', 'rules' => 'permit_empty|max_length[191]'], 'email' => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[150]'], 'registration_number' => ['label' => 'Registration Number', 'rules' => $uniqueRegistration], 'experience' => ['label' => 'Experience', 'rules' => 'permit_empty|integer'], 'consultation_duration' => ['label' => 'Consultation Duration', 'rules' => 'required|integer'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]']];

        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }

        if ((string) $this->request->getPost('country_code_manual') === '1' && trim((string) $this->request->getPost('telephone_country_name')) === '') {
            return ['status' => false, 'errors' => ['telephone_country_name' => 'Country name is required when entering a code manually.']];
        }

        if ($this->telephoneCode() === null) {
            return ['status' => false, 'errors' => ['country_code' => 'Select or enter a valid telephone country code.']];
        }

        return ['status' => true];
    }

    /**
     *-------------------------------------------------------------------
     * Doctor data by doctor ID
     *-------------------------------------------------------------------.
     */
    public function doctorById($doctorId)
    {
        $doctor = $this->doctor->select('doctors.*, telephone_country_codes.normalized_code AS telephone_country_code, COALESCE(countries.name, telephone_country_codes.label) AS telephone_country_name')->join('telephone_country_codes', 'telephone_country_codes.id = doctors.telephone_country_code_id', 'left')->join('countries', 'countries.id = telephone_country_codes.country_id', 'left')->find($doctorId);

        if ($doctor === null || !$this->inClinicScope((int) $doctor['clinic_id'])) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Data not found!']);
        }
        $specialization_ids = $this->doctorSpecializationMap->getDoctorSpecializations($doctorId);
        $language_ids = $this->doctorLanguageMap->getDoctorLanguages((int) $doctorId);
        $treatment_ids = $this->doctorTreatmentMap->getDoctorTreatments((int) $doctorId);

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => ['doctor' => $doctor, 'specialization_ids' => $specialization_ids, 'language_ids' => $language_ids, 'treatment_ids' => $treatment_ids]]);
    }

    public function view(int $doctorId)
    {
        $record = $this->doctor->findDoctorWithDetails($doctorId, $this->clinicScope());

        if ($record === null) {
            return redirect()->to(base_url('admin/doctors'))->with('error', 'Doctor not found.');
        }

        return view(ADMIN_VIEW . 'pages/doctor/view', ['doctor' => $record, 'specializations' => $this->doctorSpecializationMap->getDoctorSpecializations($doctorId)]);
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
        $doctorId = (int) $this->request->getPost('doctor_id');
        $existing = $this->doctor->find($doctorId);

        if ($existing === null || !$this->inClinicScope((int) $existing['clinic_id'])) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Doctor not found.']);
        }
        $validation = $this->_doctorValidation($doctorId);

        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $doctorData = ['clinic_id' => (int) $this->request->getPost('clinic_id'), 'city_id' => (int) $this->request->getPost('city_id'), 'doctor_code' => trim($this->request->getPost('doctor_code')), 'first_name' => trim($this->request->getPost('first_name')), 'last_name' => trim($this->request->getPost('last_name')), 'gender' => $this->request->getPost('gender'), 'dob' => $this->request->getPost('dob'), 'qualification' => trim($this->request->getPost('qualification')), 'experience' => (int) $this->request->getPost('experience'), 'registration_number' => trim($this->request->getPost('registration_number')), 'mobile' => trim($this->request->getPost('mobile')), 'email' => trim($this->request->getPost('email')), 'address' => trim($this->request->getPost('address')), 'consultation_duration' => (int) $this->request->getPost('consultation_duration'), 'notes' => trim($this->request->getPost('notes')), 'online_booking' => $this->request->getPost('online_booking') ? 1 : 0, 'display_order' => (int) $this->request->getPost('display_order'), 'status' => (int) $this->request->getPost('status')];
        $telephoneCode = $this->telephoneCode();
        $doctorData['telephone_country_code_id'] = $telephoneCode['id'];
        $doctorData['country_code_id'] = $telephoneCode['country_id'] ?? null;
        $doctorData['mobile_normalized'] = service('telephoneContact')->normalizedMobile($doctorData['mobile']);
        $specializationIds = $this->normaliseIds($this->request->getPost('specialization_ids'));
        $languages = $this->normaliseIds($this->request->getPost('language_ids'));
        $treatments = $this->normaliseIds($this->request->getPost('treatment_ids'));
        $scopeError = $this->validateAssignments((int) $doctorData['clinic_id'], (int) $doctorData['city_id'], $specializationIds);

        if ($scopeError !== null) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $scopeError]);
        }
        $masterError = $this->validateDoctorMasters($languages, $treatments);
        if ($masterError !== null) return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $masterError]);
        $db = db_connect();
        $db->transBegin();

        try {
            // Update doctor
            if (!$this->doctor->update($doctorId, $doctorData)) {
                throw new \RuntimeException('Doctor update failed.');
            }
            // Delete old mappings
            $this->doctorSpecializationMap->where('doctor_id', $doctorId)->delete();

            // Insert new mappings
            foreach ($specializationIds as $specializationId) {
                $this->doctorSpecializationMap->insert(['doctor_id' => $doctorId, 'specialization_id' => $specializationId]);
            }
            $languageIdsProvided = $this->request->getPost('language_ids_present') !== null;
            $treatmentIdsProvided = $this->request->getPost('treatment_ids_present') !== null;
            if ($languageIdsProvided && $this->sortedIds($languages) !== $this->sortedIds($this->doctorLanguageMap->getDoctorLanguages($doctorId))) {
                $this->replaceDoctorLanguages($doctorId, $languages);
            }
            if ($treatmentIdsProvided && $this->sortedIds($treatments) !== $this->sortedIds($this->doctorTreatmentMap->getDoctorTreatments($doctorId))) {
                $this->replaceDoctorTreatments($doctorId, $treatments);
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Unable to update doctor.');
            }
            $db->transCommit();
            $this->audit->record('admin.doctor_updated', ['doctor_id' => $doctorId, 'clinic_id' => (int) $doctorData['clinic_id'], 'status' => (int) $doctorData['status']]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Doctor updated successfully.']);
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->response->setJSON(['status' => false, 'msg' => 'Doctor could not be updated safely.']);
        }
    }

    /**
     *----------------------------------------------------
     * delete
     *---------------------------------------------------.
     */
    public function delete($doctorId)
    {
        $doctor = $this->doctor->find($doctorId);

        if (!$doctor || !$this->inClinicScope((int) $doctor['clinic_id'])) {
            session()->setFlashdata(['status' => false, 'msg' => 'Doctor not found.']);

            return redirect()->to('admin/doctors');
        }

        if (!$this->doctor->update($doctorId, ['status' => 0])) {
            session()->setFlashdata(['status' => false, 'msg' => 'Doctor could not be deactivated safely.']);

            return redirect()->to('admin/doctors');
        }
        $this->audit->record('admin.doctor_status_changed', ['doctor_id' => $doctorId, 'clinic_id' => (int) $doctor['clinic_id'], 'status' => 0]);
        session()->setFlashdata(['status' => true, 'msg' => 'Doctor deactivated successfully.']);

        return redirect()->to('admin/doctors');
    }

    private function clinicScope(): int
    {
        return (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
    }

    private function inClinicScope(int $clinicId): bool
    {
        return $this->clinicScope() === 0 || $this->clinicScope() === $clinicId;
    }

    /**
     * @param mixed $ids @return list<int>
     */
    private function normaliseIds($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param list<int> $specializationIds
     */
    private function validateAssignments(int $clinicId, int $cityId, array $specializationIds): ?string
    {
        if (!$this->inClinicScope($clinicId) || $this->clinic->where('id', $clinicId)->where('status', 1)->first() === null) {
            return 'Clinic is not available in your permitted scope.';
        }

        if ($this->city->find($cityId) === null) {
            return 'City is not valid.';
        }

        if ($specializationIds === []) {
            return 'Select at least one specialization.';
        }
        $activeCount = $this->specialization->whereIn('id', $specializationIds)->where('status', 1)->countAllResults();

        return $activeCount === count($specializationIds) ? null : 'One or more specializations are invalid or inactive.';
    }

    /** @param list<int> $languageIds @param list<int> $treatmentIds */
    private function validateDoctorMasters(array $languageIds, array $treatmentIds): ?string
    {
        if ($languageIds !== [] && $this->language->whereIn('id', $languageIds)->where('status', 1)->countAllResults() !== count($languageIds)) return 'One or more selected languages are invalid or inactive.';
        if ($treatmentIds !== [] && $this->popularTreatment->whereIn('id', $treatmentIds)->where('status', 1)->countAllResults() !== count($treatmentIds)) return 'One or more selected treatments are invalid or inactive.';

        return null;
    }

    /** @param list<int> $languageIds @param list<int> $treatmentIds */
    private function replaceDoctorMaps(int $doctorId, array $languageIds, array $treatmentIds): void
    {
        $this->replaceDoctorLanguages($doctorId, $languageIds);
        $this->replaceDoctorTreatments($doctorId, $treatmentIds);
    }

    private function replaceDoctorLanguages(int $doctorId, array $languageIds): void
    {
        $this->doctorLanguageMap->where('doctor_id', $doctorId)->delete();
        foreach ($languageIds as $languageId) {
            $this->doctorLanguageMap->insert(['doctor_id' => $doctorId, 'language_id' => $languageId]);
        }
    }

    private function replaceDoctorTreatments(int $doctorId, array $treatmentIds): void
    {
        $this->doctorTreatmentMap->where('doctor_id', $doctorId)->delete();
        foreach ($treatmentIds as $treatmentId) {
            $this->doctorTreatmentMap->insert(['doctor_id' => $doctorId, 'treatment_id' => $treatmentId]);
        }
    }

    /** @param list<int> $ids @return list<int> */
    private function sortedIds(array $ids): array
    {
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function telephoneCode(): ?array
    {
        try {
            return service('telephoneContact')->resolve((int) $this->request->getPost('telephone_country_code_id') ?: null, (string) $this->request->getPost('country_code_manual') === '1', (string) $this->request->getPost('country_code'), (string) $this->request->getPost('telephone_country_name'));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
