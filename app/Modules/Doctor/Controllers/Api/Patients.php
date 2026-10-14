<?php

namespace Doctor\Controllers\Api;

use Admin\Services\PatientRegistrationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Patients extends BaseController
{
    public function create(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $token = trim((string) $this->request->getHeaderLine('Authorization'));
        $expected = trim((string) env('ROOTINS_API_TOKEN', ''));

        if ($expected === '' || !hash_equals('Bearer ' . $expected, $token)) {
            return $this->response->setStatusCode(401)->setJSON(['status' => false, 'msg' => 'API authentication failed.']);
        }
        $key = 'rootins-api-patient-' . hash('sha256', $expected);
        $attempts = (int) (cache()->get($key) ?? 0);

        if ($attempts >= 60) {
            return $this->response->setStatusCode(429)->setJSON(['status' => false, 'msg' => 'Too many requests.']);
        }
        cache()->save($key, $attempts + 1, 60);
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();

        if (!is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'A valid patient payload is required.']);
        }
        $clinicId = (int) ($payload['clinic_id'] ?? 0);
        $required = ['first_name', 'last_name', 'country_code', 'mobile_number'];
        $errors = [];

        foreach ($required as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        if ($clinicId < 1) {
            $errors['clinic_id'] = 'Clinic is required.';
        }

        if ($errors !== []) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
        }
        $appointment = !empty($payload['create_appointment_now']) ? [
            'doctor_id' => (int) ($payload['appointment']['doctor_id'] ?? 0),
            'appointment_date' => $payload['appointment']['date'] ?? '',
            'appointment_time' => $payload['appointment']['time'] ?? '',
            'service_name' => $payload['appointment']['service'] ?? '',
            'appointment_type' => $payload['appointment']['type'] ?? 'in_person',
            'reason' => $payload['appointment']['reason'] ?? '',
            'status' => $payload['appointment']['status'] ?? 'scheduled',
            'created_by_type' => 'api',
            'booking_source' => 'api',
        ] : null;
        $patientData = $payload;
        $patientData['clinic_id'] = $clinicId;

        if ($appointment !== null) {
            $patientData['_appointment'] = $appointment;
        }
        unset($patientData['create_appointment_now'], $patientData['appointment']);

        try {
            $result = (new PatientRegistrationService())->register($patientData, null, ['source_type' => 'self', 'source_reference' => substr(trim((string) ($payload['source_reference'] ?? '')), 0, 100)]);
            service('audit')->record('api.patient_created', ['patient_id' => $result['id'], 'clinic_id' => $clinicId, 'source_reference' => $patientData['source_reference'] ?? null]);

            return $this->response->setStatusCode(201)->setJSON(['status' => true, 'msg' => 'Patient created successfully.', 'data' => $result]);
        } catch (Throwable $e) {
            log_message('error', 'API patient creation failed: ' . $e->getMessage());

            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'Patient could not be created safely.']);
        }
    }
}
