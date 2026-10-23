<?php

namespace App\Services\Shopify;

use Admin\Models\PrescriptionModel;
use App\Modules\Pharmacy\Services\PharmacyScope;
use App\Modules\Pharmacy\Services\OrderFulfilmentService;
use App\Modules\Pharmacy\Services\TransitionContract;
use App\Modules\Shopify\Models\ShopifyMedicineMappingModel;
use App\Modules\Shopify\Models\ShopifyOrderImportLineModel;
use App\Modules\Shopify\Models\ShopifyOrderImportModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ShopifyOrderImportService
{
    public function __construct(
        private readonly ?ShopifyOrderImportModel $imports = null,
        private readonly ?ShopifyOrderImportLineModel $lines = null,
        private readonly ?ShopifyMedicineMappingModel $mappings = null,
        private readonly ?ShopifyWebhookEventService $events = null,
        private readonly ?ShopifyConfiguration $configuration = null,
    ) {
    }

    /**
     * Process one already verified and idempotently stored webhook event.
     * Only a verified customer link, explicit product mappings, and an
     * approved ROOTINS prescription can enter the existing fulfilment flow.
     */
    public function processEvent(int $eventId): array
    {
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $events->find($eventId);

        if ($event === null) {
            throw new RuntimeException('Shopify webhook event was not found.');
        }

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);

        if (!is_array($payload) || trim((string) ($payload['id'] ?? '')) === '') {
            $events->markFailed($eventId, 'Order payload is unavailable.');

            return ['status' => 'failed', 'import_id' => null];
        }

        $store = strtolower(trim((string) $event['store_domain']));
        $import = $this->upsertImport($event, $payload);
        $importId = (int) $import['id'];

        $financialStatus = strtolower(trim((string) ($payload['financial_status'] ?? '')));
        if (trim((string) ($payload['cancelled_at'] ?? '')) !== '' || in_array($financialStatus, ['refunded', 'voided'], true)) {
            $this->setImportState($importId, 'cancelled', ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, 'last_error' => 'Shopify order is cancelled or refunded.']);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED);
            service('audit')->record('shopify.order_cancelled', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'cancelled_or_refunded']);
            return ['status' => 'cancelled', 'import_id' => $importId];
        }
        if ($financialStatus !== '' && ! in_array($financialStatus, ['paid', 'authorized', 'partially_paid'], true)) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, 'last_error' => 'Shopify payment is not in an importable state.']);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::PAYMENT_STATE_INVALID);
            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        if (in_array((string) ($import['status'] ?? ''), ['imported', 'reconciled'], true) && (int) ($import['rootins_order_id'] ?? 0) > 0) {
            $this->applyExternalStatuses((int) $import['rootins_order_id'], $payload, $store, $eventId, (int) ($import['clinic_id'] ?? 0), (string) ($event['topic'] ?? ''));
            $events->markProcessed($eventId, ShopifyOrderImportFailure::CUSTOMER_NOT_LINKED);

            return ['status' => (string) $import['status'], 'import_id' => $importId];
        }

        $identity = $this->linkedCustomer($store, (string) ($payload['customer_id'] ?? ''));

        if ($identity === null) {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'missing', 'quarantine_reason' => ShopifyOrderImportFailure::CUSTOMER_NOT_LINKED, 'last_attempted_at' => date('Y-m-d H:i:s')]);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::CUSTOMER_NOT_LINKED, $eventId);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::UNKNOWN_IMPORT_FAILURE);
            service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'customer_not_linked']);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $clinicId = ($this->configuration ?? new ShopifyConfiguration())->clinicId();
        $patientId = (int) ($identity['patient_id'] ?? 0);

        if ($patientId <= 0) {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'missing', 'quarantine_reason' => ShopifyOrderImportFailure::CUSTOMER_NOT_LINKED, 'last_attempted_at' => date('Y-m-d H:i:s')]);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::CUSTOMER_NOT_LINKED, $eventId);
            $events->markProcessed($eventId);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'linked', 'last_attempted_at' => date('Y-m-d H:i:s')]);

        // Clinic scope is a financial/fulfilment concern. A globally stored
        // prescription and its patient identity must not depend on a clinic.
        if ($clinicId === null) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::BILLING_CLINIC_UNAVAILABLE, $eventId, $patientId);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::BILLING_CLINIC_UNAVAILABLE);
            service('audit')->record('shopify.order_quarantined', [
                'event_id' => $eventId,
                'import_id' => $importId,
                'patient_id' => $patientId,
                'reason' => 'billing_clinic_unavailable',
            ]);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $prescriptionReference = trim((string) ($payload['prescription_reference'] ?? ''));
        if ($prescriptionReference !== '') {
            $document = db_connect()->table('patient_documents')
                ->where('source_type', 'shopify')->where('external_upload_reference', $prescriptionReference)
                ->where('shopify_store_domain', $store)->where('shopify_customer_id', (string) ($payload['customer_id'] ?? ''))
                ->where('patient_id', $patientId)->get(1)->getRowArray();
            $expired = $document !== null && ! empty($document['retention_expires_at']) && (string) $document['retention_expires_at'] < date('Y-m-d H:i:s');
            if ($document === null || $expired) {
                $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_MISSING, $eventId, $patientId, $clinicId);
                ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => 'invalid']);
                $events->markProcessed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'invalid_prescription_reference']);
                return ['status' => 'quarantined', 'import_id' => $importId];
            }
            db_connect()->table('patient_documents')->where('id', (int) $document['id'])->update(['shopify_order_id' => (string) $payload['id'], 'upload_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review', 'updated_at' => date('Y-m-d H:i:s')]);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review']);
            if (($document['upload_status'] ?? '') !== 'accepted') {
                $this->setImportState($importId, 'quarantined', 'awaiting_review', $eventId, $patientId, $clinicId);
                $events->markProcessed($eventId, 'awaiting_review');
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'prescription_awaiting_review']);
                return ['status' => 'quarantined', 'import_id' => $importId];
            }
        }

        $mappingResult = $this->storeLinesAndResolveMappings($importId, $store, $payload);

        if ($mappingResult['status'] !== 'ready') {
            $category = (string) ($mappingResult['category'] ?? ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED);
            $inactiveMedicine = $category === ShopifyOrderImportFailure::MEDICINE_INACTIVE;
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['mapping_status' => (string) ($mappingResult['mapping_status'] ?? ($inactiveMedicine ? 'inactive_medicine' : 'unmapped')), 'quarantine_reason' => $category, 'last_error' => (string) ($mappingResult['reason'] ?? $category)]);
            $this->setImportState($importId, $inactiveMedicine ? 'quarantined' : 'pending_mapping', $category, $eventId, $patientId, $clinicId);
            $events->markProcessed($eventId, $category);
            service('audit')->record($inactiveMedicine ? 'shopify.order_quarantined' : 'shopify.order_pending_mapping', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => $mappingResult['reason']]);

            return ['status' => $inactiveMedicine ? 'quarantined' : 'pending_mapping', 'import_id' => $importId];
        }

        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['mapping_status' => 'mapped']);

        $prescriptionRequired = $this->prescriptionRequiredForOrder($store, $payload);
        if (trim((string) ($payload['prescription_reference'] ?? '')) === '') {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_status' => $prescriptionRequired ? 'missing' : 'not_required']);
        }
        $prescriptionId = $this->candidatePrescriptionId($payload);

        if ($prescriptionRequired && ($prescriptionId === null || !$this->approvedPrescriptionMatches($prescriptionId, $patientId, $clinicId))) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_MISSING, $eventId, $patientId, $clinicId);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
            service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'missing_prescription']);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $result = (new OrderFulfilmentService(new PharmacyScope(), $clinicId))->create($prescriptionId, [
            'source_type' => 'shopify',
            'external_store_domain' => $store,
            'external_order_id' => (string) $payload['id'],
            'external_order_number' => (string) ($payload['order_number'] ?? ''),
            'delivery_address_snapshot' => $this->addressSnapshot($payload['shipping_address'] ?? []),
        ]);

        if ((int) ($result['http'] ?? 500) !== 200) {
            $category = ShopifyOrderImportFailure::fromFulfilmentResult($result);
            $this->setImportState($importId, 'failed', $category, $eventId, $patientId, $clinicId);
            throw new ShopifyOrderImportFailure($category, 'ROOTINS order fulfilment failed.');
        }

        $rootinsOrderId = (int) ($result['body']['data']['order_id'] ?? 0);
        $this->setImportState($importId, 'imported', null, $eventId, $patientId, $clinicId, $rootinsOrderId);
        $this->linkImportedLines($importId, $rootinsOrderId);
        $this->applyExternalStatuses($rootinsOrderId, $payload, $store, $eventId, $clinicId, (string) ($event['topic'] ?? ''));
        $events->markProcessed($eventId);
        service('audit')->record('shopify.order_imported', ['event_id' => $eventId, 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId, 'clinic_id' => $clinicId]);

        return ['status' => 'imported', 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId];
    }

    /** @return list<array<string, mixed>> */
    public function queue(): array
    {
        return ($this->imports ?? new ShopifyOrderImportModel())
            ->orderBy('updated_at', 'DESC')
            ->findAll(200);
    }

    public function markFailureForEvent(int $eventId, string $category): bool
    {
        return (bool) ($this->imports ?? new ShopifyOrderImportModel())->where('last_event_id', $eventId)->set([
            'status' => 'failed',
            'failure_reason' => ShopifyOrderImportFailure::normalize($category),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function importExisting(int $importId, ?int $prescriptionId = null): array
    {
        $import = ($this->imports ?? new ShopifyOrderImportModel())->find($importId);

        if ($import === null) {
            return ['http' => 404, 'body' => ['status' => false, 'msg' => 'Shopify order import was not found.']];
        }

        $eventId = (int) ($import['last_event_id'] ?? 0);
        $event = ($this->events ?? new ShopifyWebhookEventService())->find($eventId);

        if ($event === null) {
            return ['http' => 422, 'body' => ['status' => false, 'msg' => 'The original Shopify event is unavailable.']];
        }

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);

        if (!is_array($payload)) {
            return ['http' => 422, 'body' => ['status' => false, 'msg' => 'The Shopify order data is unavailable.']];
        }

        if ($prescriptionId !== null) {
            $payload['rootins_prescription_id'] = $prescriptionId;
            $event['payload_json'] = json_encode($payload);
            $events = $this->events ?? new ShopifyWebhookEventService();

            if (!$events->replacePayload((int) $event['id'], $event['payload_json'])) {
                return ['http' => 500, 'body' => ['status' => false, 'msg' => 'Shopify order mapping could not be saved safely.']];
            }
        }

        try {
            $result = $this->processEvent($eventId);

            return ['http' => 200, 'body' => ['status' => $result['status'] === 'imported', 'msg' => 'Shopify order reconciliation completed.', 'data' => $result]];
        } catch (\Throwable $exception) {
            $failure = ShopifyOrderImportFailure::fromException($exception, (string) ($event['topic'] ?? ''));
            $this->markFailureForEvent($eventId, $failure->category);
            ($this->events ?? new ShopifyWebhookEventService())->markFailed($eventId, $failure->category);
            log_message('error', 'Shopify order reconciliation failed category=' . $failure->category . ' topic=' . (string) ($event['topic'] ?? '') . ' exception=' . get_class($exception) . ' file=' . basename($exception->getFile()) . ' line=' . $exception->getLine());

            return ['http' => 500, 'body' => ['status' => false, 'msg' => 'Shopify order could not be reconciled safely.', 'data' => ['error_category' => $failure->category]]];
        }
    }

    /** @return array<string, mixed> */
    private function upsertImport(array $event, array $payload): array
    {
        $model = $this->imports ?? new ShopifyOrderImportModel();
        $store = strtolower(trim((string) $event['store_domain']));
        $orderId = trim((string) $payload['id']);
        $existing = $model->where('store_domain', $store)->where('shopify_order_id', $orderId)->first();
        $data = [
            'store_domain' => $store,
            'shopify_order_id' => $orderId,
            'shopify_order_number' => (string) ($payload['order_number'] ?? ''),
            'financial_status' => (string) ($payload['financial_status'] ?? ''),
            'fulfillment_status' => (string) ($payload['fulfillment_status'] ?? ''),
            'currency' => (string) ($payload['currency'] ?? ''),
            'total_snapshot' => (float) ($payload['total_price'] ?? 0),
            'shipping_address_snapshot' => $this->addressSnapshot($payload['shipping_address'] ?? []),
            'payload_hash' => (string) ($event['payload_hash'] ?? ''),
            'last_event_id' => (int) $event['id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'prescription_reference' => trim((string) ($payload['prescription_reference'] ?? '')) ?: null,
            'prescription_status' => trim((string) ($payload['prescription_reference'] ?? '')) !== '' ? 'awaiting_review' : null,
            'shopify_customer_id' => trim((string) ($payload['customer_id'] ?? '')) ?: null,
            'customer_name_snapshot' => trim((string) (($payload['customer']['first_name'] ?? '') . ' ' . ($payload['customer']['last_name'] ?? ''))) ?: null,
            'customer_email_snapshot' => trim((string) ($payload['customer']['email'] ?? '')) ?: null,
            'customer_mobile_snapshot' => trim((string) ($payload['customer']['phone'] ?? '')) ?: null,
            'last_attempted_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $data['status'] = 'received';
            $data['retry_count'] = 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            try {
                $model->insert($data);
            } catch (\Throwable $exception) {
                // The identity unique key is the concurrency boundary for two
                // deliveries of the same Shopify order. If another worker won
                // the insert race, continue with that row instead of creating
                // a second import or returning a duplicate-key failure.
                $existing = $model
                    ->where('store_domain', $store)
                    ->where('shopify_order_id', $orderId)
                    ->first();

                if ($existing === null) {
                    throw $exception;
                }

                $model->update((int) $existing['id'], $data);

                return $model->find((int) $existing['id']) ?? $existing;
            }

            return $model->where('id', (int) $model->getInsertID())->first() ?? $data + ['id' => (int) $model->getInsertID()];
        }

        $model->update((int) $existing['id'], $data);

        return $model->find((int) $existing['id']) ?? $existing;
    }

    /** @return array{status: string, reason: string} */
    private function storeLinesAndResolveMappings(int $importId, string $store, array $payload): array
    {
        $lineModel = $this->lines ?? new ShopifyOrderImportLineModel();
        $items = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];

        if ($items === []) {
            return ['status' => 'invalid', 'reason' => ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED, 'category' => ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED];
        }

        $unresolved = null;

        foreach ($items as $item) {
            $lineId = $this->shopifyId($item['id'] ?? null);
            $productId = $this->shopifyId($item['product_id'] ?? null);
            $variantId = $this->shopifyId($item['variant_id'] ?? null);
            $resolution = $this->resolveMapping($store, $productId, $variantId);
            $mapping = $resolution['mapping'];
            $medicineId = $mapping['medicine_id'] ?? null;
            $status = 'pending_mapping';

            if ($resolution['result'] === 'mapped') {
                $status = 'mapped';
            } elseif ($resolution['result'] === 'inactive') {
                $status = 'medicine_inactive';
                // Keep inactive medicines visibly quarantined at line level: ['status' => 'medicine_inactive'].
            }

            $existing = $lineModel->where('import_id', $importId)->where('shopify_line_item_id', $lineId)->first();
            $this->upsertImportLine($lineModel, $existing, [
                'import_id' => $importId,
                'shopify_line_item_id' => $lineId,
                'shopify_product_id' => $productId !== '' ? $productId : null,
                'shopify_variant_id' => $variantId !== '' ? $variantId : null,
                'medicine_id' => $medicineId,
                'title_snapshot' => (string) ($item['title'] ?? ''),
                'shopify_sku' => (string) ($item['sku'] ?? ''),
                'shopify_barcode' => (string) ($item['barcode'] ?? ''),
                'product_image_url' => filter_var((string) ($item['image_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $item['image_url'] : null,
                'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                'shopify_price_snapshot' => (float) ($item['price'] ?? 0),
                'status' => $existing !== null && (int) ($existing['rootins_order_item_id'] ?? 0) > 0 && $status === 'mapped' ? 'imported' : $status,
                'mapping_reason' => $resolution['reason'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($resolution['result'] !== 'mapped' && $unresolved === null) {
                $unresolved = $resolution;
            }

            log_message('debug', 'Shopify mapping resolution order=' . $this->shopifyId($payload['id'] ?? null)
                . ' line=' . $lineId . ' product=' . $productId . ' variant=' . $variantId
                . ' lookup=' . $resolution['lookup_key'] . ' result=' . $resolution['result']);
        }

        if ($unresolved !== null) {
            $category = $unresolved['result'] === 'inactive'
                ? ShopifyOrderImportFailure::MEDICINE_INACTIVE
                : ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED;

            return [
                'status' => 'pending',
                'reason' => $unresolved['reason'],
                'category' => $category,
                'mapping_status' => $unresolved['result'] === 'inactive' ? 'inactive_medicine' : ($unresolved['result'] === 'conflict' ? 'conflict' : 'unmapped'),
            ];
        }

        return ['status' => 'ready', 'reason' => ''];
    }

    private function prescriptionRequiredForOrder(string $store, array $payload): bool
    {
        $db = db_connect();
        foreach ((array) ($payload['line_items'] ?? []) as $item) {
            $variantId = $this->shopifyId($item['variant_id'] ?? null);
            $productId = $this->shopifyId($item['product_id'] ?? null);
            $required = null;
            if ($variantId !== '' && $db->tableExists('shopify_variant_catalogue')) {
                $variant = $db->table('shopify_variant_catalogue')->select('prescription_required')->where('store_domain', $store)->where('shopify_variant_id', $variantId)->get(1)->getRowArray();
                $required = $variant['prescription_required'] ?? null;
            }
            if ($required === null && $productId !== '' && $db->tableExists('shopify_product_catalogue')) {
                $product = $db->table('shopify_product_catalogue')->select('prescription_required')->where('store_domain', $store)->where('shopify_product_id', $productId)->get(1)->getRowArray();
                $required = $product['prescription_required'] ?? null;
            }
            if ((int) $required === 1) return true;
        }

        return false;
    }

    /** @return array{result: string, reason: ?string, mapping: ?array, lookup_key: string} */
    private function resolveMapping(string $store, string $productId, string $variantId): array
    {
        $db = db_connect();
        $lookupKey = $store . '|variant:' . ($variantId !== '' ? $variantId : 'none') . '|product:' . ($productId !== '' ? $productId : 'none');
        if ($db->tableExists('shopify_mapping_ignores') && $productId !== '' && $variantId !== '' && $db->table('shopify_mapping_ignores')->where('store_domain', $store)->where('shopify_product_id', $productId)->where('shopify_variant_id', $variantId)->countAllResults() > 0) {
            return ['result' => 'unmapped', 'reason' => 'Product mapping missing', 'mapping' => null, 'lookup_key' => $lookupKey];
        }
        $mappingRows = [];

        if ($variantId !== '') {
            $mappingRows = $db->table('shopify_medicine_mappings')
                ->where('store_domain', $store)
                ->where('shopify_variant_id', $variantId)
                ->get()->getResultArray();
        }

        if ($mappingRows === [] && $productId !== '') {
            $mappingRows = $db->table('shopify_medicine_mappings')
                ->where('store_domain', $store)
                ->where('shopify_product_id', $productId)
                ->groupStart()
                    ->where('shopify_variant_id', null)
                    ->orWhere('shopify_variant_id', '')
                ->groupEnd()
                ->get()->getResultArray();
        }

        if (count($mappingRows) > 1) {
            return ['result' => 'conflict', 'reason' => 'Mapping conflict', 'mapping' => null, 'lookup_key' => $lookupKey];
        }

        $mapping = $mappingRows[0] ?? null;
        if ($mapping !== null) {
            $medicine = $db->table('medicines')->select('id, status')->where('id', (int) ($mapping['medicine_id'] ?? 0))->get(1)->getRowArray();
            if ((string) ($mapping['status'] ?? '') !== 'active' || $medicine === null || (string) ($medicine['status'] ?? '') !== 'active') {
                return ['result' => 'inactive', 'reason' => 'ROOTINS medicine inactive', 'mapping' => $mapping, 'lookup_key' => $lookupKey];
            }

            return ['result' => 'mapped', 'reason' => 'Mapped', 'mapping' => $mapping, 'lookup_key' => $lookupKey];
        }

        $productExists = false;
        if ($productId !== '' && $db->tableExists('shopify_product_catalogue')) {
            $productExists = $db->table('shopify_product_catalogue')
                ->where('store_domain', $store)->where('shopify_product_id', $productId)->countAllResults() > 0;
        }

        $reason = $productId === '' || (! $productExists && $variantId === '')
            ? 'Product mapping missing'
            : ($productExists ? 'Variant mapping missing' : 'Product not found');

        return ['result' => 'unmapped', 'reason' => $reason, 'mapping' => null, 'lookup_key' => $lookupKey];
    }

    private function shopifyId(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /** @param array<string, mixed> $data */
    private function upsertImportLine(ShopifyOrderImportLineModel $model, ?array $existing, array $data): void
    {
        if ($existing !== null) {
            $model->update((int) $existing['id'], $data);
            return;
        }

        $data['created_at'] = $data['updated_at'];
        try {
            $model->insert($data);
        } catch (\Throwable $exception) {
            $race = $model->where('import_id', $data['import_id'])->where('shopify_line_item_id', $data['shopify_line_item_id'])->first();
            if ($race === null) {
                throw $exception;
            }
            $model->update((int) $race['id'], $data);
        }
    }

    private function linkedCustomer(string $store, string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }

        return (new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $store)
            ->where('shopify_customer_id', $customerId)
            ->where('link_status', 'linked')
            ->first() ?: null;
    }

    private function candidatePrescriptionId(array $payload): ?int
    {
        $value = (int) ($payload['rootins_prescription_id'] ?? 0);

        return $value > 0 ? $value : null;
    }

    private function approvedPrescriptionMatches(int $prescriptionId, int $patientId, int $clinicId): bool
    {
        $row = db_connect()->table('prescriptions')
            ->where('id', $prescriptionId)
            ->where('patient_id', $patientId)
            ->where('clinic_id', $clinicId)
            ->where('status', 'approved')
            ->get(1)
            ->getRowArray();

        return $row !== null;
    }

    private function setImportState(int $id, string $status, ?string $reason, int $eventId, int $patientId = 0, int $clinicId = 0, int $rootinsOrderId = 0): void
    {
        $data = ['status' => $status, 'failure_reason' => $reason, 'last_event_id' => $eventId, 'updated_at' => date('Y-m-d H:i:s')];

        if ($patientId > 0) {
            $data['patient_id'] = $patientId;
        }
        if ($clinicId > 0) {
            $data['clinic_id'] = $clinicId;
        }
        if ($rootinsOrderId > 0) {
            $data['rootins_order_id'] = $rootinsOrderId;
            $data['processed_at'] = date('Y-m-d H:i:s');
        }
        ($this->imports ?? new ShopifyOrderImportModel())->update($id, $data);
    }

    private function addressSnapshot(array $address): string
    {
        $parts = [];

        foreach (['address1', 'address2', 'city', 'province', 'country', 'zip'] as $field) {
            $value = trim((string) ($address[$field] ?? ''));

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }

    private function linkImportedLines(int $importId, int $orderId): void
    {
        $lineModel = $this->lines ?? new ShopifyOrderImportLineModel();
        $orderItems = db_connect()->table('medicine_order_items')
            ->where('order_id', $orderId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($orderItems as $item) {
            $line = $lineModel
                ->where('import_id', $importId)
                ->where('medicine_id', (int) ($item['medicine_id'] ?? 0))
                ->where('rootins_order_item_id', null)
                ->first();

            if ($line !== null) {
                $lineModel->update((int) $line['id'], [
                    'rootins_order_item_id' => (int) $item['id'],
                    'status' => 'imported',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function applyExternalStatuses(int $orderId, array $payload, string $store, int $eventId, int $clinicId, string $topic = ''): void
    {
        if (trim((string) ($payload['cancelled_at'] ?? '')) !== '') {
            $cancel = (new OrderFulfilmentService(new PharmacyScope(), $clinicId))->cancel(
                $orderId,
                'Shopify order cancellation received from a verified webhook.',
                true,
            );

            if ((int) ($cancel['http'] ?? 500) !== 200) {
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, 'Shopify cancellation could not be applied to the ROOTINS pharmacy order.');
            }

            service('audit')->record('shopify.order_cancelled', [
                'order_id' => $orderId,
                'event_id' => $eventId,
                'store_domain' => $store,
                'clinic_id' => $clinicId,
            ]);

            return;
        }

        $db = db_connect();
        $db->transBegin();
        $order = $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->get(1)->getRowArray();

        if ($order === null) {
            $db->transRollback();

            throw new ShopifyOrderImportFailure($topic === 'refunds/create' ? ShopifyOrderImportFailure::REFUND_ORDER_NOT_FOUND : ShopifyOrderImportFailure::ORDER_NOT_FOUND, 'Imported ROOTINS order is outside the configured clinic scope.');
        }

        $paymentTarget = $this->paymentTarget((string) ($payload['financial_status'] ?? ''));
        $deliveryTarget = $this->deliveryTarget((string) ($payload['fulfillment_status'] ?? ''), '');
        $rawFulfillment = strtolower(trim((string) ($payload['fulfillment_status'] ?? '')));
        if ($rawFulfillment !== '' && ! in_array($rawFulfillment, ['pending', 'packed', 'fulfilled', 'out_for_delivery', 'delivered', 'failed'], true)) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::INVALID_FULFILLMENT_STATE, 'Shopify fulfillment state is not supported.');
        }
        $contract = new TransitionContract();
        $changes = [
            'payment_provider' => 'shopify',
            'payment_reference' => (string) ($payload['gateway'] ?? ''),
            'delivery_provider' => (string) ($payload['shipping_provider'] ?? ''),
            'tracking_reference' => (string) ($payload['tracking_number'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($paymentTarget !== null && $paymentTarget !== (string) $order['payment_status']) {
            if (!$contract->allows('payment_billing', (string) $order['payment_status'], $paymentTarget)) {
                $db->transRollback();

                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, 'Shopify payment transition is not allowed.');
            }
            $changes['payment_status'] = $paymentTarget;
        }

        if ($deliveryTarget !== null && $deliveryTarget !== (string) $order['delivery_status']) {
            if (!$contract->allows('delivery', (string) $order['delivery_status'], $deliveryTarget)) {
                $db->transRollback();

                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::DELIVERY_STATE_INVALID, 'Shopify delivery transition is not allowed.');
            }
            $changes['delivery_status'] = $deliveryTarget;
        }

        if ($deliveryTarget === 'delivered') {
            $changes['delivered_at'] = date('Y-m-d H:i:s');
        }
        if ($deliveryTarget === 'failed') {
            $changes['failure_reason'] = ShopifyOrderImportFailure::DELIVERY_STATE_INVALID;
        }

        $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->update($changes);

        if (!$db->transStatus()) {
            $db->transRollback();

            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::UNKNOWN_IMPORT_FAILURE, 'Shopify order status update failed.');
        }
        $db->transCommit();
        service('audit')->record('shopify.order_status_synchronized', [
            'order_id' => $orderId,
            'event_id' => $eventId,
            'store_domain' => $store,
            'clinic_id' => $clinicId,
            'previous_payment_status' => $order['payment_status'],
            'new_payment_status' => $changes['payment_status'] ?? $order['payment_status'],
            'previous_delivery_status' => $order['delivery_status'],
            'new_delivery_status' => $changes['delivery_status'] ?? $order['delivery_status'],
        ]);
    }

    private function paymentTarget(string $status): ?string
    {
        return match (strtolower(trim($status))) {
            'authorized', 'partially_paid' => 'authorized',
            'paid' => 'paid',
            'refunded', 'partially_refunded' => 'refunded',
            'voided', 'failed' => 'failed',
            default => null,
        };
    }

    private function deliveryTarget(string $status, string $cancelledAt): ?string
    {
        if ($cancelledAt !== '') {
            return 'returned';
        }

        return match (strtolower(trim($status))) {
            'pending' => 'pending',
            'packed' => 'packed',
            'fulfilled' => 'dispatched',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'failed' => 'failed',
            default => null,
        };
    }
}
