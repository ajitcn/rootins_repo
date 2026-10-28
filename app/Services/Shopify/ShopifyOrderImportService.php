<?php

namespace App\Services\Shopify;

use Admin\Models\PrescriptionModel;
use App\Modules\Pharmacy\Services\PharmacyScope;
use App\Modules\Pharmacy\Services\OrderFulfilmentService;
use App\Modules\Shopify\Models\ShopifyMedicineMappingModel;
use App\Modules\Shopify\Models\ShopifyOrderImportLineModel;
use App\Modules\Shopify\Models\ShopifyOrderImportModel;
use App\Modules\Shopify\Models\ShopifyOrderNotificationModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ShopifyOrderImportService
{
    private string $diagnosticCorrelationId = '';
    private string $diagnosticTopic = '';
    private ?string $customerLookupFailure = null;
    private int $diagnosticImportId = 0;
    private string $diagnosticImportStatus = '';
    private string $diagnosticPaymentStatus = '';
    private string $diagnosticLookupType = 'none';
    private bool $diagnosticRowFound = false;
    private int $diagnosticAffectedRows = 0;
    private string $diagnosticTargetTable = 'shopify_order_imports';
    private ?string $diagnosticExceptionClass = null;
    private string $diagnosticProcessingStage = '';
    private string $diagnosticLineId = '';
    private string $diagnosticProductId = '';
    private string $diagnosticVariantId = '';

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
        $this->customerLookupFailure = null;
        $this->stage($event, '', 'payload_parsing');

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);

        if (!is_array($payload) || trim((string) ($payload['id'] ?? '')) === '') {
            $this->stage($event, '', 'payload_parsing', ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID, 'Shopify order payload is invalid.');
        }

        $orderId = $this->shopifyId($payload['id']);
        $this->stage($event, $orderId, 'order_identity_resolution');

        $payload = $this->hydrateOrderCustomer($event, $payload);
        $this->resolveOrderCustomerIdentity($event, $payload);

        if ($this->lineItemsNeedHydration($payload['line_items'] ?? null)) {
            $payload = $this->hydrateOrderLineItems($event, $payload);
        }

        $store = strtolower(trim((string) $event['store_domain']));
        $this->stage($event, $orderId, 'payload_parsed');
        $this->stage($event, $orderId, 'order_identity_resolved');
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
        $this->stage($event, $orderId, 'import_row_upserted', null, 'shopify_order_imports', 'store_domain_shopify_order_id', true, 1);
        $importId = (int) $import['id'];
        $this->diagnosticImportId = $importId;
        $this->diagnosticImportStatus = (string) ($import['status'] ?? '');
        $this->diagnosticPaymentStatus = (string) ($import['payment_status'] ?? '');
        $this->diagnosticLookupType = 'store_domain_shopify_order_id';
        $this->diagnosticRowFound = true;
        $this->diagnosticTargetTable = 'shopify_order_imports';
        $this->stage($event, $orderId, 'internal_import_id_resolved', null, 'shopify_order_imports', 'internal_import_id', true);
        $this->recordImportHistory($importId, (int) ($event['id'] ?? 0), 'import_started', (string) ($import['status'] ?? ''), (string) ($import['status'] ?? ''), null);
        $importIdentity = [
            'internal_import_id' => $importId,
            'shopify_order_id' => $orderId,
            'store_domain' => $store,
        ];
        $paidImport = strtolower((string) ($payload['financial_status'] ?? '')) === 'paid';
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
        $this->stage($event, $orderId, 'line_items_started', null, 'shopify_order_import_lines', 'internal_import_id');
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
        $this->stage($event, $orderId, 'mapping_resolved', $mappingResult['status'] === 'ready' ? null : (string) ($mappingResult['category'] ?? ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED), 'shopify_order_import_lines', 'internal_import_id', $mappingResult['status'] === 'ready');
        $this->stage($event, $orderId, 'line_items_completed', null, 'shopify_order_import_lines', 'internal_import_id', true);
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

            return array_merge($importIdentity, ['status' => $lineImportFailure || $inactiveMedicine ? 'quarantined' : 'pending_mapping', 'import_id' => $importId]);
        }

        if ($isCancelled) {
            $this->setImportState($importId, 'cancelled', ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED, 'last_error' => 'Shopify order is cancelled or refunded.']);
            $events->markProcessed($eventId, ShopifyOrderImportFailure::ORDER_ALREADY_CANCELLED);
            service('audit')->record('shopify.order_cancelled', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'cancelled_or_refunded']);
            return array_merge($importIdentity, ['status' => 'cancelled', 'import_id' => $importId]);
        }
        if ($financialStatus !== '' && ! in_array($financialStatus, ['paid', 'authorized', 'partially_paid'], true)) {
            $this->stage($event, $orderId, 'payment_state_mapping', ShopifyOrderImportFailure::PAYMENT_STATE_INVALID);
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, $eventId);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['quarantine_reason' => ShopifyOrderImportFailure::PAYMENT_STATE_INVALID, 'last_error' => 'Shopify payment is not in an importable state.']);
            $events->markFailed($eventId, ShopifyOrderImportFailure::PAYMENT_STATE_INVALID);
            return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
        }

        $importClinicId = (int) ($import['clinic_id'] ?? 0);
        if ($importClinicId <= 0) {
            $importClinicId = (int) (($this->configuration ?? new ShopifyConfiguration())->clinicId() ?? 0);
        }
        $linkedRootinsOrderId = (int) ($import['rootins_order_id'] ?? 0);
        $this->refreshImportedPatientIdentity($importId, $store, (string) ($payload['customer_id'] ?? ''));

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
            $this->applyExternalStatuses($linkedRootinsOrderId, $payload, $store, $eventId, $importClinicId, (string) ($event['topic'] ?? ''), (int) $importIdentity['internal_import_id']);
            $events->markProcessed($eventId);

            return array_merge($importIdentity, ['status' => $paidImport ? 'processed' : (string) $import['status'], 'import_id' => $importId]);
        }

        try {
            $identity = $this->linkedCustomer($store, (string) ($payload['customer_id'] ?? ''));
        } catch (\Throwable $exception) {
            if (isset($db)) $db->transRollback();
            $this->stage($event, $orderId, 'customer_patient_resolution', ShopifyOrderImportFailure::PATIENT_RESOLUTION_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PATIENT_RESOLUTION_FAILURE, 'Shopify customer identity could not be resolved.');
        }
        $clinicId = ($this->configuration ?? new ShopifyConfiguration())->clinicId();
        $this->stage($event, $orderId, 'customer_patient_resolution', $identity === null ? ShopifyOrderImportFailure::PATIENT_RESOLUTION_FAILURE : null);
        $this->stage($event, $orderId, 'customer_resolved', $identity === null ? ShopifyOrderImportFailure::PATIENT_RESOLUTION_FAILURE : null, 'shopify_patient_identity_links', 'store_domain_customer_id', $identity !== null);

        if ($identity === null) {
            $this->stage($event, $orderId, 'patient_resolved', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, 'patients', 'patient_id', false);
            $customerId = $this->shopifyId($payload['customer_id'] ?? '');
            $patientStatus = $customerId === '' ? 'guest' : 'link_pending';
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, [
                'patient_status' => $patientStatus,
                'customer_name_snapshot' => $this->customerLookupFailure !== null ? 'Customer data unavailable' : null,
                'quarantine_reason' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED,
                'last_error' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED,
                'last_attempted_at' => date('Y-m-d H:i:s'),
            ]);
            $this->setImportState($importId, 'awaiting_patient_link', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, $eventId, 0, (int) ($clinicId ?? 0));
            $events->markProcessed($eventId, ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED);
            service('audit')->record('shopify.order_awaiting_patient_link', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => $patientStatus]);

            return array_merge($importIdentity, ['status' => 'awaiting_patient_link', 'import_id' => $importId]);
        }

        $patientId = (int) ($identity['patient_id'] ?? 0);

        if ($patientId <= 0) {
            $this->stage($event, $orderId, 'patient_resolved', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, 'patients', 'patient_id', false);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'link_pending', 'quarantine_reason' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, 'last_error' => ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, 'last_attempted_at' => date('Y-m-d H:i:s')]);
            $this->setImportState($importId, 'awaiting_patient_link', ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED, $eventId, 0, (int) ($clinicId ?? 0));
            $events->markProcessed($eventId, ShopifyOrderImportFailure::SHOPIFY_PATIENT_LINK_REQUIRED);

            return array_merge($importIdentity, ['status' => 'awaiting_patient_link', 'import_id' => $importId]);
        }

        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['patient_status' => 'linked', 'last_attempted_at' => date('Y-m-d H:i:s')]);
        $this->diagnosticImportStatus = 'patient_linked';
        $this->stage($event, $orderId, 'patient_resolved', null, 'patients', 'patient_id', true);

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

            return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
        }

        $prescriptionReference = trim((string) ($payload['prescription_reference'] ?? ''));
        $prescriptionId = null;
        $prescriptionDocumentId = 0;
        $this->stage($event, $orderId, 'prescription_reference_lookup');
        if ($prescriptionReference !== '') {
            $doctorPrescription = $this->findDoctorPrescription($prescriptionReference, $patientId, (int) $clinicId);
            if ($doctorPrescription !== null) {
                if (! ShopifyPrescriptionEligibility::isOrderable($doctorPrescription)) {
                    ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => 'invalid']);
                    $this->setImportState($importId, 'quarantined', ShopifyPrescriptionEligibility::REASON, $eventId, $patientId, $clinicId);
                    $events->markFailed($eventId, ShopifyPrescriptionEligibility::REASON);
                    $this->queueNotification($importId, 'prescription_rejected');
                    return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
                }
                $prescriptionId = (int) $doctorPrescription['id'];
                ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => 'active']);
            } else {
            try {
                $document = (new ShopifyPrescriptionUploadService())->ensureOrderPrescriptionMetadata([
                    'id' => $importId,
                    'shopify_order_id' => $orderId,
                    'store_domain' => $store,
                    'shopify_customer_id' => (string) ($payload['customer_id'] ?? ''),
                    'patient_id' => $patientId,
                    'clinic_id' => $clinicId,
                ], $prescriptionReference);
            } catch (\Throwable $exception) {
                $category = $exception instanceof ShopifyPrescriptionPreviewException ? $exception->category : ShopifyOrderImportFailure::PRESCRIPTION_REFERENCE_FAILURE;
                $this->stage($event, $orderId, 'prescription_metadata_lookup', $category, 'patient_documents', 'prescription_reference', false, null, null, ['import_id' => $importId, 'upload_reference' => $prescriptionReference, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
                throw new ShopifyOrderImportFailure($category, 'Shopify prescription metadata could not be linked.');
            }
            $expired = $document !== null && ! empty($document['retention_expires_at']) && (string) $document['retention_expires_at'] < date('Y-m-d H:i:s');
            if ($document === null || $expired) {
                $failure = $document === null ? ShopifyOrderImportFailure::PRESCRIPTION_METADATA_CREATION_FAILED : ShopifyOrderImportFailure::PRESCRIPTION_MISSING;
                $this->setImportState($importId, 'quarantined', $failure, $eventId, $patientId, $clinicId);
                ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => 'invalid']);
                $events->markFailed($eventId, $failure);
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'invalid_prescription_reference']);
                return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
            }
            $prescriptionDocumentId = (int) ($document['id'] ?? 0);
            db_connect()->table('patient_documents')->where('id', (int) $document['id'])->update(['shopify_order_id' => (string) $payload['id'], 'upload_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review', 'updated_at' => date('Y-m-d H:i:s')]);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_reference' => $prescriptionReference, 'prescription_status' => ($document['upload_status'] ?? '') === 'accepted' ? 'accepted' : 'awaiting_review']);
            (new ShopifyPrescriptionUploadService())->linkOrderPrescription(['id' => $importId, 'store_domain' => $store, 'shopify_order_id' => $orderId, 'patient_id' => $patientId, 'clinic_id' => $clinicId, 'prescription_bundle_reference' => (string) ($payload['prescription_bundle_reference'] ?? '')], $prescriptionDocumentId, null, $prescriptionReference);
            $this->prescriptionImportDiagnostic($orderId, $importId, $prescriptionReference, $prescriptionDocumentId, $patientId, (int) $clinicId, 'prescription_linkage', true, null);
            if (($document['upload_status'] ?? '') !== 'accepted') {
                $this->setImportState($importId, 'quarantined', 'awaiting_review', $eventId, $patientId, $clinicId);
                $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
                service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'prescription_awaiting_review']);
                return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
            }
            }
        }

        // A successful reconciliation clears only stale mapping/quarantine
        // diagnostics; prescription, payment, and delivery state remain independent.
        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, [
            'mapping_status' => 'mapped',
            'quarantine_reason' => null,
            'last_error' => null,
        ]);

        $prescriptionRequired = $this->prescriptionRequiredForOrder($store, $payload);
        if (trim((string) ($payload['prescription_reference'] ?? '')) === '') {
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['prescription_status' => $prescriptionRequired ? 'missing' : 'not_required']);
            if ($prescriptionRequired && db_connect()->tableExists('shopify_order_import_lines')) {
                db_connect()->table('shopify_order_import_lines')->where('import_id', $importId)->whereIn('review_status', ['mapped', 'prescription_required'])->update(['review_status' => 'prescription_missing', 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->stage($event, $orderId, 'mapping_resolved', null, 'shopify_order_import_lines', 'internal_import_id', true);
        $this->stage($event, $orderId, 'prescription_resolved', null, 'shopify_order_imports', 'internal_import_id', true);
        if ($prescriptionRequired && ($prescriptionId === null || !$this->orderablePrescriptionMatches($prescriptionId, $patientId, $clinicId))) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_MISSING, $eventId, $patientId, $clinicId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_MISSING);
            $this->queueNotification($importId, 'new_prescription_required');
            service('audit')->record('shopify.order_quarantined', ['event_id' => $eventId, 'import_id' => $importId, 'reason' => 'missing_prescription']);

            return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
        }
        if ($prescriptionId !== null && ! $this->prescriptionItemsMatchOrder($prescriptionId, $importId)) {
            $this->setImportState($importId, 'quarantined', ShopifyOrderImportFailure::PRESCRIPTION_INVALID, $eventId, $patientId, $clinicId);
            $events->markFailed($eventId, ShopifyOrderImportFailure::PRESCRIPTION_INVALID);
            $this->queueNotification($importId, 'prescription_rejected');
            return array_merge($importIdentity, ['status' => 'quarantined', 'import_id' => $importId]);
        }

        if ($prescriptionId !== null || $prescriptionDocumentId > 0 || $prescriptionReference !== '') {
            (new ShopifyPrescriptionUploadService())->linkOrderPrescription(['id' => $importId, 'store_domain' => $store, 'shopify_order_id' => $orderId, 'patient_id' => $patientId, 'clinic_id' => $clinicId, 'prescription_bundle_reference' => (string) ($payload['prescription_bundle_reference'] ?? '')], $prescriptionDocumentId, $prescriptionId, $prescriptionReference, null);
            $this->prescriptionImportDiagnostic($orderId, $importId, $prescriptionReference, $prescriptionDocumentId, $patientId, (int) $clinicId, 'prescription_linkage', true, null);
        }

        $rootinsOrderId = $this->ensurePharmacyOrderForImport($event, $importId, $prescriptionId, $patientId, $clinicId, $store, $orderId, $payload, $prescriptionRequired);
        $this->stage($event, $orderId, 'order_status_update');
        $this->setImportState($importId, 'imported', null, $eventId, $patientId, $clinicId, $rootinsOrderId);
        $this->queueNotification($importId, 'complete_approval');
        try {
            $this->linkImportedLines($importId, $rootinsOrderId);
            $this->stage($event, $orderId, 'line_item_status_update');
        } catch (\Throwable $exception) {
            $this->stage($event, $orderId, 'line_item_status_update', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE, 'Shopify order lines could not be linked safely.');
        }
        $this->applyExternalStatuses($rootinsOrderId, $payload, $store, $eventId, $clinicId, (string) ($event['topic'] ?? ''), (int) $importIdentity['internal_import_id']);
        $events->markProcessed($eventId);
        service('audit')->record('shopify.order_imported', ['event_id' => $eventId, 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId, 'clinic_id' => $clinicId]);
        $this->stage($event, $orderId, 'audit_history_write');

        return array_merge($importIdentity, ['status' => $paidImport ? 'processed' : 'imported', 'import_id' => $importId, 'rootins_order_id' => $rootinsOrderId]);
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
        $model = $this->imports ?? new ShopifyOrderImportModel();
        $event = ($this->events ?? new ShopifyWebhookEventService())->find($eventId) ?? ['id' => $eventId, 'topic' => '', 'store_domain' => ''];
        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
        $orderId = is_array($payload) ? $this->shopifyId($payload['id'] ?? $payload['order_id'] ?? '') : '';
        foreach ($model->where('last_event_id', $eventId)->findAll() as $row) {
            $this->diagnosticImportId = (int) ($row['id'] ?? 0);
            $this->diagnosticImportStatus = (string) ($row['status'] ?? '');
            $this->diagnosticPaymentStatus = (string) ($row['payment_status'] ?? '');
            $this->diagnosticRowFound = true;
            $this->stage($event, $orderId, 'import_failed', ShopifyOrderImportFailure::normalize($category), 'shopify_order_imports', 'internal_import_id', true, 0);
            $this->recordImportHistory((int) $row['id'], $eventId, 'import_failed', (string) ($row['status'] ?? ''), 'failed', ShopifyOrderImportFailure::normalize($category), ShopifyOrderImportFailure::normalize($category));
        }
        return (bool) $model->where('last_event_id', $eventId)->set([
            'status' => 'failed',
            'failure_reason' => ShopifyOrderImportFailure::normalize($category),
            'last_error' => ShopifyOrderImportFailure::normalize($category),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    public function importExisting(int $importId, ?string $prescriptionReference = null, ?string $correlationId = null): array
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

        if ($prescriptionReference !== null) {
            $prescriptionReference = trim($prescriptionReference);
            if (! preg_match('/^RX[PN]-[A-Z0-9-]{6,191}$/', $prescriptionReference)) {
                return ['http' => 422, 'body' => ['status' => false, 'msg' => 'A valid prescription reference is required.']];
            }
            $payload['prescription_reference'] = $prescriptionReference;
            unset($payload['rootins_prescription_id']);
            $event['payload_json'] = json_encode($payload);
            $events = $this->events ?? new ShopifyWebhookEventService();

            if (!$events->replacePayload((int) $event['id'], $event['payload_json'])) {
                return ['http' => 500, 'body' => ['status' => false, 'msg' => 'Shopify order mapping could not be saved safely.']];
            }
        }

        try {
            $result = $this->processEvent($eventId, true, $correlationId);

            return ['http' => 200, 'body' => ['status' => in_array((string) ($result['status'] ?? ''), ['imported', 'processed', 'reconciled'], true), 'msg' => 'Shopify order reconciliation completed.', 'data' => $result]];
        } catch (\Throwable $exception) {
            $failure = ShopifyOrderImportFailure::fromException($exception, (string) ($event['topic'] ?? ''));
            $dbError = db_connect()->error();
            $sanitizedMessage = $this->sanitizeDatabaseMessage($exception->getMessage());
            $failureStage = $this->diagnosticProcessingStage !== '' ? $this->diagnosticProcessingStage : 'unknown';
            $failureTargetTable = $this->diagnosticTargetTable;
            $failureLookupType = $this->diagnosticLookupType;
            $failureRowFound = $this->diagnosticRowFound;
            $failureAffectedRows = $this->diagnosticAffectedRows;
            $failureLineId = $this->diagnosticLineId;
            $failureProductId = $this->diagnosticProductId;
            $failureVariantId = $this->diagnosticVariantId;
            $this->markFailureForEvent($eventId, $failure->category);
            ($this->events ?? new ShopifyWebhookEventService())->markFailed($eventId, $failure->category);
            log_message('error', 'Shopify order reconciliation failed ' . json_encode([
                'correlation_id' => $this->diagnosticCorrelationId,
                'import_id' => $importId,
                'shopify_order_id' => $this->shopifyId($payload['id'] ?? ''),
                'line_id' => $failureLineId !== '' ? $failureLineId : null,
                'product_id' => $failureProductId !== '' ? $failureProductId : null,
                'variant_id' => $failureVariantId !== '' ? $failureVariantId : null,
                'target_table' => $failureTargetTable,
                'lookup_type' => $failureLookupType,
                'row_found' => $failureRowFound,
                'database_error_code' => (string) ($dbError['code'] ?? ''),
                'sanitized_database_message' => $sanitizedMessage,
                'sanitized_exception_message' => $sanitizedMessage,
                'affected_rows' => $failureAffectedRows,
                'processing_stage' => $failureStage,
                'failure_category' => $failure->category,
                'exception_class' => get_class($exception),
            ], JSON_UNESCAPED_SLASHES));

            return ['http' => 500, 'body' => ['status' => false, 'msg' => 'The Shopify order could not be imported safely.', 'data' => ['error_category' => $failure->category]]];
        }
    }

    /**
     * Reconcile only refund/fulfilment webhooks. These payloads identify the
     * Shopify order in order_id; payload id is the external refund/fulfilment
     * identity and must never enter the order-import pipeline as an order ID.
     */
    public function processExternalEvent(int $eventId, string $topic, ?string $correlationId = null): array
    {
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $events->find($eventId);
        $payload = is_array($event) ? json_decode((string) ($event['payload_json'] ?? ''), true) : null;
        $this->diagnosticCorrelationId = $correlationId ?: bin2hex(random_bytes(16));
        $this->diagnosticTopic = $topic;

        if (! is_array($event) || ! is_array($payload)) {
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_PAYLOAD_INVALID, 'Shopify external webhook payload is invalid.');
        }

        $store = strtolower(trim((string) ($event['store_domain'] ?? '')));
        $shopifyOrderId = $this->shopifyId($payload['order_id'] ?? '');
        $externalId = $this->shopifyId($payload['id'] ?? '');
        $this->stage($event, $shopifyOrderId, 'external_identity_resolved', null, 'shopify_order_imports', 'store_domain_shopify_order_id', false);
        if ($store === '' || $shopifyOrderId === '' || $externalId === '') {
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_IDENTITY_NOT_FOUND, 'Shopify external webhook identity is incomplete.');
        }

        $db = db_connect();
        $sql = 'SELECT * FROM shopify_order_imports WHERE store_domain = ? AND shopify_order_id = ? LIMIT 1';
        if (strtolower((string) $db->DBDriver) === 'mysqli') $sql .= ' FOR UPDATE';
        $db->transBegin();
        $this->stage($event, $shopifyOrderId, 'import_row_lookup_started', null, 'shopify_order_imports', 'store_domain_shopify_order_id', false);
        $import = $db->query($sql, [$store, $shopifyOrderId])->getRowArray() ?: null;
        $this->diagnosticImportId = (int) ($import['id'] ?? 0);
        $this->diagnosticImportStatus = (string) ($import['status'] ?? '');
        $this->diagnosticRowFound = $import !== null;
        $this->stage($event, $shopifyOrderId, 'import_row_lookup_completed', $import === null ? ShopifyOrderImportFailure::ORDER_IMPORT_RECORD_NOT_FOUND : null, 'shopify_order_imports', 'store_domain_shopify_order_id', $import !== null);
        if ($import === null) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::ORDER_IMPORT_RECORD_NOT_FOUND, 'The Shopify order import record was not found.');
        }

        try {
            $result = $topic === 'refunds/create'
                ? $this->reconcileExternalRefund($db, $event, $payload, $import, $externalId)
                : $this->reconcileExternalFulfillment($db, $event, $payload, $import, $externalId);

            if (! $db->transStatus()) throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify external reconciliation write failed.');
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
        $events->markProcessed($eventId);
        return array_merge($result, ['internal_import_id' => (int) $import['id'], 'shopify_order_id' => $shopifyOrderId, 'store_domain' => $store]);
    }

    private function reconcileExternalRefund($db, array $event, array $payload, array $import, string $refundId): array
    {
        $importId = (int) $import['id'];
        $orderId = (string) $import['shopify_order_id'];
        $this->stage($event, $orderId, 'refund_reconciliation_started', null, 'shopify_order_import_refunds', 'internal_import_id_refund_id', true);
        $currency = strtoupper(trim((string) ($payload['currency'] ?? $payload['presentment_currency'] ?? $import['currency'] ?? '')));
        $amount = (float) ($payload['total_refunded'] ?? $payload['amount'] ?? 0);
        if ($amount <= 0) foreach ((array) ($payload['refund_transactions'] ?? $payload['transactions'] ?? []) as $transaction) $amount += (float) ($transaction['amount'] ?? 0);
        $refundStatus = $amount > 0 && (float) ($import['total_snapshot'] ?? 0) > 0 && $amount < (float) $import['total_snapshot'] ? 'partially_refunded' : 'refunded';
        $table = $db->table('shopify_order_import_refunds');
        $existing = $table->where('import_id', $importId)->where('shopify_refund_id', $refundId)->get(1)->getRowArray();
        $data = ['store_domain' => (string) $import['store_domain'], 'shopify_order_id' => $orderId, 'shopify_refund_id' => $refundId, 'amount' => $amount, 'currency' => $currency !== '' ? $currency : null, 'status' => $refundStatus, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing === null) { $data['import_id'] = $importId; $data['created_at'] = date('Y-m-d H:i:s'); $table->insert($data); } else $table->where('id', (int) $existing['id'])->update($data);
        foreach ((array) ($payload['refund_line_items'] ?? []) as $line) {
            $lineId = trim((string) ($line['line_item_id'] ?? ''));
            if ($lineId === '' || ! $db->tableExists('shopify_order_import_line_refunds')) continue;
            $lineTable = $db->table('shopify_order_import_line_refunds');
            $lineData = ['import_id' => $importId, 'store_domain' => (string) $import['store_domain'], 'shopify_order_id' => $orderId, 'shopify_refund_id' => $refundId, 'shopify_line_item_id' => $lineId, 'quantity' => max(0, (int) ($line['quantity'] ?? 0)), 'amount' => (float) ($line['amount'] ?? 0), 'status' => $refundStatus, 'updated_at' => date('Y-m-d H:i:s')];
            $lineExisting = $lineTable->where(['import_id' => $importId, 'shopify_refund_id' => $refundId, 'shopify_line_item_id' => $lineId])->get(1)->getRowArray();
            if ($lineExisting === null) { $lineData['created_at'] = date('Y-m-d H:i:s'); $lineTable->insert($lineData); } else $lineTable->where('id', (int) $lineExisting['id'])->update($lineData);
            $db->table('shopify_order_import_lines')->where('import_id', $importId)->where('shopify_line_item_id', $lineId)->update(['review_status' => $refundStatus === 'partially_refunded' ? 'partially_refunded' : 'refunded', 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $affected = $db->affectedRows();
        $this->diagnosticAffectedRows = $affected;
        $this->stage($event, $orderId, 'refund_record_upserted', null, 'shopify_order_import_refunds', 'internal_import_id_refund_id', true, $affected);
        $updates = ['payment_status' => $refundStatus, 'financial_status' => $refundStatus, 'last_event_id' => (int) $event['id'], 'retry_count' => (int) ($import['retry_count'] ?? 0) + 1, 'error_message' => null, 'updated_at' => date('Y-m-d H:i:s')];
        $db->table('shopify_order_imports')->where('id', $importId)->update($updates);
        if ((int) ($import['rootins_order_id'] ?? 0) > 0) {
            $invoice = $db->table('invoices')->where('order_id', (int) $import['rootins_order_id']);
            if ((int) ($import['clinic_id'] ?? 0) > 0) $invoice->where('clinic_id', (int) $import['clinic_id']);
            $invoice->update(['payment_status' => $refundStatus, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        if ((int) ($import['rootins_order_id'] ?? 0) > 0) {
            $db->table('medicine_orders')->where('id', (int) $import['rootins_order_id'])->update(['payment_status' => $refundStatus, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->stage($event, $orderId, 'refund_payment_reconciled', null, 'shopify_order_imports', 'internal_import_id', true, $db->affectedRows());
        $this->queueNotification($importId, $refundStatus === 'partially_refunded' ? 'partial_refund' : 'complete_refund');
        return ['status' => 'processed', 'refund_id' => $refundId, 'refund_status' => $refundStatus, 'amount' => $amount, 'currency' => $currency, 'duplicate' => $existing !== null];
    }

    private function reconcileExternalFulfillment($db, array $event, array $payload, array $import, string $fulfillmentId): array
    {
        $importId = (int) $import['id'];
        $orderId = (string) $import['shopify_order_id'];
        $this->stage($event, $orderId, 'fulfillment_reconciliation_started', null, 'shopify_order_import_fulfillments', 'internal_import_id_fulfillment_id', true);
        $rawStatus = strtolower(trim((string) ($payload['status'] ?? $payload['fulfillment_status'] ?? '')));
        $status = match ($rawStatus) { 'success', 'fulfilled' => 'fulfilled', 'open', 'in_progress', 'processing' => 'processing', 'cancelled', 'canceled' => 'cancelled', 'failure', 'failed' => 'failed', default => $rawStatus !== '' ? $rawStatus : 'processing' };
        $trackingNumber = trim((string) ($payload['tracking_number'] ?? ((array) ($payload['tracking_numbers'] ?? []))[0] ?? ''));
        $trackingUrl = trim((string) ($payload['tracking_url'] ?? ((array) ($payload['tracking_urls'] ?? []))[0] ?? ''));
        $carrier = trim((string) ($payload['tracking_company'] ?? $payload['shipping_provider'] ?? ''));
        $table = $db->table('shopify_order_import_fulfillments');
        $existing = $table->where('import_id', $importId)->where('shopify_fulfillment_id', $fulfillmentId)->get(1)->getRowArray();
        $data = ['store_domain' => (string) $import['store_domain'], 'shopify_order_id' => $orderId, 'shopify_fulfillment_id' => $fulfillmentId, 'status' => $status, 'tracking_company' => $carrier !== '' ? $carrier : null, 'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null, 'tracking_url' => $trackingUrl !== '' ? $trackingUrl : null, 'fulfilled_at' => $status === 'fulfilled' ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing === null) { $data['import_id'] = $importId; $data['created_at'] = date('Y-m-d H:i:s'); $table->insert($data); } else $table->where('id', (int) $existing['id'])->update($data);
        foreach ((array) ($payload['fulfillment_line_items'] ?? []) as $line) {
            $lineId = trim((string) ($line['line_item_id'] ?? ''));
            if ($lineId === '' || ! $db->tableExists('shopify_order_import_line_fulfillments')) continue;
            $lineTable = $db->table('shopify_order_import_line_fulfillments');
            $lineData = ['import_id' => $importId, 'store_domain' => (string) $import['store_domain'], 'shopify_order_id' => $orderId, 'shopify_fulfillment_id' => $fulfillmentId, 'shopify_line_item_id' => $lineId, 'quantity' => max(0, (int) ($line['quantity'] ?? 0)), 'status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
            $lineExisting = $lineTable->where(['import_id' => $importId, 'shopify_fulfillment_id' => $fulfillmentId, 'shopify_line_item_id' => $lineId])->get(1)->getRowArray();
            if ($lineExisting === null) { $lineData['created_at'] = date('Y-m-d H:i:s'); $lineTable->insert($lineData); } else $lineTable->where('id', (int) $lineExisting['id'])->update($lineData);
            $db->table('shopify_order_import_lines')->where('import_id', $importId)->where('shopify_line_item_id', $lineId)->update(['review_status' => $status === 'fulfilled' ? 'fulfilled' : 'partially_fulfilled', 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $affected = $db->affectedRows();
        $this->diagnosticAffectedRows = $affected;
        $this->stage($event, $orderId, 'fulfillment_record_upserted', null, 'shopify_order_import_fulfillments', 'internal_import_id_fulfillment_id', true, $affected);
        $dbStatus = $status === 'fulfilled' ? 'fulfilled' : ($status === 'cancelled' ? 'returned' : ($status === 'failed' ? 'failed' : 'processing'));
        $db->table('shopify_order_imports')->where('id', $importId)->update(['fulfillment_status' => $status, 'last_event_id' => (int) $event['id'], 'retry_count' => (int) ($import['retry_count'] ?? 0) + 1, 'error_message' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        if ((int) ($import['rootins_order_id'] ?? 0) > 0) $db->table('medicine_orders')->where('id', (int) $import['rootins_order_id'])->update(['delivery_status' => $dbStatus, 'delivery_provider' => $carrier, 'tracking_reference' => $trackingNumber, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->stage($event, $orderId, 'fulfillment_delivery_reconciled', null, 'shopify_order_imports', 'internal_import_id', true, $db->affectedRows());
        $this->queueNotification($importId, $status === 'fulfilled' ? 'complete_fulfilment' : 'partial_fulfilment');
        return ['status' => 'processed', 'fulfillment_id' => $fulfillmentId, 'delivery_status' => $status, 'duplicate' => $existing !== null];
    }

    private function queueNotification(int $importId, string $type): void
    {
        if (! db_connect()->tableExists('shopify_order_notifications')) return;
        $import = ($this->imports ?? new ShopifyOrderImportModel())->find($importId);
        if ($import === null) return;
        $contacts = ['email' => (string) ($import['customer_email_snapshot'] ?? ''), 'sms' => (string) ($import['customer_mobile_snapshot'] ?? ''), 'whatsapp' => (string) ($import['customer_mobile_snapshot'] ?? '')];
        foreach ($contacts as $channel => $address) {
            if (trim($address) === '') continue;
            try { (new ShopifyOrderNotificationModel())->insert(['store_domain' => (string) ($import['store_domain'] ?? ''), 'import_id' => $importId, 'patient_id' => (int) ($import['patient_id'] ?? 0) ?: null, 'shopify_customer_id' => (string) ($import['shopify_customer_id'] ?? ''), 'shopify_order_id' => (string) ($import['shopify_order_id'] ?? ''), 'notification_type' => $type, 'channel' => $channel, 'template_key' => 'shopify.' . $type, 'safe_payload_json' => json_encode(['order_id' => (string) ($import['shopify_order_number'] ?? $import['shopify_order_id'] ?? ''), 'recipient' => $address], JSON_UNESCAPED_SLASHES), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $e) { log_message('warning', 'Shopify notification outbox enqueue failed: ' . get_class($e)); }
        }
    }

    /** @return array<string, mixed> */
    private function upsertImport(array $event, array $payload): array
    {
        $model = $this->imports ?? new ShopifyOrderImportModel();
        $store = strtolower(trim((string) $event['store_domain']));
        $orderId = $this->shopifyId($payload['id'] ?? '');
        $existing = $model->where('store_domain', $store)->where('shopify_order_id', $orderId)->first();
        $this->diagnosticImportId = (int) ($existing['id'] ?? 0);
        $this->diagnosticRowFound = $existing !== null;
        $this->stage($event, $orderId, 'import_row_before_upsert', null, 'shopify_order_imports', 'store_domain_shopify_order_id', $existing !== null);
        $existedBeforeUpsert = $existing !== null;
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

                // The competing insert won the identity race. Preserve its
                // current status and retry metadata; never reset a failed row
                // back to received while reconciling the same order.
                unset($data['status'], $data['retry_count'], $data['created_at']);
                $model->update((int) $existing['id'], $data);

            }
        } else {
            $data['retry_count'] = (int) ($existing['retry_count'] ?? 0) + 1;
            $model->update((int) $existing['id'], $data);
        }

        $actualId = $existing !== null ? (int) $existing['id'] : (int) $model->getInsertID();
        $record = $model->find($actualId) ?? $data + ['id' => $actualId];
        $this->importIdentityDiagnostic($event, $orderId, $actualId, $existedBeforeUpsert, 'store_domain_shopify_order_id', 'order_creation_update');

        return $record;
    }

    private function importIdentityDiagnostic(array $event, string $shopifyOrderId, int $importId, bool $existedBeforeUpsert, string $lookupType, string $stage): void
    {
        log_message('info', 'Shopify medicine-order import identity diagnostic ' . json_encode([
            'shopify_order_id' => $shopifyOrderId,
            'internal_import_id' => $importId > 0 ? $importId : null,
            'import_row_existed_before_upsert' => $existedBeforeUpsert,
            'lookup_type' => $lookupType,
            'processing_stage' => $stage,
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
        ], JSON_UNESCAPED_SLASHES));
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
            $this->diagnosticLineId = $lineId;
            if ($event !== null) $this->stage($event, $orderId, 'line_item_identity_resolution', $lineId === '' ? ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ID_INVALID : null);
            if ($lineId === '') {
                return ['status' => 'invalid', 'reason' => 'Shopify line-item identity is missing.', 'category' => ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_ID_INVALID];
            }
            $productId = $this->shopifyId($item['product_id'] ?? null);
            $variantId = $this->shopifyId($item['variant_id'] ?? null);
            $this->diagnosticProductId = $productId;
            $this->diagnosticVariantId = $variantId;
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
                'review_status' => $existing['review_status'] ?? ($resolution['result'] === 'mapped' ? 'mapped' : ($resolution['result'] === 'inactive' ? 'inactive' : 'unmapped')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            try {
                $this->upsertImportLine($lineModel, $existing, $lineData);
            } catch (\Throwable $exception) {
                $dbError = db_connect()->error();
                $sanitizedMessage = $this->sanitizeDatabaseMessage($exception->getMessage());
                if ($event !== null) {
                    $this->stage($event, $this->shopifyId($payload['id'] ?? ''), 'line_item_database_upsert', ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, 'shopify_order_import_lines', 'store_domain_shopify_order_id_line_id', $existing !== null, (int) db_connect()->affectedRows(), $exception, [
                        'database_error_code' => (string) ($dbError['code'] ?? ''),
                        'sanitized_database_message' => $sanitizedMessage,
                    ]);
                }
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, 'Shopify order line write failed: ' . $sanitizedMessage);
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
            if (! $model->update((int) $existing['id'], $data)) {
                $error = db_connect()->error();
                throw new RuntimeException('Shopify order line update failed: ' . $this->sanitizeDatabaseMessage((string) ($error['message'] ?? 'database update returned false')));
            }
            return;
        }

        $data['created_at'] = $data['updated_at'];
        try {
            if ($model->insert($data) === false) {
                $error = db_connect()->error();
                throw new RuntimeException('Shopify order line insert failed: ' . $this->sanitizeDatabaseMessage((string) ($error['message'] ?? 'database insert returned false')));
            }
        } catch (\Throwable $exception) {
            $race = $model
                ->where('store_domain', $data['store_domain'])
                ->where('shopify_order_id', $data['shopify_order_id'])
                ->where('shopify_line_item_id', $data['shopify_line_item_id'])
                ->first();
            if ($race === null) {
                throw $exception;
            }
            if (! $model->update((int) $race['id'], $data)) {
                $error = db_connect()->error();
                throw new RuntimeException('Shopify order line race-update failed: ' . $this->sanitizeDatabaseMessage((string) ($error['message'] ?? 'database update returned false')));
            }
        }
    }

    private function linkedCustomer(string $store, string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }

        $link = (new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $store)
            ->where('shopify_customer_id', $customerId)
            ->where('link_status', 'linked')
            ->first() ?: null;
        if ($link !== null) {
            return $link;
        }

        // Medicine-order webhooks may consume only a pre-created, linked
        // identity. They must never infer a patient from email/phone or
        // create a second patient during order import.
        return null;
    }

    private function refreshImportedPatientIdentity(int $importId, string $store, string $customerId): void
    {
        if ($customerId === '') {
            return;
        }

        try {
            $identity = $this->linkedCustomer($store, $customerId);
            if ($identity !== null && (int) ($identity['patient_id'] ?? 0) > 0) {
                ($this->imports ?? new ShopifyOrderImportModel())->update($importId, [
                    'patient_id' => (int) $identity['patient_id'],
                    'patient_status' => 'linked',
                    'last_error' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $exception) {
            log_message('warning', 'Shopify medicine-order patient refresh unavailable exception=' . get_class($exception));
        }
    }

    /** @param array<string, mixed> $event @param array<string, mixed> $payload @return array<string, mixed> */
    private function hydrateOrderCustomer(array $event, array $payload): array
    {
        $customerId = $this->shopifyId($payload['customer_id'] ?? ($payload['customer']['id'] ?? ''));
        if ($customerId === '') return $payload;

        $payload['customer_id'] = $customerId;
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $hasDetails = trim((string) ($customer['first_name'] ?? $customer['firstName'] ?? '')) !== ''
            || trim((string) ($customer['last_name'] ?? $customer['lastName'] ?? '')) !== ''
            || trim((string) ($customer['email'] ?? '')) !== ''
            || trim((string) ($customer['phone'] ?? $customer['mobile'] ?? '')) !== '';

        if ($hasDetails) {
            $payload['customer'] = [
                'first_name' => (string) ($customer['first_name'] ?? $customer['firstName'] ?? ''),
                'last_name' => (string) ($customer['last_name'] ?? $customer['lastName'] ?? ''),
                'email' => (string) ($customer['email'] ?? ''),
                'phone' => (string) ($customer['phone'] ?? $customer['mobile'] ?? ''),
            ];
            return $payload;
        }

        try {
            $lookup = (new ShopifyCustomerLookupService())->lookup($customerId);
            $payload['customer'] = [
                'first_name' => (string) ($lookup['first_name'] ?? ''),
                'last_name' => (string) ($lookup['last_name'] ?? ''),
                'email' => (string) ($lookup['email'] ?? ''),
                'phone' => (string) ($lookup['mobile'] ?? ''),
            ];
            log_message('info', 'Shopify medicine-order customer lookup completed order=' . $this->shopifyId($payload['id'] ?? '') . ' customer=' . $customerId . ' store=' . strtolower(trim((string) ($event['store_domain'] ?? ''))));
        } catch (\Throwable $exception) {
            $this->customerLookupFailure = 'SHOPIFY_CUSTOMER_DATA_UNAVAILABLE';
            log_message('warning', 'Shopify medicine-order customer lookup unavailable category=SHOPIFY_CUSTOMER_DATA_UNAVAILABLE exception=' . get_class($exception));
        }

        return $payload;
    }

    /** @param array<string, mixed> $event @param array<string, mixed> $payload */
    private function resolveOrderCustomerIdentity(array $event, array $payload): void
    {
        // Patient registration/linking is a checkout readiness step. The
        // medicine-order webhook only consumes the existing scoped link in
        // linkedCustomer(); it must not infer or create patient identities.
    }

    private function findPrescriptionDocument(string $reference, string $store, string $customerId, int $patientId): ?array
    {
        return db_connect()->table('patient_documents')
            ->where('source_type', 'shopify')->where('external_upload_reference', $reference)
            ->where('shopify_store_domain', $store)->where('shopify_customer_id', $customerId)
            ->where('patient_id', $patientId)->get(1)->getRowArray();
    }

    private function prescriptionImportDiagnostic(string $shopifyOrderId, int $importId, string $reference, int $documentId, int $patientId, int $clinicId, string $lookup, bool $found, ?string $failure): void
    {
        log_message('info', 'Shopify prescription import diagnostic ' . json_encode([
            'correlation_id' => $this->diagnosticCorrelationId !== '' ? $this->diagnosticCorrelationId : bin2hex(random_bytes(8)),
            'shopify_order_id' => $shopifyOrderId,
            'import_id' => $importId,
            'upload_reference' => $reference !== '' ? $reference : null,
            'document_id' => $documentId > 0 ? $documentId : null,
            'patient_id' => $patientId > 0 ? $patientId : null,
            'clinic_id' => $clinicId > 0 ? $clinicId : null,
            'lookup_table' => $lookup === 'prescription_linkage' ? 'shopify_prescription_order_links' : 'shopify_order_imports',
            'lookup_result' => $found ? 'found' : 'not_found',
            'failure_category' => $failure,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function findDoctorPrescription(string $reference, int $patientId, int $clinicId): ?array
    {
        if ($reference === '' || $patientId <= 0 || $clinicId <= 0) return null;
        return db_connect()->table('prescriptions p')
            ->select('p.id, p.valid_until, p.duration_days, p.status')
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->where('p.prescription_number', $reference)
            ->where('p.patient_id', $patientId)
            ->where('p.clinic_id', $clinicId)
            ->get(1)->getRowArray() ?: null;
    }

    private function prescriptionIdForReference(string $reference, int $patientId, int $clinicId): ?int
    {
        $row = $this->findDoctorPrescription(trim($reference), $patientId, $clinicId);
        return $row !== null && ShopifyPrescriptionEligibility::isOrderable($row) ? (int) $row['id'] : null;
    }

    private function prescriptionItemsMatchOrder(int $prescriptionId, int $importId): bool
    {
        $prescribed = [];
        foreach (db_connect()->table('prescription_items')->select('medicine_id, quantity')->where('prescription_id', $prescriptionId)->get()->getResultArray() as $item) {
            $medicineId = (int) ($item['medicine_id'] ?? 0);
            if ($medicineId <= 0) return false;
            $prescribed[$medicineId] = ($prescribed[$medicineId] ?? 0) + (float) ($item['quantity'] ?? 0);
        }
        $ordered = [];
        foreach (db_connect()->table('shopify_order_import_lines')->select('medicine_id, quantity, status')->where('import_id', $importId)->get()->getResultArray() as $line) {
            $medicineId = (int) ($line['medicine_id'] ?? 0);
            if ($medicineId <= 0 || ! isset($prescribed[$medicineId]) || ! in_array((string) ($line['status'] ?? ''), ['mapped', 'imported', 'pending'], true)) return false;
            $ordered[$medicineId] = ($ordered[$medicineId] ?? 0) + (float) ($line['quantity'] ?? 0);
        }
        foreach ($ordered as $medicineId => $quantity) if ($quantity > ($prescribed[$medicineId] ?? 0)) return false;
        return $ordered !== [];
    }

    private function orderablePrescriptionMatches(int $prescriptionId, int $patientId, int $clinicId): bool
    {
        $row = db_connect()->table('prescriptions p')
            ->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')
            ->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')
            ->where('p.id', $prescriptionId)
            ->where('p.patient_id', $patientId)
            ->where('p.clinic_id', $clinicId)
            ->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)
            ->where('p.duration_days >', 0)
            ->where('p.valid_until IS NOT NULL', null, false)
            ->where('p.valid_until >=', ShopifyPrescriptionEligibility::today())
            ->get(1)
            ->getRowArray();

        return $row !== null;
    }

    private function setImportState(int $id, string $status, ?string $reason, int $eventId, int $patientId = 0, int $clinicId = 0, int $rootinsOrderId = 0): void
    {
        $this->diagnosticImportId = $id;
        $this->diagnosticImportStatus = $status;
        $imports = $this->imports ?? new ShopifyOrderImportModel();
        $existing = $imports->find($id);
        if ($existing !== null && ((string) ($existing['status'] ?? '') !== $status || (string) ($existing['failure_reason'] ?? '') !== (string) ($reason ?? ''))) {
            $this->recordImportHistory($id, $eventId, 'status_transition', (string) ($existing['status'] ?? ''), $status, (string) ($existing['failure_reason'] ?? '') ?: $reason);
        }
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
        $imports->update($id, $data);
    }

    private function recordImportHistory(int $importId, int $eventId, string $action, ?string $fromStatus, ?string $toStatus, ?string $reason = null, ?string $error = null): void
    {
        $db = db_connect();
        if (! $db->tableExists('shopify_order_import_history')) return;
        $db->table('shopify_order_import_history')->insert([
            'import_id' => $importId,
            'event_id' => $eventId > 0 ? $eventId : null,
            'action' => $action,
            'from_status' => $fromStatus ?: null,
            'to_status' => $toStatus ?: null,
            'reason' => $reason ?: null,
            'error_message' => $error ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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

    /** @return never */
    private function pharmacyOrderFailure(array $event, int $importId, string $shopifyOrderId, string $category, string $message, array $context = []): never
    {
        $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, false, 'failure', 0, 0, $category, null, $context);
        $this->stage($event, $shopifyOrderId, 'pharmacy_order_upsert_failed', $category, 'medicine_orders', 'internal_import_id', false, 0);
        throw new ShopifyOrderImportFailure($category, $message);
    }

    private function upsertPrescriptionlessPharmacyOrder(BaseConnection $db, array $event, int $importId, int $patientId, int $clinicId, string $store, string $shopifyOrderId, array $payload): int
    {
        $now = date('Y-m-d H:i:s');
        $idempotencyKey = 'shopify-import:' . $importId;
        $paymentStatus = $this->paymentTarget((string) ($payload['financial_status'] ?? '')) ?? 'pending';
        $currency = strtoupper(substr(trim((string) ($payload['currency'] ?? 'INR')), 0, 3)) ?: 'INR';
        $total = round((float) ($payload['total_price'] ?? 0), 2);

        try {
            $existing = $db->query('SELECT id, order_number FROM medicine_orders WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey])->getRowArray();
            if ($existing === null) {
                $existing = $db->query('SELECT id, order_number FROM medicine_orders WHERE external_store_domain = ? AND external_order_id = ? LIMIT 1 FOR UPDATE', [$store, $shopifyOrderId])->getRowArray();
            }

            if ($existing !== null) {
                $orderId = (int) $existing['id'];
                $db->table('medicine_orders')->where('id', $orderId)->update([
                    'patient_id' => $patientId,
                    'clinic_id' => $clinicId,
                    'payment_status' => $paymentStatus,
                    'external_store_domain' => $store,
                    'external_order_id' => $shopifyOrderId,
                    'external_order_number' => (string) ($payload['order_number'] ?? ''),
                    'delivery_address_snapshot' => $this->addressSnapshot($payload['shipping_address'] ?? []),
                    'updated_at' => $now,
                ]);
            } else {
                $orderId = (int) $db->table('medicine_orders')->insert([
                    'order_number' => 'MO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                    'prescription_id' => null,
                    'patient_id' => $patientId,
                    'clinic_id' => $clinicId,
                    'status' => 'pending',
                    'payment_status' => $paymentStatus,
                    'delivery_status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'source_type' => 'shopify',
                    'external_store_domain' => $store,
                    'external_order_id' => $shopifyOrderId,
                    'external_order_number' => (string) ($payload['order_number'] ?? ''),
                    'delivery_address_snapshot' => $this->addressSnapshot($payload['shipping_address'] ?? []),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], true);
            }

            if ($orderId <= 0) {
                throw new RuntimeException('Pharmacy order insert returned no row.');
            }

            $invoice = $db->table('invoices')->where('order_id', $orderId)->get(1)->getRowArray();
            $invoiceData = [
                'clinic_id' => $clinicId,
                'patient_id' => $patientId,
                'currency' => $currency,
                'subtotal' => $total,
                'total' => $total,
                'payment_status' => $paymentStatus,
                'updated_at' => $now,
            ];
            if ($invoice === null) {
                $invoiceData += [
                    'order_id' => $orderId,
                    'invoice_number' => 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2))),
                    'tax' => 0,
                    'discount' => 0,
                    'status' => 'draft',
                    'idempotency_key' => 'order:' . $orderId,
                    'created_at' => $now,
                ];
                if (! $db->table('invoices')->insert($invoiceData)) throw new RuntimeException('Invoice insert failed.');
            } elseif (! $db->table('invoices')->where('id', (int) $invoice['id'])->update($invoiceData)) {
                throw new RuntimeException('Invoice update failed.');
            }

            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['rootins_order_id' => $orderId, 'updated_at' => $now]);
            $db->transCommit();
            $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, true, 'success', $orderId, 1, null, null, ['prescription_status' => 'not_required', 'prescription_id' => null, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
            $this->stage($event, $shopifyOrderId, 'pharmacy_order_created_or_updated', null, 'medicine_orders', 'internal_import_id', true, 1);
            return $orderId;
        } catch (ShopifyOrderImportFailure $failure) {
            $db->transRollback();
            throw $failure;
        } catch (\Throwable $exception) {
            $db->transRollback();
            $error = $db->error();
            $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, false, 'failure', 0, (int) $db->affectedRows(), ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE, $exception, [
                'prescription_status' => 'not_required',
                'prescription_id' => null,
                'patient_id' => $patientId,
                'clinic_id' => $clinicId,
                'database_error_code' => (string) ($error['code'] ?? ''),
                'sanitized_database_message' => $this->sanitizeDatabaseMessage((string) ($error['message'] ?? $exception->getMessage())),
                'failed_field_or_constraint' => $this->databaseFieldHint((string) ($error['message'] ?? $exception->getMessage())),
            ]);
            $this->stage($event, $shopifyOrderId, 'pharmacy_order_upsert_failed', ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE, 'medicine_orders', 'internal_import_id', false, (int) $db->affectedRows(), $exception);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE, 'Pharmacy order database write failed.');
        }
    }

    /**
     * Resolve the pharmacy order from the imported order identity. Creation is
     * idempotent through shopify-import:<internal import id>; payment never
     * has to depend on a pre-existing pharmacy row.
     */
    private function ensurePharmacyOrderForImport(array $event, int $importId, ?int $prescriptionId, int $patientId, int $clinicId, string $store, string $shopifyOrderId, array $payload, bool $prescriptionRequired): int
    {
        $this->stage($event, $shopifyOrderId, 'pharmacy_order_lookup_started', null, 'medicine_orders', 'internal_import_id', false);
        $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, false, 'started', 0, 0);
        $db = db_connect();
        $db->transBegin();
        if ($patientId <= 0) {
            $db->transRollback();
            return $this->pharmacyOrderFailure($event, $importId, $shopifyOrderId, ShopifyOrderImportFailure::PATIENT_NOT_FOUND, 'The linked ROOTINS patient was not found.', ['prescription_status' => $prescriptionRequired ? 'required' : 'not_required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
        }
        if ($clinicId <= 0 || $db->table('clinics')->where('id', $clinicId)->where('status', 1)->get(1)->getRowArray() === null) {
            $db->transRollback();
            return $this->pharmacyOrderFailure($event, $importId, $shopifyOrderId, ShopifyOrderImportFailure::PHARMACY_ORDER_CLINIC_SCOPE_INVALID, 'The configured pharmacy billing clinic is invalid.', ['prescription_status' => $prescriptionRequired ? 'required' : 'not_required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
        }
        $patient = $db->table('patients')->select('id')->where('id', $patientId)->where('status', 1)->get(1)->getRowArray();
        if ($patient === null) {
            $db->transRollback();
            return $this->pharmacyOrderFailure($event, $importId, $shopifyOrderId, ShopifyOrderImportFailure::PATIENT_NOT_FOUND, 'The linked ROOTINS patient was not found.', ['prescription_status' => $prescriptionRequired ? 'required' : 'not_required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
        }
        if ($prescriptionRequired && ($prescriptionId === null || $prescriptionId <= 0 || ! $this->orderablePrescriptionMatches($prescriptionId, $patientId, $clinicId))) {
            $db->transRollback();
            return $this->pharmacyOrderFailure($event, $importId, $shopifyOrderId, ShopifyOrderImportFailure::PHARMACY_ORDER_PRESCRIPTION_REQUIRED, 'A valid completed prescription is required for this medicine order.', ['prescription_status' => 'required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
        }
        $existing = $db->query('SELECT id FROM medicine_orders WHERE idempotency_key = ? AND clinic_id = ? LIMIT 1 FOR UPDATE', ['shopify-import:' . $importId, $clinicId])->getRowArray();
        if ($existing === null) {
            $existing = $db->query('SELECT id FROM medicine_orders WHERE external_store_domain = ? AND external_order_id = ? AND clinic_id = ? LIMIT 1 FOR UPDATE', [$store, $shopifyOrderId, $clinicId])->getRowArray();
        }
        if ($existing !== null) {
            $orderId = (int) $existing['id'];
            $this->synchronizePharmacyOrderCommercials($orderId, $clinicId, $patientId, $store, $shopifyOrderId, $payload);
            ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['rootins_order_id' => $orderId, 'updated_at' => date('Y-m-d H:i:s')]);
            $db->transCommit();
            $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, true, 'success', $orderId, 1);
            $this->stage($event, $shopifyOrderId, 'pharmacy_order_created_or_updated', null, 'medicine_orders', 'internal_import_id', true, 1);
            return $orderId;
        }

        if (! $prescriptionRequired) {
            return $this->upsertPrescriptionlessPharmacyOrder($db, $event, $importId, $patientId, $clinicId, $store, $shopifyOrderId, $payload);
        }

        // OrderFulfilmentService owns its own inventory transaction. Release
        // the lookup lock before invoking it, then link the created row back
        // to this import by the idempotency key/internal import ID.
        $db->transRollback();

        try {
            $result = (new OrderFulfilmentService(new PharmacyScope(), $clinicId))->create($prescriptionId, [
                'source_type' => 'shopify',
                'external_store_domain' => $store,
                'external_order_id' => $shopifyOrderId,
                'external_order_number' => (string) ($payload['order_number'] ?? ''),
                'idempotency_key' => 'shopify-import:' . (string) $importId,
                'delivery_address_snapshot' => $this->addressSnapshot($payload['shipping_address'] ?? []),
                'diagnostic_context' => [
                    'correlation_id' => $this->diagnosticCorrelationId,
                    'shopify_order_id' => $shopifyOrderId,
                    'internal_import_id' => $importId,
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, false, 'failure', 0, 0, ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE, $exception, ['prescription_status' => $prescriptionRequired ? 'required' : 'not_required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId]);
            $this->stage($event, $shopifyOrderId, 'pharmacy_order_upsert_failed', ShopifyOrderImportFailure::PHARMACY_ORDER_CREATION_FAILED, 'medicine_orders', 'internal_import_id', false, 0, $exception);
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE, 'Pharmacy order creation failed.');
        }

        $orderId = (int) ($result['body']['data']['order_id'] ?? 0);
        if ((int) ($result['http'] ?? 500) !== 200 || $orderId <= 0) {
            $category = ShopifyOrderImportFailure::fromFulfilmentResult($result);
            $category = in_array($category, [ShopifyOrderImportFailure::UNKNOWN_IMPORT_FAILURE, ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE], true)
                ? ShopifyOrderImportFailure::PHARMACY_ORDER_DATABASE_FAILURE
                : $category;
            $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, false, 'failure', $orderId, 0, $category, null, ['prescription_status' => 'required', 'prescription_id' => $prescriptionId, 'patient_id' => $patientId, 'clinic_id' => $clinicId, 'sanitized_database_message' => (string) ($result['body']['msg'] ?? '')]);
            $this->stage($event, $shopifyOrderId, 'pharmacy_order_upsert_failed', $category, 'medicine_orders', 'internal_import_id', false, 0);
            throw new ShopifyOrderImportFailure($category, 'Pharmacy order creation returned no order record.');
        }

        $this->synchronizePharmacyOrderCommercials($orderId, $clinicId, $patientId, $store, $shopifyOrderId, $payload);
        ($this->imports ?? new ShopifyOrderImportModel())->update($importId, ['rootins_order_id' => $orderId, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->pharmacyOrderDiagnostic($event, $importId, $shopifyOrderId, true, 'success', $orderId, 1);
        $this->stage($event, $shopifyOrderId, 'pharmacy_order_created_or_updated', null, 'medicine_orders', 'internal_import_id', true, 1);
        return $orderId;
    }

    private function synchronizePharmacyOrderCommercials(int $orderId, int $clinicId, int $patientId, string $store, string $shopifyOrderId, array $payload): void
    {
        if ($orderId <= 0 || $clinicId <= 0) return;
        $paymentStatus = $this->paymentTarget((string) ($payload['financial_status'] ?? ''));
        $changes = ['external_store_domain' => $store, 'external_order_id' => $shopifyOrderId, 'external_order_number' => (string) ($payload['order_number'] ?? ''), 'updated_at' => date('Y-m-d H:i:s')];
        if ($patientId > 0) $changes['patient_id'] = $patientId;
        if ($paymentStatus !== null) $changes['payment_status'] = $paymentStatus;
        db_connect()->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->update($changes);
        $invoice = db_connect()->table('invoices')->where('order_id', $orderId)->where('clinic_id', $clinicId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if ($invoice !== null) {
            $invoiceChanges = ['currency' => (string) ($payload['currency'] ?? $invoice['currency'] ?? 'INR'), 'updated_at' => date('Y-m-d H:i:s')];
            if (array_key_exists('total_price', $payload)) $invoiceChanges['total'] = (float) $payload['total_price'];
            if ($paymentStatus !== null) $invoiceChanges['payment_status'] = $paymentStatus;
            db_connect()->table('invoices')->where('id', (int) $invoice['id'])->update($invoiceChanges);
        }
    }

    private function pharmacyOrderDiagnostic(array $event, int $importId, string $shopifyOrderId, bool $rowFound, string $result, int $rowId, int $affectedRows, ?string $category = null, ?\Throwable $exception = null, array $context = []): void
    {
        log_message('info', 'Shopify medicine-order pharmacy upsert diagnostic ' . json_encode([
            'correlation_id' => $this->diagnosticCorrelationId,
            'topic' => (string) ($event['topic'] ?? $this->diagnosticTopic),
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
            'shopify_order_id' => $shopifyOrderId,
            'webhook_id_hash' => ! empty($event['external_event_id']) ? hash('sha256', (string) $event['external_event_id']) : null,
            'internal_import_id' => $importId,
            'processing_stage' => 'pharmacy_order_upsert',
            'target_table' => 'medicine_orders',
            'lookup_type' => 'internal_import_id',
            'row_found' => $rowFound,
            'row_id' => $rowId > 0 ? $rowId : null,
            'affected_row_count' => $affectedRows,
            'upsert_result' => $result,
            'failure_category' => $category ?? 'none',
            'exception_class' => $exception === null ? null : get_class($exception),
            'database_error_code' => (string) ($context['database_error_code'] ?? ''),
            'sanitized_database_message' => (string) ($context['sanitized_database_message'] ?? ''),
            'failed_field_or_constraint' => (string) ($context['failed_field_or_constraint'] ?? ''),
            'prescription_status' => (string) ($context['prescription_status'] ?? ''),
            'prescription_id' => $context['prescription_id'] ?? null,
            'patient_id' => $context['patient_id'] ?? null,
            'clinic_id' => $context['clinic_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function sanitizeDatabaseMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?: '';
        $message = preg_replace('/\b\d{8,}\b/', '[id]', $message) ?: $message;
        return substr($message, 0, 240);
    }

    private function databaseFieldHint(string $message): string
    {
        if (preg_match('/(?:column|field) [\'`"]?([a-zA-Z0-9_]+)[\'`"]?/i', $message, $match)) return $match[1];
        if (preg_match('/constraint [`\'\"]?([^ `\'\"]+)/i', $message, $match)) return $match[1];
        return '';
    }

    private function applyExternalStatuses(int $orderId, array $payload, string $store, int $eventId, int $clinicId, string $topic = '', int $importId = 0): void
    {
        $event = ($this->events ?? new ShopifyWebhookEventService())->find($eventId) ?? [
            'id' => $eventId,
            'topic' => $topic,
            'store_domain' => $store,
        ];
        $shopifyOrderId = $this->shopifyId($payload['id'] ?? '');
        $this->stage($event, $shopifyOrderId, 'payment_update_started', null, 'shopify_order_imports', 'internal_import_id', true);
        $prescriptionRequired = $this->prescriptionRequiredForOrder($store, $payload);
        $import = ($this->imports ?? new ShopifyOrderImportModel())->find($importId) ?: [];
        $patientId = (int) ($import['patient_id'] ?? 0);
        $prescriptionId = $this->prescriptionIdForReference((string) ($import['prescription_reference'] ?? $payload['prescription_reference'] ?? ''), $patientId, $clinicId);
        $orderId = $this->ensurePharmacyOrderForImport(
            $event,
            $importId,
            $prescriptionId,
            $patientId,
            $clinicId,
            $store,
            $shopifyOrderId,
            $payload,
            $prescriptionRequired,
        );
        $this->paymentStage($event, $shopifyOrderId, $importId, $orderId, 'payment_update', 'lookup_started');
        $importId = $this->updateImportedPaymentRecord($event, $importId, $payload);
        $paymentTarget = $this->paymentTarget((string) ($payload['financial_status'] ?? ''));

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
        $this->paymentUpdateDiagnostic($event, (string) $importId, $shopifyOrderId, true, 'before_pharmacy_status_update', null, null, null, 'medicine_orders');
        $deliveryTarget = $this->deliveryTarget((string) ($payload['fulfillment_status'] ?? ''), '');
        $rawFulfillment = strtolower(trim((string) ($payload['fulfillment_status'] ?? '')));
        if ($rawFulfillment !== '' && ! in_array($rawFulfillment, ['pending', 'packed', 'fulfilled', 'out_for_delivery', 'delivered', 'failed'], true)) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::INVALID_FULFILLMENT_STATE, 'Shopify fulfillment state is not supported.');
        }
        $changes = [
            'payment_provider' => 'shopify',
            'payment_reference' => (string) ($payload['gateway'] ?? ''),
            'delivery_provider' => (string) ($payload['shipping_provider'] ?? ''),
            'tracking_reference' => (string) ($payload['tracking_number'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($paymentTarget !== null) {
            $changes['payment_status'] = $paymentTarget;
        }

        if ($deliveryTarget !== null) {
            $changes['delivery_status'] = $deliveryTarget;
        }

        if ($deliveryTarget === 'delivered') {
            $changes['delivered_at'] = date('Y-m-d H:i:s');
        }
        if ($deliveryTarget === 'failed') {
            $changes['failure_reason'] = ShopifyOrderImportFailure::DELIVERY_STATE_INVALID;
        }

        // Payment is already committed against shopify_order_imports.id.
        // Do not look up a pharmacy order by ID and make paid processing
        // depend on that local record being present.
        $this->stage($event, $shopifyOrderId, 'pharmacy_order_lookup_started', null, 'medicine_orders', 'internal_import_id', $orderId > 0);
        if ($orderId > 0) {
            $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->update($changes);
        }

        if (!$db->transStatus()) {
            $db->transRollback();

            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify order status update failed.');
        }
        $db->transCommit();
        $this->stage($event, $shopifyOrderId, 'pharmacy_order_created_or_updated', null, 'medicine_orders', 'internal_import_id', $orderId > 0, $orderId > 0 ? 1 : 0);
        $this->stage($event, $shopifyOrderId, 'import_status_update_started', null, 'shopify_order_imports', 'internal_import_id', true);
        if ($paymentTarget === 'paid') {
            $this->markPaidImportProcessed($event, $importId, $paymentTarget);
        }
        $this->diagnosticImportStatus = $paymentTarget === 'paid' ? 'processed' : $this->diagnosticImportStatus;
        $this->stage($event, $shopifyOrderId, 'import_status_update_completed', null, 'shopify_order_imports', 'internal_import_id', true);
        $this->stage($event, $shopifyOrderId, 'import_completed', null, 'shopify_order_imports', 'internal_import_id', true);
        $this->paymentUpdateDiagnostic($event, (string) $importId, $shopifyOrderId, true, 'after_pharmacy_status_update', $paymentTarget ?? 'unchanged', $paymentTarget === 'paid' ? 'processed' : 'imported', null, 'medicine_orders');
        $this->paymentStage($event, $shopifyOrderId, $importId, $orderId, 'payment_update', 'updated');
        service('audit')->record('shopify.order_status_synchronized', [
            'order_id' => $orderId,
            'event_id' => $eventId,
            'store_domain' => $store,
            'clinic_id' => $clinicId,
            'previous_payment_status' => null,
            'new_payment_status' => $changes['payment_status'] ?? null,
            'previous_delivery_status' => null,
            'new_delivery_status' => $changes['delivery_status'] ?? null,
        ]);
    }

    /**
     * Update the exact imported-order record using the ID returned by the importer.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $payload
     */
    private function updateImportedPaymentRecord(array $event, int $internalImportId, array $payload): int
    {
        $internalImportId = (string) $internalImportId;
        $shopifyOrderId = $this->shopifyId($payload['id'] ?? '');
        $db = db_connect();
        $db->transBegin();
        try {
            if ($internalImportId === '0') {
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_ROW_NOT_FOUND, 'Internal Shopify import ID was not provided for payment update.');
            }

            // The importer already resolved the identity and passed the
            // returned internal ID through the workflow. Payment must update
            // that exact row and must not perform another order lookup.
            $sql = 'SELECT * FROM shopify_order_imports WHERE id = ? LIMIT 1';
            if (strtolower((string) $db->DBDriver) === 'mysqli') $sql .= ' FOR UPDATE';
            $this->stage($event, $shopifyOrderId, 'payment_row_lookup_started', null, 'shopify_order_imports', 'internal_import_id', false);
            $record = $db->query($sql, [$internalImportId])->getRowArray() ?: null;
            $this->diagnosticImportId = (int) $internalImportId;
            $this->diagnosticRowFound = $record !== null;
            $this->diagnosticImportStatus = (string) ($record['status'] ?? '');
            $this->diagnosticPaymentStatus = (string) ($record['payment_status'] ?? '');
            $this->stage($event, $shopifyOrderId, 'payment_row_lookup_completed', $record === null ? ShopifyOrderImportFailure::PAYMENT_ROW_NOT_FOUND : null, 'shopify_order_imports', 'internal_import_id', $record !== null);
            $columnType = $this->shopifyOrderImportColumnType($db);
            $this->paymentUpdateDiagnostic($event, $internalImportId, $shopifyOrderId, $record !== null, 'before', null, null, $columnType);
            if ($record === null) {
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_ROW_NOT_FOUND, 'Shopify payment import row was not found for the internal import ID.');
            }

            $currentStatus = strtolower(trim((string) ($record['status'] ?? '')));
            $allowedStatuses = ['pending', 'failed', 'ready_to_import', 'processed', 'paid', 'imported'];
            if (! in_array($currentStatus, $allowedStatuses, true)) {
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_IMPORT_STATUS_INVALID, 'Shopify import cannot be paid from status: ' . $currentStatus . '.');
            }

            $actualImportId = (int) $record['id'];
            $paymentTarget = $this->paymentTarget((string) ($payload['financial_status'] ?? ''));
            $paymentStatus = $paymentTarget ?? (string) ($record['payment_status'] ?? 'pending');
            $paidAtRaw = trim((string) ($payload['processed_at'] ?? $payload['paid_at'] ?? $event['created_at'] ?? ''));
            $paidAt = $paidAtRaw !== '' && strtotime($paidAtRaw) !== false
                ? date('Y-m-d H:i:s', strtotime($paidAtRaw))
                : ($paymentStatus === 'paid' ? date('Y-m-d H:i:s') : '');
            $this->recordImportHistory($actualImportId, (int) ($event['id'] ?? 0), 'payment_retry', $currentStatus, $currentStatus, (string) ($record['failure_reason'] ?? '') ?: null);
            $data = [
                'last_event_id' => (int) ($event['id'] ?? 0),
                'retry_count' => (int) ($record['retry_count'] ?? 0) + 1,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt !== '' ? $paidAt : null,
                'error_message' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($paymentTarget !== null) $data['financial_status'] = $paymentTarget;
            $this->stage($event, $shopifyOrderId, 'payment_row_update_started', null, 'shopify_order_imports', 'internal_import_id', true);
            $this->paymentUpdateDiagnostic($event, (string) $actualImportId, $shopifyOrderId, true, 'before_import_payment_update', $paymentStatus, $currentStatus, $columnType);
            if (! $db->table('shopify_order_imports')->where('id', $actualImportId)->update($data) || ! $db->transStatus()) {
                throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_UPDATE_FAILED, 'Shopify imported-order payment update failed.');
            }
            $this->diagnosticAffectedRows = $db->affectedRows();
            $this->diagnosticPaymentStatus = $paymentStatus;
            $this->stage($event, $shopifyOrderId, 'payment_row_update_completed', null, 'shopify_order_imports', 'internal_import_id', true, $this->diagnosticAffectedRows);
            $this->paymentUpdateDiagnostic($event, (string) $actualImportId, $shopifyOrderId, true, 'after', $paymentStatus, $currentStatus, $columnType);
            $db->transCommit();
            $this->paymentStage($event, $shopifyOrderId, $actualImportId, (int) ($record['rootins_order_id'] ?? 0), 'payment_update', 'exact_import_row_updated');
            return $actualImportId;
        } catch (ShopifyOrderImportFailure $failure) {
            $db->transRollback();
            $this->paymentStage($event, $shopifyOrderId, (int) $internalImportId, 0, 'payment_update', 'exact_import_row_lookup_failed', $failure->category);
            throw $failure;
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_UPDATE_FAILED, 'Shopify imported-order payment update failed.');
        }
    }

    private function markPaidImportProcessed(array $event, int $importId, string $paymentTarget): void
    {
        if ($importId <= 0 || $paymentTarget !== 'paid') return;
        $db = db_connect();
        $db->transBegin();
        $record = $db->query('SELECT * FROM shopify_order_imports WHERE id = ? LIMIT 1 FOR UPDATE', [$importId])->getRowArray() ?: null;
        if ($record === null) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::PAYMENT_ROW_NOT_FOUND, 'Shopify payment import row disappeared before processing completed.');
        }
        $this->recordImportHistory($importId, (int) ($event['id'] ?? 0), 'import_processed', (string) ($record['status'] ?? ''), 'processed', (string) ($record['failure_reason'] ?? '') ?: null);
        $updated = $db->table('shopify_order_imports')->where('id', $importId)->update([
            'status' => 'processed',
            'failure_reason' => null,
            'error_message' => null,
            'processed_at' => date('Y-m-d H:i:s'),
            'last_event_id' => (int) ($event['id'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (! $updated || ! $db->transStatus()) {
            $db->transRollback();
            throw new ShopifyOrderImportFailure(ShopifyOrderImportFailure::SHOPIFY_ORDER_DATABASE_FAILURE, 'Shopify paid import processing update failed.');
        }
        $this->paymentUpdateDiagnostic($event, (string) $importId, (string) ($record['shopify_order_id'] ?? ''), true, 'after', 'paid', 'processed');
        $db->transCommit();
    }

    private function shopifyOrderImportColumnType($db): string
    {
        try {
            foreach ($db->getFieldData('shopify_order_imports') as $field) {
                if ((string) ($field->name ?? '') === 'shopify_order_id') {
                    return strtoupper(trim((string) ($field->type ?? '') . '(' . (string) ($field->max_length ?? '') . ')'));
                }
            }
        } catch (\Throwable) {
            return 'unavailable';
        }

        return 'unavailable';
    }

    private function paymentUpdateDiagnostic(array $event, string $internalImportId, string $shopifyOrderId, bool $rowFound, string $phase, ?string $paymentUpdateResult = null, ?string $finalImportStatus = null, ?string $databaseColumnType = null, string $targetTable = 'shopify_order_imports'): void
    {
        log_message('info', 'Shopify medicine-order payment update diagnostic ' . json_encode([
            'correlation_id' => $this->diagnosticCorrelationId,
            'topic' => (string) ($event['topic'] ?? $this->diagnosticTopic),
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
            'webhook_id_hash' => ! empty($event['external_event_id']) ? hash('sha256', (string) $event['external_event_id']) : null,
            'internal_import_id' => $internalImportId,
            'shopify_order_id' => $shopifyOrderId,
            'row_found' => $rowFound,
            'payment_update_result' => $paymentUpdateResult,
            'final_import_status' => $finalImportStatus,
            'target_table' => $targetTable,
            'current_import_status' => $finalImportStatus,
            'payment_status_before' => in_array($phase, ['before', 'before_import_payment_update'], true) ? ($paymentUpdateResult ?? $this->diagnosticPaymentStatus) : null,
            'payment_status_after' => $phase === 'after' || str_contains($phase, 'completed') ? $paymentUpdateResult : null,
            'lookup_type' => 'internal_import_id',
            'affected_row_count' => $this->diagnosticAffectedRows,
            'failure_category' => 'none',
            'exception_class' => $this->diagnosticExceptionClass,
            'database_column_type' => $databaseColumnType,
            'phase' => $phase,
            'event_id' => (int) ($event['id'] ?? 0),
        ], JSON_UNESCAPED_SLASHES));
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
            'refunded' => 'refunded',
            'partially_refunded' => 'partially_refunded',
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

    private function stage(array $event, string $orderId, string $stage, ?string $category = null, string $targetTable = '', string $lookupType = '', ?bool $rowFound = null, ?int $affectedRows = null, ?\Throwable $exception = null, array $context = []): void
    {
        $this->diagnosticProcessingStage = $stage;
        if ($targetTable !== '') $this->diagnosticTargetTable = $targetTable;
        if ($lookupType !== '') $this->diagnosticLookupType = $lookupType;
        if ($rowFound !== null) $this->diagnosticRowFound = $rowFound;
        if ($affectedRows !== null) $this->diagnosticAffectedRows = $affectedRows;
        $this->diagnosticExceptionClass = $exception === null ? null : get_class($exception);
        log_message('info', 'Shopify medicine-order import stage ' . json_encode([
            'correlation_id' => $this->diagnosticCorrelationId,
            'topic' => $this->diagnosticTopic !== '' ? $this->diagnosticTopic : (string) ($event['topic'] ?? ''),
            'shopify_order_id' => $orderId,
            'line_id' => $this->diagnosticLineId !== '' ? $this->diagnosticLineId : null,
            'product_id' => $this->diagnosticProductId !== '' ? $this->diagnosticProductId : null,
            'variant_id' => $this->diagnosticVariantId !== '' ? $this->diagnosticVariantId : null,
            'store_domain' => strtolower(trim((string) ($event['store_domain'] ?? ''))),
            'webhook_id_hash' => ! empty($event['external_event_id']) ? hash('sha256', (string) $event['external_event_id']) : null,
            'internal_import_id' => $this->diagnosticImportId > 0 ? $this->diagnosticImportId : null,
            'processing_stage' => $stage,
            'current_import_status' => $this->diagnosticImportStatus,
            'payment_status' => $this->diagnosticPaymentStatus,
            'target_table' => $this->diagnosticTargetTable,
            'lookup_type' => $this->diagnosticLookupType,
            'row_found' => $this->diagnosticRowFound,
            'affected_row_count' => $this->diagnosticAffectedRows,
            'failure_category' => $category ?? 'none',
            'exception_class' => $this->diagnosticExceptionClass,
            'database_error_code' => (string) ($context['database_error_code'] ?? ''),
            'sanitized_database_message' => (string) ($context['sanitized_database_message'] ?? ''),
        ], JSON_UNESCAPED_SLASHES));
    }
}
