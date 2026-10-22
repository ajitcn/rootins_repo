<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyWebhookEventModel;

final class ShopifyWebhookEventService
{
    public function __construct(private readonly ?ShopifyWebhookEventModel $model = null)
    {
    }

    /**
     * Store a verified webhook event exactly once.
     *
     * @return array{accepted: bool, duplicate: bool, id: int|null}
     */
    public function ingest(string $storeDomain, string $eventId, string $topic, string $rawBody): array
    {
        $eventId = trim($eventId);
        $topic = trim($topic);
        $storeDomain = strtolower(trim($storeDomain));

        if ($eventId === '' || $topic === '' || $storeDomain === '') {
            return ['accepted' => false, 'duplicate' => false, 'id' => null];
        }

        $model = $this->model ?? new ShopifyWebhookEventModel();
        $existing = $model
            ->where('store_domain', $storeDomain)
            ->where('external_event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return [
                'accepted' => true,
                'duplicate' => true,
                'id' => (int) ($existing['id'] ?? 0),
            ];
        }

        try {
            $id = $model->insert([
                'store_domain' => $storeDomain,
                'external_event_id' => $eventId,
                'topic' => $topic,
                'payload_hash' => hash('sha256', $rawBody),
                'payload_json' => $this->safePayload($rawBody),
                'status' => 'received',
                'retry_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            return ['accepted' => true, 'duplicate' => false, 'id' => (int) $id];
        } catch (\Throwable $exception) {
            $existing = $model
                ->where('store_domain', $storeDomain)
                ->where('external_event_id', $eventId)
                ->first();

            if ($existing !== null) {
                return [
                    'accepted' => true,
                    'duplicate' => true,
                    'id' => (int) ($existing['id'] ?? 0),
                ];
            }

            log_message('error', 'Shopify webhook event storage failed safely.');

            return ['accepted' => false, 'duplicate' => false, 'id' => null];
        }
    }

    public function markProcessed(int $eventId, ?string $errorCategory = null): bool
    {
        $data = [
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $data['error_message'] = $errorCategory === null ? null : ShopifyOrderImportFailure::normalize($errorCategory);

        return $this->update($eventId, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $eventId): ?array
    {
        $model = $this->model ?? new ShopifyWebhookEventModel();

        return $model->where('id', $eventId)->first();
    }

    /** @return array<string, mixed>|null */
    public function findLatestCustomerEvent(string $storeDomain, string $customerId): ?array
    {
        $model = $this->model ?? new ShopifyWebhookEventModel();

        return $model
            ->where('store_domain', strtolower(trim($storeDomain)))
            ->whereIn('topic', ['customers/create', 'customers/update'])
            ->like('payload_json', '"id":"' . trim($customerId) . '"')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Persist only minimized identity/order fields needed for processing.
     * Full Shopify webhook payloads are intentionally never stored.
     */
    private function safePayload(string $rawBody): ?string
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return null;
        }

        $safe = [];

        if ((isset($payload['line_items']) && is_array($payload['line_items'])) || isset($payload['order_id'])) {
            foreach (['id', 'order_id', 'order_number', 'currency', 'total_price', 'financial_status', 'fulfillment_status', 'cancelled_at', 'gateway', 'shipping_provider', 'tracking_number'] as $key) {
                if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
                    $safe[$key] = $payload[$key];
                }
            }
            if (!isset($safe['id']) && isset($payload['order_id'])) {
                $safe['id'] = (string) $payload['order_id'];
            }
            $safe['customer_id'] = (string) ($payload['customer']['id'] ?? '');
            $safe['shipping_address'] = $this->safeAddress($payload['shipping_address'] ?? []);
            $safe['line_items'] = [];

            foreach ((array) ($payload['line_items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $safe['line_items'][] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'variant_id' => (string) ($item['variant_id'] ?? ''),
                    'title' => (string) ($item['title'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'price' => (float) ($item['price'] ?? 0),
                    'properties' => $this->safeAppointmentProperties($item['properties'] ?? []),
                ];
            }
            $safe['rootins_prescription_id'] = $this->prescriptionReference($payload);
            $safe['prescription_reference'] = $this->shopifyPrescriptionReference($payload);
            $safe['prescription_bundle_reference'] = $this->shopifyPrescriptionBundleReference($payload);
            $safe['rootins_appointment_intent'] = $this->appointmentIntentReference($payload);

            return json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        foreach (['id', 'email', 'phone', 'first_name', 'last_name'] as $key) {
            if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
                $safe[$key] = (string) $payload[$key];
            }
        }

        return $safe === [] ? null : json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return array<string, string> */
    private function safeAddress(mixed $address): array
    {
        if (!is_array($address)) {
            return [];
        }

        $safe = [];

        foreach (['address1', 'address2', 'city', 'province', 'country', 'zip', 'phone'] as $field) {
            if (isset($address[$field]) && is_scalar($address[$field])) {
                $safe[$field] = (string) $address[$field];
            }
        }

        return $safe;
    }

    public function replacePayload(int $eventId, string $payload): bool
    {
        return $this->update($eventId, [
            'payload_json' => $payload,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function prescriptionReference(array $payload): ?int
    {
        foreach ((array) ($payload['note_attributes'] ?? []) as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            $name = strtolower(trim((string) ($attribute['name'] ?? '')));

            if (in_array($name, ['rootins_prescription_id', 'prescription_id'], true)) {
                $id = (int) ($attribute['value'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }

    private function shopifyPrescriptionReference(array $payload): ?string
    {
        $values = [];
        foreach ((array) ($payload['metafields'] ?? []) as $field) {
            if (is_array($field) && strtolower(trim((string) ($field['namespace'] ?? ''))) === 'rootins' && strtolower(trim((string) ($field['key'] ?? ''))) === 'prescription_reference') $values[] = (string) ($field['value'] ?? '');
        }
        foreach ([(array) ($payload['note_attributes'] ?? []), (array) ($payload['attributes'] ?? [])] as $attributes) {
            foreach ($attributes as $attribute) {
                if (!is_array($attribute)) continue;
                $name = strtolower(trim((string) ($attribute['name'] ?? $attribute['key'] ?? '')));
                if (in_array($name, ['rootins.prescription_reference', 'rootins_prescription_reference'], true)) $values[] = (string) ($attribute['value'] ?? '');
            }
        }
        foreach ((array) ($payload['line_items'] ?? []) as $item) {
            foreach ((array) ($item['properties'] ?? []) as $property) {
                if (!is_array($property)) continue;
                $name = strtolower(trim((string) ($property['name'] ?? '')));
                if (in_array($name, ['rootins.prescription_reference', 'rootins_prescription_reference'], true)) $values[] = (string) ($property['value'] ?? '');
            }
        }
        foreach ($values as $value) {
            $value = trim($value);
            if (preg_match('/^RXP-[A-Z0-9-]{16,191}$/', $value)) return $value;
        }
        return null;
    }

    private function shopifyPrescriptionBundleReference(array $payload): ?string
    {
        foreach ([(array) ($payload['note_attributes'] ?? []), (array) ($payload['attributes'] ?? [])] as $attributes) {
            foreach ($attributes as $attribute) {
                if (!is_array($attribute)) continue;
                $name = strtolower(trim((string) ($attribute['name'] ?? $attribute['key'] ?? '')));
                $value = trim((string) ($attribute['value'] ?? ''));
                if (in_array($name, ['rootins.prescription_bundle_reference', 'rootins_prescription_bundle_reference'], true) && preg_match('/^RXB-[A-Z0-9-]{16,191}$/', $value)) return $value;
            }
        }
        return null;
    }

    private function appointmentIntentReference(array $payload): ?string
    {
        foreach ((array) ($payload['note_attributes'] ?? []) as $attribute) {
            if (!is_array($attribute) || strtolower(trim((string) ($attribute['name'] ?? ''))) !== 'rootins_appointment_intent') continue;
            $value = trim((string) ($attribute['value'] ?? ''));
            return preg_match('/^RINT-[A-Z0-9]+$/', $value) === 1 ? $value : null;
        }
        foreach ((array) ($payload['line_items'] ?? []) as $item) {
            foreach ((array) ($item['properties'] ?? []) as $property) {
                if (!is_array($property) || strtolower(trim((string) ($property['name'] ?? ''))) !== 'rootins_appointment_intent') continue;
                $value = trim((string) ($property['value'] ?? ''));
                return preg_match('/^RINT-[A-Z0-9]+$/', $value) === 1 ? $value : null;
            }
        }
        return null;
    }

    /** @return list<array{name:string,value:string}> */
    private function safeAppointmentProperties(mixed $properties): array
    {
        $safe = [];
        foreach ((array) $properties as $property) {
            if (!is_array($property) || strtolower(trim((string) ($property['name'] ?? ''))) !== 'rootins_appointment_intent') continue;
            $value = trim((string) ($property['value'] ?? ''));
            if (preg_match('/^RINT-[A-Z0-9]+$/', $value) === 1) $safe[] = ['name' => 'rootins_appointment_intent', 'value' => $value];
        }
        return $safe;
    }

    public function markFailed(int $eventId, string $error): bool
    {
        return $this->update($eventId, [
            'status' => 'failed',
            'error_message' => ShopifyOrderImportFailure::normalize($error),
            'retry_count' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function removeEndpointTestEvents(string $storeDomain, string $eventId): void
    {
        ($this->model ?? new ShopifyWebhookEventModel())
            ->where('store_domain', strtolower(trim($storeDomain)))
            ->where('external_event_id', trim($eventId))
            ->where('topic', 'rootins/endpoint-test')
            ->delete();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(int $eventId, array $data): bool
    {
        $model = $this->model ?? new ShopifyWebhookEventModel();

        return (bool) $model->where('id', $eventId)->set($data)->update();
    }
}
