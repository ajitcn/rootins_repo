<?php

namespace Admin\Models\Doctor;

use CodeIgniter\Model;

class DoctorScheduleModel extends Model
{
    protected $table            = 'doctor_schedules';
    protected $primaryKey       = 'id';

    protected $returnType       = 'array';

    protected $useSoftDeletes   = false;

    protected $protectFields    = true;

    protected $allowedFields = [
        'doctor_id',
        'day_of_week',
        'shift_name',
        'start_time',
        'end_time',
        'slot_duration',
        'max_patients',
        'status',
    ];

    protected $useTimestamps = true;

    protected $dateFormat    = 'datetime';

    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = true;


    public function getSchedules()
    {
        return $this->select('
                doctor_schedules.*,
                doctors.doctor_code,
                doctors.first_name,
                doctors.last_name
            ')
            ->join('doctors', 'doctors.id = doctor_schedules.doctor_id')
            ->orderBy('doctors.first_name', 'ASC')
            ->orderBy('doctor_schedules.day_of_week', 'ASC')
            ->orderBy('doctor_schedules.start_time', 'ASC')
            ->findAll();
    }



   /**
     * Check if doctor already has an overlapping schedule
     *
     * @param int      $doctorId
     * @param int      $dayOfWeek
     * @param string   $startTime
     * @param string   $endTime
     * @param int|null $ignoreId
     *
     * @return bool
     */
   
    public function hasOverlap(
        int $doctorId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $ignoreId = null
    ): bool {

        $builder = $this->builder();

        $builder->where('doctor_id', $doctorId)
                ->where('day_of_week', $dayOfWeek)
                ->where('deleted_at', null)
                ->groupStart()
                    ->where('start_time <', $endTime)
                    ->where('end_time >', $startTime)
                ->groupEnd();

        // Ignore current schedule while updating
        if (!empty($ignoreId)) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

}