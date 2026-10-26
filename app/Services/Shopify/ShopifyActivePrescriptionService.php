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
        $today = date('Y-m-d');
        $prescriptions = $db->table('prescriptions p')
            ->select('p.id, p.prescription_number, p.prescription_date, p.valid_until')
            ->where('p.patient_id', $patientId)
            ->whereIn('p.status', ['active', 'approved', 'completed', 'finalized'])
            ->groupStart()
                ->where('p.valid_until >=', $today)
                ->orGroupStart()->where('p.valid_until', null)->where('p.prescription_date >=', $today)->groupEnd()
            ->groupEnd()
            ->orderBy('p.prescription_date', 'DESC')
            ->orderBy('p.id', 'DESC')
            ->get()->getResultArray();

        $result = [];
        foreach ($prescriptions as $prescription) {
            $items = $this->items($db, $storeDomain, (int) $prescription['id']);
            $result[] = [
                'prescription_reference' => (string) $prescription['prescription_number'],
                'valid_until' => $prescription['valid_until'] ?: $prescription['prescription_date'],
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
