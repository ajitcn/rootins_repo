<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorConsultationFee extends Model
{
    protected $table = 'doctor_consultation_fees';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'doctor_id',
        'clinic_id',
        'consultation_type',
        'consultation_mode',
        'fee',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}