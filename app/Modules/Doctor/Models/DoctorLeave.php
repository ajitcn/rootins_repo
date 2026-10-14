<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorLeave extends Model
{
    protected $table = 'doctor_leaves';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['doctor_id', 'leave_type', 'start_date', 'end_date', 'start_time', 'end_time', 'reason', 'status'];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function hasOverlap(int $doctorId, string $startDate, string $endDate, ?int $ignoreId = null): bool
    {
        $query = $this->where('doctor_id', $doctorId)
            ->where('status !=', 0)
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate);

        if ($ignoreId !== null) {
            $query->where('id !=', $ignoreId);
        }

        return $query->countAllResults() > 0;
    }
}
