<?php

namespace App\Services\Common;

use Admin\Models\PatientDocumentModel;
use DateTimeImmutable;
use RuntimeException;

final class PrescriptionSnapshotService
{
    public function __construct(
        private readonly PatientDocumentModel $documents = new PatientDocumentModel(),
        private readonly R2StorageLibrary $storage = new R2StorageLibrary(),
        private readonly PrescriptionPdfService $pdf = new PrescriptionPdfService(),
    ) {}

    public function createIfFinalized(int $prescriptionId): ?int
    {
        $db = db_connect();
        $prescription = $db->table('prescriptions p')
            ->select('p.*, patients.registration_number, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.doctor_code, clinics.clinic_name, clinics.address AS clinic_address, clinics.pincode AS clinic_pincode')
            ->join('patients', 'patients.id = p.patient_id', 'left')
            ->join('doctors', 'doctors.id = p.doctor_id', 'left')
            ->join('clinics', 'clinics.id = p.clinic_id', 'left')
            ->where('p.id', $prescriptionId)->get(1)->getRowArray();
        if ($prescription === null || ! in_array((string) $prescription['status'], ['active', 'approved', 'completed', 'finalized'], true)) return null;
        $reference = (string) ($prescription['prescription_number'] ?? '');
        $existing = $this->documents->where('source_type', 'rootins')->where('document_type', 'prescription_pdf')->where('external_upload_reference', $reference)->where('patient_id', (int) $prescription['patient_id'])->first();
        if ($existing !== null) return (int) $existing['id'];
        $items = $db->table('prescription_items')->where('prescription_id', $prescriptionId)->orderBy('id', 'ASC')->get()->getResultArray();
        $pdf = $this->pdf->generate($prescription, $items);
        $tmp = tempnam(sys_get_temp_dir(), 'rootins-rx-');
        if ($tmp === false || file_put_contents($tmp, $pdf) === false) throw new RuntimeException('Prescription PDF snapshot could not be prepared.');
        $key = 'patients/' . (int) $prescription['patient_id'] . '/prescriptions/' . $reference . '.pdf';
        $uploaded = false;
        try {
            $this->storage->upload($key, $tmp);
            $uploaded = true;
            $now = date('Y-m-d H:i:s');
            $retention = (new DateTimeImmutable($now))->modify('+365 days')->format('Y-m-d H:i:s');
            $id = $this->documents->insert(['patient_id' => (int) $prescription['patient_id'], 'clinic_id' => (int) $prescription['clinic_id'], 'document_type' => 'prescription_pdf', 'title' => 'Prescription ' . $reference, 'file_name' => $reference . '.pdf', 'original_file_name' => $reference . '.pdf', 'file_path' => $key, 'file_size' => strlen($pdf), 'mime_type' => 'application/pdf', 'source_type' => 'rootins', 'external_upload_reference' => $reference, 'upload_status' => 'accepted', 'retention_expires_at' => $retention, 'uploaded_by_type' => 'system', 'uploaded_by_id' => (int) $prescription['doctor_id'], 'created_at' => $now, 'updated_at' => $now]);
            if (! $id) throw new RuntimeException('Prescription PDF metadata could not be saved.');
            return (int) $id;
        } catch (\Throwable $exception) {
            if ($uploaded) { try { $this->storage->delete($key); } catch (\Throwable) { log_message('error', 'Prescription snapshot cleanup failed safely.'); } }
            throw $exception;
        } finally { @unlink($tmp); }
    }
}
