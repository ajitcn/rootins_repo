<?php

namespace App\Services;

use Admin\Models\PatientModel;

final class PatientScope
{
    public function current(): ?array
    {
        $patientSession = session('patient');
        $patientId = (int) ($patientSession['id'] ?? 0);

        if ($patientId <= 0) {
            $patientId = (int) ((session('user') ?? [])['patient_id'] ?? 0);
        }

        $query = (new PatientModel())
            ->select('patients.*, clinics.clinic_name')
            ->join('clinics', 'clinics.id = patients.clinic_id', 'left')
            ->where('patients.status', 1)
            ->groupStart()
            ->where('clinics.id IS NULL', null, false)
            ->orWhere('clinics.status', 1)
            ->groupEnd();

        if ($patientId > 0) {
            $query->where('patients.id', $patientId);

            $sessionUserId = (int) ($patientSession['user_id'] ?? (session('user')['id'] ?? 0));

            if ($sessionUserId > 0) {
                $query->where('patients.user_id', $sessionUserId);
            }
        } else {
            $userId = (int) ((session('user') ?? [])['id'] ?? 0);

            if ($userId <= 0) {
                return null;
            }

            $query->where('patients.user_id', $userId);
        }

        $patient = $query->first();

        if ($patient === null) {
            return null;
        }

        return [
            'user_id' => (int) ($patientSession['actor_id'] ?? $patient['user_id'] ?? 0),
            'patient_id' => (int) $patient['id'],
            'clinic_id' => (int) $patient['clinic_id'],
            'patient' => $patient,
        ];
    }
}
