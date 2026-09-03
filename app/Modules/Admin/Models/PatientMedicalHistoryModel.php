<?php

namespace Admin\Models;

use CodeIgniter\Model;
class PatientMedicalHistoryModel extends Model
{
    protected $table = 'patient_medical_histories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['patient_id', 'title', 'details', 'history_date'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
