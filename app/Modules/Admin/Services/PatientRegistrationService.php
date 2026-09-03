<?php

namespace Admin\Services;

use Admin\Models\PatientModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use App\Services\LocationService;
use Admin\Models\Location\Country;
class PatientRegistrationService
{
    protected PatientModel $patient;
    protected BaseConnection $db;
    public function __construct(?PatientModel $patient = null, ?BaseConnection $db = null)
    {
        $this->patient = $patient ?? new PatientModel();
        $this->db = $db ?? db_connect();
    }
    /**
     * Register a patient and record the initial clinic assignment atomically.
     *
     * @param array<string, mixed> $patientData
     * @return array{id: int, registration_number: string}
     */
    public function register(array $patientData, ?int $transferredBy = null, array $source = []): array
    {
        if (empty($patientData['clinic_id'])) {
            throw new InvalidArgumentException('A clinic is required for patient registration.');
        }
        $appointment = $patientData['_appointment'] ?? null;
        unset($patientData['_appointment']);
        $sourceType = (string) ($source['source_type'] ?? $patientData['source_type'] ?? 'admin');
        if (!in_array($sourceType, ['admin', 'doctor', 'api', 'patient', 'import'], true)) {
            throw new InvalidArgumentException('Patient source is invalid.');
        }
        $patientData['source_type'] = $sourceType;
        $patientData['source_user_id'] = $source['source_user_id'] ?? $transferredBy;
        $patientData['source_reference'] = $source['source_reference'] ?? ($patientData['source_reference'] ?? null);
        $mobileNormalized = preg_replace('/[^0-9]/', '', (string) ($patientData['mobile_number'] ?? ''));
        if ($mobileNormalized !== '') {
            $duplicate = $this->db->table('patients')->where('clinic_id', (int) $patientData['clinic_id'])->where('country_code', (string) ($patientData['country_code'] ?? ''))->where('mobile_normalized', $mobileNormalized)->where('status', 1)->get()->getRowArray();
            if ($duplicate !== null) {
                throw new InvalidArgumentException('A patient with this mobile number already exists in the clinic.');
            }
        }
        $this->db->transBegin();
        try {
            $patientData = array_merge($patientData, (new LocationService())->resolvePatientLocation($patientData));
            unset($patientData['country_name'], $patientData['state_name'], $patientData['city_name'], $patientData['locality_name']);
            if (!empty($patientData['country_code_id'])) {
                $country = (new Country())->find((int) $patientData['country_code_id']);
                if ($country === null || empty($country['phone_code'])) {
                    throw new InvalidArgumentException('A valid country code is required.');
                }
                $patientData['country_code'] = $country['phone_code'];
            }
            $patientData['mobile_normalized'] = empty($patientData['mobile_number']) ? null : preg_replace('/[^0-9]/', '', (string) $patientData['mobile_number']);
            $now = date('Y-m-d H:i:s');
            $temporaryRegistrationNumber = 'TMP-' . bin2hex(random_bytes(8));
            $insertData = array_merge($patientData, ['registration_number' => $temporaryRegistrationNumber, 'created_at' => $now, 'updated_at' => $now]);
            // Some deployed patient schemas keep email as NOT NULL even
            // though it is optional in the registration workflow. Store an
            // empty value for an omitted email rather than sending SQL NULL.
            if (!array_key_exists('email', $insertData) || $insertData['email'] === null) {
                $insertData['email'] = '';
            }
            if (!$this->db->table('patients')->insert($insertData)) {
                throw new RuntimeException('Patient create failed.');
            }
            $patientId = (int) $this->db->insertID();
            if ($patientId < 1) {
                throw new RuntimeException('Patient create failed.');
            }
            $registrationNumber = sprintf('PAT-%06d', $patientId);
            if (!$this->db->table('patients')->where('id', $patientId)->update(['registration_number' => $registrationNumber])) {
                throw new RuntimeException('Patient registration number assignment failed.');
            }
            if (!$this->db->table('patient_clinic_transfers')->insert(['patient_id' => $patientId, 'from_clinic_id' => null, 'to_clinic_id' => (int) $patientData['clinic_id'], 'transferred_at' => date('Y-m-d H:i:s'), 'transferred_by' => $transferredBy, 'reason' => 'Initial clinic assignment', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new RuntimeException('Initial clinic assignment history failed.');
            }
            if ($appointment !== null) {
                $this->createAppointmentInTransaction($appointment, $patientId, (int) $patientData['clinic_id'], $transferredBy);
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Patient registration transaction failed.');
            }
            $this->db->transCommit();
            return ['id' => $patientId, 'registration_number' => $registrationNumber];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $appointment */
    private function createAppointmentInTransaction(array $appointment, int $patientId, int $clinicId, ?int $createdBy): void
    {
        $doctorId = (int) ($appointment['doctor_id'] ?? 0);
        $date = trim((string) ($appointment['appointment_date'] ?? ''));
        $time = trim((string) ($appointment['appointment_time'] ?? ''));
        $service = trim((string) ($appointment['service_name'] ?? ''));
        if ($doctorId < 1 || $service === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            throw new InvalidArgumentException('Appointment details are invalid.');
        }
        $doctor = $this->db->table('doctors')->where('id', $doctorId)->where('clinic_id', $clinicId)->where('status', 1)->get()->getRowArray();
        if ($doctor === null) {
            throw new InvalidArgumentException('The selected doctor is invalid for this clinic.');
        }
        $conflict = $this->db->table('appointments')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('appointment_date', $date)->where('appointment_time', $time)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])->get()->getRowArray();
        if ($conflict !== null) {
            throw new InvalidArgumentException('The selected appointment time is already booked.');
        }
        $now = date('Y-m-d H:i:s');
        if (!$this->db->table('appointments')->insert(['appointment_number' => 'TMP-' . bin2hex(random_bytes(8)), 'patient_id' => $patientId, 'doctor_id' => $doctorId, 'clinic_id' => $clinicId, 'appointment_date' => $date, 'appointment_time' => $time, 'duration_minutes' => (int) ($appointment['duration_minutes'] ?? 15), 'appointment_type' => (string) ($appointment['appointment_type'] ?? 'in_person'), 'service_name' => $service, 'status' => (string) ($appointment['status'] ?? 'scheduled'), 'created_by_type' => (string) ($appointment['created_by_type'] ?? 'user'), 'created_by_id' => $createdBy, 'booking_source' => (string) ($appointment['booking_source'] ?? 'doctor'), 'reason_for_visit' => trim((string) ($appointment['reason'] ?? $appointment['reason_for_visit'] ?? '')) ?: null, 'created_at' => $now, 'updated_at' => $now])) {
            throw new RuntimeException('Appointment creation failed.');
        }
        $appointmentId = (int) $this->db->insertID();
        if (!$this->db->table('appointments')->where('id', $appointmentId)->update(['appointment_number' => sprintf('APT-%06d', $appointmentId)])) {
            throw new RuntimeException('Appointment number assignment failed.');
        }
    }
}
