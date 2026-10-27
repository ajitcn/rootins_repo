<?php

namespace Pharmacy\Services;

final class PharmacyScope
{
    /**
     * Pharmacy Assistant records are always resolved from the authenticated user and
     * role-owned clinic scope; request parameters never establish ownership.
     */
    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $user = session('user');

        return is_array($user) ? $user : null;
    }

    public function clinicId(): ?int
    {
        $roles = session('roles') ?? [];

        if (!in_array('Pharmacy Assistant', $roles, true)) {
            return null;
        }
        $user = $this->user() ?? [];
        $clinicId = (int) ($user['clinic_id'] ?? session('clinic_id') ?? 0);

        return $clinicId > 0 ? $clinicId : null;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, session('permissions') ?? [], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function prescription(int $id, string $select = 'prescriptions.*'): ?array
    {
        $clinicId = $this->clinicId();

        if ($clinicId === null || $id <= 0) {
            return null;
        }

        return db_connect()->table('prescriptions')
            ->select($select)
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->where('prescriptions.id', $id)
            ->where('prescriptions.clinic_id', $clinicId)
            ->where('doctors.clinic_id', $clinicId)
            ->get()->getRowArray() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function patient(int $id): ?array
    {
        $clinicId = $this->clinicId();

        if ($clinicId === null || $id <= 0) {
            return null;
        }

        return db_connect()->table('patients')->where('id', $id)->where('clinic_id', $clinicId)->get()->getRowArray() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function order(int $id): ?array
    {
        $clinicId = $this->clinicId();

        if ($clinicId === null || $id <= 0) {
            return null;
        }

        return db_connect()->table('medicine_orders')
            ->select('medicine_orders.*')
            ->join('patients', 'patients.id = medicine_orders.patient_id')
            ->where('medicine_orders.id', $id)
            ->where('medicine_orders.clinic_id', $clinicId)
            ->get()->getRowArray() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function inventoryBatch(int $id): ?array
    {
        if ($this->clinicId() === null || $id <= 0) {
            return null;
        }

        return db_connect()->table('medicine_inventory_batches')
            ->where('id', $id)->get()->getRowArray() ?: null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function audit(string $action, array $context = []): string
    {
        $clinicId = $this->clinicId();

        if ($clinicId === null) {
            return '';
        }

        return service('audit')->record($action, array_merge(['clinic_id' => $clinicId], $context));
    }
}
