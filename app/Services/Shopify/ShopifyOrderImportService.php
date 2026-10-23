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
    private string $diagnosticCorrelationId = '';
    private string $diagnosticTopic = '';

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
    public function processEvent(int $eventId, bool $preserveLineCommercials = false, ?string $correlationId = null): array
    {
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $events->find($eventId);

        if ($event === null) {
            throw new RuntimeException('Shopify webhook event was not found.');
        }

        $this->diagnosticCorrelationId = $correlationId ?: bin2hex(random_bytes(16));
        $this->diagnosticTopic = (string) ($event['topic'] ?? '');
        $this->stage($event, '', 'payload_parsing');

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);

        if (!is_array($payload) || trim((string) ($payload['id'] ?? '')) === '') {
            $this->stage($event, '', 'payload_parsing', ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID, 'Shopify order payload is invalid.');
        }

        $orderId = $this->shopifyId($payload['id']);
        $this->stage($event, $orderId, 'order_identity_resolution');

        if ($this->lineItemsNeedHydration($payload['line_items'] ?? null)) {
            $payload = $this->hydrateOrderLineItems($event, $payload);
        }

        $store = strtolower(trim((string) $event['store_domain']));
        try {
            $db = db_connect();
            $db->transBegin();
            $import = $this->upsertImport($event, $payload);
        } catch (ShopifyOrderImportFailure $failure) {
            if (isset($db) && $db->transStatus() === false) $db->transRollback();
            throw $failure;
        } catch (\Throwable $exception) {
            if (isset($db)) $db->transRollback();
            $this->stage($event, $orderId, 'order_creation_update', ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify order database write failed.');
        }
        $importId = (int) $import['id'];
        $this->stage($event, $orderId, 'order_creation_update');
        if (! $db->transStatus()) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify order database write failed.');
        }
        $db->transCommit();

        // Persist lines before patient/prescription gating. This keeps an
        // order visible for Pharmacy reconciliation even when identity data
        // is incomplete, and makes mapping independent from patient linkage.
        $db = db_connect();
        $db->transBegin();
        try {
            $mappingResult = $this->storeLinesAndResolveMappings($importId, $store, $payload, $preserveLineCommercials, $event);
            if (! $db->transStatus()) throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, 'Shopify order line write failed.');
            $db->transCommit();
        } catch (ShopifyOrderImportFailure $failure) {
            $db->transRollback();
            throw $failure;
        } catch (\Throwable $exception) {
            $db->transRollback();
            $this->stage($event, $orderId, 'line_item_import', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, 'Shopify order line import failed.');
        }
        $this->stage($event, $orderId, 'line_item_import');

        $financialStatus = strtolower(trim((string) ($payload['financial_status'] ?? '')));
        $isCancelled = trim((string) ($payload['cancelled_at'] ?? '')) !== '' || in_array($financialStatus, ['refunded', 'voided'], true);
        if ($mappingResult['status'] !== 'ready' && ! $isCancelled) {
            $category = (string) ($mappingResult['category'] ?? ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED);
            $inactiveMedicine = $category === ShopifyOrderImportFailure::MEDICINE_INACTIVE;
            $lineImportFailure = in_array($category, [
                ShopifyOrderImportFailure::SHOPIFY_ORDER_LINE_IMPORT_FAILURE,
                ShopifyOrderImportFailure::SHOPIFY_LINE_ITEMS_MISSING,
                ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ID_INVALID,
                ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE,
                ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE,
            ], true);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, [
                'mapping_status' => (string) ($mappingResult['mapping_status'] ?? ($inactiveMedicine ? 'inactive_medicine' : 'unmapped')),
                'quarantine_reason' => $category,
                'last_error' => (string) ($mappingResult['reason'] ?? $category),
            ]);
            $this->setImportState($importId, ($lineImportFailure || $inactiveMedicine) ? 'quarantined' : 'pending_mapping', $category, $eventId, 0, $clinicId ?? 0);
            $events->markFailed($eventId, $category);
            service('audit')->record(($lineImportFailure || $inactiveMedicine) ? 'shopify.order_quarantined' : 'shopify.order_pending_mapping', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => $mappingResult['reason']]);

            return ['status' => $lineImportFailure || $inactiveMedicine ? 'quarantined' : 'pending_mapping', 'import_id' => $importId];
        }

        if ($isCancelled) {
            $this->setImportState($importId, 'cancelled', ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, 'last_error' => 'Shopify order is cancelled or refunded.']);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED);
            service('audit')->record('shopify.order_cancelled', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'cancelled_or_refunded']);
            return ['status' => 'cancelled', 'import_id' => $importId];
        }
        if ($financialStatus !== '' && ! in_array($financialStatus, ['paid', 'authorized', 'partially_paid'], true)) {
            $this->stage($event, $orderId, 'payment_state_mapping', ShopifyOrderImportFailure::PAYMENT_STATE_INVALID);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, 'last_error' => 'Shopify payment is not in an importable state.']);
            $events->markFailed($eventId, ShopifyOrderImportFailure::PAYMENT_STATE_INVALID);
            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $importClinicId = (int) ($import['clinic_id'] ?? 0);
        if ($importClinicId <= 0) {
            $importClinicId = (int) (($this->configuration ?? new ShopifyConfiguration())->clinicId() ?? 0);
        }
        $linkedRootinsOrderId = (int) ($import['rootins_order_id'] ?? 0);

        if (in_array((string) ($import['status'] ?? ''), ['imported', 'reconciled'], true)
            && $linkedRootinsOrderId > 0
            && $this->pharmacyOrderExists($linkedRootinsOrderId, $importClinicId)) {
            try {
                $this->linkImportedLines($importId, $linkedRootinsOrderId);
                $this->stage($event, $orderId, 'line_item_status_update');
            } catch (\Throwable $exception) {
                $this->stage($event, $orderId, 'line_item_status_update', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE);
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE, 'Shopify order lines could not be linked safely.');
            }
            $this->applyExternalStatuses($linkedRootinsOrderId, $payload, $store, $eventId, $importClinicId, (string) ($event['topic'] ?? ''), $importId);
            $events->markProcessed($eventId);

            return ['status' => (string) $import['status'], 'import_id' => $importId];
        }

        // A prior retry may have created the pharmacy order while the import
        // row still carries a stale or missing rootins_order_id. Resolve the
        // same external identity before creating anything new.
        $existingPharmacyOrder = $this->findPharmacyOrderByShopifyIdentity($store, $orderId, $importClinicId);
        if ($existingPharmacyOrder !== null) {
            $existingRootinsOrderId = (int) $existingPharmacyOrder['id'];
            $this->setImportState($importId, 'imported', null, $eventId, (int) ($import['patient_id'] ?? 0), $importClinicId, $existingRootinsOrderId);
            $this->linkImportedLines($importId, $existingRootinsOrderId);
            $this->applyExternalStatuses($existingRootinsOrderId, $payload, $store, $eventId, $importClinicId, (string) ($event['topic'] ?? ''), $importId);
            $events->markProcessed($eventId);
            $this->paymentStage($event, $orderId, $importId, $existingRootinsOrderId, 'payment_update', 'existing_pharmacy_order_reused');

            return ['status' => 'imported', 'import_id' => $importId, 'rootins_order_id' => $existingRootinsOrderId];
        }

        try {
            $identity = $this->linkedCustomer($store, (string) ($payload['customer_id'] ?? ''));
        } catch (\Throwable $exception) {
            if (isset($db)) $db->transRollback();
            $this->stage($event, $orderId, 'customer_patient_resolution', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE, 'Shopify customer identity could not be resolved.');
        }
        $this->stage($event, $orderId, 'customer_patient_resolution', $identity === null ? ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE : null);

        if ($identity === null) {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'missing', 'quarantine_reason' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE, 'last_attempted_at' => date('Y-m-d H:i:s')]);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE, $eventId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE);
            service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'customer_not_linked']);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $clinicId = ($this->configuration ?? new ShopifyConfiguration())->clinicId();
        $patientId = (int) ($identity['patient_id'] ?? 0);

        if ($patientId <= 0) {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'missing', 'quarantine_reason' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE, 'last_attempted_at' => date('Y-m-d H:i:s')]);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE, $eventId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_FAILURE);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'linked', 'last_attempted_at' => date('Y-m-d H:i:s')]);

        // Clinic scope is a financial/fulfilment concern. A globally stored
        // prescription and its patient identity must not depend on a clinic.
        if ($clinicId === null) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::BILLING_CLINIC_UNAVAILABLE, $eventId, $patientId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::BILLING_CLINIC_UNAVAILABLE);
            service('audit')->record('shopify.order_quarantined', [
                'event_id' => $eventId,
                'import_id' => $importId,
                'patient_id' => $patientId,
                'reason' => 'billing_clinic_unavailable',
            ]);

            return ['status' => 'quarantined', 'import_id' => $importId];
        }

        $prescriptionReference = trim((string) ($payload['prescription_reference'] ?? ''));
        $this->stage($event, $orderId, 'prescription_reference_lookup');
        if ($prescriptionReference !== '') {
            try {
                $document = $this->findPrescriptionDocument($prescriptionReference, $store, (string) ($payload['customer_id'] ?? ''), $patientId);
            } catch (\Throwable $exception) {
                $this->stage($event, $orderId, 'prescription_reference_lookup', ShopifyOrderImportFailure::SHOPIFY_PRESCRIPTION_LINK_FAILURE);
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_PRESCRIPTION_LINK_FAILURE, 'Shopify prescription reference could not be linked.');
            }
            $expired = $document !== null && ! empty($document['retention_expires_at']) && (string) $document['retention_expires_at'] < date('Y-m-d H:i:s');
            if ($document === null || $expired) {
                $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_MISSING, $eventId, $patientId, $clinicId);
                ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => 'invalid']);
                $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'invalid_prescription_reference']);
                return ['status' => 'quarantined', 'import_id' => $importId];
            }
            db_connect()->table('patient_documents')->where('id', (int) $document['id'])->update(['shopify_order_id' => (string) $payload['id'], 'upload_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review', 'updated_at' => date('Y-m-d H:i:s')]);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review']);
            if (($document['upload_status'] ?? '') !== 'accepted') {
                $this->setImportState($importId, 'quarantined', 'awaiting_review', $eventId, $patientId, $clinicId);
                $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'prescription_awaiting_review']);
                return ['status' => 'quarantined', 'import_id' => $importId];
            }
        }

        // A successful reconciliation clears only stale mapping/quarantine
        // diagnostics; prescription, payment, and delivery state remain independent.
        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, [
            'mapping_status' => 'mapped',
            'quarantine_reason' => null,
            'last_error' => null,
            'failure_reason' => null,
        ]);

        $prescriptionRequired = $this->prescriptionRequiredForOrder($store, $payload);
        if (trim((string) ($payload['prescription_reference'] ?? '')) === '') {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_status' => $prescriptionRequired ? 'missing' : 'not_required']);
        }
        $prescriptionId = $this->candidatePrescriptionId($payload);

        if ($prescriptionRequired && ($prescriptionId === null || !$this->approvedPrescriptionMatches($prescriptionId, $patientId, $clinicId))) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_MISSING, $eventId, $patientId, $clinicId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
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
        $this->stage($event, $orderId, 'order_status_update');
        $this->setImportState($importId, 'imported', null, $eventId, $patientId, $clinicId, $rootinsOrderId);
        try {
            $this->linkImportedLines($importId, $rootinsOrderId);
            $this->stage($event, $orderId, 'line_item_status_update');
        } catch (\Throwable $exception) {
            $this->stage($event, $orderId, 'line_item_status_update', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE, 'Shopify order lines could not be linked safely.');
        }
        $this->applyExternalStatuses($rootinsOrderId, $payload, $store, $eventId, $clinicId, (string) ($event['topic'] ?? ''), $importId);
        $events->markProcessed($eventId);
        service('audit')->record('shopify.order_imported', ['event_id' => $eventId, 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId, 'clinic_id' => $clinicId]);
        $this->stage($event, $orderId, 'audit_history_write');

        return ['status' => 'imported', 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId];
    }

    /** @return list<array<string, mixed>> */
    public function queue(): array
    {
        return ($this->imports ?? new ShopifyOrderImportModel())
            ->orderBy('updated_at', 'DESC')
            ->findAll(200);
    }

    /** @return array<string, mixed> */
    public function recheckMapping(int $importId): array
    {
        $imports = $this->imports ?? new ShopifyOrderImportModel();
        $import = $imports->find($importId);
        if ($import === null) return ['status' => false, 'msg' => 'Shopify order import was not found.', 'data' => ['error' => 'not_found']];
        $event = ($this->events ?? new ShopifyWebhookEventService())->find((int) ($import['last_event_id'] ?? 0));
        if ($event === null) return ['status' => false, 'msg' => 'The original Shopify event is unavailable.', 'data' => ['error' => 'event_missing']];
        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
        if (!is_array($payload) || trim((string) ($payload['id'] ?? '')) === '') return ['status' => false, 'msg' => 'The Shopify order data is unavailable.', 'data' => ['error' => 'payload_missing']];
        $payload = $this->hydrateOrderLineItems($event, $payload);
        $mapping = $this->storeLinesAndResolveMappings((int) $import['id'], strtolower(trim((string) $import['store_domain'])), $payload, true);
        $now = date('Y-m-d H:i:s');
        $updates = ['updated_at' => $now];
        if ($mapping['status'] === 'ready') {
            $updates['mapping_status'] = 'mapped';
            $updates['quarantine_reason'] = null;
            $updates['last_error'] = null;
            $updates['failure_reason'] = null;
            if (($import['status'] ?? '') === 'pending_mapping' || in_array((string) ($import['quarantine_reason'] ?? ''), [ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED, ShopifyOrderImportFailure::MEDICINE_INACTIVE], true)) {
                $updates['status'] = 'ready_to_import';
            }
        } else {
            $updates['mapping_status'] = (string) ($mapping['mapping_status'] ?? 'unmapped');
            $updates['quarantine_reason'] = (string) ($mapping['category'] ?? ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED);
            $updates['last_error'] = (string) ($mapping['reason'] ?? 'Mapping could not be resolved.');
            if (($import['status'] ?? '') !== 'imported' && ($import['status'] ?? '') !== 'cancelled') $updates['status'] = $updates['mapping_status'] === 'inactive_medicine' ? 'quarantined' : 'pending_mapping';
        }
        $imports->update($importId, $updates);
        return ['status' => true, 'msg' => 'Shopify order mapping rechecked.', 'data' => ['import_id' => $importId, 'mapping_status' => $updates['mapping_status'], 'line_items_present' => count((array) ($payload['line_items'] ?? []))]];
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
            $result = $this->processEvent($eventId, true);

            return ['http' => 200, 'body' => ['status' => $result['status'] === 'imported', 'msg' => 'Shopify order reconciliation completed.', 'data' => $result]];
        } catch (\Throwable $exception) {
            $failure = ShopifyOrderImportFailure::fromException($exception, (string) ($event['topic'] ?? ''));
            $this->markFailureForEvent($eventId, $failure->category);
            ($this->events ?? new ShopifyWebhookEventService())->markFailed($eventId, $failure->category);
            log_message('error', 'Shopify order reconciliation failed category=' . $failure->category . ' topic=' . (string) ($event['topic'] ?? '') . ' exception=' . get_class($exception));

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
    private function storeLinesAndResolveMappings(int $importId, string $store, array $payload, bool $preserveLineCommercials = false, ?array $event = null): array
    {
        $lineModel = $this->lines ?? new ShopifyOrderImportLineModel();
        $items = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
        $orderId = $this->shopifyId($payload['id'] ?? '');
        if ($event !== null) {
            $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_items_present', $items === [] ? ShopifyOrderImportFailure::SHOPIFY_LINE_ITEMS_MISSING : null);
            $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_count');
        }

        if ($items === []) {
            return ['status' => 'invalid', 'reason' => 'No Shopify line items were available.', 'category' => ShopifyOrderImportFailure::SHOPIFY_LINE_ITEMS_MISSING];
        }

        $unresolved = null;
        $mappingFailure = false;

        foreach ($items as $item) {
            $lineId = $this->shopifyId($item['id'] ?? null);
            if ($event !== null) $this->stage($event, $orderId, 'line_item_identity_resolution', $lineId === '' ? ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ID_INVALID : null);
            if ($lineId === '') {
                return ['status' => 'invalid', 'reason' => 'Shopify line-item identity is missing.', 'category' => ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ID_INVALID];
            }
            $productId = $this->shopifyId($item['product_id'] ?? null);
            $variantId = $this->shopifyId($item['variant_id'] ?? null);
            if ($event !== null) {
                $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'product_id_extraction');
                $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'variant_id_extraction');
                $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'medicine_mapping_lookup');
            }
            try {
                $resolution = $this->resolveMapping($store, $productId, $variantId);
            } catch (\Throwable $exception) {
                // Mapping is intentionally non-blocking. The order and line
                // remain visible to Pharmacy for reconciliation.
                $resolution = [
                    'result' => 'unmapped',
                    'reason' => 'Mapping lookup unavailable',
                    'mapping' => null,
                    'lookup_key' => $store . '|variant:' . $variantId . '|product:' . $productId,
                ];
                $mappingFailure = true;
            }
            if ($event !== null) $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'shopify_product_variant_extraction');
            if ($event !== null) $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'mapping_resolution', $resolution['result'] === 'mapped' ? null : ($mappingFailure ? ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_MAPPING_FAILURE : null));
            $mapping = $resolution['mapping'];
            $medicineId = $mapping['medicine_id'] ?? null;
            $status = 'pending_mapping';

            if ($resolution['result'] === 'mapped') {
                $status = 'mapped';
            } elseif ($resolution['result'] === 'inactive') {
                $status = 'medicine_inactive';
                // Keep inactive medicines visibly quarantined at line level: ['status' => 'medicine_inactive'].
            }

            $existing = $lineModel
                ->where('store_domain', $store)
                ->where('shopify_order_id', $orderId)
                ->where('shopify_line_item_id', $lineId)
                ->first();
            $lineData = [
                'import_id' => $importId,
                'store_domain' => $store,
                'shopify_order_id' => $orderId,
                'shopify_line_item_id' => $lineId,
                'shopify_product_id' => $productId !== '' ? $productId : null,
                'shopify_variant_id' => $variantId !== '' ? $variantId : null,
                'medicine_id' => $medicineId,
                'title_snapshot' => (string) ($item['title'] ?? ''),
                'shopify_sku' => (string) ($item['sku'] ?? ''),
                'shopify_barcode' => (string) ($item['barcode'] ?? ''),
                'product_image_url' => filter_var((string) ($item['image_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $item['image_url'] : null,
                'quantity' => $preserveLineCommercials && $existing !== null ? (int) ($existing['quantity'] ?? 0) : max(0, (int) ($item['quantity'] ?? 0)),
                'shopify_price_snapshot' => $preserveLineCommercials && $existing !== null ? (float) ($existing['shopify_price_snapshot'] ?? 0) : (float) ($item['price'] ?? 0),
                'status' => $existing !== null && (int) ($existing['rootins_order_item_id'] ?? 0) > 0 && $status === 'mapped' ? 'imported' : $status,
                'mapping_reason' => $resolution['reason'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            try {
                $this->upsertImportLine($lineModel, $existing, $lineData);
            } catch (\Throwable $exception) {
                if ($event !== null) $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_database_upsert', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE);
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, 'Shopify order line write failed.');
            }
            if ($event !== null) $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_database_upsert');
            if ($event !== null) $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_status_update');

            if ($resolution['result'] !== 'mapped' && $unresolved === null) {
                $unresolved = $resolution;
            }

            log_message('debug', 'Shopify mapping resolution order=' . $this->shopifyId($payload['id'] ?? null)
                . ' line=' . $lineId . ' product=' . $productId . ' variant=' . $variantId
                . ' lookup=' . $resolution['lookup_key'] . ' result=' . $resolution['result']);
        }

        if ($unresolved !== null) {
            $category = $mappingFailure ? ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_MAPPING_FAILURE : ($unresolved['result'] === 'inactive'
                ? ShopifyOrderImportFailure::MEDICINE_INACTIVE
                : ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED);

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
        try {
            foreach ((array) ($payload['line_items'] ?? []) as $item) {
                $variantId = $this->shopifyId($item['variant_id'] ?? null);
                $productId = $this->shopifyId($item['product_id'] ?? null);
                $required = null;
                if ($variantId !== '' && $db->tableExists('shopify_variant_catalogue')) {
                    $variant = $db->table('shopify_variant_catalogue')->select('prescription_required')->where('store_domain', $store)->whereIn('shopify_variant_id', $this->shopifyIdAliases($variantId, 'ProductVariant'))->get(1)->getRowArray();
                    $required = $variant['prescription_required'] ?? null;
                }
                if ($required === null && $productId !== '' && $db->tableExists('shopify_product_catalogue')) {
                    $product = $db->table('shopify_product_catalogue')->select('prescription_required')->where('store_domain', $store)->whereIn('shopify_product_id', $this->shopifyIdAliases($productId, 'Product'))->get(1)->getRowArray();
                    $required = $product['prescription_required'] ?? null;
                }
                if ((int) $required === 1) return true;
            }
        } catch (\Throwable $exception) {
            log_message('warning', 'Shopify prescription-required metadata lookup failed safely.');
            return true;
        }

        return false;
    }

    /** @return array{result: string, reason: ?string, mapping: ?array, lookup_key: string} */
    private function resolveMapping(string $store, string $productId, string $variantId): array
    {
        $db = db_connect();
        $lookupKey = $store . '|variant:' . ($variantId !== '' ? $variantId : 'none') . '|product:' . ($productId !== '' ? $productId : 'none');
        if ($db->tableExists('shopify_mapping_ignores') && $productId !== '' && $variantId !== '' && $db->table('shopify_mapping_ignores')->where('store_domain', $store)->whereIn('shopify_product_id', $this->shopifyIdAliases($productId, 'Product'))->whereIn('shopify_variant_id', $this->shopifyIdAliases($variantId, 'ProductVariant'))->countAllResults() > 0) {
            return ['result' => 'unmapped', 'reason' => 'Product mapping missing', 'mapping' => null, 'lookup_key' => $lookupKey];
        }
        $mappingRows = [];

        if ($variantId !== '') {
            $mappingRows = $db->table('shopify_medicine_mappings')
                ->where('store_domain', $store)
                ->whereIn('shopify_variant_id', $this->shopifyIdAliases($variantId, 'ProductVariant'))
                ->get()->getResultArray();
        }

        if ($mappingRows === [] && $productId !== '') {
            $mappingRows = $db->table('shopify_medicine_mappings')
                ->where('store_domain', $store)
                ->whereIn('shopify_product_id', $this->shopifyIdAliases($productId, 'Product'))
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
                ->where('store_domain', $store)->whereIn('shopify_product_id', $this->shopifyIdAliases($productId, 'Product'))->countAllResults() > 0;
        }

        $reason = $productId === '' || (! $productExists && $variantId === '')
            ? 'Product mapping missing'
            : ($productExists ? 'Variant mapping missing' : 'Product not found');

        return ['result' => 'unmapped', 'reason' => $reason, 'mapping' => null, 'lookup_key' => $lookupKey];
    }

    /** @return list<string> */
    private function shopifyIdAliases(string $value, string $resource): array
    {
        $value = $this->shopifyId($value);
        if ($value === '') return [];
        $aliases = [$value];
        if (preg_match('#^gid://shopify/' . preg_quote($resource, '#') . '/([0-9]+)$#i', $value, $matches) === 1) {
            $aliases[] = $matches[1];
        } elseif (ctype_digit($value)) {
            $aliases[] = 'gid://shopify/' . $resource . '/' . $value;
        }
        return array_values(array_unique($aliases));
    }

    /** @return array<string, mixed> */
    private function hydrateOrderLineItems(array $event, array $payload): array
    {
        $store = strtolower(trim((string) ($event['store_domain'] ?? '')));
        $orderId = $this->shopifyId($payload['id'] ?? '');
        $existingImport = $store !== '' && $orderId !== ''
            ? ($this->imports ?? new ShopifyOrderImportModel())->where('store_domain', $store)->where('shopify_order_id', $orderId)->first()
            : null;
        if ($existingImport !== null) {
            $existingLines = ($this->lines ?? new ShopifyOrderImportLineModel())->where('import_id', (int) $existingImport['id'])->orderBy('id', 'ASC')->findAll();
            if ($existingLines !== []) {
                $payload['line_items'] = array_map(static fn (array $line): array => [
                    'id' => (string) ($line['shopify_line_item_id'] ?? ''),
                    'title' => (string) ($line['title_snapshot'] ?? ''),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                    'sku' => (string) ($line['shopify_sku'] ?? ''),
                    'barcode' => (string) ($line['shopify_barcode'] ?? ''),
                    'variant_id' => (string) ($line['shopify_variant_id'] ?? ''),
                    'product_id' => (string) ($line['shopify_product_id'] ?? ''),
                    'price' => (float) ($line['shopify_price_snapshot'] ?? 0),
                ], $existingLines);
                $this->stage($event, $orderId, 'line_items_reused');
                return $payload;
            }
        }

        try {
            $orderGid = 'gid://shopify/Order/' . $this->shopifyId($payload['id'] ?? '');
            $result = (new ShopifyClient(new ShopifyConfiguration()))->graphql(<<<'GRAPHQL'
query OrderLineRepair($id: ID!) {
  order(id: $id) {
    id
    name
    lineItems(first: 250) {
      nodes {
        id
        title
        quantity
        sku
        variant {
          id
          product { id }
        }
        originalUnitPriceSet { shopMoney { amount } }
      }
    }
  }
}
GRAPHQL, ['id' => $orderGid]);
            $order = is_array($result['order'] ?? null) ? $result['order'] : [];
            $items = [];
            foreach ((array) ($order['lineItems']['nodes'] ?? []) as $line) {
                if (!is_array($line) || $this->shopifyId($line['id'] ?? '') === '') continue;
                $items[] = [
                    'id' => $this->shopifyId($line['id'] ?? ''),
                    'title' => (string) ($line['title'] ?? ''),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                    'sku' => (string) ($line['sku'] ?? ''),
                    'variant_id' => (string) ($line['variant']['id'] ?? ''),
                    'product_id' => (string) ($line['variant']['product']['id'] ?? ''),
                    'price' => (float) ($line['originalUnitPriceSet']['shopMoney']['amount'] ?? 0),
                ];
            }
            if ($items !== []) {
                $payload['line_items'] = $items;
                log_message('info', 'Shopify order line repair completed order=' . $this->shopifyId($payload['id'] ?? '') . ' line_count=' . count($items));
            } else {
                $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_import', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEMS_MISSING);
            }
        } catch (\Throwable $exception) {
            $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_import', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEMS_MISSING);
            log_message('warning', 'Shopify order line repair unavailable topic=' . (string) ($event['topic'] ?? '') . ' error=' . get_class($exception));
        }
        return $payload;
    }

    private function lineItemsNeedHydration(mixed $items): bool
    {
        if (! is_array($items) || $items === []) return true;
        foreach ($items as $item) {
            if (! is_array($item) || $this->shopifyId($item['id'] ?? '') === '') return true;
            if (! array_key_exists('product_id', $item) || ! array_key_exists('variant_id', $item)) return true;
        }
        return false;
    }

    private function shopifyId(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if (preg_match('#^gid://shopify/[A-Za-z]+/([0-9]+)$#i', $value, $matches) === 1) return $matches[1];
        return $value;
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
            $race = $model
                ->where('store_domain', $data['store_domain'])
                ->where('shopify_order_id', $data['shopify_order_id'])
                ->where('shopify_line_item_id', $data['shopify_line_item_id'])
                ->first();
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

    private function findPrescriptionDocument(string $reference, string $store, string $customerId, int $patientId): ?array
    {
        return db_connect()->table('patient_documents')
            ->where('source_type', 'shopify')->where('external_upload_reference', $reference)
            ->where('shopify_store_domain', $store)->where('shopify_customer_id', $customerId)
            ->where('patient_id', $patientId)->get(1)->getRowArray();
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

    private function pharmacyOrderExists(int $orderId, int $clinicId): bool
    {
        return $orderId > 0 && $clinicId > 0 && db_connect()->table('medicine_orders')
            ->select('id')
            ->where('id', $orderId)
            ->where('clinic_id', $clinicId)
            ->get(1)
            ->getRowArray() !== null;
    }

    /** @return array<string, mixed>|null */
    private function findPharmacyOrderByShopifyIdentity(string $store, string $orderId, int $clinicId): ?array
    {
        if ($store === '' || $orderId === '' || $clinicId <= 0) return null;

        return db_connect()->table('medicine_orders')
            ->select('id, clinic_id, patient_id, status, payment_status')
            ->where('external_store_domain', $store)
            ->where('external_order_id', $orderId)
            ->where('clinic_id', $clinicId)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    private function applyExternalStatuses(int $orderId, array $payload, string $store, int $eventId, int $clinicId, string $topic = '', int $importId = 0): void
    {
        $event = ($this->events ?? new ShopifyWebhookEventService())->find($eventId) ?? [
            'id' => $eventId,
            'topic' => $topic,
            'store_domain' => $store,
        ];
        $this->paymentStage($event, $this->shopifyId($payload['id'] ?? ''), $importId, $orderId, 'payment_update', 'lookup_started');

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
            $externalOrderId = $this->shopifyId($payload['id'] ?? '');
            if ($externalOrderId !== '' && $store !== '') {
                $order = $db->table('medicine_orders')
                    ->where('external_store_domain', $store)
                    ->where('external_order_id', $externalOrderId)
                    ->where('clinic_id', $clinicId)
                    ->get(1)
                    ->getRowArray();
                if ($order !== null) {
                    $orderId = (int) $order['id'];
                    $this->paymentStage($event, $externalOrderId, $importId, $orderId, 'payment_update', 'external_identity_recovered');
                }
            }
        }

        if ($order === null) {
            $db->transRollback();
            $this->paymentStage($event, $this->shopifyId($payload['id'] ?? ''), $importId, $orderId, 'payment_update', 'pharmacy_order_not_found', $topic === 'refunds/create' ? ShopifyOrderImportFailure::REFUND_ORDER_NOT_FOUND : ShopifyOrderImportFailure::ORDER_NOT_FOUND);

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

            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify order status update failed.');
        }
        $db->transCommit();
        $this->paymentStage($event, $this->shopifyId($payload['id'] ?? ''), $importId, $orderId, 'payment_update', 'updated');
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

    /** Safe payment diagnostics; never include SQL, payloads, or credentials. */
    private function paymentStage(array $event, string $orderId, int $importId, int $rootinsOrderId, string $stage, string $lookup, string $category = 'none'): void
    {
        log_message('info', 'Shopify medicine-order payment stage ' . json_encode([
            'shopify_order_id' => $orderId,
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
            'internal_import_reference' => $importId > 0 ? $importId : null,
            'internal_pharmacy_order_reference' => $rootinsOrderId > 0 ? $rootinsOrderId : null,
            'payment_update_stage' => $stage,
            'lookup_result' => $lookup,
            'failure_category' => $category,
        ], JSON_UNESCAPED_SLASHES));
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

    private function stage(array $event, string $orderId, string $stage, ?string $category = null): void
    {
        log_message('info', 'Shopify medicine-order import stage ' . json_encode([
            'correlation_id' => $this->diagnosticCorrelationId,
            'topic' => $this->diagnosticTopic !== '' ? $this->diagnosticTopic : (string) ($event['topic'] ?? ''),
            'shopify_order_id' => $orderId,
            'webhook_id_hash' => hash('sha256', (string) ($event['external_event_id'] ?? $event['id'] ?? '')),
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
            'processing_stage' => $stage,
            'failure_category' => $category ?? 'none',
        ], JSON_UNESCAPED_SLASHES));
    }
}
