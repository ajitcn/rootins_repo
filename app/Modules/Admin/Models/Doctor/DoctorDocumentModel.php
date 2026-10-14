<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

class DoctorDocumentModel extends Model
{
    protected $table = 'doctor_documents';

    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;

    protected $returnType = 'array';

    protected $allowedFields = ['doctor_id', 'document_type', 'document_name', 'file_name', 'file_path', 'file_size', 'mime_type', 'remarks', 'status', 'created_at', 'updated_at', 'deleted_at'];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $useSoftDeletes = false;

    protected $deletedField = 'deleted_at';
}
