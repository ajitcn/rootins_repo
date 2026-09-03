<?php

namespace Admin\Models\Master;

use CodeIgniter\Model;
class DoctorSpecializationModel extends Model
{
    protected $table = 'doctor_specializations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['specialization_name', 'description', 'status'];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = true;
    protected $cleanValidationRules = true;
    /**
     * Get active specializations
     */
    public function getActive()
    {
        return $this->where('status', 1)->orderBy('specialization_name', 'ASC')->findAll();
    }
    /**
     * Get specialization dropdown
     */
    public function dropdown()
    {
        return $this->select('id, specialization_name')->where('status', 1)->orderBy('specialization_name', 'ASC')->findAll();
    }
}
