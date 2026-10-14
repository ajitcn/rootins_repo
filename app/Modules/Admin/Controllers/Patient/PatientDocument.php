<?php

namespace Admin\Controllers\Patient;

use Admin\Models\PatientDocumentModel;
use Admin\Models\PatientModel;
use App\Controllers\BaseAdminController;
use App\Services\Common\R2StorageLibrary;

class PatientDocument extends BaseAdminController
{
    protected PatientDocumentModel $document;

    protected PatientModel $patient;

    protected R2StorageLibrary $storage;

    public function __construct()
    {
        $this->document = new PatientDocumentModel();
        $this->patient = new PatientModel();
        $this->storage = new R2StorageLibrary();
    }

    public function index(int $patientId)
    {
        $patient = $this->patient->find($patientId);

        if ($patient === null) {
            return redirect()->to(base_url('admin/patients'))->with('error', 'Patient not found.');
        }

        return view(ADMIN_VIEW . 'pages/patient/documents', ['patient' => $patient, 'documents' => $this->document->where('patient_id', $patientId)->orderBy('created_at', 'DESC')->findAll()]);
    }

    public function save(int $patientId)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }

        if ($this->patient->find($patientId) === null) {
            return $this->failure('Patient not found.');
        }
        $validation = $this->validateDocument(true);

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $file = $this->request->getFile('document');
        $fileName = $file->getRandomName();
        $r2Key = 'patients/' . $patientId . '/documents/' . $fileName;

        try {
            $this->storage->uploadFile($r2Key, $file);
            $documentId = $this->document->insert(['patient_id' => $patientId, 'document_type' => trim((string) $this->request->getPost('document_type')), 'title' => trim((string) $this->request->getPost('title')), 'file_name' => $fileName, 'file_path' => $r2Key, 'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType()]);

            if (!$documentId) {
                throw new \RuntimeException('Patient document insert failed.');
            }
        } catch (\Throwable $e) {
            try {
                $this->storage->delete($r2Key);
            } catch (\Throwable $cleanupException) {
                log_message('error', 'Patient document R2 cleanup failed: ' . $cleanupException->getMessage());
            }
            log_message('error', 'Patient document upload failed: ' . $e->getMessage());

            return $this->failure('Unable to save patient document.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Patient document uploaded successfully.']);
    }

    public function edit(int $patientId, int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }
        $document = $this->findPatientDocument($patientId, $id);

        if ($document === null) {
            return $this->failure('Patient document not found.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $document]);
    }

    public function update(int $patientId, int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }
        $existing = $this->findPatientDocument($patientId, $id);

        if ($existing === null) {
            return $this->failure('Patient document not found.');
        }
        $validation = $this->validateDocument(false);

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $updateData = ['document_type' => trim((string) $this->request->getPost('document_type')), 'title' => trim((string) $this->request->getPost('title'))];
        $file = $this->request->getFile('document');
        $newR2Key = null;

        if ($file && $file->isValid()) {
            $fileName = $file->getRandomName();
            $newR2Key = 'patients/' . $patientId . '/documents/' . $fileName;

            try {
                $this->storage->uploadFile($newR2Key, $file);
            } catch (\Throwable $e) {
                log_message('error', 'Patient document replacement upload failed: ' . $e->getMessage());

                return $this->failure('Unable to upload replacement document.');
            }
            $updateData['file_name'] = $fileName;
            $updateData['file_path'] = $newR2Key;
            $updateData['file_size'] = $file->getSize();
            $updateData['mime_type'] = $file->getMimeType();
        }

        if (!$this->document->update($id, $updateData)) {
            if ($newR2Key !== null) {
                $this->cleanupR2Object($newR2Key);
            }

            return $this->failure('Unable to update patient document.');
        }

        if ($newR2Key !== null && !empty($existing['file_path'])) {
            $this->cleanupR2Object($existing['file_path']);
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Patient document updated successfully.']);
    }

    public function download(int $patientId, int $id)
    {
        $document = $this->findPatientDocument($patientId, $id);

        if ($document === null || empty($document['file_path'])) {
            return redirect()->back()->with('error', 'Patient document not found.');
        }

        return redirect()->to($this->storage->getUrl($document['file_path']));
    }

    public function delete(int $patientId, int $id)
    {
        $document = $this->findPatientDocument($patientId, $id);

        if ($document === null) {
            return $this->failure('Patient document not found.');
        }

        if (!$this->document->delete($id)) {
            return $this->failure('Unable to delete patient document.');
        }

        if (!empty($document['file_path'])) {
            $this->cleanupR2Object($document['file_path']);
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Patient document deleted successfully.']);
    }

    private function validateDocument(bool $fileRequired): array
    {
        $rules = ['document_type' => ['label' => 'Document Type', 'rules' => 'required|max_length[100]'], 'title' => ['label' => 'Title', 'rules' => 'required|max_length[255]']];

        if ($fileRequired) {
            $rules['document'] = ['label' => 'Document', 'rules' => ['uploaded[document]', 'max_size[document,10240]', 'ext_in[document,pdf,jpg,jpeg,png,doc,docx]']];
        } else {
            $file = $this->request->getFile('document');

            if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                $rules['document'] = ['label' => 'Document', 'rules' => ['max_size[document,10240]', 'ext_in[document,pdf,jpg,jpeg,png,doc,docx]']];
            }
        }

        return $this->validate($rules) ? [] : $this->validator->getErrors();
    }

    private function findPatientDocument(int $patientId, int $id): ?array
    {
        return $this->document->where('patient_id', $patientId)->where('id', $id)->first();
    }

    private function cleanupR2Object(string $key): void
    {
        try {
            $this->storage->delete($key);
        } catch (\Throwable $e) {
            log_message('error', 'Unable to delete patient document from R2: ' . $e->getMessage());
        }
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON(['status' => false, 'msg' => $message]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
    }
}
