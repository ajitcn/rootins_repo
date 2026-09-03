<?php

namespace Doctor\Models;

use CodeIgniter\Model;
class DoctorSpecialization extends Model
{
    protected $table = 'doctor_specializations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['specialization_name', 'description', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
