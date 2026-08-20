<?php

namespace  Doctor\Models;

use CodeIgniter\Model;

class DoctorDocument extends Model
{
    protected $table = 'doctor_documents';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = true;

    protected $allowedFields = [

        'doctor_id',
        'document_type',
        'document_name',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'remarks',
        'status'

    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $deletedField = 'deleted_at';
}