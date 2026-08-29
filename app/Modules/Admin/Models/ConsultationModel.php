<?php

namespace Admin\Models;

use CodeIgniter\Model;

class ConsultationModel extends Model
{
    protected $table          = 'consultations';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields  = true;

    protected $allowedFields = [
        'consultation_number',
        'patient_id',
        'doctor_id',
        'clinic_id',
        'appointment_id',
        'consultation_date',
        'consultation_time',
        'chief_complaint',
        'clinical_notes',
        'diagnosis',
        'treatment_plan',
        'follow_up_date',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
