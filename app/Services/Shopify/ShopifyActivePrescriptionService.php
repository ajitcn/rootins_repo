<?php

namespace App\Services\Shopify;

use CodeIgniter\Database\BaseConnection;

final class ShopifyActivePrescriptionService
{
    /** @return array<string,mixed> */
    public function forCustomer(string $storeDomain, string $customerId, int $patientId): array
    {
        $store = ShopifyProxyConfigurationService::normalizeStoreDomain($storeDomain);
        $customerId = trim($customerId);
        $customer = ['shopify_customer_id' => $customerId, 'patient_reference' => ''];
        if ($patientId <= 0 || $customerId === '') {
            return ['customer' => $customer, 'prescriptions' => [], 'excluded_items' => []];
        }

        $db = db_connect();
        $patient = $db->table('patients')->select('registration_number')->where('id', $patientId)->where('status', 1)->get(1)->getRowArray();
        $customer['patient_reference'] = (string) ($patient['registration_number'] ?? '');
        $hasDurationDays = $db->fieldExists('duration_days', 'prescriptions');
        $durationSelect = $hasDurationDays ? 'p.duration_days' : 'NULL AS duration_days';
        $prescriptions = $db->table('prescriptions p')
            ->select("p.id, p.prescription_number, p.patient_id, p.prescription_date, p.created_at, {$durationSelect}, p.valid_until, CONCAT_WS(' ', d.first_name, d.last_name) AS doctor_name", false)
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->where('p.patient_id', $patientId)
            ->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)
            ->orderBy('p.created_at', 'DESC')->orderBy('p.id', 'DESC')->get()->getResultArray();

        $allExcluded = [];
        $result = [];
        $today = ShopifyPrescriptionEligibility::today();
        foreach ($prescriptions as $prescription) {
            $calculatedValidUntil = ShopifyPrescriptionEligibility::calculateValidUntil((string) ($prescription['created_at'] ?? ''), $prescription['duration_days'] ?? null);
            $validUntil = $calculatedValidUntil ?? $this->storedValidUntil($prescription['valid_until'] ?? null);
            $cartPush = $this->cartPush($db, $store, (int) $prescription['id'], $customerId);
            $cartStatus = $this->displayCartStatus($cartPush['status'] ?? null, $validUntil, $today);
            $items = $this->items($db, $store, (int) $prescription['id'], (int) $prescription['patient_id'], (string) $prescription['prescription_number'], $cartStatus);
            foreach ($items['excluded_items'] as $excluded) $allExcluded[] = $excluded;
            $result[] = [
                'reference' => (string) $prescription['prescription_number'],
                'status' => 'completed',
                'cart_status' => $cartStatus,
                'valid_until' => $validUntil,
                'doctor_name' => trim((string) ($prescription['doctor_name'] ?? '')),
                'prescribed_at' => (string) ($prescription['prescription_date'] ?? ''),
                'items' => $items['items'],
            ];
        }

        return ['customer' => $customer, 'prescriptions' => $result, 'excluded_items' => $allExcluded];
    }

    /** @return array{items:list<array<string,mixed>>,excluded_items:list<array<string,mixed>>} */
    private function items(BaseConnection $db, string $store, int $prescriptionId, int $patientId, string $reference, string $cartStatus): array
    {
        $rows = $db->table('prescription_items pi')
            ->select('pi.id AS prescription_item_id, pi.medicine_id, pi.medicine_name, pi.dosage, pi.frequency, pi.duration, pi.duration_unit, pi.quantity, m.status AS medicine_status')
            ->join('medicines m', 'm.id = pi.medicine_id', 'left')
            ->where('pi.prescription_id', $prescriptionId)->orderBy('pi.id', 'ASC')->get()->getResultArray();
        $items = [];
        $excluded = [];
        foreach ($rows as $row) {
            $mapping = $db->table('shopify_medicine_mappings sm')
                ->select('sm.shopify_variant_id, v.is_active AS variant_active')
                ->join('shopify_variant_catalogue v', 'v.store_domain = sm.store_domain AND v.shopify_variant_id = sm.shopify_variant_id', 'left')
                ->where('sm.store_domain', $store)->where('sm.medicine_id', (int) ($row['medicine_id'] ?? 0))->where('sm.status', 'active')->get(1)->getRowArray();
            $base = [
                'prescription_item_id' => (string) ($row['prescription_item_id'] ?? ''),
                'medicine_name' => (string) ($row['medicine_name'] ?? ''),
                'dosage' => (string) ($row['dosage'] ?? ''),
                'frequency' => (string) ($row['frequency'] ?? ''),
                'duration' => trim((string) ($row['duration'] ?? '') . ' ' . (string) ($row['duration_unit'] ?? '')),
            ];
            $storedQuantity = $row['quantity'] ?? null;
            $normalizedQuantity = $this->normalizeQuantity($storedQuantity);
            $reason = $this->itemExclusionReason($row, $mapping, $normalizedQuantity);
            $variantId = $mapping === null ? null : $this->cartVariantId((string) ($mapping['shopify_variant_id'] ?? ''));
            if ($variantId === null && $reason === null) $reason = 'SHOPIFY_MEDICINE_MAPPING_REQUIRED';
            if ($reason !== null) {
                $base['reference'] = $reference;
                $base['reason'] = $reason;
                $base['stored_quantity'] = $storedQuantity;
                $base['normalized_quantity'] = $normalizedQuantity;
                $excluded[] = $base;
                $this->logItemDiagnostic($reference, $prescriptionId, $patientId, $row, $storedQuantity, $normalizedQuantity, $mapping, $reason);
                continue;
            }
            $quantity = $normalizedQuantity;
            $base['variant_id'] = $variantId;
            $base['quantity'] = $quantity;
            $base['quantity_remaining'] = in_array($cartStatus, ['ordered', 'paid', 'cancelled', 'expired'], true) ? 0 : $quantity;
            $base['cart_property'] = ['_rx_reference' => $reference];
            $items[] = $base;
        }
        return ['items' => $items, 'excluded_items' => $excluded];
    }

    /** @return array<string,mixed>|null */
    private function cartPush(BaseConnection $db, string $store, int $prescriptionId, string $customerId): ?array
    {
        if (! $db->tableExists('shopify_prescription_cart_pushes')) return null;
        return $db->table('shopify_prescription_cart_pushes')->select('status, cart_pushed_at')->where('store_domain', $store)->where('prescription_id', $prescriptionId)->where('shopify_customer_id', $customerId)->get(1)->getRowArray() ?: null;
    }

    private function displayCartStatus(?string $status, ?string $validUntil, string $today): string
    {
        if (in_array($status, ['ordered', 'paid', 'cancelled'], true)) return $status;
        if ($validUntil === null || $validUntil < $today) return 'expired';
        return $status === 'cart_pushed' ? 'cart_pushed' : 'available';
    }

    private function cartVariantId(string $value): ?string
    {
        $value = trim($value);
        if (ctype_digit($value) && (int) $value > 0) return $value;
        if (preg_match('#^gid://shopify/ProductVariant/(\d+)$#i', $value, $matches) === 1) return $matches[1];
        return null;
    }

    private function storedValidUntil(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function normalizeQuantity(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) return null;
        if (is_int($value) || (is_string($value) && preg_match('/^\s*\d+\s*$/', $value) === 1)) return (int) $value;
        if (is_float($value) && floor($value) === $value) return (int) $value;
        return null;
    }

    /** @param array<string,mixed> $row @param array<string,mixed>|null $mapping */
    private function itemExclusionReason(array $row, ?array $mapping, ?int $quantity): ?string
    {
        if (! isset($row['medicine_id']) || (int) $row['medicine_id'] <= 0) return 'PRESCRIPTION_ITEM_MEDICINE_INVALID';
        if ($quantity === null || $quantity < 1) return 'PRESCRIPTION_ITEM_QUANTITY_INVALID';
        if (strtolower(trim((string) ($row['medicine_status'] ?? ''))) !== 'active') return 'ROOTINS_MEDICINE_INACTIVE';
        if ($mapping === null || (int) ($mapping['variant_active'] ?? 0) !== 1) return 'SHOPIFY_MEDICINE_MAPPING_REQUIRED';
        return null;
    }

    /** @param array<string,mixed> $row @param array<string,mixed>|null $mapping */
    private function logItemDiagnostic(string $reference, int $prescriptionId, int $patientId, array $row, mixed $storedQuantity, ?int $normalizedQuantity, ?array $mapping, string $reason): void
    {
        log_message('warning', 'Shopify prescription item excluded: ' . json_encode([
            'prescription_reference' => $reference,
            'prescription_id' => $prescriptionId,
            'patient_id' => $patientId,
            'prescription_item_id' => (int) ($row['prescription_item_id'] ?? 0),
            'medicine_id' => $row['medicine_id'] ?? null,
            'stored_quantity' => $storedQuantity,
            'normalized_quantity' => $normalizedQuantity,
            'mapping_result' => $mapping === null ? 'missing' : ((int) ($mapping['variant_active'] ?? 0) === 1 ? 'active' : 'inactive'),
            'reason' => $reason,
        ], JSON_UNESCAPED_SLASHES));
    }
}
