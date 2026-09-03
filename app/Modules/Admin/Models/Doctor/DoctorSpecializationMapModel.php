<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;
class DoctorSpecializationMapModel extends Model
{
    protected $table = 'doctor_specialization_map';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['doctor_id', 'specialization_id'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;
    public function getDoctorSpecializations(int $doctorId): array
    {
        return $this->where('doctor_id', $doctorId)->findColumn('specialization_id') ?? [];
    }
}
