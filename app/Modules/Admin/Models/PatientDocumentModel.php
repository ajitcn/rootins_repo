<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PatientDocumentModel extends Model
{
    protected $table          = 'patient_documents';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'patient_id',
        'document_type',
        'title',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
