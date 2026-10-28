<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyPrescriptionCartPushModel;
use DateTimeImmutable;
use RuntimeException;

final class ShopifyPrescriptionCartPushService
{
    /** @return array{reference:string,cart_status:string,updated_items:int} */
    public function record(array $context, array $payload): array
    {
        $store = ShopifyProxyConfigurationService::normalizeStoreDomain((string) ($context['store_domain'] ?? ''));
        $customerId = trim((string) ($context['customer_id'] ?? ''));
        $patientId = (int) ($context['patient_id'] ?? 0);
        $reference = trim((string) ($payload['reference'] ?? $payload['prescription_reference'] ?? ''));
        $cartStatus = strtolower(trim((string) ($payload['cart_status'] ?? '')));
        $idempotencyKey = trim((string) ($payload['_idempotency_key'] ?? ''));
        $items = $this->normalizeItems($payload['items'] ?? []);

        if ($store === '' || $customerId === '' || $patientId <= 0 || $reference === '' || $cartStatus !== 'pushed' || $items === [] || $idempotencyKey === '') {
            throw new RuntimeException('SHOPIFY_PRESCRIPTION_CART_STATUS_INVALID');
        }

        $requestHash = hash('sha256', json_encode(['reference' => $reference, 'cart_status' => $cartStatus, 'items' => $items], JSON_UNESCAPED_SLASHES));
        $model = new ShopifyPrescriptionCartPushModel();
        $existingByKey = $model->where('store_domain', $store)->where('shopify_customer_id', $customerId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existingByKey !== null) {
            if ((string) ($existingByKey['request_hash'] ?? '') !== $requestHash) throw new RuntimeException('SHOPIFY_IDEMPOTENCY_KEY_REUSED');
            return ['reference' => (string) $existingByKey['prescription_reference'], 'cart_status' => 'cart_pushed', 'updated_items' => $this->storedItemCount($existingByKey)];
        }

        $db = db_connect();
        $prescription = $db->table('prescriptions p')
            ->select('p.id, p.prescription_number, p.patient_id, p.status, p.created_at, p.duration_days')
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->where('p.prescription_number', $reference)->where('p.patient_id', $patientId)
            ->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)->get(1)->getRowArray();
        $validUntil = $prescription === null ? null : ShopifyPrescriptionEligibility::calculateValidUntil((string) ($prescription['created_at'] ?? ''), $prescription['duration_days'] ?? null);
        if ($prescription === null || $validUntil === null || $validUntil < ShopifyPrescriptionEligibility::today()) {
            throw new RuntimeException(ShopifyPrescriptionEligibility::REASON);
        }

        $existing = $model->where('store_domain', $store)->where('prescription_id', (int) $prescription['id'])->where('shopify_customer_id', $customerId)->first();
        if ($existing !== null && in_array((string) ($existing['status'] ?? ''), ['ordered', 'paid', 'cancelled', 'expired'], true)) {
            throw new RuntimeException('SHOPIFY_PRESCRIPTION_NOT_CART_ELIGIBLE');
        }
        $allowed = $this->allowedItems($db, $store, (int) $prescription['id'], $reference, $existing);
        foreach ($items as $item) {
            $variant = $item['variant_id'];
            if (! isset($allowed[$variant])) throw new RuntimeException('SHOPIFY_PRESCRIPTION_VARIANT_INVALID');
            if ($item['quantity'] > $allowed[$variant]['quantity_remaining']) throw new RuntimeException('SHOPIFY_PRESCRIPTION_QUANTITY_EXCEEDED');
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if ($existing !== null) {
            $model->update((int) $existing['id'], [
                'variant_ids_json' => json_encode(array_column($items, 'variant_id')),
                'items_json' => json_encode($items), 'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash, 'cart_pushed_at' => $now, 'status' => 'cart_pushed', 'updated_at' => $now,
            ]);
            return ['reference' => $reference, 'cart_status' => 'cart_pushed', 'updated_items' => count($items)];
        }

        try {
            $inserted = $model->insert([
                'store_domain' => $store, 'prescription_id' => (int) $prescription['id'], 'prescription_reference' => $reference,
                'patient_id' => $patientId, 'shopify_customer_id' => $customerId,
                'variant_ids_json' => json_encode(array_column($items, 'variant_id')), 'items_json' => json_encode($items),
                'idempotency_key' => $idempotencyKey, 'request_hash' => $requestHash,
                'cart_pushed_at' => $now, 'status' => 'cart_pushed', 'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            $raced = $model->where('store_domain', $store)->where('prescription_id', (int) $prescription['id'])->where('shopify_customer_id', $customerId)->first();
            if ($raced !== null) return ['reference' => $reference, 'cart_status' => 'cart_pushed', 'updated_items' => $this->storedItemCount($raced)];
            throw $exception;
        }
        if (! $inserted) throw new RuntimeException('SHOPIFY_PRESCRIPTION_CART_STATUS_FAILED');
        return ['reference' => $reference, 'cart_status' => 'cart_pushed', 'updated_items' => count($items)];
    }

    public function reconcileWebhookEvent(int $eventId, string $topic, string $store): void
    {
        $event = db_connect()->table('shopify_webhook_events')->where('id', $eventId)->get(1)->getRowArray();
        $payload = is_array($event) ? json_decode((string) ($event['payload_json'] ?? ''), true) : null;
        if (! is_array($payload)) return;
        $reference = trim((string) ($payload['prescription_reference'] ?? ''));
        if ($reference === '') return;
        $status = match ($topic) {
            'orders/create' => 'ordered',
            'orders/paid' => 'paid',
            'orders/cancelled' => 'cancelled',
            'refunds/create' => 'cancelled',
            default => null,
        };
        if ($status === null) return;
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $changes = ['status' => $status, 'shopify_order_id' => (string) ($payload['id'] ?? $payload['order_id'] ?? ''), 'last_event_topic' => $topic, 'last_event_id' => $eventId, 'updated_at' => $now];
        if ($status === 'paid') $changes['ordered_at'] = $now;
        db_connect()->table('shopify_prescription_cart_pushes')->where('store_domain', ShopifyProxyConfigurationService::normalizeStoreDomain($store))->where('prescription_reference', $reference)->update($changes);
    }

    /** @return array<string,array{quantity_remaining:int}> */
    private function allowedItems(object $db, string $store, int $prescriptionId, string $reference, ?array $existing): array
    {
        $rows = $db->table('shopify_medicine_mappings m')
            ->select('m.shopify_variant_id, pi.quantity')
            ->join('prescription_items pi', 'pi.medicine_id = m.medicine_id AND pi.prescription_id = ' . $prescriptionId, 'inner')
            ->join('shopify_variant_catalogue v', 'v.store_domain = m.store_domain AND v.shopify_variant_id = m.shopify_variant_id AND v.is_active = 1', 'inner')
            ->where('m.store_domain', $store)->where('m.status', 'active')->get()->getResultArray();
        $ordered = [];
        if ($db->tableExists('shopify_order_imports') && $db->tableExists('shopify_order_import_lines')) {
            foreach ($db->table('shopify_order_import_lines l')->select('l.shopify_variant_id, SUM(l.quantity) AS total_quantity')->join('shopify_order_imports oi', 'oi.id = l.import_id', 'inner')->where('oi.prescription_reference', $reference)->whereNotIn('oi.status', ['cancelled', 'quarantined'])->groupBy('l.shopify_variant_id')->get()->getResultArray() as $row) {
                $ordered[$this->cartVariantId((string) $row['shopify_variant_id'])] = (int) $row['total_quantity'];
            }
        }
        $result = [];
        foreach ($rows as $row) {
            $variant = $this->cartVariantId((string) $row['shopify_variant_id']);
            if ($variant === null) continue;
            $remaining = max(0, (int) $row['quantity'] - (int) ($ordered[$variant] ?? 0));
            if ($existing !== null && in_array((string) ($existing['status'] ?? ''), ['ordered', 'paid'], true)) $remaining = 0;
            $result[$variant] = ['quantity_remaining' => $remaining];
        }
        return $result;
    }

    /** @return list<array{variant_id:string,quantity:int}> */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) return [];
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $variant = $this->cartVariantId((string) ($item['variant_id'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variant === null || $quantity === false) throw new RuntimeException('SHOPIFY_PRESCRIPTION_CART_STATUS_ITEM_INVALID');
            $normalized[$variant] = ($normalized[$variant] ?? 0) + (int) $quantity;
        }
        $result = [];
        foreach ($normalized as $variant => $quantity) $result[] = ['variant_id' => $variant, 'quantity' => $quantity];
        usort($result, static fn (array $a, array $b): int => $a['variant_id'] <=> $b['variant_id']);
        return $result;
    }

    private function storedItemCount(array $row): int
    {
        $items = json_decode((string) ($row['items_json'] ?? ''), true);
        return is_array($items) ? count($items) : count((array) json_decode((string) ($row['variant_ids_json'] ?? '[]'), true));
    }

    private function cartVariantId(string $value): ?string
    {
        $value = trim($value);
        if (ctype_digit($value) && (int) $value > 0) return $value;
        if (preg_match('#^gid://shopify/ProductVariant/(\d+)$#i', $value, $matches) === 1) return $matches[1];
        return null;
    }
}
