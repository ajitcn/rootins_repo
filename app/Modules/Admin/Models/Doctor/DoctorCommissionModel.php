<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;
class DoctorCommissionModel extends Model
{
    protected $table = 'doctor_commissions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'commission_for', 'reference_id', 'commission_type', 'commission_value', 'effective_from', 'effective_to', 'status', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    /**
     * --------------------------------------------------------
     * Check Date Overlap
     * --------------------------------------------------------
     */
    public function hasOverlap(int $doctorId, string $commissionFor, ?int $ignoreId = null): bool
    {
        $builder = $this->builder();
        $builder->where('doctor_id', $doctorId)->where('commission_for', $commissionFor);
        // Ignore current schedule while updating
        if (!empty($ignoreId)) {
            $builder->where('id !=', $ignoreId);
        }
        return $builder->countAllResults() > 0;
    }
}
