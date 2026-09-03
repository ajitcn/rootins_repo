<?php

namespace Admin\Controllers\Doctor;

use App\Controllers\BaseAdminController;
use App\Services\Common\R2StorageLibrary;
use Admin\Models\Doctor\DoctorDocumentModel;
use Admin\Models\Doctor\DoctorModel;
class DoctorDocument extends BaseAdminController
{
    protected DoctorDocumentModel $doctorDocument;
    protected DoctorModel $doctor;
    protected R2StorageLibrary $storage;
    public function __construct()
    {
        $this->doctorDocument = new DoctorDocumentModel();
        $this->doctor = new DoctorModel();
        $this->storage = new R2StorageLibrary();
    }
    /**
     * --------------------------------------------------------
     * List
     * --------------------------------------------------------
     */
    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->doctorDocument->select('doctor_documents.*, doctors.first_name, doctors.last_name, doctors.doctor_code')->join('doctors', 'doctors.id = doctor_documents.doctor_id');
        if ($search !== '') {
            $query->groupStart()->like('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->orLike('doctor_documents.document_name', $search)->groupEnd();
        }
        if ($status === '0' || $status === '1') {
            $query->where('doctor_documents.status', (int) $status);
        }
        $data['documents'] = $query->orderBy('doctors.first_name', 'ASC')->orderBy('doctor_documents.created_at', 'DESC')->paginate($pageSize, 'doctor_documents');
        $data['pager'] = $this->doctorDocument->pager;
        $data['filters'] = ['search' => $search, 'status' => $status, 'page_size' => $pageSize];
        $data['doctors'] = $this->doctor->select('id, first_name, last_name')->orderBy('first_name', 'ASC')->findAll();
        return view(ADMIN_VIEW . 'pages/doctor/documents', $data);
    }
    /**
     * --------------------------------------------------------
     * Create / Upload
     * --------------------------------------------------------
     */
    public function create()
    {
        $data = $this->_getDocumentData();
        $validation = $this->validateDocument($data);
        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $file = $this->request->getFile('document');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Please select a valid document.']);
        }
        /*
         * Generate unique file name.
         */
        $newFileName = $file->getRandomName();
        /*
         * R2 object key.
         *
         * Example:
         * doctors/15/documents/abc123.pdf
         */
        $r2Key = 'doctors/' . $data['doctor_id'] . '/documents/' . $newFileName;
        try {
            /*
             * Upload directly to Cloudflare R2.
             */
            $this->storage->uploadFile($r2Key, $file);
        } catch (\Throwable $e) {
            log_message('error', 'Doctor document R2 upload failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to upload document.']);
        }
        /*
         * Store document information.
         *
         * file_path contains R2 object key.
         */
        $documentData = ['doctor_id' => $data['doctor_id'], 'document_type' => $data['document_type'], 'document_name' => $data['document_name'], 'file_name' => $newFileName, 'file_path' => $r2Key, 'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType(), 'remarks' => $data['remarks'], 'status' => $data['status'], 'created_at' => date('Y-m-d H:i:s')];
        /*
         * Save DB record.
         */
        try {
            $documentId = $this->doctorDocument->insert($documentData);
        } catch (\Throwable $e) {
            /*
             * DB failed.
             * Remove uploaded R2 object.
             */
            try {
                $this->storage->delete($r2Key);
            } catch (\Throwable $deleteException) {
                log_message('error', 'Failed to rollback R2 doctor document: ' . $deleteException->getMessage());
            }
            log_message('error', 'Doctor document DB insert failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to save document information.']);
        }
        if (!$documentId) {
            /*
             * DB insert returned false.
             */
            try {
                $this->storage->delete($r2Key);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to rollback R2 object: ' . $e->getMessage());
            }
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to save document information.']);
        }
        return $this->response->setJSON(['status' => true, 'msg' => 'Doctor document uploaded successfully.']);
    }
    /**
     * --------------------------------------------------------
     * Get Document By ID
     * --------------------------------------------------------
     */
    public function documentById(int $id)
    {
        $document = $this->doctorDocument->find($id);
        if (!$document) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Doctor document not found.']);
        }
        /*
         * Add public URL if required by frontend.
         */
        if (!empty($document['file_path'])) {
            $document['file_url'] = $this->storage->getUrl($document['file_path']);
        }
        return $this->response->setJSON(['status' => true, 'data' => $document]);
    }
    /**
     * --------------------------------------------------------
     * Update
     * --------------------------------------------------------
     */
    public function update()
    {
        $id = (int) $this->request->getPost('id');
        if (!$id) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Invalid doctor document.']);
        }
        $existing = $this->doctorDocument->find($id);
        if (!$existing) {
            return $this->response->setJSON(['status' => false, 'msg' => 'Doctor document not found.']);
        }
        $data = $this->_getDocumentData();
        /*
         * Validate metadata.
         */
        $validation = $this->validateDocument($data, false);
        if ($validation['status'] !== true) {
            return $this->response->setJSON(['status' => false, 'msg' => reset($validation['errors']), 'errors' => $validation['errors']]);
        }
        $updateData = ['doctor_id' => $data['doctor_id'], 'document_type' => $data['document_type'], 'document_name' => $data['document_name'], 'remarks' => $data['remarks'], 'status' => $data['status'], 'updated_at' => date('Y-m-d H:i:s')];
        /*
         * Check if replacement file was uploaded.
         */
        $file = $this->request->getFile('document');
        $newR2Key = null;
        if ($file && $file->isValid()) {
            /*
             * Generate new file name.
             */
            $newFileName = $file->getRandomName();
            /*
             * New R2 object key.
             */
            $newR2Key = 'doctors/' . $data['doctor_id'] . '/documents/' . $newFileName;
            /*
             * Upload new file first.
             *
             * Do NOT delete old file before this succeeds.
             */
            try {
                $this->storage->uploadFile($newR2Key, $file);
            } catch (\Throwable $e) {
                log_message('error', 'Doctor document replacement upload failed: ' . $e->getMessage());
                return $this->response->setJSON(['status' => false, 'msg' => 'Unable to upload replacement document.']);
            }
            /*
             * Update database.
             */
            $updateData['file_name'] = $newFileName;
            $updateData['file_path'] = $newR2Key;
            $updateData['file_size'] = $file->getSize();
            $updateData['mime_type'] = $file->getMimeType();
        }
        /*
         * Update DB.
         */
        if (!$this->doctorDocument->update($id, $updateData)) {
            /*
             * DB failed.
             * Delete newly uploaded R2 object.
             */
            if ($newR2Key) {
                try {
                    $this->storage->delete($newR2Key);
                } catch (\Throwable $e) {
                    log_message('error', 'Failed to rollback replacement R2 object: ' . $e->getMessage());
                }
            }
            return $this->response->setJSON(['status' => false, 'msg' => 'Unable to update doctor document.']);
        }
        /*
         * DB update succeeded.
         *
         * Now delete old R2 object.
         */
        if ($newR2Key && !empty($existing['file_path'])) {
            try {
                $this->storage->delete($existing['file_path']);
            } catch (\Throwable $e) {
                /*
                 * Do not fail the whole update.
                 *
                 * DB is already updated.
                 * Log the orphaned old file for cleanup.
                 */
                log_message('error', 'Unable to delete old doctor document from R2: ' . $e->getMessage());
            }
        }
        return $this->response->setJSON(['status' => true, 'msg' => 'Doctor document updated successfully.']);
    }
    /**
     * --------------------------------------------------------
     * Download / View
     * --------------------------------------------------------
     */
    public function download(int $id)
    {
        $document = $this->doctorDocument->find($id);
        if (!$document) {
            return redirect()->back()->with('error', 'Doctor document not found.');
        }
        if (empty($document['file_path'])) {
            return redirect()->back()->with('error', 'Document file not found.');
        }
        /*
         * Get R2 public URL.
         */
        $url = $this->storage->getUrl($document['file_path']);
        /*
         * Redirect browser to R2.
         */
        return redirect()->to($url);
    }
    /**
     * --------------------------------------------------------
     * Delete
     * --------------------------------------------------------
     */
    public function delete(int $id)
    {
        $document = $this->doctorDocument->find($id);
        if (!$document) {
            session()->setFlashdata(['status' => false, 'msg' => 'Doctor document not found.']);
            return redirect()->to('admin/doctor-documents');
        }
        /*
         * Delete DB record.
         */
        if (!$this->doctorDocument->delete($id)) {
            session()->setFlashdata(['status' => false, 'msg' => 'Unable to delete doctor document.']);
            return redirect()->to('admin/doctor-documents');
        }
        /*
         * Delete physical object from R2.
         */
        if (!empty($document['file_path'])) {
            try {
                $this->storage->delete($document['file_path']);
            } catch (\Throwable $e) {
                /*
                 * DB deletion succeeded, so don't tell
                 * the user that the complete operation failed.
                 *
                 * Log the R2 deletion failure for cleanup.
                 */
                log_message('error', 'Unable to delete doctor document from R2: ' . $e->getMessage());
            }
        }
        session()->setFlashdata(['status' => true, 'msg' => 'Doctor document deleted successfully.']);
        return redirect()->to('admin/doctor-documents');
    }
    /**
     * --------------------------------------------------------
     * Get Request Data
     * --------------------------------------------------------
     */
    protected function _getDocumentData(): array
    {
        return ['doctor_id' => (int) $this->request->getPost('doctor_id'), 'document_type' => trim($this->request->getPost('document_type')), 'document_name' => trim($this->request->getPost('document_name')), 'remarks' => trim($this->request->getPost('remarks')), 'status' => (int) $this->request->getPost('status')];
    }
    /**
     * --------------------------------------------------------
     * Validation
     * --------------------------------------------------------
     */
    protected function validateDocument(array $data, bool $fileRequired = true): array
    {
        $rules = ['doctor_id' => ['label' => 'Doctor', 'rules' => 'required|is_natural_no_zero'], 'document_type' => ['label' => 'Document Type', 'rules' => 'required|max_length[100]'], 'document_name' => ['label' => 'Document Name', 'rules' => 'required|max_length[255]'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]']];
        /*
         * File validation is handled separately by CI4.
         */
        if ($fileRequired) {
            $rules['document'] = ['label' => 'Document', 'rules' => ['uploaded[document]', 'max_size[document,10240]', 'ext_in[document,pdf,jpg,jpeg,png,doc,docx]']];
        }
        if (!$this->validate($rules)) {
            return ['status' => false, 'errors' => $this->validator->getErrors()];
        }
        return ['status' => true];
    }
}
