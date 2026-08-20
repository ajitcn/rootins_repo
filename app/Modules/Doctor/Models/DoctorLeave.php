<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorLeave extends Model
{
    protected $table = 'doctor_leaves';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'doctor_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'status',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}