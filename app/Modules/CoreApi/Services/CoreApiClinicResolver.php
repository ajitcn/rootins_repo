<?php

namespace App\Modules\CoreApi\Services;

final class CoreApiClinicResolver
{
    public function activeClinicId(mixed $value): int
    {
        $clinicId = (int) $value;
        if ($clinicId < 1 || db_connect()->table('clinics')->where('id', $clinicId)->where('status', 1)->countAllResults() < 1) {
            throw new CoreApiException(422, 'CLINIC_REQUIRED', 'A valid active clinic is required.', ['clinic_id' => 'Select a valid active clinic.']);
        }

        return $clinicId;
    }

    /** @return array{id:int,clinic_id:int} */
    public function patient(string $reference): array
    {
        $patient = db_connect()->table('patients')->select('id, clinic_id')->where('registration_number', trim($reference))->where('status', 1)->get()->getRowArray();
        if ($patient === null) {
            throw new CoreApiException(404, 'PATIENT_NOT_FOUND', 'The patient reference was not found.', ['patient_reference' => 'The patient reference was not found.']);
        }

        return ['id' => (int) $patient['id'], 'clinic_id' => (int) $patient['clinic_id']];
    }

    public function patientClinic(array $input, bool $referenceRequired = true): int
    {
        $reference = trim((string) ($input['patient_reference'] ?? ''));
        if ($reference !== '') {
            $patient = $this->patient($reference);
            $requested = (int) ($input['clinic_id'] ?? 0);
            if ($requested > 0 && $requested !== $patient['clinic_id']) {
                throw new CoreApiException(403, 'CLINIC_SCOPE_INVALID', 'The requested clinic does not match the authorized patient clinic.', ['clinic_id' => 'The clinic is not authorized for this patient.']);
            }

            return $patient['clinic_id'];
        }
        if ($referenceRequired) {
            throw new CoreApiException(422, 'PATIENT_NOT_FOUND', 'A patient reference is required.', ['patient_reference' => 'Patient reference is required.']);
        }

        return $this->activeClinicId($input['clinic_id'] ?? 0);
    }

    public function doctorClinic(string $reference): ?int
    {
        if (trim($reference) === '') {
            return null;
        }
        $doctor = db_connect()->table('doctors')->select('clinic_id')->where('doctor_code', trim($reference))->where('status', 1)->where('online_booking', 1)->get()->getRowArray();

        return $doctor === null ? null : (int) $doctor['clinic_id'];
    }

    public function reconcileRequestedClinic(array $input, int $resolvedClinicId): int
    {
        $requested = (int) ($input['clinic_id'] ?? 0);
        if ($requested > 0 && $requested !== $resolvedClinicId) {
            throw new CoreApiException(403, 'CLINIC_SCOPE_INVALID', 'The requested clinic is not authorized for this operation.', ['clinic_id' => 'The clinic does not match the authorized domain record.']);
        }

        return $resolvedClinicId;
    }
}
