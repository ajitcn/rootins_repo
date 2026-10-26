<?php

namespace Admin\Models;

use CodeIgniter\Model;

class PrescriptionModel extends Model
{
    protected $table = 'prescriptions';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $protectFields = true;

    protected $allowedFields = ['prescription_number', 'consultation_id', 'patient_id', 'doctor_id', 'clinic_id', 'prescription_date', 'valid_until', 'notes', 'status', 'created_at', 'updated_at'];

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';
}
