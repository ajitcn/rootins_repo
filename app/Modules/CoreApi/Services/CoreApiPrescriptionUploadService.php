<?php

namespace App\Modules\CoreApi\Services;

use Admin\Models\PatientDocumentModel;
use App\Services\Common\R2StorageLibrary;
use CodeIgniter\HTTP\Files\UploadedFile;
use DateTimeImmutable;

final class CoreApiPrescriptionUploadService
{
    private const MIME_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public function __construct(private readonly ?PatientDocumentModel $documents = null, private readonly ?R2StorageLibrary $storage = null)
    {
    }

    /** @return array<string, string> */
    public function upload(array $credential, array $input, UploadedFile $file): array
    {
        $patientReference = trim((string) ($input['patient_reference'] ?? ''));
        $externalReference = trim((string) ($input['external_upload_reference'] ?? ''));
        $errors = [];

        if ($patientReference === '') {
            $errors['patient_reference'] = 'Patient reference is required.';
        }
        if ($externalReference === '' || mb_strlen($externalReference) > 191) {
            $errors['external_upload_reference'] = 'A valid external upload reference is required.';
        }
        if ($errors !== []) {
            throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the upload fields.', $errors);
        }

        $resolver = new CoreApiClinicResolver();
        $clinicId = $resolver->patientClinic($input);
        $db = db_connect();
        $patient = $db->table('patients')->select('id')->where('clinic_id', $clinicId)->where('registration_number', $patientReference)->where('status', 1)->get()->getRowArray();

        if ($patient === null) {
            throw new CoreApiException(404, 'PATIENT_NOT_FOUND', 'The patient reference was not found.', ['patient_reference' => 'The patient reference was not found.']);
        }
        if (! $file->isValid() || $file->hasMoved() || $file->getSize() > 10485760) {
            throw new CoreApiException(422, 'FILE_INVALID', 'The prescription file is invalid.', ['document' => 'Upload a valid PDF, JPG, JPEG, or PNG up to 10 MB.']);
        }

        $extension = strtolower($file->getClientExtension());
        $mime = $file->getMimeType();

        if (! isset(self::MIME_TYPES[$extension]) || self::MIME_TYPES[$extension] !== $mime) {
            throw new CoreApiException(422, 'FILE_TYPE_INVALID', 'The prescription file type is not allowed.', ['document' => 'Only PDF, JPG, JPEG, and PNG files are allowed.']);
        }

        $documents = $this->documents ?? new PatientDocumentModel();
        $existing = $documents->where('source_type', 'core_api')->where('clinic_id', $clinicId)->where('patient_id', (int) $patient['id'])->where('external_upload_reference', $externalReference)->first();

        if ($existing !== null) {
            return ['upload_reference' => (string) $externalReference, 'status' => (string) ($existing['upload_status'] ?? 'uploaded'), 'received_at' => (string) ($existing['created_at'] ?? '')];
        }

        $key = 'patients/' . (int) $patient['id'] . '/core-api-prescriptions/' . $file->getRandomName();
        $uploaded = false;

        try {
            ($this->storage ?? new R2StorageLibrary())->uploadFile($key, $file);
            $uploaded = true;
            $now = date('Y-m-d H:i:s');
            $retention = (new DateTimeImmutable($now))->modify('+' . max(1, (int) env('CORE_API_PRESCRIPTION_RETENTION_DAYS', 365)) . ' days')->format('Y-m-d H:i:s');
            $db->transBegin();
            $id = $documents->insert(['patient_id' => (int) $patient['id'], 'clinic_id' => $clinicId, 'document_type' => 'prescription_upload', 'title' => trim((string) ($input['title'] ?? 'Prescription upload')) ?: 'Prescription upload', 'file_name' => basename($key), 'original_file_name' => $file->getClientName(), 'file_path' => $key, 'file_size' => $file->getSize(), 'mime_type' => $mime, 'source_type' => 'core_api', 'external_upload_reference' => $externalReference, 'upload_status' => 'uploaded', 'retention_expires_at' => $retention, 'uploaded_by_type' => 'core_api', 'uploaded_by_id' => (int) $patient['id'], 'created_at' => $now, 'updated_at' => $now]);

            if (! $id || ! $db->transCommit()) {
                throw new \RuntimeException('Prescription upload metadata could not be saved.');
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            if ($uploaded) {
                try {
                    ($this->storage ?? new R2StorageLibrary())->delete($key);
                } catch (\Throwable $cleanup) {
                    log_message('error', 'Core API prescription cleanup failed safely.');
                }
            }
            throw $exception;
        }

        service('audit')->record('core_api.prescription_uploaded', ['clinic_id' => $clinicId, 'document_id' => (int) $id, 'source_type' => 'core_api']);

        return ['upload_reference' => $externalReference, 'status' => 'uploaded', 'received_at' => date('c')];
    }

    /** @return array<string, mixed> */
    public function details(array $credential, string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 191) {
            throw new CoreApiException(404, 'PRESCRIPTION_NOT_FOUND', 'Prescription document not found.');
        }

        $document = db_connect()->table('patient_documents documents')
            ->select('documents.external_upload_reference, documents.original_file_name, documents.file_name, documents.file_path, documents.file_size, documents.mime_type, documents.created_at, documents.upload_status, documents.review_message, documents.reviewed_at, documents.retention_expires_at, patients.registration_number')
            ->join('patients', 'patients.id = documents.patient_id AND patients.clinic_id = documents.clinic_id', 'inner')
            ->where('documents.source_type', 'core_api')
            ->where('documents.external_upload_reference', $reference)
            ->where('patients.status', 1)
            ->get()->getRowArray();

        if ($document === null || empty($document['file_path']) || (! empty($document['retention_expires_at']) && (string) $document['retention_expires_at'] < date('Y-m-d H:i:s'))) {
            throw new CoreApiException(404, 'PRESCRIPTION_NOT_FOUND', 'Prescription document not found.');
        }

        $status = in_array((string) ($document['upload_status'] ?? ''), ['uploaded', 'under_review', 'accepted', 'rejected', 'clarification_required'], true) ? (string) $document['upload_status'] : 'uploaded';
        $message = $this->reviewMessage($status, $document['review_message'] ?? null);
        $now = new DateTimeImmutable('now', $this->timezone());
        $minutes = 10;
        if (! empty($document['retention_expires_at'])) {
            $retention = new DateTimeImmutable((string) $document['retention_expires_at'], $this->timezone());
            $minutes = max(1, min(10, (int) ceil(($retention->getTimestamp() - $now->getTimestamp()) / 60)));
        }
        $downloadUrl = null;
        try {
            $downloadUrl = ($this->storage ?? new R2StorageLibrary())->getTemporaryUrl((string) $document['file_path'], $minutes, (string) ($document['mime_type'] ?? 'application/octet-stream'), 'inline');
        } catch (\Throwable $exception) {
            log_message('error', 'Core API prescription download URL generation failed.');
        }
        $expiresAt = $downloadUrl === null ? null : $now->modify('+' . $minutes . ' minutes')->format(DateTimeImmutable::ATOM);

        return [
            'prescription_reference' => (string) $document['external_upload_reference'],
            'document_reference' => (string) $document['external_upload_reference'],
            'patient_reference' => (string) $document['registration_number'],
            'original_filename' => (string) ($document['original_file_name'] ?: 'Prescription document'),
            'file_type' => (string) ($document['mime_type'] ?? 'application/octet-stream'),
            'file_size' => (int) ($document['file_size'] ?? 0),
            'uploaded_at' => $this->formatDateTime($document['created_at'] ?? null),
            'status' => $status,
            'status_message' => $message,
            'reviewed_at' => $this->formatDateTime($document['reviewed_at'] ?? null),
            'clarification_message' => $status === 'clarification_required' ? $message : null,
            'download_url' => $downloadUrl,
            'download_url_expires_at' => $expiresAt,
        ];
    }

    private function reviewMessage(string $status, mixed $message): string
    {
        $safe = trim(strip_tags((string) $message));
        $safe = mb_substr($safe, 0, 500);
        if ($safe !== '' && in_array($status, ['rejected', 'clarification_required'], true)) return $safe;
        return match ($status) {
            'under_review' => 'Prescription is under review.',
            'accepted' => 'Prescription has been accepted.',
            'rejected' => 'Prescription was rejected.',
            'clarification_required' => 'Additional clarification is required.',
            default => 'Prescription upload received.',
        };
    }

    private function formatDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : (new DateTimeImmutable($value, $this->timezone()))->format(DateTimeImmutable::ATOM);
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
    }
}
