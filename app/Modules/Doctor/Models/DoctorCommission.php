<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorCommission extends Model
{
    protected $table = 'doctor_commissions';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [

        'doctor_id',
        'clinic_id',
        'commission_for',
        'reference_id',
        'commission_type',
        'commission_value',
        'effective_from',
        'effective_to',
        'status'

    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}