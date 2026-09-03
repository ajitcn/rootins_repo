<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;
class DoctorConsultationFeeModel extends Model
{
    protected $table = 'doctor_consultation_fees';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['doctor_id', 'consultation_type', 'consultation_mode', 'fee', 'effective_from', 'effective_to', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $useSoftDeletes = false;
    /**
     * --------------------------------------------------------
     * Check Date Overlap
     * --------------------------------------------------------
     */
    public function hasOverlap(int $doctorId, string $consultationType, string $consultationMode, ?int $ignoreId = null): bool
    {
        $builder = $this->db->table('doctor_consultation_fees')->where('doctor_id', $doctorId)->where('consultation_type', $consultationType)->where('consultation_mode', $consultationMode)->where('status', 1);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }
        return $builder->countAllResults() > 0;
    }
}
