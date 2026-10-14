<?php

namespace Doctor\Models;

use CodeIgniter\Model;

class DoctorSchedule extends Model
{
    protected $table = 'doctor_schedules';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = ['doctor_id', 'day_of_week', 'shift_name', 'start_time', 'end_time', 'break_start_time', 'break_end_time', 'slot_duration', 'max_patients', 'status'];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    public function hasOverlap(int $doctorId, int $dayOfWeek, string $startTime, string $endTime, ?int $ignoreId = null): bool
    {
        $query = $this->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('deleted_at', null)
            ->where('start_time <', $endTime)
            ->where('end_time >', $startTime);

        if ($ignoreId !== null) {
            $query->where('id !=', $ignoreId);
        }

        return $query->countAllResults() > 0;
    }
}
