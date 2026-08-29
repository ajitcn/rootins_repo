<?php

namespace Admin\Services;

use Admin\Models\PatientModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PatientRegistrationService
{
    protected PatientModel $patient;
    protected BaseConnection $db;

    public function __construct(?PatientModel $patient = null, ?BaseConnection $db = null)
    {
        $this->patient = $patient ?? new PatientModel();
        $this->db      = $db ?? db_connect();
    }

    /**
     * Register a patient and record the initial clinic assignment atomically.
     *
     * @param array<string, mixed> $patientData
     * @return array{id: int, registration_number: string}
     */
    public function register(array $patientData, ?int $transferredBy = null): array
    {
        if (empty($patientData['clinic_id'])) {
            throw new InvalidArgumentException('A clinic is required for patient registration.');
        }

        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $temporaryRegistrationNumber = 'TMP-' . bin2hex(random_bytes(8));

            $insertData = array_merge($patientData, [
                'registration_number' => $temporaryRegistrationNumber,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            if (! $this->db->table('patients')->insert($insertData)) {
                throw new RuntimeException('Patient create failed.');
            }

            $patientId = (int) $this->db->insertID();

            if ($patientId < 1) {
                throw new RuntimeException('Patient create failed.');
            }

            $registrationNumber = sprintf('PAT-%06d', $patientId);

            if (! $this->db->table('patients')
                ->where('id', $patientId)
                ->update(['registration_number' => $registrationNumber])) {
                throw new RuntimeException('Patient registration number assignment failed.');
            }

            if (! $this->db->table('patient_clinic_transfers')->insert([
                'patient_id'     => $patientId,
                'from_clinic_id' => null,
                'to_clinic_id'   => (int) $patientData['clinic_id'],
                'transferred_at' => date('Y-m-d H:i:s'),
                'transferred_by' => $transferredBy,
                'reason'         => 'Initial clinic assignment',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('Initial clinic assignment history failed.');
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Patient registration transaction failed.');
            }

            $this->db->transCommit();

            return [
                'id'                 => $patientId,
                'registration_number' => $registrationNumber,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
}
