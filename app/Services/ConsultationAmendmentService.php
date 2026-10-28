<?php

namespace App\Services;

use App\Services\Shopify\ShopifyPrescriptionEligibility;
use RuntimeException;

final class ConsultationAmendmentService
{
    /** @return array<string,mixed> */
    public function amend(int $consultationId, string $reason, array $payload, array $scope): array
    {
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('AMENDMENT_REASON_REQUIRED');
        if (mb_strlen($reason) > 2000) throw new RuntimeException('AMENDMENT_REASON_TOO_LONG');
        $db = db_connect();
        $consultation = $db->table('consultations')->where('id', $consultationId)->where('doctor_id', (int) $scope['doctor_id'])->where('clinic_id', (int) $scope['clinic_id'])->where('status', 'completed')->get(1)->getRowArray();
        if ($consultation === null) throw new RuntimeException('COMPLETED_CONSULTATION_REQUIRED');

        $prescription = $db->table('prescriptions')->where('consultation_id', $consultationId)->where('doctor_id', (int) $scope['doctor_id'])->where('clinic_id', (int) $scope['clinic_id'])->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
        $submittedItems = array_key_exists('items', $payload) ? $this->items($payload['items']) : null;
        if ($submittedItems === false) throw new RuntimeException('AMENDMENT_PRESCRIPTION_ITEMS_INVALID');
        $oldItems = $prescription === null ? [] : $db->table('prescription_items')->where('prescription_id', (int) $prescription['id'])->orderBy('id')->get()->getResultArray();
        $items = $submittedItems ?? $oldItems;
        $prescriptionChanged = $this->prescriptionChanged($prescription, $oldItems, $submittedItems, $payload);
        $orderExists = $prescription !== null && $this->hasExistingOrder($db, (int) $prescription['id'], (string) ($prescription['prescription_number'] ?? ''));
        if ($orderExists) throw new RuntimeException('AMENDMENT_ORDER_LOCKED');

        $actorId = (int) ((session('user') ?? [])['id'] ?? 0);
        $this->audit('consultation.amendment_requested', $scope + ['consultation_id' => $consultationId, 'prescription_id' => $prescription['id'] ?? null, 'reason' => $reason, 'prescription_changed' => $prescriptionChanged]);
        $now = date('Y-m-d H:i:s');
        $revision = (int) ($db->table('consultation_amendments')->selectMax('revision_number')->where('original_consultation_id', $consultationId)->get()->getRow('revision_number') ?? 0) + 1;
        $corrected = $this->consultationSnapshot($consultation, $payload);
        $newPrescriptionId = null;
        $newReference = null;
        $db->transBegin();
        try {
            if ($prescriptionChanged) {
                if ($prescription !== null) $db->table('prescriptions')->where('id', (int) $prescription['id'])->where('status', 'completed')->update(['status' => 'amended', 'updated_at' => $now]);
                if ($db->tableExists('shopify_prescription_cart_pushes')) {
                    if ($prescription !== null) $db->table('shopify_prescription_cart_pushes')->where('prescription_id', (int) $prescription['id'])->whereIn('status', ['available', 'cart_pushed'])->update(['status' => 'cancelled', 'last_event_topic' => 'consultation_amendment', 'updated_at' => $now]);
                }
                $durationDays = $this->durationDays($payload, $prescription);
                $newPrescriptionId = $db->table('prescriptions')->insert([
                    'prescription_number' => 'TMP-RX-' . bin2hex(random_bytes(8)), 'consultation_id' => $consultationId,
                    'patient_id' => (int) $consultation['patient_id'], 'doctor_id' => (int) $consultation['doctor_id'], 'clinic_id' => (int) $consultation['clinic_id'],
                    'prescription_date' => date('Y-m-d'), 'duration_days' => $durationDays, 'valid_until' => ShopifyPrescriptionEligibility::calculateValidUntil($now, $durationDays),
                    'notes' => $prescription['notes'] ?? null, 'status' => 'completed', 'created_at' => $now, 'updated_at' => $now,
                ], true);
                if (! $newPrescriptionId) throw new RuntimeException('AMENDMENT_PRESCRIPTION_CREATE_FAILED');
                $newReference = sprintf('RXP-%06d', $newPrescriptionId);
                $db->table('prescriptions')->where('id', $newPrescriptionId)->update(['prescription_number' => $newReference]);
                foreach ($items as &$item) { $item['prescription_id'] = $newPrescriptionId; $item['created_at'] = $now; $item['updated_at'] = $now; }
                unset($item);
                if ($items !== [] && ! $db->table('prescription_items')->insertBatch($items)) throw new RuntimeException('AMENDMENT_PRESCRIPTION_ITEMS_CREATE_FAILED');
            }
            $revisionId = $db->table('consultation_amendments')->insert([
                'original_consultation_id' => $consultationId, 'revision_number' => $revision, 'patient_id' => (int) $consultation['patient_id'], 'doctor_id' => (int) $consultation['doctor_id'], 'clinic_id' => (int) $consultation['clinic_id'], 'appointment_id' => $consultation['appointment_id'] ?: null, 'prescription_id' => $newPrescriptionId ?: ($prescription['id'] ?? null), 'revision_json' => json_encode(['original_consultation' => $consultation, 'consultation' => $corrected, 'original_items' => $oldItems, 'items' => $items], JSON_UNESCAPED_SLASHES), 'amendment_reason' => $reason, 'order_review_required' => 0, 'review_status' => 'not_required', 'created_by' => $actorId ?: null, 'created_at' => $now, 'updated_at' => $now,
            ], true);
            if (! $revisionId) throw new RuntimeException('AMENDMENT_REVISION_CREATE_FAILED');
            if (! $db->table('consultations')->where('id', $consultationId)->update($this->consultationUpdate($corrected, $now))) throw new RuntimeException('AMENDMENT_CONSULTATION_UPDATE_FAILED');
            if (! $db->transCommit()) throw new RuntimeException('AMENDMENT_TRANSACTION_FAILED');
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
        $this->audit('consultation.amendment_created', $scope + ['consultation_id' => $consultationId, 'revision_id' => $revisionId, 'prescription_id' => $newPrescriptionId, 'prescription_changed' => $prescriptionChanged]);
        return ['consultation_id' => $consultationId, 'revision_id' => (int) $revisionId, 'prescription_id' => $newPrescriptionId ?: ($prescription['id'] ?? null), 'prescription_reference' => $newReference ?: ($prescription['prescription_number'] ?? null), 'prescription_changed' => $prescriptionChanged, 'order_review_required' => false];
    }

    private function items(mixed $raw): array|false
    {
        if (! is_array($raw) || $raw === []) return false;
        $items = [];
        foreach ($raw as $item) {
            if (! is_array($item)) return false;
            $medicineId = (int) ($item['medicine_id'] ?? 0);
            $quantity = trim((string) ($item['quantity'] ?? ''));
            if ($medicineId <= 0 || ! ctype_digit($quantity) || (int) $quantity < 1) return false;
            $medicine = db_connect()->table('medicines')->where('id', $medicineId)->where('status', 'active')->get(1)->getRowArray();
            if ($medicine === null) return false;
            $items[] = ['medicine_id' => $medicineId, 'medicine_name' => (string) $medicine['medicine_name'], 'dosage' => $this->optionValue($item['dosage'] ?? null, 'prescription_doses', 'name'), 'frequency' => $this->optionValue($item['frequency'] ?? null, 'prescription_frequencies', 'name'), 'duration' => $this->optionValue($item['duration'] ?? null, 'prescription_durations', 'code'), 'duration_unit' => $item['duration_unit'] ?? 'days', 'instructions' => $this->optionValue($item['intake'] ?? ($item['instructions'] ?? null), 'prescription_intakes', 'name'), 'quantity' => (int) $quantity, 'notes' => $item['notes'] ?? null];
        }
        return $items;
    }

    private function prescriptionChanged(?array $prescription, array $oldItems, ?array $submittedItems, array $payload): bool
    {
        if ($prescription === null) return $submittedItems !== null && $submittedItems !== [];
        if ($submittedItems === null) return false;
        $old = array_map(static fn (array $item): array => ['medicine_id' => (int) ($item['medicine_id'] ?? 0), 'quantity' => (int) ($item['quantity'] ?? 0), 'dosage' => (string) ($item['dosage'] ?? ''), 'frequency' => (string) ($item['frequency'] ?? ''), 'duration' => (string) ($item['duration'] ?? ''), 'duration_unit' => (string) ($item['duration_unit'] ?? ''), 'instructions' => (string) ($item['instructions'] ?? ''), 'notes' => (string) ($item['notes'] ?? '')], $oldItems);
        $new = array_map(static fn (array $item): array => ['medicine_id' => (int) ($item['medicine_id'] ?? 0), 'quantity' => (int) ($item['quantity'] ?? 0), 'dosage' => (string) ($item['dosage'] ?? ''), 'frequency' => (string) ($item['frequency'] ?? ''), 'duration' => (string) ($item['duration'] ?? ''), 'duration_unit' => (string) ($item['duration_unit'] ?? ''), 'instructions' => (string) ($item['instructions'] ?? ''), 'notes' => (string) ($item['notes'] ?? '')], $submittedItems);
        return $old !== $new || (array_key_exists('medicine_duration', $payload) && trim((string) $payload['medicine_duration']) !== trim((string) ($prescription['duration_days'] ?? '')));
    }

    private function optionValue(mixed $value, string $table, string $column): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        if (ctype_digit($value)) {
            $row = db_connect()->table($table)->select($column)->where('id', (int) $value)->get(1)->getRowArray();
            if ($row !== null) return (string) ($row[$column] ?? $value);
        }
        return $value;
    }

    private function hasExistingOrder(object $db, int $prescriptionId, string $reference): bool
    {
        if ($db->tableExists('medicine_orders') && $db->table('medicine_orders')->where('prescription_id', $prescriptionId)->countAllResults() > 0) return true;
        if ($db->tableExists('shopify_order_imports') && $db->table('shopify_order_imports')->where('prescription_reference', $reference)->whereNotIn('status', ['cancelled', 'quarantined'])->countAllResults() > 0) return true;
        return $db->tableExists('shopify_prescription_cart_pushes') && $db->table('shopify_prescription_cart_pushes')->where('prescription_id', $prescriptionId)->whereIn('status', ['ordered', 'paid'])->countAllResults() > 0;
    }

    private function durationDays(array $payload, ?array $prescription): int
    {
        $value = trim((string) ($payload['medicine_duration'] ?? $prescription['duration_days'] ?? ''));
        if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 3650) throw new RuntimeException('AMENDMENT_DURATION_INVALID');
        return (int) $value;
    }

    private function consultationSnapshot(array $current, array $payload): array
    {
        $fields = ['consultation_date', 'consultation_time', 'chief_complaint', 'clinical_notes', 'diagnosis', 'treatment_plan', 'diagnostic_tests', 'diet_plan', 'follow_up_date', 'follow_up_after_days', 'follow_up_time', 'follow_up_type', 'follow_up_type_id', 'follow_up_remarks', 'next_appointment_after_days', 'next_appointment_date', 'next_appointment_time', 'next_appointment_type', 'status'];
        $snapshot = [];
        foreach ($fields as $field) $snapshot[$field] = array_key_exists($field, $payload) ? $payload[$field] : ($current[$field] ?? null);
        $snapshot['status'] = 'completed';
        return $snapshot;
    }

    private function consultationUpdate(array $snapshot, string $now): array
    {
        $fields = ['consultation_date', 'consultation_time', 'chief_complaint', 'clinical_notes', 'diagnosis', 'treatment_plan', 'diagnostic_tests', 'diet_plan', 'follow_up_date', 'follow_up_after_days', 'follow_up_time', 'follow_up_type', 'follow_up_type_id', 'follow_up_remarks', 'next_appointment_after_days', 'next_appointment_date', 'next_appointment_time', 'next_appointment_type'];
        $data = ['status' => 'completed', 'updated_at' => $now];
        foreach ($fields as $field) if (array_key_exists($field, $snapshot)) $data[$field] = $snapshot[$field] === '' ? null : $snapshot[$field];
        return $data;
    }

    private function audit(string $action, array $context): void { service('audit')->record($action, $context); }
}
