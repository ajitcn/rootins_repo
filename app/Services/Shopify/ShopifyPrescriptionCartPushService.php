<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyPrescriptionCartPushModel;
use DateTimeImmutable;
use RuntimeException;

final class ShopifyPrescriptionCartPushService
{
    /** @return array<string,mixed> */
    public function record(array $context, array $payload): array
    {
        $store = ShopifyProxyConfigurationService::normalizeStoreDomain((string) ($context['store_domain'] ?? ''));
        $customerId = trim((string) ($context['customer_id'] ?? ''));
        $patientId = (int) ($context['patient_id'] ?? 0);
        $reference = trim((string) ($payload['prescription_reference'] ?? ''));
        $variantIds = $this->normalizedIds($payload['variant_ids'] ?? []);

        if ($store === '' || $customerId === '' || $patientId <= 0 || $reference === '' || $variantIds === []) {
            throw new RuntimeException('SHOPIFY_PRESCRIPTION_CART_PUSH_INVALID');
        }

        $db = db_connect();
        $prescription = $db->table('prescriptions p')
            ->select('p.id, p.prescription_number, p.patient_id, p.status, p.duration_days, p.valid_until')
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->where('p.prescription_number', $reference)
            ->where('p.patient_id', $patientId)
            ->get(1)->getRowArray();

        if ($prescription === null || ! ShopifyPrescriptionEligibility::isOrderable($prescription)) {
            throw new RuntimeException(ShopifyPrescriptionEligibility::REASON);
        }

        $allowed = $this->allowedVariantIds($store, (int) $prescription['id']);
        if (array_diff($variantIds, $allowed) !== []) {
            throw new RuntimeException('SHOPIFY_PRESCRIPTION_VARIANT_INVALID');
        }

        $model = new ShopifyPrescriptionCartPushModel();
        $existing = $model->where('store_domain', $store)->where('prescription_id', (int) $prescription['id'])->where('shopify_customer_id', $customerId)->first();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        if ($existing !== null) {
            $status = (string) ($existing['status'] ?? 'cart_pushed');
            if ($status === 'cart_pushed') {
                $model->update((int) $existing['id'], ['variant_ids_json' => json_encode($variantIds), 'cart_pushed_at' => $now, 'status' => 'cart_pushed', 'updated_at' => $now]);
            }
            return ['prescription_reference' => $reference, 'status' => $status, 'idempotent' => true];
        }

        try {
            $id = $model->insert([
                'store_domain' => $store,
                'prescription_id' => (int) $prescription['id'],
                'prescription_reference' => $reference,
                'patient_id' => $patientId,
                'shopify_customer_id' => $customerId,
                'variant_ids_json' => json_encode($variantIds),
                'cart_pushed_at' => $now,
                'status' => 'cart_pushed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            $raced = $model->where('store_domain', $store)->where('prescription_id', (int) $prescription['id'])->where('shopify_customer_id', $customerId)->first();
            if ($raced !== null) return ['prescription_reference' => $reference, 'status' => (string) ($raced['status'] ?? 'cart_pushed'), 'idempotent' => true];
            throw $exception;
        }
        if (! $id) throw new RuntimeException('SHOPIFY_PRESCRIPTION_CART_PUSH_FAILED');

        return ['prescription_reference' => $reference, 'status' => 'cart_pushed', 'idempotent' => false];
    }

    public function reconcileWebhookEvent(int $eventId, string $topic, string $store): void
    {
        $event = db_connect()->table('shopify_webhook_events')->where('id', $eventId)->get(1)->getRowArray();
        $payload = is_array($event) ? json_decode((string) ($event['payload_json'] ?? ''), true) : null;
        if (! is_array($payload)) return;
        $reference = trim((string) ($payload['prescription_reference'] ?? ''));
        if ($reference === '') return;
        $status = match ($topic) {
            'orders/create' => 'order_created',
            'orders/paid' => 'ordered',
            'orders/cancelled' => 'cancelled',
            'refunds/create' => 'refunded',
            default => null,
        };
        if ($status === null) return;
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $changes = ['status' => $status, 'shopify_order_id' => (string) ($payload['id'] ?? $payload['order_id'] ?? ''), 'last_event_topic' => $topic, 'last_event_id' => $eventId, 'updated_at' => $now];
        if ($status === 'ordered') $changes['ordered_at'] = $now;
        db_connect()->table('shopify_prescription_cart_pushes')->where('store_domain', ShopifyProxyConfigurationService::normalizeStoreDomain($store))->where('prescription_reference', $reference)->update($changes);
    }

    /** @return list<string> */
    private function allowedVariantIds(string $store, int $prescriptionId): array
    {
        $rows = db_connect()->table('shopify_medicine_mappings m')
            ->select('m.shopify_variant_id')
            ->join('prescription_items pi', 'pi.medicine_id = m.medicine_id')
            ->join('shopify_variant_catalogue v', 'v.store_domain = m.store_domain AND v.shopify_variant_id = m.shopify_variant_id AND v.is_active = 1')
            ->where('pi.prescription_id', $prescriptionId)->where('m.store_domain', $store)->where('m.status', 'active')->get()->getResultArray();
        return $this->normalizedIds(array_column($rows, 'shopify_variant_id'));
    }

    /** @return list<string> */
    private function normalizedIds(mixed $ids): array
    {
        if (! is_array($ids)) $ids = [$ids];
        $result = [];
        foreach ($ids as $id) {
            $value = trim((string) $id);
            if (preg_match('#^gid://shopify/ProductVariant/(\d+)$#i', $value, $match) === 1) $value = $match[1];
            if (ctype_digit($value) && (int) $value > 0) $result[] = $value;
        }
        return array_values(array_unique($result));
    }
}
