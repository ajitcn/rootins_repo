<?php

namespace Admin\Controllers\Clinic;

use Admin\Models\Clinic\ClinicModel;
use Admin\Models\Location\TelephoneCountryCode;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Clinic extends BaseAdminController
{
    protected ClinicModel $clinic;

    public function __construct()
    {
        $this->clinic = new ClinicModel();
    }

    public function index(): string
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        $countryId = max(0, (int) $this->request->getGet('country_id'));
        $stateId = max(0, (int) $this->request->getGet('state_id'));
        $cityId = max(0, (int) $this->request->getGet('city_id'));
        $requestedPageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($requestedPageSize, [10, 25, 50, 100], true) ? $requestedPageSize : 15;
        $query = $this->clinic->select('clinics.*, countries.name AS country_name, states.name AS state_name, cities.name AS city_name, telephone_country_codes.normalized_code AS telephone_country_code, (SELECT COUNT(*) FROM doctors WHERE doctors.clinic_id = clinics.id) AS doctors_count, (SELECT COUNT(*) FROM patients WHERE patients.clinic_id = clinics.id) AS patients_count, (SELECT COUNT(DISTINCT appointment_services.service_id) FROM appointment_services INNER JOIN appointments ON appointments.id = appointment_services.appointment_id WHERE appointments.clinic_id = clinics.id AND appointment_services.service_id IS NOT NULL) AS services_count')->join('countries', 'countries.id = clinics.country_id', 'left')->join('states', 'states.id = clinics.state_id', 'left')->join('cities', 'cities.id = clinics.city_id', 'left')->join('telephone_country_codes', 'telephone_country_codes.id = clinics.telephone_country_code_id', 'left');

        if ($search !== '') {
            $query->groupStart()->like('clinics.clinic_name', $search)->orLike('clinics.clinic_code', $search)->orLike('clinics.contact_person', $search)->orLike('clinics.email', $search)->groupEnd();
        }

        if ($status === '0' || $status === '1') {
            $query->where('clinics.status', (int) $status);
        }

        if ($countryId > 0) {
            $query->where('clinics.country_id', $countryId);
        }

        if ($stateId > 0) {
            $query->where('clinics.state_id', $stateId);
        }

        if ($cityId > 0) {
            $query->where('clinics.city_id', $cityId);
        }
        $records = $query->orderBy('clinics.clinic_name', 'ASC')->paginate($pageSize, 'clinics');
        $location = service('location');

        return view(ADMIN_VIEW . 'pages/clinic/index', ['countries' => $location->countries(), 'telephoneCodes' => (new TelephoneCountryCode())->dropdown(), 'filterStates' => $countryId > 0 ? $location->states($countryId) : [], 'filterCities' => $stateId > 0 ? $location->cities($stateId) : [], 'records' => $records, 'pager' => $this->clinic->pager, 'filters' => ['search' => $search, 'status' => $status, 'country_id' => $countryId, 'state_id' => $stateId, 'city_id' => $cityId, 'page_size' => $pageSize], 'canCreate' => $this->hasPermission('clinic.create'), 'canEdit' => $this->hasPermission('clinic.edit'), 'canDelete' => $this->hasPermission('clinic.delete')]);
    }

    public function view(int $clinicId): string|RedirectResponse
    {
        $clinic = $this->clinic->getClinic($clinicId);

        if ($clinic === null) {
            return redirect()->to(base_url('admin/clinics'))->with('error', 'Clinic not found.');
        }

        return view(ADMIN_VIEW . 'pages/clinic/view', ['clinic' => $clinic]);
    }

    public function create(): ResponseInterface
    {
        if (!$this->hasPermission('clinic.create')) {
            return $this->json(403, false, 'You do not have permission to perform this action.');
        }

        return $this->saveClinic(null);
    }

    public function update(): ResponseInterface
    {
        $clinicId = (int) $this->request->getPost('clinic_id');

        if ($clinicId < 1 || $this->clinic->find($clinicId) === null) {
            return $this->json(404, false, 'Clinic not found.');
        }

        return $this->saveClinic($clinicId);
    }

    public function clinicById(int $clinicId): ResponseInterface
    {
        $data = $this->clinic->select('clinics.*, telephone_country_codes.normalized_code AS telephone_country_code, COALESCE(countries.name, telephone_country_codes.label) AS telephone_country_name')->join('telephone_country_codes', 'telephone_country_codes.id = clinics.telephone_country_code_id', 'left')->join('countries', 'countries.id = telephone_country_codes.country_id', 'left')->find($clinicId);

        return $data === null ? $this->json(404, false, 'Clinic not found.') : $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $data]);
    }

    public function delete(int $clinicId): RedirectResponse
    {
        $clinic = $this->clinic->find($clinicId);

        if ($clinic === null) {
            return redirect()->to(base_url('admin/clinics'))->with('error', 'Clinic not found.');
        }

        if (!$this->clinic->update($clinicId, ['status' => 0])) {
            return redirect()->to(base_url('admin/clinics'))->with('error', 'Clinic could not be deactivated safely.');
        }
        service('audit')->record('admin.clinic_status_changed', ['clinic_id' => $clinicId, 'status' => 0]);

        return redirect()->to(base_url('admin/clinics'))->with('success', 'Clinic deactivated successfully.');
    }

    private function saveClinic(?int $clinicId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $errors = $this->validateClinic($clinicId);

        if ($errors !== []) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
        }
        $data = $this->clinicData();

        try {
            if ($clinicId === null) {
                if (!$this->clinic->insert($data)) {
                    throw new \RuntimeException('Clinic create failed.');
                }
                $clinicId = (int) $this->clinic->getInsertID();
                service('audit')->record('admin.clinic_created', ['clinic_id' => $clinicId]);

                return $this->json(200, true, 'Clinic created successfully.');
            }

            if (!$this->clinic->update($clinicId, $data)) {
                throw new \RuntimeException('Clinic update failed.');
            }
            service('audit')->record('admin.clinic_updated', ['clinic_id' => $clinicId, 'status' => (int) $data['status']]);

            return $this->json(200, true, 'Clinic updated successfully.');
        } catch (Throwable $e) {
            log_message('error', 'Clinic write failed for clinic ID {clinicId}.', ['clinicId' => $clinicId ?? 0]);

            return $this->json(500, false, $clinicId === null ? 'Clinic could not be created safely.' : 'Clinic could not be updated safely.');
        }
    }

    private function validateClinic(?int $clinicId): array
    {
        $rules = ['clinic_name' => 'required|min_length[3]|max_length[150]', 'clinic_code' => 'required|min_length[2]|max_length[20]', 'contact_person' => 'permit_empty|max_length[100]', 'mobile' => 'permit_empty|max_length[20]', 'telephone_country_code_id' => 'permit_empty|integer', 'country_code' => 'permit_empty|max_length[5]', 'telephone_country_name' => 'permit_empty|max_length[191]', 'email' => 'permit_empty|valid_email|max_length[150]', 'country_id' => 'required|integer|is_not_unique[countries.id]', 'state_id' => 'required|integer|is_not_unique[states.id]', 'city_id' => 'required|integer|is_not_unique[cities.id]', 'address' => 'permit_empty|max_length[2000]', 'pincode' => 'permit_empty|max_length[10]', 'status' => 'required|in_list[0,1]'];

        if (!$this->validate($rules)) {
            return $this->validator->getErrors();
        }

        if ((string) $this->request->getPost('country_code_manual') === '1' && trim((string) $this->request->getPost('mobile')) !== '' && trim((string) $this->request->getPost('telephone_country_name')) === '') {
            return ['telephone_country_name' => 'Country name is required when entering a code manually.'];
        }

        if ($this->telephoneCode() === null && trim((string) $this->request->getPost('mobile')) !== '') {
            return ['country_code' => 'Select or enter a valid telephone country code.'];
        }
        $name = trim((string) $this->request->getPost('clinic_name'));
        $code = trim((string) $this->request->getPost('clinic_code'));
        $query = $this->clinic->groupStart()->where('clinic_name', $name)->orWhere('clinic_code', $code)->groupEnd();

        if ($clinicId !== null) {
            $query->where('id !=', $clinicId);
        }
        $duplicate = $query->first();

        if ($duplicate !== null) {
            return ['clinic_name' => (string) $duplicate['clinic_name'] === $name ? 'Clinic name is already registered.' : 'Clinic code is already registered.'];
        }

        return [];
    }

    private function clinicData(): array
    {
        $telephoneCode = $this->telephoneCode();

        return ['clinic_name' => trim((string) $this->request->getPost('clinic_name')), 'clinic_code' => strtoupper(trim((string) $this->request->getPost('clinic_code'))), 'contact_person' => trim((string) $this->request->getPost('contact_person')) ?: null, 'mobile' => trim((string) $this->request->getPost('mobile')) ?: null, 'mobile_normalized' => service('telephoneContact')->normalizedMobile($this->request->getPost('mobile')), 'country_id' => (int) $this->request->getPost('country_id'), 'country_code_id' => $telephoneCode['country_id'] ?? null, 'telephone_country_code_id' => $telephoneCode['id'] ?? null, 'email' => trim((string) $this->request->getPost('email')) ?: null, 'state_id' => (int) $this->request->getPost('state_id'), 'city_id' => (int) $this->request->getPost('city_id'), 'address' => trim((string) $this->request->getPost('address')) ?: null, 'pincode' => trim((string) $this->request->getPost('pincode')) ?: null, 'status' => (int) $this->request->getPost('status')];
    }

    private function telephoneCode(): ?array
    {
        $manual = (string) $this->request->getPost('country_code_manual') === '1';
        $mobile = trim((string) $this->request->getPost('mobile'));

        if ($mobile === '' && !$manual && trim((string) $this->request->getPost('telephone_country_code_id')) === '') {
            return null;
        }

        try {
            return service('telephoneContact')->resolve((int) $this->request->getPost('telephone_country_code_id') ?: null, $manual, (string) $this->request->getPost('country_code'), (string) $this->request->getPost('telephone_country_name'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function json(int $code, bool $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['status' => $status, 'msg' => $message]);
    }
}
