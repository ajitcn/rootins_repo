<?php

namespace App\Services\Shopify;

use Admin\Models\PatientDocumentModel;
use Admin\Models\PatientModel;
use Admin\Models\User;
use App\Services\Common\R2StorageLibrary;
use App\Services\PatientScope;
use App\Modules\Shopify\Models\ShopifyPrescriptionOrderLinkModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Pharmacy\Services\PharmacyScope;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Handles Shopify prescription-upload metadata and private R2 objects.
 *
 * Uploads are documents only. This service never creates a clinical
 * prescription; an authorized review workflow must accept the document first.
 */
final class ShopifyPrescriptionUploadService
{
    private const MAX_BYTES = 10485760;

    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private const STATUSES = ['uploaded', 'awaiting_review', 'under_review', 'accepted', 'rejected', 'clarification_required'];

    private const TRANSITIONS = [
        'uploaded' => ['awaiting_review', 'under_review'],
        'awaiting_review' => ['under_review', 'accepted', 'rejected', 'clarification_required'],
        'under_review' => ['accepted', 'rejected', 'clarification_required'],
        'accepted' => [],
        'rejected' => [],
        'clarification_required' => ['under_review'],
    ];

    public function __construct(
        private readonly ?ShopifyConfiguration $configuration = null,
        private readonly ?ShopifyHandoffService $handoffService = null,
        private readonly PatientDocumentModel $documents = new PatientDocumentModel(),
        private readonly R2StorageLibrary $storage = new R2StorageLibrary(),
    ) {
    }

    /**
     * @return array{upload_reference: string, status: string, received_at: string, message: string}
     */
    public function upload(array $handoff, string $handoffToken, UploadedFile $file, string $externalReference, string $title = ''): array
    {
        $scope = $this->scope($handoff);
        $externalReference = trim($externalReference);
        $title = trim($title) ?: 'Prescription upload';

        if ($externalReference === '' || mb_strlen($externalReference) > 191) {
            throw new ShopifyPrescriptionUploadValidationException(['external_upload_reference' => 'A valid external upload reference is required.']);
        }

        if (mb_strlen($title) > 255) {
            throw new ShopifyPrescriptionUploadValidationException(['title' => 'The upload title is too long.']);
        }

        $existing = $this->documents
            ->where('source_type', 'shopify')
            ->where('shopify_store_domain', $scope['store_domain'])
            ->where('external_upload_reference', $externalReference)
            ->where('clinic_id', $scope['clinic_id'])
            ->where('patient_id', $scope['patient_id'])
            ->first();

        if ($existing !== null) {
            return $this->safeResponse($existing, true);
        }

        $this->validateFile($file);
        $key = 'patients/' . $scope['patient_id'] . '/shopify-prescriptions/' . $file->getRandomName();
        $uploaded = false;

        try {
            $this->storage->uploadFile($key, $file);
            $uploaded = true;
            ($this->handoffService ?? new ShopifyHandoffService())->consume($handoffToken);
            $now = date('Y-m-d H:i:s');
            $retentionDays = max(1, (int) env('SHOPIFY_PRESCRIPTION_RETENTION_DAYS', 365));
            $retention = (new DateTimeImmutable($now))->modify('+' . $retentionDays . ' days')->format('Y-m-d H:i:s');
            $db = db_connect();
            $db->transBegin();
            $documentId = $this->documents->insert([
                'patient_id' => $scope['patient_id'],
                'clinic_id' => $scope['clinic_id'],
                'document_type' => 'prescription_upload',
                'title' => $title,
                'file_name' => basename($key),
                'file_path' => $key,
                'file_size' => $file->getSize(),
                'mime_type' => $this->detectedMime($file),
                'source_type' => 'shopify',
                'shopify_store_domain' => $scope['store_domain'],
                'shopify_customer_id' => $scope['customer_id'],
                'external_upload_reference' => $externalReference,
                'upload_status' => 'uploaded',
                'retention_expires_at' => $retention,
                'uploaded_by_type' => 'shopify',
                'uploaded_by_id' => $scope['patient_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! $documentId || ! $db->transCommit()) {
                throw new RuntimeException('Prescription upload metadata could not be saved.');
            }
        } catch (\Throwable $exception) {
            if (isset($db)) {
                $db->transRollback();
            }

            if ($uploaded) {
                try {
                    $this->storage->delete($key);
                } catch (\Throwable $cleanupException) {
                    log_message('error', 'Shopify prescription upload cleanup failed safely.');
                }
            }

            throw $exception;
        }

        service('audit')->record('shopify.prescription_upload_created', [
            'document_id' => (int) $documentId,
            'patient_id' => $scope['patient_id'],
            'clinic_id' => $scope['clinic_id'],
            'store_domain' => $scope['store_domain'],
            'external_upload_reference' => $externalReference,
            'status' => 'uploaded',
        ]);
        $this->notifyReviewers($scope['clinic_id'], (int) $documentId);

        return $this->safeResponse([
            'external_upload_reference' => $externalReference,
            'upload_status' => 'uploaded',
            'created_at' => date('Y-m-d H:i:s'),
        ], false);
    }

    /**
     * Upload through the signed Shopify App Proxy customer scope.
     * The reference is the only Shopify-facing identifier; storage keys and
     * ROOTINS identifiers remain server-side.
     *
     * @param array{store_domain:string,customer_id:string,patient_id:int} $context
     * @return array{prescription_reference:string,status:string,received_at:string,file_type:string,file_size:int}
     */
    public function uploadFromProxy(array $context, UploadedFile $file, string $externalReference, string $title = '', string $bundleReference = ''): array
    {
        $store = strtolower(trim((string) ($context['store_domain'] ?? '')));
        $customer = trim((string) ($context['customer_id'] ?? ''));
        $patientId = (int) ($context['patient_id'] ?? 0);
        if ($store === '' || $customer === '' || $patientId < 1) {
            throw new ShopifyPrescriptionUploadValidationException(['customer' => 'A signed Shopify customer scope is required.']);
        }

        $link = db_connect()->table('shopify_patient_identity_links')
            ->where('shopify_store_domain', $store)
            ->where('shopify_customer_id', $customer)
            ->where('patient_id', $patientId)
            ->where('link_status', 'linked')
            ->get(1)->getRowArray();
        $patient = (new PatientModel())->where('id', $patientId)->where('status', 1)->first();
        if ($link === null || $patient === null) {
            throw new ShopifyPrescriptionUploadCustomerException('The Shopify customer is not linked to an active ROOTINS patient.');
        }

        $externalReference = trim($externalReference);
        if (! preg_match('/^RX[PN]-[A-Z0-9-]{6,191}$/', $externalReference)) {
            throw new ShopifyPrescriptionUploadValidationException(['prescription_reference' => 'A valid prescription reference is required.']);
        }
        $title = trim($title) ?: 'Prescription upload';
        if (mb_strlen($title) > 255) {
            throw new ShopifyPrescriptionUploadValidationException(['title' => 'The upload title is too long.']);
        }

        $existing = $this->documents->where('source_type', 'shopify')
            ->where('shopify_store_domain', $store)->where('shopify_customer_id', $customer)
            ->where('external_upload_reference', $externalReference)->first();
        if ($existing !== null) {
            return $this->proxyResponse($existing);
        }

        $this->validateFile($file);
        $mime = $this->detectedMime($file);
        $bundleReference = trim($bundleReference);
        if ($bundleReference !== '' && ! preg_match('/^RXB-[A-Z0-9-]{16,191}$/', $bundleReference)) {
            throw new ShopifyPrescriptionUploadValidationException(['bundle_reference' => 'The prescription bundle reference is invalid.']);
        }
        $key = 'patients/' . $patientId . '/shopify-prescriptions/' . bin2hex(random_bytes(24)) . '.' . strtolower((string) $file->getClientExtension());
        $uploaded = false;
        $db = db_connect();
        try {
            $this->storage->uploadFile($key, $file);
            $uploaded = true;
            $now = date('Y-m-d H:i:s');
            $retention = (new DateTimeImmutable($now))->modify('+' . max(1, (int) env('SHOPIFY_PRESCRIPTION_RETENTION_DAYS', 365)) . ' days')->format('Y-m-d H:i:s');
            $db->transBegin();
            $id = $this->documents->insert([
                'patient_id' => $patientId, 'clinic_id' => null, 'document_type' => 'prescription_upload',
                'title' => $title, 'file_name' => basename($key), 'original_file_name' => $file->getClientName(),
                'file_path' => $key, 'file_size' => (int) $file->getSize(), 'mime_type' => $mime,
                'source_type' => 'shopify', 'shopify_store_domain' => $store, 'shopify_customer_id' => $customer,
                'external_upload_reference' => $externalReference, 'upload_status' => 'uploaded',
                'shopify_bundle_reference' => $bundleReference !== '' ? $bundleReference : null,
                'retention_expires_at' => $retention, 'uploaded_by_type' => 'shopify', 'uploaded_by_id' => $patientId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if (! $id || ! $db->transCommit()) throw new RuntimeException('Prescription metadata could not be saved.');
        } catch (\Throwable $exception) {
            $db->transRollback();
            if ($uploaded) { try { $this->storage->delete($key); } catch (\Throwable) { log_message('error', 'Shopify prescription cleanup failed safely.'); } }
            throw $exception;
        }
        service('audit')->record('shopify.prescription_upload_created', ['source_type' => 'shopify', 'store_domain' => $store, 'external_upload_reference' => $externalReference]);
        $this->notifyReviewers(null, (int) $id);
        return $this->proxyResponse(['external_upload_reference' => $externalReference, 'upload_status' => 'uploaded', 'created_at' => date('Y-m-d H:i:s'), 'mime_type' => $mime, 'file_size' => (int) $file->getSize()]);
    }

    /** @return array{document_id: int, status: string} */
    public function review(int $documentId, string $decision, string $reason, bool $confirmed): array
    {
        $pharmacy = new PharmacyScope();
        $clinicId = $pharmacy->clinicId();
        $isPharmacist = in_array('Pharmacy Assistant', session('roles') ?? [], true);

        if (! $isPharmacist || ! $pharmacy->can('pharmacy.prescription.review.view')) {
            throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload review is not permitted.');
        }

        $decision = strtolower(trim($decision));
        $targets = [
            'start' => 'under_review',
            'approve' => 'accepted',
            'reject' => 'rejected',
            'clarify' => 'clarification_required',
        ];

        if (! isset($targets[$decision])) {
            throw new ShopifyPrescriptionUploadValidationException(['decision' => 'Review action is invalid.']);
        }

        $permission = match ($decision) {
            'approve' => 'pharmacy.prescription.review.approve',
            'reject' => 'pharmacy.prescription.review.reject',
            'clarify' => 'pharmacy.prescription.review.clarify',
            default => 'pharmacy.prescription.review.view',
        };

        if (! $pharmacy->can($permission)) {
            throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload review is not permitted.');
        }

        $reason = trim($reason);
        $errors = [];

        if (in_array($decision, ['reject', 'clarify'], true) && $reason === '') {
            $errors['reason'] = 'A reason is required for this review action.';
        }

        if ($decision === 'approve' && ! $confirmed) {
            $errors['confirmation'] = 'Approval confirmation is required.';
        }

        if ($errors !== []) {
            throw new ShopifyPrescriptionUploadValidationException($errors);
        }

        $document = $this->documents
            ->where('id', $documentId)
            ->where('source_type', 'shopify')
            ->first();

        if ($document === null) {
            throw new ShopifyPrescriptionUploadNotFoundException('Prescription upload not found.');
        }

        $previous = (string) ($document['upload_status'] ?? '');
        $target = $targets[$decision];

        if (! in_array($target, self::TRANSITIONS[$previous] ?? [], true)) {
            throw new ShopifyPrescriptionUploadConflictException('This upload status transition is not allowed.');
        }

        $updated = $this->documents
            ->where('id', $documentId)
            ->where('upload_status', $previous)
            ->set([
                'upload_status' => $target,
                'review_message' => $reason !== '' ? $reason : null,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();

        if (! $updated) {
            throw new ShopifyPrescriptionUploadConflictException('The upload status changed. Refresh and try again.');
        }

        service('audit')->record('shopify.prescription_upload_reviewed', [
            'document_id' => $documentId,
            'previous_status' => $previous,
            'new_status' => $target,
            'reason' => $reason,
            'clinic_id' => $clinicId,
        ]);
        try {
            (new \Patient\Services\PatientNotificationService())->create(
                (int) $document['patient_id'],
                (int) $clinicId,
                'Prescription upload status updated',
                'Your prescription upload status is now ' . str_replace('_', ' ', $target) . '.',
                'shopify-prescription-upload:' . $documentId . ':' . $target,
                'prescription_upload',
                $documentId,
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify prescription upload patient notification failed safely.');
        }

        return ['document_id' => $documentId, 'status' => $target];
    }

    public function find(int $documentId): ?array
    {
        return $this->documents
            ->where('id', $documentId)
            ->where('source_type', 'shopify')
            ->first();
    }

    /**
     * Resolve a pharmacy preview from the signed order token and the actual
     * Shopify import linkage. The document id is only a locator; every scope
     * value is checked against the import before the private object is read.
     *
     * @param array<string, mixed> $import
     * @return array<string, mixed>
     */
    public function resolvePharmacyPreview(string $token, int $orderId, int $clinicId, int $userId, array $import): array
    {
        try {
            $payload = $this->decodeVerifiedAccessToken($token);
        } catch (ShopifyPrescriptionPreviewException $exception) {
            $this->previewAuthorizationDiagnostic($orderId, (int) ($import['id'] ?? 0), $userId, 0, $clinicId, (int) ($import['clinic_id'] ?? 0), (int) ($import['patient_id'] ?? 0), 0, (string) ($import['shopify_order_id'] ?? ''), [], [], $exception->category);
            throw $exception;
        }
        $documentId = (int) ($payload['document_id'] ?? 0);
        $importId = (int) ($import['id'] ?? 0);
        $patientId = (int) ($import['patient_id'] ?? 0);
        $store = trim((string) ($import['store_domain'] ?? ''));
        $customerId = trim((string) ($import['shopify_customer_id'] ?? ''));
        $reference = trim((string) ($import['prescription_reference'] ?? ''));
        $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'token', true, null);
        if (in_array($orderId, [71, 72], true)) $this->previewOrderDiagnostic($orderId, $import, $documentId, $reference);

        $roles = is_array(session('roles')) ? session('roles') : [];
        $permissions = is_array(session('permissions')) ? session('permissions') : [];
        $activeUser = db_connect()->table('users')->where('id', $userId)->where('status', 1)->get(1)->getRowArray();
        $pharmacyAuthorized = in_array('Pharmacy Assistant', $roles, true) && in_array('pharmacy.shopify_orders.view', $permissions, true);
        $adminAuthorized = (bool) array_intersect(['Admin', 'Super Admin'], $roles) && in_array('prescription.view', $permissions, true) && in_array('pharmacy.shopify_orders.view', $permissions, true);
        $permissionFailure = $activeUser === null ? 'PRESCRIPTION_USER_INACTIVE' : ((! $pharmacyAuthorized && ! $adminAuthorized) ? 'PRESCRIPTION_PHARMACIST_PERMISSION_REQUIRED' : null);
        $this->previewAuthorizationDiagnostic($orderId, $importId, $userId, (int) ($payload['user_id'] ?? 0), (int) ($payload['clinic_id'] ?? 0), (int) ($import['clinic_id'] ?? 0), $patientId, $documentId, (string) ($import['shopify_order_id'] ?? ''), $roles, $permissions, $permissionFailure);
        if ($permissionFailure !== null) throw new ShopifyPrescriptionPreviewException($permissionFailure);
        if ((int) ($payload['order_id'] ?? 0) !== $orderId || $importId !== $orderId) {
            $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'token_scope', false, 'PRESCRIPTION_ORDER_DOCUMENT_MISMATCH');
            throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_ORDER_DOCUMENT_MISMATCH');
        }
        if ((int) ($payload['clinic_id'] ?? 0) !== $clinicId || (int) ($import['clinic_id'] ?? 0) !== $clinicId) {
            $this->previewAuthorizationDiagnostic($orderId, $importId, $userId, (int) ($payload['user_id'] ?? 0), (int) ($payload['clinic_id'] ?? 0), (int) ($import['clinic_id'] ?? 0), $patientId, $documentId, (string) ($import['shopify_order_id'] ?? ''), $roles, $permissions, 'PRESCRIPTION_CLINIC_MISMATCH');
            throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_CLINIC_MISMATCH');
        }
        if ((int) ($payload['user_id'] ?? 0) !== $userId) {
            $this->previewAuthorizationDiagnostic($orderId, $importId, $userId, (int) ($payload['user_id'] ?? 0), (int) ($payload['clinic_id'] ?? 0), (int) ($import['clinic_id'] ?? 0), $patientId, $documentId, (string) ($import['shopify_order_id'] ?? ''), $roles, $permissions, 'PRESCRIPTION_USER_MISMATCH');
            throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_USER_MISMATCH');
        }
        if ($clinicId <= 0 || $userId <= 0 || $patientId <= 0) throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_ACCESS_DENIED');

        $link = null;
        if (db_connect()->tableExists('shopify_prescription_order_links')) {
            $link = db_connect()->table('shopify_prescription_order_links')
                ->where('store_domain', $store)->where('shopify_order_id', (string) ($import['shopify_order_id'] ?? ''))->where('import_id', $importId)
                ->where('clinic_id', $clinicId)->where('patient_id', $patientId)->get(1)->getRowArray();
            $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'order_document_linkage', $link !== null, $link === null ? 'PRESCRIPTION_LINKAGE_NOT_FOUND' : null, 'shopify_prescription_order_links');
            if ($link !== null && (int) ($link['document_id'] ?? 0) > 0 && $documentId > 0 && (int) $link['document_id'] !== $documentId) {
                $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'order_document_linkage', false, 'PRESCRIPTION_ORDER_DOCUMENT_MISMATCH', 'shopify_prescription_order_links');
                throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_ORDER_DOCUMENT_MISMATCH');
            }
            if ($link !== null && (int) ($link['document_id'] ?? 0) > 0) $documentId = (int) $link['document_id'];
        }
        $document = null;
        if ($documentId > 0) {
            $query = $this->documents->where('id', $documentId)->where('source_type', 'shopify')->where('clinic_id', $clinicId)->where('patient_id', $patientId);
            if ($store !== '') $query->where('shopify_store_domain', $store);
            if ($customerId !== '') $query->where('shopify_customer_id', $customerId);
            $document = $query->first();
            $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'document_lookup', $document !== null, $document === null ? 'PRESCRIPTION_DOCUMENT_METADATA_NOT_FOUND' : null, 'patient_documents', $document !== null && trim((string) ($document['file_path'] ?? '')) !== '');
        }

        if ($document === null && $store !== '' && $customerId !== '' && $patientId > 0) {
            $query = $this->documents->where('source_type', 'shopify')->where('clinic_id', $clinicId)->where('patient_id', $patientId)->where('shopify_store_domain', $store)->where('shopify_customer_id', $customerId);
            if ($reference !== '') $query->where('external_upload_reference', $reference);
            elseif (! empty($import['shopify_order_id'])) $query->where('shopify_order_id', (string) $import['shopify_order_id']);
            $document = $query->orderBy('id', 'DESC')->first();
        }

        if ($document === null && $reference !== '' && $patientId > 0) {
            $query = $this->documents->where('source_type', 'rootins')->where('document_type', 'prescription_pdf')
                ->where('external_upload_reference', $reference)->where('clinic_id', $clinicId)->where('patient_id', $patientId);
            if ($documentId > 0) $query->where('id', $documentId);
            $document = $query->first();
        }

        if ($document === null && $documentId > 0) {
            $unscopedDocument = $this->documents->where('id', $documentId)->where('source_type', 'shopify')->where('clinic_id', $clinicId)->first();
            if ($unscopedDocument !== null && (int) ($unscopedDocument['patient_id'] ?? 0) !== $patientId) {
                $this->previewAuthorizationDiagnostic($orderId, $importId, $userId, (int) ($payload['user_id'] ?? 0), (int) ($payload['clinic_id'] ?? 0), (int) ($import['clinic_id'] ?? 0), (int) ($unscopedDocument['patient_id'] ?? 0), $documentId, (string) ($import['shopify_order_id'] ?? ''), $roles, $permissions, 'PRESCRIPTION_PATIENT_MISMATCH');
                throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_PATIENT_MISMATCH');
            }
        }

        if ($document === null) {
            $document = $this->findExternalPreviewDocument($documentId, $reference, (string) ($import['shopify_order_id'] ?? ''), $store, $customerId, $patientId, $clinicId);
            if ($document === null && $reference !== '') {
                // The token's document_id may be stale. Resolve the canonical upload by
                // its stored prescription/upload reference before reporting metadata missing.
                $document = $this->findExternalPreviewDocument(0, $reference, (string) ($import['shopify_order_id'] ?? ''), $store, $customerId, $patientId, $clinicId, true);
            }
            $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'prescription_document_lookup', $document !== null, $document === null ? 'PRESCRIPTION_DOCUMENT_METADATA_NOT_FOUND' : null, (string) ($document['_lookup_table'] ?? 'prescription_documents'), $document !== null && trim((string) ($document['file_path'] ?? '')) !== '');
        }

        if ($document !== null) {
            $storageKey = $this->storage->normalizeKey((string) ($document['file_path'] ?? ''));
            $storageExists = $storageKey !== '' && $this->storage->objectExists($storageKey);
            $this->previewDiagnostic($orderId, $importId, (int) ($document['id'] ?? $documentId), (string) ($document['external_upload_reference'] ?? $reference), 'storage_object_lookup', $storageExists, $storageExists ? null : 'PRESCRIPTION_STORAGE_OBJECT_NOT_FOUND', (string) ($document['_lookup_table'] ?? 'patient_documents'), $storageKey !== '', $storageKey);
            if (! $storageExists && $reference !== '') {
                $replacement = $this->findExternalPreviewDocument(0, $reference, (string) ($import['shopify_order_id'] ?? ''), $store, $customerId, $patientId, $clinicId, true);
                if ($replacement !== null) {
                    $replacementKey = $this->storage->normalizeKey((string) ($replacement['file_path'] ?? ''));
                    if ($replacementKey !== '' && $this->storage->objectExists($replacementKey)) $document = $replacement;
                }
            }
            if ($document !== null && ! $this->storage->objectExists($this->storage->normalizeKey((string) ($document['file_path'] ?? '')))) {
                $this->previewDiagnostic($orderId, $importId, (int) ($document['id'] ?? $documentId), $reference, 'storage_object_lookup', false, 'PRESCRIPTION_STORAGE_OBJECT_NOT_FOUND', (string) ($document['_lookup_table'] ?? 'patient_documents'), trim((string) ($document['file_path'] ?? '')) !== '', $this->storage->normalizeKey((string) ($document['file_path'] ?? '')));
                throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_STORAGE_OBJECT_NOT_FOUND');
            }
        }

        if ($document === null) {
            $category = $link !== null ? 'PRESCRIPTION_DOCUMENT_METADATA_NOT_FOUND' : 'PRESCRIPTION_LINKAGE_NOT_FOUND';
            $this->previewDiagnostic($orderId, $importId, $documentId, $reference, 'prescription_upload_lookup', false, $category, 'patient_documents', false);
            throw new ShopifyPrescriptionPreviewException($category);
        }

        $isDoctorSnapshot = (string) ($document['source_type'] ?? '') === 'rootins';
        $shopifyScopeMatches = (string) ($document['shopify_store_domain'] ?? '') === $store
            && (string) ($document['shopify_customer_id'] ?? '') === $customerId;
        $orderDocumentMatches = empty($document['shopify_order_id']) || (string) $document['shopify_order_id'] === (string) ($import['shopify_order_id'] ?? '');
        if ((int) ($document['clinic_id'] ?? 0) !== $clinicId || (int) ($document['patient_id'] ?? 0) !== $patientId
            || (! $isDoctorSnapshot && (! $shopifyScopeMatches || ! $orderDocumentMatches))) {
            $failure = (int) ($document['clinic_id'] ?? 0) !== $clinicId ? 'PRESCRIPTION_CLINIC_MISMATCH' : ((int) ($document['patient_id'] ?? 0) !== $patientId ? 'PRESCRIPTION_PATIENT_MISMATCH' : 'PRESCRIPTION_ORDER_DOCUMENT_MISMATCH');
            $this->previewDiagnostic($orderId, $importId, (int) ($document['id'] ?? 0), $reference, 'document_scope', false, $failure);
            throw new ShopifyPrescriptionPreviewException($failure);
        }

        if ((string) ($document['_lookup_table'] ?? 'patient_documents') !== 'patient_documents') {
            $document = $this->ensureDocumentMetadata($document, $import, $reference);
        }

        try {
            $this->linkOrderPrescription($import, (int) $document['id'], null, $reference, $link);
        } catch (\Throwable $exception) {
            $this->previewDiagnostic($orderId, $importId, (int) $document['id'], $reference, 'order_document_linkage_repair', false, 'PRESCRIPTION_LINKAGE_NOT_FOUND', 'shopify_prescription_order_links');
            throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_LINKAGE_NOT_FOUND');
        }
        $this->previewDiagnostic($orderId, $importId, (int) $document['id'], (string) ($document['external_upload_reference'] ?? $reference), 'prescription_linkage', true, null, (string) ($document['_lookup_table'] ?? 'patient_documents'), trim((string) ($document['file_path'] ?? '')) !== '');
        return $document;
    }

    /** @param array<string, mixed> $upload @param array<string, mixed> $import @return array<string, mixed> */
    private function ensureDocumentMetadata(array $upload, array $import, string $reference): array
    {
        $source = (string) ($upload['source_type'] ?? 'shopify') === 'rootins' ? 'rootins' : 'shopify';
        $query = $this->documents->where('source_type', $source)->where('patient_id', (int) ($import['patient_id'] ?? 0))->where('clinic_id', (int) ($import['clinic_id'] ?? 0));
        if ($reference !== '') $query->where('external_upload_reference', $reference);
        elseif (! empty($import['shopify_order_id'])) $query->where('shopify_order_id', (string) $import['shopify_order_id']);
        $existing = $query->first();
        if ($existing !== null) return $existing;
        $now = date('Y-m-d H:i:s');
        $data = [
            'patient_id' => (int) ($import['patient_id'] ?? 0),
            'clinic_id' => (int) ($import['clinic_id'] ?? 0),
            'document_type' => $source === 'rootins' ? 'prescription_pdf' : 'prescription_upload',
            'source_type' => $source,
            'shopify_store_domain' => $source === 'shopify' ? (string) ($import['store_domain'] ?? '') : null,
            'shopify_customer_id' => $source === 'shopify' ? (string) ($import['shopify_customer_id'] ?? '') : null,
            'external_upload_reference' => (string) ($upload['external_upload_reference'] ?? $reference) ?: null,
            'shopify_order_id' => $source === 'shopify' ? (string) ($import['shopify_order_id'] ?? '') : null,
            'upload_status' => (string) ($upload['upload_status'] ?? 'uploaded'),
            'retention_expires_at' => $upload['retention_expires_at'] ?? null,
            'title' => (string) ($upload['title'] ?? 'Prescription upload'),
            'file_name' => (string) ($upload['file_name'] ?? 'prescription-upload'),
            'original_file_name' => (string) ($upload['original_file_name'] ?? $upload['file_name'] ?? 'prescription-upload'),
            'file_path' => $this->storage->normalizeKey((string) ($upload['file_path'] ?? '')),
            'file_size' => (int) ($upload['file_size'] ?? 0),
            'mime_type' => (string) ($upload['mime_type'] ?? 'application/octet-stream'),
            'uploaded_by_type' => (string) ($upload['uploaded_by_type'] ?? 'shopify'),
            'uploaded_by_id' => (int) ($upload['uploaded_by_id'] ?? ($import['patient_id'] ?? 0)) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $id = $this->documents->insert($data, true);
        if (! $id) throw new RuntimeException('Prescription document metadata could not be repaired.');
        $data['id'] = (int) $id;
        return $data;
    }

    /** @param array<string, mixed> $import */
    private function previewOrderDiagnostic(int $orderId, array $import, int $documentId, string $reference): void
    {
        $db = db_connect();
        $importId = (int) ($import['id'] ?? 0);
        $link = $db->tableExists('shopify_prescription_order_links') ? $db->table('shopify_prescription_order_links')->where('import_id', $importId)->get(1)->getRowArray() : null;
        $candidate = $this->findExternalPreviewDocument($documentId, $reference, (string) ($import['shopify_order_id'] ?? ''), (string) ($import['store_domain'] ?? ''), (string) ($import['shopify_customer_id'] ?? ''), (int) ($import['patient_id'] ?? 0), (int) ($import['clinic_id'] ?? 0));
        $key = $candidate === null ? '' : $this->storage->normalizeKey((string) ($candidate['file_path'] ?? ''));
        log_message('info', 'Shopify prescription preview diagnostic ' . json_encode([
            'correlation_id' => bin2hex(random_bytes(8)),
            'order_id' => $orderId,
            'import_id' => (int) ($import['id'] ?? 0),
            'shopify_order_id' => (string) ($import['shopify_order_id'] ?? ''),
            'patient_id' => (int) ($import['patient_id'] ?? 0),
            'prescription_reference' => $reference !== '' ? $reference : null,
            'prescription_upload_id' => (int) ($link['upload_id'] ?? ($candidate['id'] ?? 0)) ?: null,
            'order_document_linkage' => $link !== null,
            'document_id' => $documentId,
            'document_table' => (string) ($candidate['_lookup_table'] ?? ($link !== null ? 'shopify_prescription_order_links' : 'unknown')),
            'storage_disk' => 'r2',
            'storage_bucket' => (string) env('R2_BUCKET'),
            'canonical_storage_key' => $key !== '' ? $key : null,
            'storage_object_exists' => $key !== '' ? $this->storage->objectExists($key) : false,
            'upload_reference' => ((string) ($candidate['external_upload_reference'] ?? $reference)) !== '' ? (string) ($candidate['external_upload_reference'] ?? $reference) : null,
            'failure_category' => null,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed>|null */
    private function findExternalPreviewDocument(int $documentId, string $reference, string $shopifyOrderId, string $store, string $customerId, int $patientId, int $clinicId, bool $externalFirst = false): ?array
    {
        $db = db_connect();
        $tables = $externalFirst ? ['shopify_prescription_uploads', 'prescription_documents', 'patient_documents'] : ['patient_documents', 'shopify_prescription_uploads', 'prescription_documents'];
        foreach ($tables as $tableName) {
            if (! $db->tableExists($tableName)) continue;
            $fields = array_map(static fn ($field): string => (string) ($field->name ?? ''), $db->getFieldData($tableName));
            $query = $db->table($tableName);
            if ($documentId > 0 && in_array('id', $fields, true)) $query->where('id', $documentId);
            elseif ($reference !== '' && in_array('upload_reference', $fields, true)) $query->where('upload_reference', $reference);
            elseif ($reference !== '' && in_array('external_upload_reference', $fields, true)) $query->where('external_upload_reference', $reference);
            elseif ($shopifyOrderId !== '' && in_array('shopify_order_id', $fields, true)) $query->where('shopify_order_id', $shopifyOrderId);
            else continue;
            foreach ([['patient_id', $patientId], ['clinic_id', $clinicId], ['store_domain', $store], ['shopify_store_domain', $store], ['shopify_customer_id', $customerId]] as [$field, $value]) {
                if ($value !== '' && $value !== 0 && in_array($field, $fields, true)) $query->where($field, $value);
            }
            if ($tableName === 'patient_documents' && in_array('source_type', $fields, true)) $query->whereIn('source_type', ['shopify', 'rootins']);
            $row = $query->get(1)->getRowArray();
            if ($row === null) continue;
            $row['_lookup_table'] = $tableName;
            $row['source_type'] = (string) ($row['source_type'] ?? ($tableName === 'patient_documents' ? 'shopify' : 'shopify'));
            $row['patient_id'] = (int) ($row['patient_id'] ?? $patientId);
            $row['clinic_id'] = (int) ($row['clinic_id'] ?? $clinicId);
            $row['shopify_store_domain'] = (string) ($row['shopify_store_domain'] ?? $row['store_domain'] ?? $store);
            $row['shopify_customer_id'] = (string) ($row['shopify_customer_id'] ?? $customerId);
            $row['shopify_order_id'] = (string) ($row['shopify_order_id'] ?? $shopifyOrderId);
            $row['file_path'] = (string) ($row['file_path'] ?? $row['storage_key'] ?? $row['object_key'] ?? $row['path'] ?? '');
            $row['file_name'] = (string) ($row['file_name'] ?? $row['original_file_name'] ?? $row['name'] ?? 'prescription-upload');
            $row['mime_type'] = (string) ($row['mime_type'] ?? $row['content_type'] ?? 'application/octet-stream');
            $row['external_upload_reference'] = (string) ($row['external_upload_reference'] ?? $row['upload_reference'] ?? $reference);
            return $row;
        }
        return null;
    }

    /** @param array<string, mixed> $import @param array<string, mixed>|null $existing */
    public function linkOrderPrescription(array $import, int $documentId = 0, ?int $prescriptionId = null, string $reference = '', ?array $existing = null): void
    {
        if (! db_connect()->tableExists('shopify_prescription_order_links')) return;
        $now = date('Y-m-d H:i:s');
        $data = [
            'store_domain' => (string) ($import['store_domain'] ?? ''),
            'shopify_order_id' => (string) ($import['shopify_order_id'] ?? ''),
            'import_id' => (int) ($import['id'] ?? 0),
            'patient_id' => (int) ($import['patient_id'] ?? 0) ?: null,
            'clinic_id' => (int) ($import['clinic_id'] ?? 0) ?: null,
            'prescription_id' => $prescriptionId,
            'prescription_reference' => $reference !== '' ? $reference : null,
            'bundle_reference' => trim((string) ($import['prescription_bundle_reference'] ?? '')) ?: null,
            'document_id' => $documentId > 0 ? $documentId : null,
            'upload_reference' => (string) (($documentId > 0 ? $this->documents->find($documentId)['external_upload_reference'] ?? null : null) ?? $reference) ?: null,
            'status' => 'linked',
            'updated_at' => $now,
        ];
        $table = db_connect()->table('shopify_prescription_order_links');
        if ($existing === null) {
            $existing = $table->where('store_domain', $data['store_domain'])->where('shopify_order_id', $data['shopify_order_id'])->where('import_id', $data['import_id'])->get(1)->getRowArray();
        }
        if ($existing === null) {
            $data['created_at'] = $now;
            $table->insert($data);
        } else {
            $table->where('id', (int) $existing['id'])->update($data);
        }
    }

    /**
     * Resolve download access from a signed Patient handoff or staff scope.
     *
     * @return array{document: array<string, mixed>, actor: string}
     */
    public function authorizeDownload(int $documentId, ?string $handoffToken = null): array
    {
        $document = $this->find($documentId);

        if ($document === null) {
            throw new ShopifyPrescriptionUploadNotFoundException('Prescription upload not found.');
        }

        if ($handoffToken !== null && trim($handoffToken) !== '') {
            $handoff = ($this->handoffService ?? new ShopifyHandoffService())->verify($handoffToken);
            $scope = $this->scope($handoff);

            if ((int) ($document['patient_id'] ?? 0) !== $scope['patient_id']
                || (int) ($document['clinic_id'] ?? 0) !== $scope['clinic_id']
                || (string) ($document['shopify_store_domain'] ?? '') !== $scope['store_domain']) {
                throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload access is not permitted.');
            }

            service('audit')->record('shopify.prescription_upload_viewed', [
                'document_id' => $documentId,
                'patient_id' => $scope['patient_id'],
                'clinic_id' => $scope['clinic_id'],
                'actor_type' => 'patient',
            ]);

            return ['document' => $document, 'actor' => 'patient'];
        }

        $roles = session('roles') ?? [];
        $permissions = session('permissions') ?? [];
        $clinicId = (int) (session('user')['clinic_id'] ?? session('clinic_id') ?? 0);

        if (in_array('Pharmacy Assistant', $roles, true)) {
            $pharmacy = new PharmacyScope();

            if (! $pharmacy->can('pharmacy.prescription.review.view')) {
                throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload access is not permitted.');
            }

            service('audit')->record('shopify.prescription_upload_viewed', [
                'document_id' => $documentId,
                'clinic_id' => (int) $document['clinic_id'],
                'actor_type' => 'pharmacy',
            ]);

            return ['document' => $document, 'actor' => 'pharmacy'];
        }

        if (in_array('Doctor', $roles, true) && in_array('prescription.view', $permissions, true)) {
            $user = (new User())->select('doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
            $doctorId = (int) ($user['doctor_id'] ?? 0);
            $doctor = db_connect()->table('doctors')->where('id', $doctorId)->where('status', 1)->get()->getRowArray();
            $doctorClinicId = (int) ($doctor['clinic_id'] ?? 0);
            $hasPatientRelationship = db_connect()->table('appointments')->where('doctor_id', $doctorId)->where('patient_id', (int) $document['patient_id'])->where('clinic_id', (int) $document['clinic_id'])->countAllResults() > 0;

            if ($doctor === null
                || $doctorClinicId <= 0
                || ($clinicId > 0 && $clinicId !== $doctorClinicId)
                || $doctorClinicId !== (int) $document['clinic_id']
                || ! $hasPatientRelationship) {
                throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload access is not permitted.');
            }

            service('audit')->record('shopify.prescription_upload_viewed', [
                'document_id' => $documentId,
                'doctor_id' => $doctorId,
                'clinic_id' => $doctorClinicId,
                'actor_type' => 'doctor',
            ]);

            return ['document' => $document, 'actor' => 'doctor'];
        }

        if (session('patient_authenticated') === true) {
            $context = (new PatientScope())->current();

            if ($context === null || $context['patient_id'] !== (int) $document['patient_id'] || $context['clinic_id'] !== (int) $document['clinic_id']) {
                throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload access is not permitted.');
            }

            service('audit')->record('shopify.prescription_upload_viewed', [
                'document_id' => $documentId,
                'patient_id' => $context['patient_id'],
                'clinic_id' => $context['clinic_id'],
                'actor_type' => 'patient',
            ]);

            return ['document' => $document, 'actor' => 'patient'];
        }

        throw new ShopifyPrescriptionUploadAuthorizationException('Prescription upload access is not permitted.');
    }

    public function temporaryUrl(array $document, int $minutes = 10): string
    {
        if (empty($document['file_path']) || ! empty($document['retention_expires_at']) && $document['retention_expires_at'] < date('Y-m-d H:i:s')) {
            throw new ShopifyPrescriptionUploadNotFoundException('Prescription upload is unavailable.');
        }

        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) ($document['file_name'] ?? 'prescription-upload')));

        return $this->storage->getTemporaryUrl(
            (string) $document['file_path'],
            $minutes,
            (string) ($document['mime_type'] ?? 'application/octet-stream'),
            'inline; filename="' . $fileName . '"',
        );
    }

    public function issueAccessToken(int $orderId, int $documentId, int $clinicId, int $userId, int $ttl = 300): string
    {
        $payload = [
            'order_id' => $orderId,
            'document_id' => $documentId,
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'expires_at' => time() + max(1, $ttl),
        ];
        $encoded = $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR));
        return $encoded . '.' . $this->sign($encoded);
    }

    public function verifyAccessToken(string $token, int $orderId, int $documentId, int $clinicId, int $userId): bool
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2 || ! hash_equals($this->sign($parts[0]), $parts[1])) return false;
        $payload = json_decode($this->base64UrlDecode($parts[0]), true);
        return is_array($payload)
            && (int) ($payload['order_id'] ?? 0) === $orderId
            && (int) ($payload['document_id'] ?? 0) === $documentId
            && (int) ($payload['clinic_id'] ?? 0) === $clinicId
            && (int) ($payload['user_id'] ?? 0) === $userId
            && (int) ($payload['expires_at'] ?? 0) >= time();
    }

    /** @return array<string, mixed> */
    private function decodeVerifiedAccessToken(string $token): array
    {
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2 || $parts[0] === '' || ! hash_equals($this->sign($parts[0]), $parts[1])) {
            throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_PREVIEW_TOKEN_INVALID');
        }
        $payload = json_decode($this->base64UrlDecode($parts[0]), true);
        if (! is_array($payload)) throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_PREVIEW_TOKEN_INVALID');
        if ((int) ($payload['expires_at'] ?? 0) < time()) throw new ShopifyPrescriptionPreviewException('PRESCRIPTION_PREVIEW_TOKEN_EXPIRED');
        return $payload;
    }

    private function previewDiagnostic(int $orderId, int $importId, int $documentId, string $reference, string $lookupType, bool $rowFound, ?string $failureCategory, string $lookupTable = 'patient_documents', ?bool $storageKeyPresent = null, string $storageKey = ''): void
    {
        log_message('info', 'Shopify pharmacy prescription preview ' . json_encode([
            'correlation_id' => bin2hex(random_bytes(8)),
            'order_id' => $orderId,
            'import_id' => $importId,
            'document_id' => $documentId,
            'upload_reference' => $reference !== '' ? $reference : null,
            'lookup_table' => $lookupTable,
            'lookup_type' => $lookupType,
            'row_found' => $rowFound,
            'storage_key_present' => $storageKeyPresent,
            'storage_key' => $storageKey !== '' ? $storageKey : null,
            'failure_category' => $failureCategory,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function previewAuthorizationDiagnostic(int $orderId, int $importId, int $userId, int $tokenUserId, int $tokenClinicId, int $orderClinicId, int $patientId, int $documentId, string $shopifyOrderId, array $roles, array $permissions, ?string $failure): void
    {
        log_message('info', 'Shopify pharmacy prescription authorization ' . json_encode([
            'correlation_id' => bin2hex(random_bytes(8)),
            'authenticated_user_id' => $userId,
            'token_user_id' => $tokenUserId,
            'token_clinic_id' => $tokenClinicId,
            'current_clinic_scope' => $orderClinicId,
            'order_clinic_id' => $orderClinicId,
            'order_id' => $orderId,
            'import_id' => $importId,
            'shopify_order_id' => $shopifyOrderId,
            'document_owner_patient_id' => $patientId,
            'document_id' => $documentId,
            'permission_result' => ! in_array($failure, ['PRESCRIPTION_PHARMACIST_PERMISSION_REQUIRED', 'PRESCRIPTION_USER_INACTIVE', 'PRESCRIPTION_PREVIEW_TOKEN_INVALID', 'PRESCRIPTION_PREVIEW_TOKEN_EXPIRED'], true),
            'failure_rule' => $failure,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @return array{body:string, mime_type:string, file_name:string} */
    public function readAuthorizedDocument(array $document): array
    {
        if (empty($document['file_path']) || (! empty($document['retention_expires_at']) && $document['retention_expires_at'] < date('Y-m-d H:i:s'))) {
            throw new ShopifyPrescriptionUploadNotFoundException('Prescription upload is unavailable.');
        }
        $object = $this->storage->getObject((string) $document['file_path']);
        return [
            'body' => (string) $object['Body'],
            'mime_type' => (string) ($document['mime_type'] ?? 'application/octet-stream'),
            'file_name' => preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) ($document['file_name'] ?? 'prescription-upload'))),
        ];
    }

    private function sign(string $value): string
    {
        $key = (string) env('SHOPIFY_PRESCRIPTION_ACCESS_KEY');
        if ($key === '') $key = (string) config('Encryption')->key;
        if ($key === '') throw new RuntimeException('Prescription access signing is not configured.');
        return $this->base64Url(hash_hmac('sha256', $value, $key, true));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    /** @return array{upload_reference: string, status: string, received_at: string, message: string} */
    private function safeResponse(array $document, bool $duplicate): array
    {
        return [
            'upload_reference' => (string) ($document['external_upload_reference'] ?? ''),
            'status' => (string) ($document['upload_status'] ?? 'uploaded'),
            'received_at' => (string) ($document['created_at'] ?? date('Y-m-d H:i:s')),
            'message' => $duplicate ? 'This upload was already received.' : 'Prescription upload received for review.',
        ];
    }

    /** @return array{prescription_reference:string,status:string,received_at:string,file_type:string,file_size:int} */
    private function proxyResponse(array $document): array
    {
        return [
            'prescription_reference' => (string) ($document['external_upload_reference'] ?? ''),
            'status' => (string) ($document['upload_status'] ?? 'uploaded'),
            'received_at' => (string) ($document['created_at'] ?? date('Y-m-d H:i:s')),
            'file_type' => (string) ($document['mime_type'] ?? 'application/octet-stream'),
            'file_size' => (int) ($document['file_size'] ?? 0),
        ];
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ShopifyPrescriptionUploadValidationException(['document' => 'The prescription file is invalid or incomplete.']);
        }

        if ($file->getSize() === null || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw new ShopifyPrescriptionUploadValidationException(['document' => 'Prescription files must be between 1 byte and 10 MB.']);
        }

        $extension = strtolower((string) $file->getClientExtension());
        $mime = $this->detectedMime($file);

        if (! isset(self::MIME_TYPES[$extension]) || self::MIME_TYPES[$extension] !== $mime) {
            throw new ShopifyPrescriptionUploadValidationException(['document' => 'Only valid PDF, JPG, JPEG, PNG, or WEBP prescription files are allowed.']);
        }
    }

    private function detectedMime(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();

        if (function_exists('finfo_open') && is_file($file->getTempName())) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $file->getTempName());
                finfo_close($finfo);
            }
        }

        return strtolower(trim($mime));
    }

    /** @return array{patient_id: int, clinic_id: int, store_domain: string, customer_id: string} */
    private function scope(array $handoff): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $clinicId = $configuration->clinicId();
        $patientId = (int) ($handoff['patient_id'] ?? 0);
        $store = strtolower(trim((string) ($handoff['store_domain'] ?? '')));
        $customerId = trim((string) ($handoff['customer_id'] ?? ''));

        if ($clinicId === null || $store === '' || $store !== strtolower($configuration->storeDomain()) || $patientId < 1 || $customerId === '') {
            throw new InvalidArgumentException('Shopify patient scope is invalid.');
        }

        $link = db_connect()->table('shopify_patient_identity_links')
            ->where('shopify_store_domain', $store)
            ->where('shopify_customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->where('link_status', 'linked')
            ->get()
            ->getRowArray();
        $patient = (new PatientModel())
            ->where('id', $patientId)
            ->where('clinic_id', $clinicId)
            ->where('status', 1)
            ->first();

        if ($link === null || $patient === null) {
            throw new InvalidArgumentException('Shopify patient scope is invalid.');
        }

        return [
            'patient_id' => $patientId,
            'clinic_id' => $clinicId,
            'store_domain' => $store,
            'customer_id' => $customerId,
        ];
    }

    private function notifyReviewers(?int $clinicId, int $documentId): void
    {
        try {
            $db = db_connect();
            $message = 'A new Shopify prescription upload is waiting for review.';

            // Global uploads have no billing clinic yet. Pharmacy notifications
            // remain clinic-scoped, so defer notification creation until the
            // later order/billing scope is resolved.
            if ($clinicId === null) return;

            $doctors = $clinicId === null ? [] : $db->table('doctors')->select('id')->where('clinic_id', $clinicId)->where('status', 1)->get()->getResultArray();
            foreach ($doctors as $doctor) {
                $db->table('doctor_notifications')->insert([
                    'doctor_id' => (int) $doctor['id'],
                    'clinic_id' => $clinicId,
                    'title' => 'New prescription upload',
                    'message' => $message,
                    'channel' => 'in_app',
                    'status' => 'unread',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $usersQuery = $db->table('users AS users')
                ->select('users.id')
                ->join('user_roles', 'user_roles.user_id = users.id')
                ->join('roles', 'roles.id = user_roles.role_id')
                ->where('users.status', 1)
                ->where('roles.role_name', 'Pharmacy Assistant');
            if ($clinicId !== null) $usersQuery->where('users.clinic_id', $clinicId);
            $users = $usersQuery->get()->getResultArray();

            foreach ($users as $user) {
                $eventKey = 'shopify-prescription-upload:' . $documentId . ':pharmacy:' . (int) $user['id'];
                $exists = $db->table('pharmacy_notifications')->where('event_key', $eventKey)->get(1)->getRowArray();

                if ($exists !== null) {
                    continue;
                }

                $db->table('pharmacy_notifications')->insert([
                    'clinic_id' => $clinicId,
                    'recipient_user_id' => (int) $user['id'],
                    'recipient_role' => 'Pharmacy Assistant',
                    'type' => 'prescription_upload',
                    'title' => 'New prescription upload',
                    'message' => $message,
                    'related_entity_type' => 'prescription_upload',
                    'related_entity_id' => $documentId,
                    'event_key' => $eventKey,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify prescription upload notification failed safely.');
        }
    }
}

final class ShopifyPrescriptionUploadValidationException extends InvalidArgumentException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Shopify prescription upload validation failed.');
    }
}

final class ShopifyPrescriptionUploadCustomerException extends RuntimeException
{
}

final class ShopifyPrescriptionUploadAuthorizationException extends RuntimeException
{
}

final class ShopifyPrescriptionUploadConflictException extends RuntimeException
{
}

final class ShopifyPrescriptionUploadNotFoundException extends RuntimeException
{
}

final class ShopifyPrescriptionPreviewException extends RuntimeException
{
    public function __construct(public readonly string $category)
    {
        parent::__construct($category);
    }
}
