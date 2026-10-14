<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PatientDocumentModel extends Model
{
    protected $table = 'patient_documents';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = [
        'patient_id',
        'clinic_id',
        'document_type',
        'title',
        'file_name',
        'original_file_name',
        'file_path',
        'file_size',
        'mime_type',
        'source_type',
        'shopify_store_domain',
        'shopify_customer_id',
        'external_upload_reference',
        'upload_status',
        'review_message',
        'reviewed_at',
        'retention_expires_at',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
