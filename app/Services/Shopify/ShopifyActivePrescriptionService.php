<?php

namespace App\Services\Shopify;

use CodeIgniter\Database\BaseConnection;

final class ShopifyActivePrescriptionService
{
    /** @return array<string,mixed> */
    public function forCustomer(string $storeDomain, string $customerId, int $patientId): array
    {
        if ($patientId <= 0 || trim($customerId) === '') {
            return ['prescriptions' => []];
        }

        $db = db_connect();
        $today = ShopifyPrescriptionEligibility::today();
        $prescriptions = $db->table('prescriptions p')
            ->select('p.id, p.prescription_number, p.prescription_date, p.duration_days, p.valid_until, p.patient_id, patients.registration_number AS patient_reference')
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->join('patients', 'patients.id = p.patient_id AND patients.status = 1')
            ->where('p.patient_id', $patientId)
            ->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)
            ->where('p.duration_days >', 0)
            ->where('p.valid_until IS NOT NULL', null, false)
            ->where('p.valid_until >=', $today)
            ->orderBy('p.prescription_date', 'DESC')
            ->orderBy('p.id', 'DESC')
            ->get()->getResultArray();

        $result = [];
        foreach ($prescriptions as $prescription) {
            $items = $this->items($db, $storeDomain, (int) $prescription['id']);
            $result[] = [
                'prescription_reference' => (string) $prescription['prescription_number'],
                'patient_reference' => (string) ($prescription['patient_reference'] ?? ''),
                'duration_days' => (int) ($prescription['duration_days'] ?? 0),
                'valid_until' => (string) $prescription['valid_until'],
                'items' => $items['items'],
                'excluded_items' => $items['excluded_items'],
            ];
        }

        return ['prescriptions' => $result];
    }

    /** @return array{items:list<array<string,mixed>>,excluded_items:list<array<string,mixed>>} */
    private function items(BaseConnection $db, string $storeDomain, int $prescriptionId): array
    {
        $rows = $db->table('prescription_items pi')
            ->select('pi.medicine_id, pi.medicine_name, pi.dosage, pi.frequency, pi.duration, pi.duration_unit, pi.instructions, pi.quantity, m.status AS medicine_status, m.medicine_code')
            ->join('medicines m', 'm.id = pi.medicine_id', 'left')
            ->where('pi.prescription_id', $prescriptionId)
            ->get()->getResultArray();
        $items = [];
        $excluded = [];
        foreach ($rows as $row) {
            $mapping = $db->table('shopify_medicine_mappings sm')
                ->select('sm.shopify_variant_id, sm.status AS mapping_status, v.is_active AS variant_active')
                ->join('shopify_variant_catalogue v', 'v.store_domain = sm.store_domain AND v.shopify_variant_id = sm.shopify_variant_id', 'left')
                ->where('sm.store_domain', ShopifyProxyConfigurationService::normalizeStoreDomain($storeDomain))
                ->where('sm.medicine_id', (int) ($row['medicine_id'] ?? 0))
                ->where('sm.status', 'active')->get(1)->getRowArray();
            $reason = ((int) ($row['medicine_id'] ?? 0) <= 0 || strtolower((string) ($row['medicine_status'] ?? '')) !== 'active')
                ? 'MEDICINE_INACTIVE'
                : ($mapping === null || (int) ($mapping['variant_active'] ?? 0) !== 1 ? 'SHOPIFY_MEDICINE_MAPPING_REQUIRED' : null);
            $base = [
                'medicine_id' => (int) ($row['medicine_id'] ?? 0),
                'medicine_reference' => (string) ($row['medicine_code'] ?: sprintf('MED-%05d', (int) $row['medicine_id'])),
                'medicine_name' => (string) ($row['medicine_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'dosage' => (string) ($row['dosage'] ?? ''), 'frequency' => (string) ($row['frequency'] ?? ''),
                'duration' => trim((string) ($row['duration'] ?? '') . ' ' . (string) ($row['duration_unit'] ?? '')),
                'instructions' => (string) ($row['instructions'] ?? ''),
            ];
            if ($reason !== null || (int) ($row['quantity'] ?? 0) <= 0) {
                $base['reason'] = $reason ?? 'PRESCRIPTION_INVALID';
                $excluded[] = $base;
                continue;
            }
            $base['shopify_variant_id'] = (string) $mapping['shopify_variant_id'];
            $items[] = $base;
        }
        return ['items' => $items, 'excluded_items' => $excluded];
    }
}
